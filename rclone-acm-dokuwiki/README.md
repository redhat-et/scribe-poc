# RHACM with Rclone
This scenario will use a source cluster running SCribe, OCS, and RHACM to create and manage applications
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
kubectl create secret generic rclone-secret --from-file=rclone.conf=./rclone.conf -n dokuwiki --dry-run > destination-rlcone/rclone.yaml
kubectl create secret generic rclone-secret --from-file=rclone.conf=./rclone.conf -n dokuwiki --dry-run > source-rlcone/rclone.yaml
rm -f rclone.conf
```

