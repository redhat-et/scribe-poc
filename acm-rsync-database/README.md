# Scribe Rsync Demonstration with ACM
The following demonstration will use ACM to manage the placement of applications. Scribe will
rsync the data between the primary and failover cluster. To begin fork the repository as you
will need to commit changes that are revelant to your environment.


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
## ACM configuration
Both clusters have been added into ACM already. We will first define the application on the ACM cluster. Both clusters have been deployed on AWS.

We will need to add the label *purpose: database* and *site: primary* to our primary cluster. This will deploy the 
application to our cluster.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster primary purpose=database
oc label managedcluster primary site=primary
oc label managedcluster primary storage=gp2
```

Create the application components for the database application.

```
oc create -f acm-database-application/
```

After the initial application deployment onto the primary cluster the failover cluster can be
labeled so that it can be managed by the placement rules with ACM.

```
oc label managedcluster failover purpose=database
oc label managedcluster failover site=failover
oc label managedcluster primary storage=gp2
```
## ACM configuration for Scribe
The failover cluster is required to be deployed first because we need to derive the value of
the load balancer service to give to our primary cluster. The following will deploy the Scribe
*replicationdestination*.

```
oc create -f scribe-rsync-failover-acm-configuration
```

Once the items have propogated to the failover cluster get the LB service address using either the console or
the kubeconfig of the failover cluster.

```
export KUBECONFIG=/tmp/failover
oc get replicationdestination database-destination -n database --template={{.status.rsync.address}}
ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com
```

For this example, our value is *ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com*.

Modify the file *source-rsync/replicationsource.yaml* replacing the value of *DESTINATION ADDRESS*.

```
vi source-rsync/replicationsource.yaml
            - name: DESTINATION_ADDRESS
              value: ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com
```

Extract the SSH keys from the failover repository. They will be saved within the git repository.

```
export KUBECONFIG=/tmp/failover
oc get secrets -n database scribe-rsync-dest-src-database-destination -o yaml > ./source-rsync/secret.yaml
vi ./source-rsync/secret.yaml
# ^^^ remove the owner reference (.metadata.ownerReferences)
```

Commit the changes to your git repository.

```
git add source-rsync/replicationsource.yaml source-rsync/secret.yaml
git commit -m 'secret and loadbalancer to be used by scribe'
git push origin main
```

Now the Scribe *replicationsource* needs to be deployed.

```
export KUBECONFIG=/tmp/acm
oc create -f scribe-rsync-primary-acm-configuration
```

## Validating Scribe Functionality
Now that the Scribe *replicationsource* and *replicationdestination* objects have been deployed, we can verify that the pvc has been copied from the
primary cluster to the failover cluster.

```
export KUBECONFIG=/tmp/failover
oc get replicationdestination database-destination -n dest --template={{.status.latestImage.name}}
scribe-dest-database-destination-20201203174504
```

If the latest image field is populated that means that Scribe is running successfully

## Adding Data and Failing
Now we will add data to the database and fail the primary cluster.

```
export KUBECONFIG=/tmp/primary
oc rsh -n database `oc get pods -n database | grep mysql | awk '{print $1}'` /bin/bash
mysql -u root -p$MYSQL_ROOT_PASSWORD
create database synced;
exit
exit
```

For our example, we take a snapshot every two minutes. Ensure that you wait two minutes after the database commands have been issued before beginning the next steps.

Log into AWS or the provider of the primary cluster and shutdown all instances associated with that OpenShift cluster. This will cause ACM to deploy the database 
application onto the failover cluster.

## Verifying Failover
A job named *transfer-pvc* will be deployed to the failover cluster which will identify the latest Scribe volume snapshot and create a PVC based off of that information.
We log into the database on the failover cluster and verify that the *synced* database exists.
```
export KUBECONFIG=/tmp/failover
oc rsh -n database `oc get pods -n database | grep mysql | awk '{print $1}'` /bin/bash
mysql -u root -p$MYSQL_ROOT_PASSWORD
show databases;
   +--------------------+
   | Database           |
   +--------------------+
   | information_schema |
   | mysql              |
   | performance_schema |
   | synced             |
   | sys                |
   +--------------------+
   5 rows in set (0.00 sec)

exit
exit
```
## Wrap Up
This concludes the disaster recovery demonstration. We automatically failed an application from a cluster to another cluster. Persistent data was rsynced from one cluster to
another using Scribe.
