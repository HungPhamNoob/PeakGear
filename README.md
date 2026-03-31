# PeakGear - Magento 2 E-Commerce

Dự án thương mại điện tử PeakGear, xây dựng trên nền tảng Magento 2.4.7 với Docker.

## Yêu cầu hệ thống

- **Docker Desktop** (Windows/Mac) hoặc **Docker Engine + Docker Compose** (Linux)
- **RAM**: tối thiểu 4GB trống cho Docker (khuyến nghị 8GB)
- **Dung lượng đĩa**: tối thiểu 5GB trống
- **Port trống**: 80, 3307, 6379, 8080, 9200

## Hướng dẫn cài đặt (dành cho Developer)

### Bước 1: Clone dự án

```bash
git clone <repo-url>
cd PeakGear
```

### Bước 2: Tạo file .env

```bash
cp .env.example .env
```

Mở file `.env` và điền **Magento Marketplace Keys**:

```
MAGENTO_PUBLIC_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAGENTO_PRIVATE_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Cách lấy Magento Keys (miễn phí):**
1. Vào https://marketplace.magento.com, đăng ký tài khoản (miễn phí)
2. Vào **My Profile** > **Access Keys**
3. Nhấn **Create A New Access Key**, đặt tên bất kỳ
4. Copy **Public Key** và **Private Key** vào file `.env`

### Bước 3: Chạy script cài đặt

```bash
bash scripts/setup-for-developer.sh
```

Script sẽ tự động:
1. Tạo file `src/auth.json` từ keys trong `.env`
2. Build Docker images và khởi động containers
3. Cài đặt Composer dependencies (`src/vendor/`)
4. Cài đặt Magento (tạo database, admin user, v.v.)

**Thời gian:** khoảng 5-10 phút (lần đầu), tùy tốc độ mạng.

### Bước 4: Truy cập website

| Service    | URL                          |
|------------|------------------------------|
| Storefront | http://localhost             |
| Admin      | http://localhost/admin       |
| phpMyAdmin | http://localhost:8080        |
| OpenSearch | http://localhost:9200        |

**Tài khoản Admin mặc định:**
- Username: `admin`
- Password: `admin123`

(Có thể thay đổi trong `.env` trước khi chạy script)

## Sử dụng hàng ngày

### Khởi động project

```bash
docker compose up -d
```

### Dừng project

```bash
docker compose down
```

### Xem logs

```bash
# Tất cả containers
docker compose logs -f

# Chỉ PHP
docker compose logs -f php

# Chỉ Nginx
docker compose logs -f nginx
```

### Vào container PHP (để chạy lệnh Magento)

```bash
docker compose exec php bash
```

## Lệnh Magento thường dùng

```bash
# =========================================================
# PeakGear Magento Workflow (Docker Compose)
# Mục tiêu: rerender nhanh, rõ pipeline, tránh chạy dư.
# Service php đã cấu hình user www-data trong docker-compose,
# nên không cần thêm flag -u www-data khi exec.
# =========================================================

# Pipeline 0: Kiểm tra module (chỉ khi cần xác minh)
docker compose exec php php /var/www/html/bin/magento module:status

# Pipeline 1: Update nhẹ (chỉ .phtml hoặc layout XML)
docker compose exec php php /var/www/html/bin/magento cache:clean

