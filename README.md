# PHP CRUD · CI/CD with GitHub Actions + DockerHub + ArgoCD + Kubernetes

```
php-crud-argocd/
├── .github/
│   └── workflows/
│       └── ci-cd.yml          ← GitHub Actions pipeline
├── crud-CI/
│   ├── index.php              ← PHP CRUD application
│   └── Dockerfile             ← Container build
└── crud-CD/
    ├── deployment.yml         ← K8s Deployments (app + mysql)
    ├── service.yml            ← K8s Services
    ├── secret.yml             ← MySQL credentials
    └── argocd-app.yml         ← ArgoCD Application definition
```

---

## 🚀 Full CI/CD Flow

```
git push → GitHub Actions → Docker Build → DockerHub
                                              ↓
                              Update deployment.yml image tag
                                              ↓
                              ArgoCD detects Git change
                                              ↓
                              kubectl apply → K8s cluster
```

---

## ⚙️ Step-by-Step Setup

### 1. GitHub Secrets (Settings → Secrets → Actions)

| Secret | Value |
|--------|-------|
| `DOCKERHUB_USERNAME` | Your DockerHub username |
| `DOCKERHUB_TOKEN` | DockerHub Access Token (not password) |
| `GH_PAT` | GitHub Personal Access Token (repo write scope) |

### 2. Install ArgoCD on Kubernetes

```bash
kubectl create namespace argocd
kubectl apply -n argocd -f https://raw.githubusercontent.com/argoproj/argo-cd/stable/manifests/install.yaml

# Wait for pods
kubectl wait --for=condition=Ready pods --all -n argocd --timeout=120s

# Get admin password
kubectl -n argocd get secret argocd-initial-admin-secret \
  -o jsonpath="{.data.password}" | base64 -d && echo

# Port-forward UI
kubectl port-forward svc/argocd-server -n argocd 8080:443
# Visit: https://localhost:8080  (admin / <password above>)
```

### 3. Update `crud-CD/argocd-app.yml`

Replace `YOUR_USERNAME` with your GitHub username:
```yaml
repoURL: https://github.com/YOUR_USERNAME/php-crud-argoCD
```

### 4. Apply ArgoCD Application

```bash
kubectl apply -f crud-CD/argocd-app.yml
```

### 5. Update Secret values

```bash
# Generate base64 values
echo -n 'your-root-password' | base64
echo -n 'your-db-user' | base64
echo -n 'your-db-password' | base64
```
Paste results into `crud-CD/secret.yml`.

### 6. Update Deployment image

In `crud-CD/deployment.yml`, replace:
```yaml
image: YOUR_DOCKERHUB_USERNAME/php-crud-app:latest
```

### 7. Push to GitHub → Watch the magic!

```bash
git add .
git commit -m "feat: initial setup"
git push origin main
```

---

## 🔍 Useful Commands

```bash
# Watch ArgoCD sync
argocd app get php-crud-app
argocd app sync php-crud-app       # Manual sync

# K8s status
kubectl get all -n php-crud
kubectl get pods -n php-crud -w    # Watch pods
kubectl logs -n php-crud -l app=php-crud

# Get app URL (LoadBalancer)
kubectl get svc php-crud-service -n php-crud

# For Minikube
minikube service php-crud-service -n php-crud
```

---

## 🔐 Production Security Tips

- Use **Sealed Secrets** (`kubeseal`) — never commit plain secrets to Git
- Use **image digest** instead of `:latest` tag
- Add **NetworkPolicy** to restrict pod-to-pod communication
- Enable **ArgoCD RBAC** for team access control
