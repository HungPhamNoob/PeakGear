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
docker exec -it peakgear_php bash
```

## Lệnh Magento thường dùng

```bash
# Clear cache
docker exec peakgear_php php /var/www/html/bin/magento cache:flush

# Reindex
docker exec peakgear_php php /var/www/html/bin/magento indexer:reindex

# Kiểm tra module status
docker exec peakgear_php php /var/www/html/bin/magento module:status

# Chạy setup:upgrade (sau khi thêm module)
docker exec peakgear_php php /var/www/html/bin/magento setup:upgrade

# Deploy static content (production mode)
docker exec peakgear_php php /var/www/html/bin/magento setup:static-content:deploy -f
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
docker exec peakgear_php chown -R www-data:www-data /var/www/html/var
docker exec peakgear_php chown -R www-data:www-data /var/www/html/generated
docker exec peakgear_php chown -R www-data:www-data /var/www/html/pub/static
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
2. Nếu có thay đổi composer: `docker exec peakgear_php bash -c 'cd /var/www/html && composer install'`
3. Nếu có thay đổi module: `docker exec peakgear_php php /var/www/html/bin/magento setup:upgrade`
4. Clear cache: `docker exec peakgear_php php /var/www/html/bin/magento cache:flush`
