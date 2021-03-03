# External Replication
This example will copy application data from a web page into a Kubernetes cluster.

## Prerequisites
This demo requires server running with httpd and php installed. A global load
balancer is suggested but not required to ensure that 0 downtime occurs when switching
the traffic from the server to the route within the Kubernetes environment. This can be
worked around by modifying DNS changing the record of the server URL to the OpenShift environment.

## Application
On the server copy the contents of the `php-webpage` directory into the directory in which httpd is serving pages.

```
cp -rp php-webpage/ /var/www/html
```

You should now be able to browse to the page and see the uploader application.

## Destination
Now create the Kubernetes objects.

NOTE: Before starting modify `kubernetes/route.yaml` to an address that relates to your environment.

```
oc new-project uploader
oc create -f kubernetes/
```

The `replicationdestination` will create the Loadbalancer service which is used by the `external-rsync-source` binary.
Using the commands below record the Loadbalancer address and copy the SSH key to the server.

```
oc get replicationdestination uploader-destination -n uploader --template={{.status.rsync.address}}
ac9ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com

oc get secret -n uploader scribe-rsync-dest-src-uploader-destination --template {{.data.source}} | base64 -d > ~/replication-key
chmod 0600 ~/replication-key
```

## Rsync
Now that the Loadbalancer address is known and the SSH key is on the server. We will perform a sync. Using the binary `https://github.com/backube/scribe/blob/master/bin/external-rsync-source` perform the following.

```
external-rsync-source -i replication-key -d ac9ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com -s /var/www/html/uploader/
```

The data should now be in the PVC on the Kubernetes cluster. This can be verified by browsing to the site within the Kubernetes cluster and stopping the server.