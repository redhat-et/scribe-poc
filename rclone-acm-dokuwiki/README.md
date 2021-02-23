# RHACM with Rclone
This scenario will use a source cluster running Scribe, OCS, and RHACM to create and manage applications
and data on new clusters.

We also assume that RHACM has been deployed.

Depending on the storageclass your cluster has as default the value may need to modified. 
This requires using the replace functionality of RHACM. Follow the notes [here](https://access.redhat.com/documentation/en-us/red_hat_advanced_cluster_management_for_kubernetes/2.1/html/manage_applications/managing-applications#subscribing-git-resources)

## Base configurations
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

## OCS
On the RHACM cluster, perform the following to install OCS.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster local-cluster storage=ocs
```

## Dokuwiki
We will deploy our Dokuwiki application using RHACM.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster local-cluster site=headquarters
```

```
oc create -f ./acm-app-configuration
```

## Scribe
The Scribe components will automatically be installed on all clusters by default.

Rclone requires object storage to be available such as minio or S3. This example, 
will use S3. Create the file `rclone.conf`

```
vi rclone.conf

[aws-s3-bucket]
type = s3
provider = AWS
env_auth = false
access_key_id = *******
secret_access_key = ******
region = <region>
location_constraint = <region>
acl = private
```

Create a secret based on the `rclone.conf` file and then remove the file.
```
oc create secret generic rclone-secret --from-file=rclone.conf=./rclone.conf -n dokuwiki --dry-run > destination-rlcone/rclone.yaml
oc create secret generic rclone-secret --from-file=rclone.conf=./rclone.conf -n dokuwiki --dry-run > source-rlcone/rclone.yaml
rm -f rclone.conf
```

Commit the rclone secret to git.
```
git add destination-rlcone/rclone.yaml source-rlcone/rclone.yaml
git commit -m 'addition of rclone secret'
git push origin main
```

It's not time to create the replication application. This will place the Scribe `replicationdestination` and `replicationsource` objects.

```
oc create -f scribe-source-acm-configuration/
oc create -f scribe-destination-acm-configuration/
```

## Dokuwiki
At this point you can make modifications to the Dokuwiki site.

There is one hack that is required. Due to how rclone handles storage replication. Empty directories are not copied so we will
need to place in a file so that a directory will be copied.

```
oc rsh -n dokuwiki `oc get pods -n dokuwiki |grep my-release | awk '{print $1}'` bash -c 'echo "smiley" > /bitnami/dokuwiki/lib/images/smileys/local/smiley'
```

## Adding clusters
Now possible to add and remove clusters using ACM as you see fit.

The most important thing is to define the site=remote and the storage=xxx label specifying the storage the cluster will use.