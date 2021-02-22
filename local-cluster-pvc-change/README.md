# Modify PVC Storage
As providers mature CSI drivers are becoming available. This scenario will
migrate storage from a gp2 `storageclass` to a gp2-csi `storageclass`. The
process is defined to ensure limited to no downtime when swiching the underlying storage.


## Deploying the application
A Dokuwiki application will be deployed with a pvc using gp2. 

```
oc create -f ./application/ -n dokuwiki
```

## Deploying Scribe
We will use Scribe to manage the replication between our gp2 and gp2-csi `storageclass`.

To deploy Scribe install the Scribe Helm chart using the instructions (here)[https://scribe-replication.readthedocs.io/en/latest/installation/index.html#kubernetes-openshift]

Now that Scribe has been deployed we can define the `replicationsource` and `replicationdestination` objects.

```
oc create -f ./destination-rsync
oc create -f ./source-rsync
```

This will replicate the data from gp2 to a gp2-csi every 8 minutes. As it gets closer to the time to perform
the migration. We will modify the `replicationsource` object to use a shorter increment of time. This is
possibly because rsync replicates only the files that have changed allowing for replication to happen in the
smaller replication window without worrying about replication jobs stacking up.

Modify the `replicationsource` to synchronize every 1 minute rather than 8.

```
vi ./source-rsync/replicationsource.yaml
oc replace -f ./source-rsync/replicationsource.yaml
```

## Migration
When you believe that most if not all data has been replicated perform the following. 

Pause the `replicationdestination` object by adding in the following line `paused: true`

```
vi ./destination-sync/replicationdestination.yaml
...redacted
spec:
  paused: true
  rsync:
    serviceType: ClusterIP
    copyMethod: None
...redacted
```

Replace the `replicationdestination` object.
```
oc replace -f ./destination-sync/replicationdestination.yaml
```

Modify the deployment to use the new pvc.
```
vi ./application/deployment.yaml
...redacted
      volumes:
      - name: dokuwiki-data
        persistentVolumeClaim:
          claimName: dokuwiki-csi
```

Apply the changes to the deployment which will cause a rollout of a new pod.
```
oc apply -f ../application/deployment.yaml
```

Due to how pods are deployed the application should stay online and gracefully transfer between the container
using gp2 to the container using gp2-csi.