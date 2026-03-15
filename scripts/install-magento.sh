#!/bin/bash
#
# PeakGear - Install Magento into running Docker containers.
#
# This script:
#   1. Checks all containers are running
#   2. Waits for MySQL and OpenSearch to be ready
#   3. Runs Magento setup:install
#   4. Disables TwoFactorAuth (for development)
#   5. Sets developer mode, reindexes, flushes cache
#
# Prerequisites:
#   - Docker containers running (docker compose up -d)
#   - Composer dependencies installed (src/vendor/ exists)
#   - .env file configured
#
# Usage:
#   bash scripts/install-magento.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ---------- Load .env ----------
if [ -f "$PROJECT_DIR/.env" ]; then
    set -a
    . "$PROJECT_DIR/.env"
    set +a
else
    echo "ERROR: .env file not found!"
    echo "  cp .env.example .env   # then fill in your values"
    exit 1
fi

# ---------- Helper functions ----------
require_env() {
    local var_name="$1"
    if [ -z "${!var_name:-}" ]; then
        echo "ERROR: Required environment variable '$var_name' is not set in .env"
        exit 1
    fi
}

check_container_running() {
    local container_name="$1"
    if ! docker ps --format '{{.Names}}' | grep -q "^${container_name}$"; then
        echo "ERROR: Container '${container_name}' is not running."
        echo "  Run 'docker compose up -d' first."
        exit 1
    fi
}

wait_for_service() {
    local service_name="$1"
    local check_cmd="$2"
    local max_retries="${3:-30}"
    local sleep_time="${4:-3}"

    echo "Waiting for $service_name..."
    for i in $(seq 1 "$max_retries"); do
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "  $service_name is ready."
            return 0
        fi
        if [ "$i" -lt "$max_retries" ]; then
            echo "  Attempt $i/$max_retries - waiting ${sleep_time}s..."
            sleep "$sleep_time"
        fi
    done
    echo ""
    echo "ERROR: $service_name did not become ready after $((max_retries * sleep_time))s."
    echo "  Check logs: docker compose logs"
    exit 1
}

# ---------- Start ----------
echo "=========================================="
echo "  Magento Install"
echo "=========================================="

# Check Magento source exists
if [ ! -f "$PROJECT_DIR/src/bin/magento" ]; then
    echo "ERROR: Magento source not found (src/bin/magento missing)."
    echo "  Make sure you cloned the repo correctly."
    exit 1
fi

# Check vendor exists
if [ ! -f "$PROJECT_DIR/src/vendor/autoload.php" ]; then
    echo "ERROR: Composer dependencies not installed (src/vendor/autoload.php missing)."
    echo "  Run: docker exec peakgear_php bash -c 'cd /var/www/html && composer install'"
    exit 1
fi

# Check for existing installation
if [ -f "$PROJECT_DIR/src/app/etc/env.php" ]; then
    echo ""
    echo "WARNING: Magento is already installed (app/etc/env.php exists)."
    echo "Reinstalling will reset the database."
    echo ""
    read -r -p "Continue? (y/n): " response
    if [ "${response,,}" != "y" ]; then
        echo "Aborted."
        exit 0
    fi
fi

# Check containers are running
check_container_running "peakgear_php"
check_container_running "peakgear_mysql"
check_container_running "peakgear_opensearch"
check_container_running "peakgear_redis"

# Check required env vars
require_env "MYSQL_DATABASE"
require_env "MYSQL_USER"
require_env "MYSQL_PASSWORD"

# Wait for services to be healthy
wait_for_service "MySQL" \
    "docker exec peakgear_mysql mysqladmin ping -h localhost -u root -p'${MYSQL_ROOT_PASSWORD:-rootpassword}' --silent" 30 3

wait_for_service "OpenSearch" \
    "docker exec peakgear_php curl -sf http://opensearch:9200 -o /dev/null" 30 3

wait_for_service "Redis" \
    "docker exec peakgear_redis redis-cli ping" 10 2

# ---------- Install Magento ----------
echo ""
echo "Running Magento setup:install (this takes 1-3 minutes)..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento setup:install \
    --base-url=http://${MAGENTO_HOST:-localhost} \
    --db-host=mysql \
    --db-name=${MYSQL_DATABASE} \
    --db-user=${MYSQL_USER} \
    --db-password=${MYSQL_PASSWORD} \
    --search-engine=opensearch \
    --opensearch-host=opensearch \
    --opensearch-port=9200 \
    --admin-firstname=${MAGENTO_ADMIN_FIRSTNAME:-Admin} \
    --admin-lastname=${MAGENTO_ADMIN_LASTNAME:-Admin} \
    --admin-email=${MAGENTO_ADMIN_EMAIL:-admin@example.com} \
    --admin-user=${MAGENTO_ADMIN_USER:-admin} \
    --admin-password=${MAGENTO_ADMIN_PASSWORD:-admin123} \
    --language=en_US \
    --currency=USD \
    --timezone=Asia/Ho_Chi_Minh \
    --use-rewrites=1 \
    --backend-frontname=admin \
    --session-save=redis \
    --session-save-redis-host=redis \
    --session-save-redis-db=0 \
    --cache-backend=redis \
    --cache-backend-redis-server=redis \
    --cache-backend-redis-db=1 \
    --page-cache=redis \
    --page-cache-redis-server=redis \
    --page-cache-redis-db=2 \
    --cleanup-database"

# ---------- Disable 2FA for development ----------
echo ""
echo "Disabling TwoFactorAuth (development only)..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth"

# ---------- Developer mode ----------
echo ""
echo "Setting developer mode..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento deploy:mode:set developer"

# ---------- Setup upgrade ----------
echo ""
echo "Running setup:upgrade..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento setup:upgrade"

# ---------- Reindex ----------
echo ""
echo "Reindexing..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento indexer:reindex"

# ---------- Cache flush ----------
echo ""
echo "Flushing cache..."
docker exec peakgear_php bash -c "cd /var/www/html && php bin/magento cache:flush"

echo ""
echo "=========================================="
echo "  Magento installed successfully!"
echo "=========================================="
echo ""
echo "  Storefront:  http://${MAGENTO_HOST:-localhost}/"
echo "  Admin Panel: http://${MAGENTO_HOST:-localhost}/admin"
echo "  Admin User:  ${MAGENTO_ADMIN_USER:-admin}"
echo "  Admin Pass:  ${MAGENTO_ADMIN_PASSWORD:-admin123}"
echo "  phpMyAdmin:  http://${MAGENTO_HOST:-localhost}:8080"
echo ""
