# ACM Scribe Dokuwiki
This scenario sets up primary and a failover clusters within RHACM. A global load balancer such as Haproxy or Cloudflare is used for this solution but is out of scope for this document.

We also assume that RHACM has been deployed.

Depending on the storageclass your cluster has as default the value may need to modified. 
This requires using the replace functionality of RHACM. Follow the notes [here](https://access.redhat.com/documentation/en-us/red_hat_advanced_cluster_management_for_kubernetes/2.1/html/manage_applications/managing-applications#subscribing-git-resources)

## Clusters
Using either RHACM or importing clusters create two clusters primary and failover.

### Base configurations
We need to define the Scribe deployment and Storage class modifcaitions as an application in RHACM.

The storage class modifications are in place because they contain a `volumesnapshotclass` definition and
modifications to the default storage class to use CSI when available.

```
oc create -f ../storage-class-modifications/storage-class-acm-definition
```

Scribe can now be deployed as well
```
oc create -f ../scribe-deployment
```

### Primary
Working first with the primary cluster we will label our cluster and then initialize our application.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster primary purpose=dokuwiki
oc label managedcluster primary site=primary
oc label managedcluster primary storage=gp2
```

Before deploying the applications within RHACM define a [route specific to your environment](./application/route.yaml)

The application can now be defined within RHACM.

```
oc create -f ./acm-application-configuration
```

### Failover
The failover cluster can now be labeled.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster failover purpose=dokuwiki
oc label managedcluster failover site=failover
oc label managedcluster failover storage=gp2
```

## Scribe configuration
Now that the clusters are online and the Dokuwiki application is running we will define our `replicationsource` and
`replicationdestination` objects.

We must first start with deploying the `replicationdestination` on the Failover cluster 
as it will create a load balancer or if using Submariner provide a clusterIP that will
be accessible by the Primary cluster.

```
export KUBECONFIG=/tmp/acm
oc create -f scribe-rsync-failover-acm-configuration
```
This will create SSH keys and the Kubernetes service. 

NOTE: SSH keys can be defined when creating the `replicationdestination` rather than Scribe creating keys.

We will need the value of the Kubernetes service to provide to the `replicationsource`. This value can be derived
from the command line or browsing the application with RHACM.

```
export KUBECONFIG=/tmp/failover
oc get replicationdestination database-destination -n dokuwiki --template={{.status.rsync.address}}
```

Using the Kubernetes service above modify the `replicationsource.yaml` replacing the value in the address line.
```
vi source-rsync/replicationsource.yaml
```

While on the Failover cluster also obtain the secret.
```
$ kubectl get secret -n dest scribe-rsync-dest-src-database-destination -o yaml > ./acm-dokuwiki/source-rsync/secret.yaml
$ vi ./acm-dokuwiki/source-rsync/secret.yaml
# ^^^ remove the owner reference (.metadata.ownerReferences)
```

Commit both of these files to the git repository.
```
git add source-rsync/replicationsource.yaml
git add source-rsync/secret.yaml
git commit -m 'service and secret'
git push origin main
```

We now have all of the required items to create the `replicationsource` application within RHACM.
```
export KUBECONFIG=/tmp/acm
oc create -f scribe-rsync-primary-acm-configuration
```

Replication between the Primary and Failover clusters is now defined. 

## Failing over
Our Dokuwiki application is defined in RHACM to ensure that 1 copy of our application is deployed at all times
amongst our clusters with label `purpose=dokuwiki`. 

Feel free to update the Dokuwiki site on the primary with a new message. Replication happens every 2 minutes. 

Log into AWS and power off all servers on the primary cluster.  Once RHACM sets the primary cluster to a Not Ready
state the application will begin to deploy on the Failover cluster.

This concludes using RHACM and Scribe to perform a DR type scenario.