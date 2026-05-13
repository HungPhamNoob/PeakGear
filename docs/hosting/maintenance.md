# Maintenance Guide

Hướng dẫn bảo trì và troubleshoot PeakGear trên production.

## Mục Lục

1. [Daily Maintenance](#daily-maintenance)
2. [Weekly Maintenance](#weekly-maintenance)
3. [Monthly Maintenance](#monthly-maintenance)
4. [Troubleshooting](#troubleshooting)
5. [Emergency Procedures](#emergency-procedures)

---

## Daily Maintenance

### Kiểm Tra Services

```bash
# Check service status
docker compose -f docker-compose.prod.yaml ps

# Expected output: all services should show "healthy" or "Up"
```

### Xem Logs

```bash
# All services
docker compose -f docker-compose.prod.yaml logs --tail=50

# Specific service
docker compose -f docker-compose.prod.yaml logs --tail=50 -f nginx
docker compose -f docker-compose.prod.yaml logs --tail=50 -f php
docker compose -f docker-compose.prod.yaml logs --tail=50 -f mysql
```

### Monitor Resources

```bash
# Docker stats
docker stats --no-stream

# Kiểm tra disk space
df -h

# Kiểm tra memory
free -h

# Kiểm tra load
uptime
```

### Check Magento Cache

```bash
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento cache:status

# Flush nếu cần
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento cache:flush
```

---

## Weekly Maintenance

### Database Backup

```bash
# Tạo backup
bash /opt/peakgear/scripts/backup.sh

# List backups
bash /opt/peakgear/scripts/backup.sh list

# Kiểm tra backup files
ls -lh /opt/peakgear/backups/
```

### Verify Backups

```bash
# Test restore database (tùy chọn, trên staging)
# gunzip < /opt/peakgear/backups/peakgear_db_20240101_120000.sql.gz | head -20
```

### Cleanup Old Logs

```bash
# Xóa Docker logs cũ
truncate -s 0 /var/lib/docker/containers/*/*-json.log

# Xóa Magento logs cũ (nếu có)
docker compose -f docker-compose.prod.yaml exec php find /var/www/html/var/log -name "*.log" -mtime +7 -delete
```

### Check SSL Certificate

```bash
# Kiểm tra expiry
openssl x509 -enddate -noout -in /opt/peakgear/docker/nginx/ssl/fullchain.pem

# Test renewal (Let's Encrypt)
certbot renew --dry-run
```

### Update Docker Images (Optional)

```bash
# Pull latest images
docker compose -f docker-compose.prod.yaml pull

# Rebuild với latest images
docker compose -f docker-compose.prod.yaml up -d --build

# Verify sau khi update
docker compose -f docker-compose.prod.yaml ps
```

---

## Monthly Maintenance

### Full System Reboot

```bash
# Backup trước khi reboot
bash /opt/peakgear/scripts/backup.sh

# Stop services
docker compose -f docker-compose.prod.yaml down

# Reboot droplet
reboot

# Sau khi reboot, SSH vào và start lại
docker compose -f docker-compose.prod.yaml up -d

# Verify
docker compose -f docker-compose.prod.yaml ps
```

### Full Reindex

```bash
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento indexer:reindex
```

### Security Audit

```bash
# Check for outdated packages
apt update && apt list --upgradable

# Update Docker
apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Update system packages (cẩn thận)
apt upgrade -y
```

### Review Logs

```bash
# Apache/Nginx access logs
docker compose -f docker-compose.prod.yaml logs nginx | tail -1000

# PHP errors
docker compose -f docker-compose.prod.yaml logs php | grep -i error

# MySQL errors
docker compose -f docker-compose.prod.yaml logs mysql | grep -i error
```

### Clean Up Docker

```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Remove unused networks
docker network prune

# Full cleanup (cẩn thận!)
docker system prune -a --volumes
```

---

## Troubleshooting

### Service Không Start Được

```bash
# Xem logs chi tiết
docker compose -f docker-compose.prod.yaml logs

# Kiểm tra port conflicts
netstat -tlnp | grep -E '80|443|3306|6379|9200'

# Thử rebuild
docker compose -f docker-compose.prod.yaml down
docker compose -f docker-compose.prod.yaml up -d --build
```

### Database Connection Failed

```bash
# Check MySQL status
docker compose -f docker-compose.prod.yaml exec mysql mysqladmin ping -h localhost -u root -p

# Kiểm tra MySQL logs
docker compose -f docker-compose.prod.yaml logs mysql

# Fix: Restart MySQL
docker compose -f docker-compose.prod.yaml restart mysql

# Đợi healthy rồi thử lại
sleep 30
docker compose -f docker-compose.prod.yaml ps
```

### OpenSearch Unhealthy

```bash
# OpenSearch cần 60-90 giây để start
# Kiểm tra
curl http://localhost:9200

# Nếu lỗi, restart
docker compose -f docker-compose.prod.yaml restart opensearch

# Đợi
sleep 90

# Reindex search
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento indexer:reindex search
```

### Redis Connection Failed

```bash
# Test connection
docker compose -f docker-compose.prod.yaml exec redis redis-cli ping

# Restart Redis
docker compose -f docker-compose.prod.yaml restart redis
```

### PHP-FPM Errors

```bash
# Test PHP config
docker compose -f docker-compose.prod.yaml exec php php-fpm -t

# Check logs
docker compose -f docker-compose.prod.yaml logs php

# Restart PHP
docker compose -f docker-compose.prod.yaml restart php
```

### Nginx 502 Bad Gateway

```bash
# PHP có thể chưa ready
# Check PHP status
docker compose -f docker-compose.prod.yaml exec php php-fpm -t

# Restart both
docker compose -f docker-compose.prod.yaml restart php
docker compose -f docker-compose.prod.yaml restart nginx

# Kiểm tra socket
docker compose -f docker-compose.prod.yaml exec nginx ls -la /var/run/
```

### Website Load Chậm

```bash
# Check resource usage
docker stats

# Kiểm tra database queries
docker compose -f docker-compose.prod.yaml exec mysql mysql -u root -p -e "SHOW PROCESSLIST;"

# Clear Magento cache
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento cache:flush

# Reindex
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento indexer:reindex

# Check MySQL slow query log
docker compose -f docker-compose.prod.yaml exec mysql cat /var/log/mysql/slow-query.log
```

### Permission Denied Errors

```bash
# Fix permissions
docker compose -f docker-compose.prod.yaml exec php bash -c "
    chown -R www-data:www-data /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type d -exec chmod 775 {} \;
    find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type f -exec chmod 664 {} \;
    chmod +x /var/www/html/bin/magento
"
```

### Cron Jobs Not Running

```bash
# Check cron
docker compose -f docker-compose.prod.yaml exec php crontab -l

# Run manually
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento cron:run

# Check Magento cron schedule
docker compose -f docker-compose.prod.yaml exec php php /var/www/html/bin/magento cron:schedule:run
```

### Disk Full

```bash
# Check disk usage
df -h

# Docker cleanup
docker system prune -a
docker volume prune

# Xóa old backups
find /opt/peakgear/backups -name "*.gz" -mtime +7 -delete

# Xóa Docker logs
truncate -s 0 /var/lib/docker/containers/*/*-json.log

# Clean Magento cache
docker compose -f docker-compose.prod.yaml exec php rm -rf /var/www/html/var/cache/*
docker compose -f docker-compose.prod.yaml exec php rm -rf /var/www/html/var/page_cache/*

# Kiểm tra large files
du -sh /var/www/html/*
```

---

## Emergency Procedures

### Database Backup Failed

```bash
# Thử backup manual
docker compose -f docker-compose.prod.yaml exec -T mysql mysqldump \
    -u root -p"${MYSQL_ROOT_PASSWORD}" \
    peakgear > /opt/peakgear/backups/peakgear_db_emergency_$(date +%Y%m%d_%H%M%S).sql

# Nếu MySQL down hoàn toàn, data vẫn còn trong volume
docker volume ls | grep peakgear_mysql
```

### Complete Recovery

```bash
# 1. Stop services
docker compose -f docker-compose.prod.yaml down

# 2. Xóa volumes (CẨN THẬN - mất data!)
docker volume rm peakgear_mysql_data peakgear_opensearch_data peakgear_redis_data

# 3. Start lại
docker compose -f docker-compose.prod.yaml up -d

# 4. Restore data từ backup
bash /opt/peakgear/scripts/backup.sh restore /opt/peakgear/backups/peakgear_db_LATEST.sql.gz

# 5. Reinstall Magento nếu cần
# (chạy setup:install lại hoặc restore từ backup khác)
```

### Rollback Deployment

```bash
# 1. Git rollback
cd /opt/peakgear
git log --oneline -10
git revert HEAD  # hoặc git reset --hard <commit-id>

# 2. Backup current state
bash /opt/peakgear/scripts/backup.sh

# 3. Redeploy
bash /opt/peakgear/scripts/deploy.sh
```

### Service Unresponsive

```bash
# 1. Force restart
docker compose -f docker-compose.prod.yaml restart

# 2. Nếu không được, full restart
docker compose -f docker-compose.prod.yaml down
docker compose -f docker-compose.prod.yaml up -d

# 3. Kiểm tra
docker compose -f docker-compose.prod.yaml ps
curl -I http://localhost

# 4. Nếu vẫn lỗi, rebuild từ đầu
docker compose -f docker-compose.prod.yaml down
docker compose -f docker-compose.prod.yaml up -d --build
```

---

## Health Check Commands

```bash
# Quick health check
docker compose -f docker-compose.prod.yaml ps | grep -E "(Up|healthy)" | wc -l

# Full health check script
#!/bin/bash
echo "=== PeakGear Health Check ==="
echo ""

echo "1. Docker Services:"
docker compose -f /opt/peakgear/docker-compose.prod.yaml ps
echo ""

echo "2. Nginx:"
curl -s -o /dev/null -w "%{http_code}" http://localhost/
echo ""
echo ""

echo "3. OpenSearch:"
curl -s http://localhost:9200 | jq -r '.version.number' 2>/dev/null || echo "OpenSearch not responding"
echo ""

echo "4. Redis:"
docker compose -f /opt/peakgear/docker-compose.prod.yaml exec -T redis redis-cli ping 2>/dev/null || echo "Redis not responding"
echo ""

echo "5. MySQL:"
docker compose -f /opt/peakgear/docker-compose.prod.yaml exec -T mysql mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD}" 2>/dev/null || echo "MySQL not responding"
echo ""

echo "6. Disk Space:"
df -h / | tail -1
echo ""

echo "7. Memory:"
free -h | grep Mem
echo ""

echo "=== Health Check Complete ==="
```