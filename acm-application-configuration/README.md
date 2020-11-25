# Scribe Rsync Demonstration with ACM
The following demonstration will use ACM to manage the placement of applications. Scribe will
rsync the data between the primary and failover cluster.

## ACM configuration
Both clusters have been added into ACM already. We will first define the application on the ACM cluster.

```
export KUBECONFIG=/tmp/acm
oc create -f store-front/
```

We will first need to add the label *purpose: store* to our primary cluster. This will deploy the 
application to our cluster.

```
oc label managedcluster primary purpose=store
```

### Adding in the failover cluster
The failover cluster can be labeled so that it can be managed by the placement rules with ACM.

```
oc label managedcluster failover purpose=store
```

## Scribe deployment
The failover cluster is required to be deployed first because we need to derive the value of
the load balancer service to give to our primary cluster.

```
oc create -f scribe-rsync-failover
```
Once the items have propogated to the failover cluster get the LB service address using either the console or
the kubeconfig of the failover cluster.

For this example, our value is *ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com*.

Modify the file *primary-scribe-configuration/deploy-source.yaml* replacing the value of *DESTINATION ADDRESS*.

```
vi primary-scribe-configuration/deploy-source.yaml
            - name: DESTINATION_ADDRESS
              value: ac96b981383304b519defbd5ad89d750-a4fc2845d395b0d4.elb.us-east-1.amazonaws.com
```

Validate that the *scribe-rsync-source* was successful. We can now trigger a failure of the main cluster.