# Pipeline 2: Update assets/theme (LESS/CSS/JS, images, theme)
docker compose exec php php /var/www/html/bin/magento cache:flush
docker compose exec php rm -rf /var/www/html/pub/static/frontend/PeakGear/climbing/* /var/www/html/var/view_preprocessed/*
docker compose exec php php /var/www/html/bin/magento setup:static-content:deploy -f

# Pipeline 3: Sau khi thêm module mới hoặc thay đổi module.xml/registration.php
docker compose exec php php /var/www/html/bin/magento setup:upgrade
docker compose exec php php /var/www/html/bin/magento cache:flush

# Pipeline 4: Khi dữ liệu index bị lệch (catalog/product/price...)
docker compose exec php php /var/www/html/bin/magento indexer:reindex

# Pipeline FULL: Tổng hợp (khi thay đổi lớn hoặc nghi ngờ cache bám)
# 1) setup:upgrade  2) flush cache  3) clear static  4) deploy static  5) reindex
docker compose exec php php /var/www/html/bin/magento setup:upgrade
docker compose exec php php /var/www/html/bin/magento cache:flush
docker compose exec php rm -rf /var/www/html/pub/static/frontend/PeakGear/climbing/* /var/www/html/var/view_preprocessed/*
docker compose exec php php /var/www/html/bin/magento setup:static-content:deploy -f
docker compose exec php php /var/www/html/bin/magento indexer:reindex

#nếu có lỗi:
docker compose exec php bash -c "rm -rf generated/* var/cache/* var/page_cache/* var/di/* && php bin/magento setup:di:compile && php bin/magento cache:flush"

docker compose exec php bash -c "chmod -R 777 var pub/static generated"
```

### One-time fix để `docker compose exec php ...` chạy đúng user (không cần `--user`)

Nếu từng chạy lệnh Magento bằng root trước đó, hãy chạy 1 lần:

```bash
docker compose up -d --no-deps --force-recreate php
docker compose exec php id
docker compose exec --user root php chown -R www-data:www-data /var/www/html/pub/static /var/www/html/var /var/www/html/generated
```

Kết quả mong muốn của `docker compose exec php id`: `uid=33(www-data)`.

### Fix lỗi permission (pub/static, var, generated)

```bash
docker compose exec --user root php chown -R www-data:www-data /var/www/html/pub/static /var/www/html/var /var/www/html/generated
```

## Cấu trúc dự án

```
PeakGear/
├── docker/
│   ├── php/                # PHP-FPM 8.2 config, Dockerfile
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   ├── php-fpm.conf
│   │   └── supervisord.conf
│   └── nginx/              # Nginx config
│       └── nginx.conf
├── scripts/
│   ├── setup-for-developer.sh   # Setup 1 lệnh cho developer mới
│   ├── install-magento.sh       # Cài đặt Magento vào containers
│   └── setup-magento.sh         # Download source Magento (ít khi dùng)
├── src/                    # Magento 2.4.7 source code
│   ├── app/                # Modules, themes, config
│   ├── bin/magento         # Magento CLI
│   ├── composer.json
│   ├── composer.lock
│   ├── lib/                # Libraries
│   ├── pub/                # Public files (static, media)
│   └── ...
├── docker-compose.yaml     # Docker services
├── .env.example            # Template config
├── .env                    # Config thực tế (KHÔNG commit)
├── .gitignore
└── README.md
```

## Docker Services

| Container            | Image                          | Port  | Mô tả                    |
|----------------------|--------------------------------|-------|---------------------------|
| peakgear_php         | PHP 8.2 FPM (custom)          | 9000  | PHP + Composer + Cron     |
| peakgear_nginx       | Nginx 1.24                     | 80    | Web server                |
| peakgear_mysql       | MySQL 8.0                      | 3307  | Database                  |
| peakgear_opensearch  | OpenSearch 2.12                | 9200  | Search engine             |
| peakgear_redis       | Redis 7                        | 6379  | Cache & Sessions          |
| peakgear_phpmyadmin  | phpMyAdmin                     | 8080  | Database GUI              |

## Khắc phục sự cố

### "The page isn't working" / ERR_TOO_MANY_REDIRECTS

Magento chưa được cài đặt. Chạy:
```bash
bash scripts/install-magento.sh
```

### Container không khởi động

```bash
# Kiểm tra trạng thái
docker compose ps

# Xem logs
docker compose logs

# Khởi động lại từ đầu
docker compose down
docker compose up -d
```

### OpenSearch unhealthy

OpenSearch cần khoảng 30-60 giây để khởi động hoàn tất:
```bash
# Kiểm tra trực tiếp
curl http://localhost:9200

# Nếu vẫn không được, restart
docker compose restart opensearch
```

### Lỗi permissions (Linux)

```bash
docker compose exec --user root php chown -R www-data:www-data /var/www/html/var
docker compose exec --user root php chown -R www-data:www-data /var/www/html/generated
docker compose exec --user root php chown -R www-data:www-data /var/www/html/pub/static
```

### Muốn cài đặt lại từ đầu (reset database)

```bash
# Xóa toàn bộ data (database, cache, search index)
docker compose down -v

# Cài lại
docker compose up -d
bash scripts/install-magento.sh
```

## Lưu ý quan trọng

1. **KHÔNG commit file `.env`** - File này chứa mật khẩu, đã có trong `.gitignore`
2. **`src/vendor/`** không được commit - Được tạo bởi `composer install`, mỗi developer tự chạy
3. **`src/app/etc/env.php`** không được commit - Chứa config database, tự tạo khi install
4. **`src/app/etc/config.php`** ĐƯỢC commit - Chứa danh sách modules, đảm bảo mọi người dùng cùng config
5. **Database** lưu trong Docker volume - Persist khi restart, mất khi chạy `docker compose down -v`

## Quy trình làm việc nhóm

1. Pull code mới nhất: `git pull`
2. Nếu có thay đổi composer: `docker compose exec php bash -c 'cd /var/www/html && composer install'`
3. Nếu có thay đổi module: `docker compose exec php php /var/www/html/bin/magento setup:upgrade`
4. Clear cache: `docker compose exec php php /var/www/html/bin/magento cache:flush`
