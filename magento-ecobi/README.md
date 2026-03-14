# Magento 2.4.7 - Docker Development Environment

## Yêu cầu hệ thống

- Docker Desktop / Docker Engine
- Docker Compose V2
- Tối thiểu 8GB RAM
- Các ports sau còn trống: 80, 443, 3308, 6380, 9201, 5672, 15672, 1080

## Cấu trúc dự án

```
magento-ecobi/
├── bin/                    # Scripts tiện ích
├── compose.yaml            # Docker Compose config
├── env/                    # Biến môi trường
│   ├── db.env             # MySQL credentials
│   └── magento.env        # Magento credentials
├── src/                    # Magento source code
│   ├── app/code/          # Custom modules
│   ├── app/design/         # Themes
│   └── pub/media/         # Media files (images)
├── Makefile               # CLI commands
└── README.md
```

## Các bước chạy dự án

### 1. Kiểm tra vendor dependencies

```bash
# Kiểm tra vendor directory
ls -la src/vendor/autoload.php

# Nếu chưa có, chạy composer install
docker compose exec phpfpm composer install --no-interaction --prefer-dist
```

### 2. Kiểm tra nginx.conf

```bash
# Nginx cần file nginx.conf (không phải nginx.conf.sample)
ls -la src/nginx.conf

# Nếu chưa có, copy từ sample
cp src/nginx.conf.sample src/nginx.conf
```

### 3. Khởi động Docker containers

```bash
docker compose up -d
```

### 4. Cài đặt Magento (chỉ khi database trống)

```bash
# Kiểm tra database
docker compose exec db mysql -u root -pmagento -e "USE magento; SHOW TABLES;" 2>/dev/null | wc -l
```

Nếu database trống (0 tables), chạy cài đặt:

```bash
docker compose exec phpfpm bin/magento setup:install \
  --backend-frontname=admin \
  --db-host=db \
  --db-name=magento \
  --db-user=magento \
  --db-password=magento \
  --admin-firstname=admin \
  --admin-lastname=user \
  --admin-email=admin@gmail.com \
  --admin-user=admin \
  --admin-password=Admin@123 \
  --language=vi_VN \
  --currency=VND \
  --timezone=Asia/Ho_Chi_Minh \
  --use-rewrites=1 \
  --session-save=redis \
  --cache-backend=redis \
  --cache-backend-redis-server=redis \
  --cache-backend-redis-db=0 \
  --page-cache=redis \
  --page-cache-redis-server=redis \
  --page-cache-redis-db=1 \
  --amqp-host=rabbitmq \
  --amqp-port=5672 \
  --amqp-user=magento \
  --amqp-password=magento \
  --search-engine=opensearch \
  --opensearch-host=opensearch \
  --opensearch-port=9200 \
  --opensearch-index-prefix=magento
```

### 5. Cấu hình HTTPS và URL

```bash
# Cấu hình base URLs
docker compose exec phpfpm php /var/www/html/bin/magento config:set web/secure/use_in_frontend 1
docker compose exec phpfpm php /var/www/html/bin/magento config:set web/secure/base_url https://localhost/
docker compose exec phpfpm php /var/www/html/bin/magento config:set web/unsecure/base_url http://localhost/

# Flush cache
docker compose exec phpfpm php /var/www/html/bin/magento cache:flush
```

### 6. Cấu hình Theme

```bash
# Theme đã đăng ký: Magento/blank, Magento/luma, Solwin/freego, Solwin/freego_child

# Xem danh sách theme
docker compose exec db mysql -u magento -pmagento magento -e "SELECT * FROM theme WHERE area='frontend';"

# Đổi theme (theme_id: 3=luma, 4=freego, 5=freego_child)
docker compose exec db mysql -u magento -pmagento magento -e "INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'design/theme/theme_id', '4') ON DUPLICATE KEY UPDATE value='4';"

# Flush cache
docker compose exec phpfpm php /var/www/html/bin/magento cache:flush
```

## Truy cập dự án

| Service          | URL                          |
|------------------|------------------------------|
| Frontend         | https://localhost/           |
| Admin            | https://localhost/admin/     |
| Mailcatcher     | http://localhost:1080/      |
| OpenSearch       | http://localhost:9201/       |
| RabbitMQ         | http://localhost:15672/      |
| MySQL           | localhost:3308               |
| Redis           | localhost:6380              |

## Thông tin đăng nhập

| Service      | Username | Password   |
|--------------|----------|------------|
| Magento Admin| admin    | Admin@123  |
| MySQL        | magento  | magento   |
| RabbitMQ     | magento  | magento   |

## Các lệnh hữu ích

```bash
# Xem trạng thái containers
docker compose ps

# Xem logs
docker compose logs -f

# Restart containers
docker compose restart

# Stop containers
docker compose stop

# Chạy lệnh Magento
docker compose exec phpfpm bin/magento <command>

# Flush cache
docker compose exec phpfpm php /var/www/html/bin/magento cache:flush

# Reindex
docker compose exec phpfpm php /var/www/html/bin/magento indexer:reindex

# MySQL CLI
docker compose exec db mysql -u magento -pmagento magento
```

## Xử lý sự cố

### Lỗi 502 Bad Gateway

```bash
# Kiểm tra PHP-FPM
docker compose exec phpfpm ps aux | grep php-fpm

# Restart PHP-FPM
docker compose restart phpfpm

# Kiểm tra socket
docker compose exec app ls -la /sock/
```

### Lỗi database connection

```bash
# Kiểm tra MySQL
docker compose exec db mysql -u root -pmagento -e "SELECT 1"

# Kiểm tra credentials trong env.php
docker compose exec phpfpm cat /var/www/html/app/etc/env.php | grep db
```

### Xóa và cài đặt lại

```bash
# Xóa volumes (mất dữ liệu)
docker compose down -v

# Xóa config files
rm -f src/app/etc/env.php src/app/etc/config.php

# Cài đặt lại (lặp lại bước 4)
```

## Modules custom trong dự án

- **Boolfly_ZaloPay** - Thanh toán ZaloPay
- **ExchangeRate_CurrencyExchange** - Tỷ giá tiền tệ
- **Lillik_PriceDecimal** - Format giá decimal
- **Magefan_Community** - Magefan extensions
- **Me_DecimalDelimiter** - Custom delimiter
- **Me_RestApi** - REST API custom
- **News_VNExpress** - Tin tức từ VnExpress
- **OpenWeather_WeatherForecast** - Thời tiết
- **Solwin_*** - Các extensions của Solwin
- **Utklasad_AdminProductGridCategoryFilter** - Filter category trong admin
- **Vnpayment_VNPAY** - Thanh toán VNPAY

## Lưu ý

- Database sẽ mất dữ liệu khi xóa Docker volumes
- Nên backup database thường xuyên: `make mysqldump`
- Ảnh sản phẩm trong `pub/media/` cần được gán với sản phẩm trong database để hiển thị
