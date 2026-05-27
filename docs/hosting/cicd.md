# CI/CD Setup

Hướng dẫn cấu hình CI/CD pipeline để tự động deploy khi có push lên GitHub.

## Mục Lục

1. [Tổng Quan](#tổng-quan)
2. [GitHub Actions Workflow](#github-actions-workflow)
3. [Cấu Hình Secrets](#cấu-hình-secrets)
4. [Cấu Hình Droplet](#cấu-hình-droplet)
5. [Testing CI/CD](#testing-cicd)

---

## Tổng Quan

```
┌─────────────┐    Push     ┌───────────┐    SSH     ┌─────────────┐
│   Developer │ ──────────► │  GitHub   │ ─────────► │   Droplet   │
│   Laptop    │   to main   │  Actions  │            │ Production  │
└─────────────┘             └───────────┘            └─────────────┘
                                  │
                                  ├── Lint (PR only)
                                  ├── Smart Deploy
                                  │     ├── code-only → reload PHP
                                  │     ├── composer  → composer install
                                  │     └── config    → full deploy
                                  └── Health check
```

### Workflow Features

- **Zero-downtime deploy**: PHP-FPM graceful reload, không stop/start containers
- **Smart deploy**: Tự detect thay đổi code để skip steps không cần thiết
- **Auto-deploy**: Push lên `main` hoặc `production`
- **PR Merge**: Deploy tự động khi merge PR
- **Lint Check**: Kiểm tra PHP syntax và coding standards
- **Health Check**: Verify site hoạt động sau deploy
- **Rollback Support**: Revert git commit hoặc restore backup

---

## GitHub Actions Workflow

File workflow đã được tạo tại: `.github/workflows/deploy.yml`

### Trigger Conditions

| Event | Branch | Action |
|-------|--------|--------|
| Push | `main`, `production` | Smart deploy |
| PR merged | `main`, `production` | Smart deploy |

### Jobs

1. **deploy** (chính)
   - SSH vào droplet
   - Pull code
   - Chạy `deploy.sh` với smart detection:
     - `code-only`: Chỉ reload PHP-FPM + flush cache
     - `composer`: Chạy `composer install` + reload
     - `config`: Full deploy (setup:upgrade, static content, reindex)
   - Health check

2. **lint** (tùy chọn, cho PR)
   - PHP syntax check
   - Coding standard check (PHP CodeSniffer)

3. **test** (disabled by default)
   - Unit tests (enable khi có tests)

---

## Cấu Hình Secrets

### Bước 1: Tạo SSH Key cho Deploy

Trên máy local (KHÔNG phải trên droplet):

```bash
# Tạo SSH key mới (dành riêng cho GitHub Actions)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f github_deploy_key

# Xem public key
cat github_deploy_key.pub
```

### Bước 2: Thêm Public Key vào Droplet

```bash
# SSH vào droplet
ssh root@YOUR_DROPLET_IP

# Thêm public key vào authorized_keys
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
# Paste public key vào file
chmod 600 ~/.ssh/authorized_keys
```

### Bước 3: Thêm Secrets vào GitHub

1. Vào **GitHub Repository** → **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**:

| Secret Name | Value |
|-------------|-------|
| `DROPLET_SSH_KEY` | Nội dung private key (`github_deploy_key`) |

3. Click **New repository variable**:

| Variable Name | Value |
|---------------|-------|
| `DROPLET_IP` | IP address của droplet (ví dụ: `164.92.185.123`) |

### Bước 4: Bảo Mật Private Key

```bash
# Xóa private key khỏi máy local (đã lưu trong GitHub Secrets)
rm github_deploy_key
rm github_deploy_key.pub

# Giữ an toàn
# KHÔNG bao giờ commit private key vào repository
```

---

## Cấu Hình Droplet

### SSH Configuration

Đảm bảo droplet chấp nhận SSH key:

```bash
# Trên droplet
nano /etc/ssh/sshd_config
```

Kiểm tra/cấu hình:

```conf
PubkeyAuthentication yes
AuthorizedKeysFile .ssh/authorized_keys
PasswordAuthentication no
PermitRootLogin yes
```

```bash
# Restart SSH
systemctl restart sshd
```

### Quyền Thực Thi Deployment

```bash
# Trên droplet, đảm bảo git repo có quyền
cd /opt/peakgear
chown -R root:root .git

# Hoặc nếu deploy bằng docker:
usermod -aG docker root
```

### Firewall (nếu có)

```bash
# Mở port cho SSH
ufw allow 22/tcp

# Đảm bảo HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp
```

---

## Testing CI/CD

### Test 1: Tạo Test Push

```bash
# Trên máy local
git checkout main
git pull origin main

# Tạo dummy commit
echo "# Test" >> README.md
git add README.md
git commit -m "test: CI/CD deployment test"
git push origin main
```

### Test 2: Kiểm Tra GitHub Actions

1. Vào **GitHub Repository** → **Actions** tab
2. Xem workflow chạy:
   - Status: ✅ Success hoặc ❌ Failed
   - Click vào workflow để xem logs chi tiết

### Test 3: Verify Deployment

```bash
# SSH vào droplet và xem logs
ssh root@YOUR_DROPLET_IP

# Xem deployment logs
docker compose -f /opt/peakgear/docker-compose.prod.yaml logs -f

# Kiểm tra site
curl -I http://YOUR_DROPLET_IP
```

### Debugging

#### Lỗi: "Permission denied (publickey)"

```bash
# Kiểm tra SSH key trên droplet
cat ~/.ssh/authorized_keys

# Test SSH key locally
ssh -i github_deploy_key root@YOUR_DROPLET_IP "echo success"
```

#### Lỗi: "Host key verification failed"

```bash
# Trên máy local, xóa known hosts cũ
ssh-keygen -R YOUR_DROPLET_IP
```

#### Lỗi: "Could not resolve hostname"

Kiểm tra biến `DROPLET_IP` trong GitHub repository settings.

---

## Data Safety

### KHÔNG mất dữ liệu vì:

| Data | Storage | Preserved |
|------|---------|-----------|
| Database | `mysql_data` volume | ✅ Không bị ảnh hưởng |
| Redis Cache | `redis_data` volume | ✅ Không bị ảnh hưởng |
| OpenSearch | `opensearch_data` volume | ✅ Không bị ảnh hưởng |
| Media files | `./src/pub/media` (git) | ✅ Git tracked |
| Settings | `core_config_data` (DB) | ✅ DB preserved |
| Code | Git pull | ✅ Overwrite đúng expected |

### Backup trước deploy

```bash
# Backup được tạo tự động trước mỗi deploy
# Xem backup scripts: scripts/backup.sh
```

### Rollback

```bash
# Qua GitHub
git revert HEAD
git push

# Hoặc trên droplet
ssh root@DROPLET_IP "cd /opt/peakgear && git reset --hard HEAD~1 && bash scripts/deploy.sh --force"
```

---

## Cấu Hình Nâng Cao

### Multi-Environment Deployment

Tạo workflow cho staging và production riêng biệt:

```yaml
# .github/workflows/deploy-staging.yml
name: Deploy to Staging

on:
  push:
    branches:
      - develop

env:
  DEPLOY_BRANCH: "develop"

jobs:
  deploy-staging:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy to Staging Droplet
        run: |
          ssh root@${{ vars.STAGING_IP }} "
            cd /opt/peakgear-staging
            git pull origin develop
            bash scripts/deploy.sh
          "
```

### Docker Hub Integration

Nếu muốn build và push Docker image:

```yaml
- name: Login to Docker Hub
  uses: docker/login-action@v3
  with:
    username: ${{ secrets.DOCKERHUB_USERNAME }}
    password: ${{ secrets.DOCKERHUB_TOKEN }}

- name: Build and push
  uses: docker/build-push-action@v5
  with:
    context: .
    push: true
    tags: |
      yourdockerhub/peakgear:latest
      yourdockerhub/peakgear:${{ github.sha }}
```

### Notification

Thêm Slack/Discord notification:

```yaml
- name: Notify on failure
  if: failure()
  uses: slackapi/slack-github-action@v1
  with:
    payload: |
      {
        "text": "Deployment failed: ${{ github.event.head_commit.message }}"
      }
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

---

## Quick Reference

```bash
# === Setup SSH Key ===
ssh-keygen -t ed25519 -C "github-actions" -f deploy_key
cat deploy_key.pub  # Add to droplet

# === GitHub Secrets ===
# DROPLET_SSH_KEY = private key content
# DROPLET_IP = droplet IP address

# === Test deployment ===
git commit --allow-empty -m "trigger deployment"
git push

# === Rollback ===
git revert HEAD
git push
# Hoặc trên droplet:
git reset --hard <previous-commit>
bash scripts/deploy.sh
```