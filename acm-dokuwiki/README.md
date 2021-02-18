# ACM Scribe Dokuwiki
This scenario sets up primary and a failover clusters within RHACM. A global load balancer such as Haproxy or Cloudflare is used for this solution but is out of scope for this document.

We also assume that RHACM has been deployed.

## Clusters
Using either RHACM or importing clusters create two clusters primary and failover.

### Base configurations
We need to define 


Scribe can now be deployed as well
```
oc create -f ../scribe-deployment
```

Storage configurations items 

### Primary
Working first with the primary cluster we will label our cluster and then initialize our application.

```
export KUBECONFIG=/tmp/acm
oc label managedcluster primary purpose=dokuwiki
oc label managedcluster primary site=primary
```

Before deploying the applications within RHACM define a [route specific to your environment](./application/route.yaml)

The application can now be defined within ACM.

```
oc create -f ./acm-application-configuration
```

### Failover
The failover cluster can now be labeled.
```
export KUBECONFIG=/tmp/acm
oc label managedcluster primary purpose=dokuwiki
oc label managedcluster primary site=failover
```