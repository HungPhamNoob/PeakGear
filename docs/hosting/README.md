# PeakGear Hosting Documentation

Hướng dẫn triển khai PeakGear (Magento 2.4.7) lên **DigitalOcean** với Docker.

## Mục Lục

1. [Tổng Quan](#tổng-quan)
2. [Yêu Cầu](#yêu-cầu)
3. [Cấu Hình Đề Xuất](#cấu-hình-đề-xuất)
4. [Hướng Dẫn Thiết Lập](#hướng-dẫn-thiết-lập)
   - [Bước 1: Tạo Droplet](#bước-1-tạo-droplet)
   - [Bước 2: Cài Đặt Docker](#bước-2-cài-đặt-docker)
   - [Bước 3: Thiết Lập Domain](#bước-3-thiết-lập-domain)
   - [Bước 4: Deploy Code](#bước-4-deploy-code)
   - [Bước 5: Cấu Hình SSL](#bước-5-cấu-hình-ssl)
5. [Quản Lý Production](#quản-lý-production)
6. [Backup & Restore](#backup--restore)
7. [CI/CD](#cicd)
8. [Monitoring](#monitoring)

---

## Tổng Quan

Dự án sử dụng **Docker Compose** để chạy tất cả services trên **một single droplet**:

```
┌─────────────────────────────────────────────────┐
│              Single Droplet                      │
│                                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐     │
│  │  Nginx  │──│   PHP   │──│    MySQL    │     │
│  │  (443)  │  │  8.2    │  │    8.0      │     │
│  └─────────┘  └─────────┘  └─────────────┘     │
│                  │                               │
│  ┌─────────┐    │    ┌─────────────┐            │
│  │  Redis  │◄───┴────│ OpenSearch  │            │
│  │   7.0   │         │    2.12     │            │
│  └─────────┘         └─────────────┘            │
│                                                  │
│  4 vCPU / 8-16 GB RAM / 160-320 GB SSD         │
└─────────────────────────────────────────────────┘
```

### Files Quan Trọng

| File | Mục đích |
|------|----------|
| `docker-compose.prod.yaml` | Production Docker configuration |
| `docker/nginx/nginx.prod.conf` | Production Nginx configuration |
| `scripts/deploy.sh` | Deployment automation script |
| `scripts/backup.sh` | Backup automation script |
| `docs/hosting/` | Thư mục chứa documentation này |

---

## Yêu Cầu

### Hardware (DigitalOcean Droplet)

| Spec | Minimum | Recommended |
|------|---------|-------------|
| **CPU** | 4 vCPU | 4 vCPU |
| **RAM** | 8 GB | 16 GB |
| **SSD** | 160 GB | 320 GB |
| **Cost** | ~$48/tháng | ~$96/tháng |

### Software

- **OS**: Debian 12 x64
- **Docker**: 24.x+
- **Docker Compose**: 2.x+

### Domain

- Domain đã pointed A record về droplet IP
- (Tùy chọn) SSL certificate từ Let's Encrypt

---

## Cấu Hình Đề Xuất

### Single Droplet Specs

| Tier | vCPU | RAM | SSD | Chi Phí/Tháng |
|------|------|-----|-----|---------------|
| Starter | 2 | 4 GB | 80 GB | ~$24 |
| Basic | 4 | 8 GB | 160 GB | ~$48 |
| Standard | 4 | 16 GB | 320 GB | ~$96 |
| Performance | 8 | 32 GB | 640 GB | ~$192 |

**Khuyến nghị**: Start với **Basic** (4 vCPU, 8GB RAM), upgrade khi cần.

---

## Hướng Dẫn Thiết Lập

### Bước 1: Tạo Droplet

#### Qua DigitalOcean Dashboard

1. Đăng nhập [DigitalOcean Dashboard](https://cloud.digitalocean.com)
2. Click **Create** → **Droplets**
3. Chọn cấu hình:
   - **Choose an image**: Debian 12 x64
   - **Choose a size**: 4 vCPU, 8GB RAM, 160GB SSD
   - **Choose a datacenter region**: Singapore (sgp1)
   - **Authentication**: SSH keys (khuyến nghị)
4. Click **Create Droplet**
5. Copy IP address sau khi tạo xong

#### Qua doctl (CLI)

```bash
# Tạo droplet
doctl compute droplet create peakgear \
    --image debian-12-x64 \
    --size s-4vcpu-8gb \
    --region sgp1 \
    --ssh-keys YOUR_SSH_KEY_FINGERPRINT \
    --tag peakgear

# Lấy IP
doctl compute droplet list peakgear
```

### Bước 2: Cài Đặt Docker

SSH vào droplet:

```bash
ssh root@YOUR_DROPLET_IP
```

Cài đặt Docker:

```bash
# Update system
apt update && apt upgrade -y

# Install prerequisites
apt install -y ca-certificates curl gnupg lsb-release

# Add Docker GPG key
mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Add Docker repository
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Enable and start Docker
systemctl enable docker
systemctl start docker

# Verify installation
docker compose version
```

### Bước 3: Thiết Lập Domain

#### Trong DigitalOcean Dashboard

1. Vào **Networking** → **Domains**
2. Nhập domain của bạn và click **Add Domain**
3. Tạo **A Record**:
   - **Hostname**: `@` hoặc `www`
   - **Will redirect to**: Chọn droplet của bạn
   - **TTL**: 3600
4. Tạo **AAAA Record** nếu có IPv6

#### Xác minh DNS

```bash
# Kiểm tra DNS propagation
dig +short peakgear.stramify.id.vn
# Hoặc
nslookup peakgear.stramify.id.vn

# Kết quả mong đợi: Droplet IP address
```

### Bước 4: Deploy Code

#### 4.1 Chuẩn bị Repository

```bash
# Tạo thư mục project
mkdir -p /opt/peakgear
cd /opt/peakgear

# Clone repository
git clone https://github.com/YOUR_USERNAME/PeakGear.git .
git checkout production  # Hoặc branch phù hợp
```

#### 4.2 Cấu hình Environment

```bash
# Copy và chỉnh sửa .env
cp .env.example .env
nano .env
```

Nội dung `.env` cho production (tham khảo `.env.example` đầy đủ):

```env
# --- Magento ---
MAGENTO_VERSION=2.4.7
COMPOSER_VERSION=2.7.0

# Magento Marketplace Keys (REQUIRED)
MAGENTO_PUBLIC_KEY=your_magento_public_key
MAGENTO_PRIVATE_KEY=your_magento_private_key

# --- MySQL ---
MYSQL_ROOT_PASSWORD=YourSecureRootPassword123!
MYSQL_DATABASE=magento
MYSQL_USER=magento
MYSQL_PASSWORD=YourSecureDBPassword123!

# --- OpenSearch ---
OPENSEARCH_PASSWORD=YourSecureOpenSearchPassword123!

# --- Redis ---
REDIS_PASSWORD=

# --- Magento Storefront ---
MAGENTO_HOST=peakgear.stramify.id.vn

# --- Magento Admin ---
MAGENTO_ADMIN_USER=admin
MAGENTO_ADMIN_PASSWORD=YourSecureAdminPassword123!
MAGENTO_ADMIN_EMAIL=admin@peakgear.stramify.id.vn
MAGENTO_ADMIN_FIRSTNAME=Admin
MAGENTO_ADMIN_LASTNAME=Admin

# --- VNPay (tùy chọn) ---
VNPAY_TMN_CODE=YOUR_TMN_CODE
VNPAY_HASH_SECRET=YOUR_HASH_SECRET
VNPAY_PAYMENT_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_PATH=vnpay/payment/return/index

# --- ZaloPay (tùy chọn) ---
ZALOPAY_APP_ID=YOUR_APP_ID
ZALOPAY_APP_USER=user_test
ZALOPAY_KEY1=YOUR_KEY1
ZALOPAY_KEY2=YOUR_KEY2
ZALOPAY_CREATE_URL=https://sandbox.zalopay.com.vn/v001/tpe/createorder
ZALOPAY_QUERY_URL=https://sandbox.zalopay.com.vn/v001/tpe/getstatusbyapptransid
```

#### 4.3 Build và Start

```bash
# Build images
docker compose -f docker-compose.prod.yaml build

# Start services
docker compose -f docker-compose.prod.yaml up -d

# Theo dõi logs
   docker compose -f docker-compose.prod.yaml logs -f
```

#### 4.4 Chờ Services Healthy

```bash
# Kiểm tra trạng thái
docker compose -f docker-compose.prod.yaml ps

# Đợi services healthy (khoảng 2-3 phút)
# MySQL và OpenSearch cần thời gian khởi động

# Kiểm tra từng service
docker compose -f docker-compose.prod.yaml exec mysql mysqladmin ping -h localhost -u root -p
curl -s http://localhost:9200
docker compose -f docker-compose.prod.yaml exec redis redis-cli ping
```

#### 4.5 Cài Đặt Magento

Sử dụng script deploy.sh để cài đặt tự động (khuyến nghị):

```bash
# Chạy deploy script (sẽ tự động cài đặt Magento nếu chưa có)
bash scripts/deploy.sh
```

Hoặc cài đặt thủ công:

```bash
# Vào container PHP
docker compose -f docker-compose.prod.yaml exec php bash

# Cài đặt Magento
cd /var/www/html
php bin/magento setup:install \
    --db-host=mysql \
    --db-name=magento \
    --db-user=magento \
    --db-password='magento' \
    --base-url=http://peakgear.stramify.id.vn \
    --base-url-secure=https://peakgear.stramify.id.vn \
    --admin-firstname=Admin \
    --admin-lastname=Admin \
    --admin-email=admin@example.com \
    --admin-user=admin \
    --admin-password=admin123 \
    --language=en_US \
    --currency=USD \
    --timezone=Asia/Ho_Chi_Minh \
    --search-engine=opensearch \
    --opensearch-host=opensearch \
    --opensearch-port=9200 \
    --use-rewrites=1 \
    --session-save=redis \
    --session-save-redis-host=redis \
    --session-save-redis-port=6379 \
    --session-save-redis-db=0 \
    --cache-backend=redis \
    --cache-backend-redis-host=redis \
    --cache-backend-redis-port=6379 \
    --cache-backend-redis-db=1 \
    --page-cache=redis \
    --page-cache-redis-host=redis \
    --page-cache-redis-port=6379 \
    --page-cache-redis-db=2

# Deploy static content
php bin/magento setup:static-content:deploy -f --jobs=4

# Set production mode
php bin/magento deploy:mode:set production

# Reindex
php bin/magento indexer:reindex

# Setup cron
php bin/magento cron:install

# Flush cache
php bin/magento cache:flush

# Exit container
exit
```

#### 4.6 Fix Permissions

```bash
docker compose -f docker-compose.prod.yaml exec php bash -c "
    chown -R www-data:www-data /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type d -exec chmod 775 {} \;
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type f -exec chmod 664 {} \;
"
```

### Bước 5: Cấu Hình SSL

#### Sử dụng Let's Encrypt (Khuyến nghị)

```bash
# Cài đặt Certbot
apt install -y certbot python3-certbot-nginx

# Tạm dừng Nginx để Let's Encrypt verify
docker compose -f docker-compose.prod.yaml stop nginx

# Lấy certificate
certbot certonly --standalone -d peakgear.streamify.id.vn -d www.peakgear.streamify.id.vn

# Copy certificates vào project
mkdir -p /opt/peakgear/docker/nginx/ssl
cp /etc/letsencrypt/live/peakgear.streamify.id.vn/fullchain.pem /opt/peakgear/docker/nginx/ssl/
cp /etc/letsencrypt/live/peakgear.streamify.id.vn/privkey.pem /opt/peakgear/docker/nginx/ssl/

# Restart Nginx
docker compose -f docker-compose.prod.yaml start nginx

# Auto-renewal setup
systemctl edit --full --force certbot.timer
```

Nội dung cho certbot timer:

```ini
[Unit]
Description=Run certbot twice daily

[Timer]
OnCalendar=*-*-* 00,12:00:00
RandomizedDelaySec=3600
Persistent=true

[Install]
WantedBy=timers.target
```

```bash
systemctl enable certbot.timer
systemctl start certbot.timer
```

#### Uncomment HTTPS Config trong nginx.prod.conf

```bash
nano /opt/peakgear/docker/nginx/nginx.prod.conf
```

Uncomment phần HTTPS server (server block thứ 2) và comment phần HTTP redirect.

#### Restart Nginx

```bash
docker compose -f docker-compose.prod.yaml restart nginx
```

---

## Quản Lý Production

### Sử Dụng Deploy Script

```bash
# Deployment thông thường (tự động backup)
bash /opt/peakgear/scripts/deploy.sh

# Deployment không backup
bash /opt/peakgear/scripts/deploy.sh --no-backup
```

### Các Lệnh Docker Thường Dùng

```bash
# Xem trạng thái
docker compose -f docker-compose.prod.yaml ps

# Xem logs
docker compose -f docker-compose.prod.yaml logs -f
docker compose -f docker-compose.prod.yaml logs -f nginx
docker compose -f docker-compose.prod.yaml logs -f php

# Restart services
docker compose -f docker-compose.prod.yaml restart

# Stop/Start
docker compose -f docker-compose.prod.yaml stop
docker compose -f docker-compose.prod.yaml start

# Rebuild sau khi thay đổi code
docker compose -f docker-compose.prod.yaml up -d --build
```

### Magento CLI Commands

```bash
# Vào container
docker compose -f docker-compose.prod.yaml exec php bash

# Cache management
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento cache:status

# Reindex
php bin/magento indexer:reindex
php bin/magento indexer:status

# Static content deploy (sau khi thay đổi theme)
php bin/magento setup:static-content:deploy -f --jobs=4

# Module management
php bin/magento module:status
php bin/magento module:enable Vendor_Module
php bin/magento setup:upgrade

# Setup cron
php bin/magento cron:run

# Production mode
php bin/magento deploy:mode:show
php bin/magento deploy:mode:set production
php bin/magento deploy:mode:set developer
```

---

## Backup & Restore

### Tạo Backup

```bash
# Backup đầy đủ (database + media + config)
bash /opt/peakgear/scripts/backup.sh

# Chỉ backup database
bash /opt/peakgear/scripts/backup.sh backup_database

# List backups
bash /opt/peakgear/scripts/backup.sh list
```

### Restore từ Backup

```bash
# Khôi phục database
bash /opt/peakgear/scripts/backup.sh restore /opt/peakgear/backups/peakgear_db_20240101_120000.sql.gz
```

### Backup Files

| Loại | Location | Retention |
|------|----------|-----------|
| Database | `/opt/peakgear/backups/peakgear_db_*.sql.gz` | 7 days |
| Media | `/opt/peakgear/backups/peakgear_media_*.tar.gz` | 7 days |
| Config | `/opt/peakgear/backups/peakgear_config_*.tar.gz` | 7 days |

### Automated Backup với Cron

```bash
# Thêm vào crontab
crontab -e

# Chạy backup hàng ngày lúc 2h sáng
0 2 * * * /opt/peakgear/scripts/backup.sh >> /var/log/backup.log 2>&1

# Hoặc backup 2 lần mỗi ngày
0 2,14 * * * /opt/peakgear/scripts/backup.sh >> /var/log/backup.log 2>&1
```

---

## Monitoring

### Kiểm Tra Services

```bash
# Check all services health
docker compose -f docker-compose.prod.yaml ps

# Check specific service
curl -s http://localhost:9200  # OpenSearch
docker compose -f docker-compose.prod.yaml exec redis redis-cli ping  # Redis
```

### Logs

```bash
# All logs
docker compose -f docker-compose.prod.yaml logs --tail=100

# Specific service
docker compose -f docker-compose.prod.yaml logs --tail=100 -f nginx
docker compose -f docker-compose.prod.yaml logs --tail=100 -f php
docker compose -f docker-compose.prod.yaml logs --tail=100 -f mysql
```

### DigitalOcean Monitoring

```bash
# Cài đặt monitoring agent (đã có sẵn trên droplet)
# Kiểm tra trong DigitalOcean Dashboard > Droplet > Graphs

# Theo dõi:
# - CPU Usage
# - Memory Usage
# - Disk I/O
# - Network Traffic
```

---

## Troubleshooting

### Services Không Start

```bash
# Xem logs chi tiết
docker compose -f docker-compose.prod.yaml logs

# Restart tất cả
docker compose -f docker-compose.prod.yaml restart

# Rebuild nếu có lỗi
docker compose -f docker-compose.prod.yaml down
docker compose -f docker-compose.prod.yaml up -d --build
```

### MySQL Không Healthy

```bash
# Kiểm tra logs
docker compose -f docker-compose.prod.yaml logs mysql

# Xem docker logs chi tiết
docker logs peakgear_mysql

# Thử reconnect
docker compose -f docker-compose.prod.yaml restart mysql
```

### OpenSearch Không Healthy

```bash
# OpenSearch cần 60-90 giây để start
# Kiểm tra
curl http://localhost:9200

# Nếu không hoạt động
docker compose -f docker-compose.prod.yaml restart opensearch
```

### 503 Service Unavailable

```bash
# Kiểm tra PHP health
docker compose -f docker-compose.prod.yaml exec php php-fpm -t

# Kiểm tra Nginx logs
docker compose -f docker-compose.prod.yaml logs nginx

# Restart PHP
docker compose -f docker-compose.prod.yaml restart php
```

### Permission Issues

```bash
# Fix permissions
docker compose -f docker-compose.prod.yaml exec php bash -c "
    chown -R www-data:www-data /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type d -exec chmod 775 {} \;
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type f -exec chmod 664 {} \;
"
```

---

## Quick Reference

```bash
# === DEPLOYMENT ===
git -C /opt/peakgear pull origin main
bash /opt/peakgear/scripts/deploy.sh

# === MONITORING ===
docker compose -f docker-compose.prod.yaml ps
docker compose -f docker-compose.prod.yaml logs -f

# === BACKUP ===
bash /opt/peakgear/scripts/backup.sh

# === MAGENTO ===
docker compose -f docker-compose.prod.yaml exec php bash
php bin/magento cache:flush
php bin/magento indexer:reindex

# === SSL RENEWAL ===
certbot renew
docker compose -f docker-compose.prod.yaml restart nginx
```

---

## CI/CD

Dự án hỗ trợ **GitHub Actions** để tự động deploy khi có push lên GitHub.

Xem chi tiết: [docs/hosting/cicd.md](./cicd.md)

### Nhanh

1. Tạo SSH key cho deploy:
   ```bash
   ssh-keygen -t ed25519 -C "github-actions" -f deploy_key
   ```

2. Thêm public key vào droplet:
   ```bash
   ssh root@YOUR_DROPLET_IP "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys" < deploy_key.pub
   ```

3. Thêm vào GitHub Secrets:
   - `DROPLET_SSH_KEY`: Nội dung private key `deploy_key`
   - `DROPLET_IP`: IP của droplet

4. Push code lên `main` → **Tự động deploy!**