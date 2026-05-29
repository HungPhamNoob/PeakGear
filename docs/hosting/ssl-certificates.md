# SSL Certificates Configuration

Hướng dẫn cấu hình SSL cho PeakGear trên production.

## Mục Lục

1. [Let's Encrypt (Khuyến Nghị)](#lets-encrypt-khuyến-nghị)
2. [Manual SSL Certificate](#manual-ssl-certificate)
3. [Auto-Renewal Setup](#auto-renewal-setup)
4. [SSL Hardening](#ssl-hardening)

---

## Let's Encrypt (Khuyến Nghị)

Let's Encrypt cung cấp **SSL miễn phí** và tự động renew.

### Bước 1: Cài Đặt Certbot

```bash
# SSH vào droplet
ssh root@YOUR_DROPLET_IP

# Cài đặt Certbot
apt update && apt upgrade -y
apt install -y certbot python3-certbot-nginx
```

### Bước 2: Lấy SSL Certificate

```bash
# Di chuyển vào thư mục project
cd /opt/peakgear

# Tạm dừng Nginx container
docker compose -f docker-compose.prod.yaml stop nginx

# Lấy certificate cho domain
certbot certonly --standalone -d peakgear.stramify.id.vn -d www.peakgear.stramify.id.vn

# Kết quả mong đợi:
# Successfully received certificate.
# Certificate is saved at: /etc/letsencrypt/live/peakgear.stramify.id.vn/fullchain.pem
# Key is saved at: /etc/letsencrypt/live/peakgear.stramify.id.vn/privkey.pem
```

### Bước 3: Copy Certificates

```bash
# Tạo thư mục SSL
mkdir -p /opt/peakgear/docker/nginx/ssl

# Copy certificates
cp /etc/letsencrypt/live/peakgear.stramify.id.vn/fullchain.pem /opt/peakgear/docker/nginx/ssl/
cp /etc/letsencrypt/live/peakgear.stramify.id.vn/privkey.pem /opt/peakgear/docker/nginx/ssl/

# Set proper permissions
chmod 644 /opt/peakgear/docker/nginx/ssl/fullchain.pem
chmod 600 /opt/peakgear/docker/nginx/ssl/privkey.pem
```

### Bước 4: Cập Nhật Nginx Config

```bash
nano /opt/peakgear/docker/nginx/nginx.prod.conf
```

1. **Uncomment HTTPS server block** (server block thứ 2)
2. **Comment hoặc remove HTTP server block** (hoặc uncomment return 301 redirect)

### Bước 5: Restart Nginx

```bash
# Start lại Nginx
docker compose -f docker-compose.prod.yaml start nginx

# Kiểm tra
curl -I https://peakgear.stramify.id.vn
```

---

## Manual SSL Certificate

Nếu bạn có SSL certificate từ provider khác (Comodo, DigiCert, etc.):

### Bước 1: Upload Certificates

```bash
# Tạo thư mục SSL
mkdir -p /opt/peakgear/docker/nginx/ssl

# Upload certificate files (sử dụng scp, sftp, hoặc nano để tạo file)
# Copy nội dung certificate vào:
nano /opt/peakgear/docker/nginx/ssl/fullchain.pem
nano /opt/peakgear/docker/nginx/ssl/privkey.pem

# Set permissions
chmod 644 /opt/peakgear/docker/nginx/ssl/fullchain.pem
chmod 600 /opt/peakgear/docker/nginx/ssl/privkey.pem
```

### Bước 2: Cấu Hình Nginx

```bash
nano /opt/peakgear/docker/nginx/nginx.prod.conf
```

Đảm bảo SSL directives trong server block:

```nginx
server {
    listen 443 ssl http2;
    server_name peakgear.stramify.id.vn www.peakgear.stramify.id.vn;

    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;

    # SSL Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    # ... phần còn lại của config
}
```

### Bước 3: Restart Nginx

```bash
docker compose -f docker-compose.prod.yaml restart nginx
```

---

## Auto-Renewal Setup

### Let's Encrypt Auto-Renewal

Let's Encrypt certificates **hết hạn sau 90 ngày**. Cấu hình auto-renewal:

```bash
# Test renewal
certbot renew --dry-run

# Tạo systemd timer cho renewal
cat > /etc/systemd/system/certbot-renewal.service << 'EOF'
[Unit]
Description=Certbot Renewal Service
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/bin/certbot renew --quiet --deploy-hook "docker compose -f /opt/peakgear/docker-compose.prod.yaml restart nginx"
PrivateTmp=true
EOF

cat > /etc/systemd/system/certbot-renewal.timer << 'EOF'
[Unit]
Description=Certbot Renewal Timer
Requires=certbot-renewal.service

[Timer]
OnCalendar=*-*-* 03:00:00
RandomizedDelaySec=3600
Persistent=true

[Install]
WantedBy=timers.target
EOF

# Enable và start timer
systemctl enable certbot-renewal.timer
systemctl start certbot-renewal.timer

# Kiểm tra
systemctl list-timers | grep certbot
```

### Manual Certificate Renewal

```bash
# Với certificate thường, theo dõi expiry date
openssl x509 -enddate -in /opt/peakgear/docker/nginx/ssl/fullchain.pem

# Renew (tùy provider)
# Sau khi có certificate mới:
cp new_certificate.pem /opt/peakgear/docker/nginx/ssl/fullchain.pem
docker compose -f docker-compose.prod.yaml restart nginx
```

---

## SSL Hardening

### Thêm HSTS Header

Trong nginx.prod.conf, thêm vào HTTPS server block:

```nginx
# HSTS (HTTP Strict Transport Security)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Uncomment dòng sau chỉ sau khi đã xác nhận HTTPS hoạt động tốt
# add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

### OCSP Stapling

Thêm vào HTTPS server block:

```nginx
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

### SSL Configuration Hoàn Chỉnh

```nginx
# HTTPS Server Block Example
server {
    listen 443 ssl http2;
    server_name peakgear.stramify.id.vn www.peakgear.stramify.id.vn;

    # SSL Certificates
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;

    # SSL Hardening
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # ... rest of config
}
```

### Test SSL Configuration

```bash
# Test SSL certificate
openssl s_client -connect peakgear.stramify.id.vn:443 -servername peakgear.stramify.id.vn

# Kiểm tra SSL rating
# Truy cập: https://www.ssllabs.com/ssltest/analyze.html?d=peakgear.stramify.id.vn

# Test local
curl -I https://localhost
```

---

## Troubleshooting

### Certificate Not Trusted

```bash
# Cài đặt CA certificate
apt install -y ca-certificates
update-ca-certificates
```

### Mixed Content Warnings

Đảm bảo tất cả resources sử dụng `https://`:

```bash
# Kiểm tra mixed content
# Sử dụng browser DevTools > Console

# Trong Magento, cập nhật base URL
docker compose -f docker-compose.prod.yaml exec php bash -c "
php /var/www/html/bin/magento config:set web/secure/base_url https://peakgear.stramify.id.vn/
php /var/www/html/bin/magento config:set web/unsecure/base_url https://peakgear.stramify.id.vn/
php /var/www/html/bin/magento cache:flush
"
```

### Redirect Loop

Kiểm tra nginx config - **KHÔNG** uncomment redirect 301 trong HTTP block nếu đã có HTTPS block riêng biệt:

```nginx
# HTTP block - KHÔNG uncomment return 301 nếu dùng separate HTTPS block
server {
    listen 80;
    server_name peakgear.stramify.id.vn www.peakgear.stramify.id.vn;
    root /var/www/html/pub;
    # return 301 https://$server_name$request_uri;  # COMMENT DÒNG NÀY NẾU CÓ HTTPS BLOCK
    # HOẶC:
    # uncomment chỉ khi KHÔNG có HTTPS server block riêng
}
```

### SSL Certificate Expired

```bash
# Check expiration
openssl x509 -enddate -noout -in /opt/peakgear/docker/nginx/ssl/fullchain.pem

# Renew với certbot
certbot renew

# Copy renewed certificate
cp /etc/letsencrypt/live/peakgear.stramify.id.vn/fullchain.pem /opt/peakgear/docker/nginx/ssl/
cp /etc/letsencrypt/live/peakgear.stramify.id.vn/privkey.pem /opt/peakgear/docker/nginx/ssl/

# Restart nginx
docker compose -f docker-compose.prod.yaml restart nginx
```