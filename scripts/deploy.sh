#!/bin/bash
set -e

# ===========================================
# PeakGear Deployment Script
# For Single Droplet Production
# ===========================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="docker-compose.prod.yaml"
BACKUP_ENABLED="${BACKUP_ENABLED:-true}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_prerequisites() {
    log_info "Checking prerequisites..."

    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    if ! command -v docker compose &> /dev/null && ! docker compose version &> /dev/null; then
        log_error "Docker Compose is not available. Please install Docker Compose first."
        exit 1
    fi

    if [ ! -f "$PROJECT_DIR/.env" ]; then
        log_error ".env file not found. Please copy .env.example to .env and configure it."
        exit 1
    fi

    if [ ! -f "$PROJECT_DIR/$COMPOSE_FILE" ]; then
        log_error "$COMPOSE_FILE not found."
        exit 1
    fi

    log_info "Prerequisites check passed."
}

backup_before_deploy() {
    if [ "$BACKUP_ENABLED" != "true" ]; then
        return
    fi

    log_info "Creating backup before deployment..."
    bash "$SCRIPT_DIR/backup.sh" || log_warn "Backup failed, continuing anyway..."
}

stop_services() {
    log_info "Stopping existing services..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" down || true
}

pull_code() {
    log_info "Updating code from repository..."

    if [ -d "$PROJECT_DIR/.git" ]; then
        cd "$PROJECT_DIR"
        git pull origin main
        cd - > /dev/null
    else
        log_warn "Not a git repository, skipping code pull."
    fi
}

build_images() {
    log_info "Building Docker images..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" build --no-cache php
}

start_services() {
    log_info "Starting services..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" up -d

    log_info "Waiting for services to be healthy..."
    sleep 10

    local max_attempts=30
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        if docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" ps | grep -q "healthy"; then
            log_info "Services are healthy."
            return 0
        fi
        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo ""
    log_warn "Services may not be fully healthy yet. Check status manually."
}

install_magento() {
    log_info "Checking Magento installation status..."

    if [ ! -f "$PROJECT_DIR/src/app/etc/env.php" ]; then
        log_info "Magento not installed. Running setup..."

        docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento setup:install \
            --db-host=mysql \
            --db-name="${MYSQL_DATABASE:-peakgear}" \
            --db-user="${MYSQL_USER:-peakgear}" \
            --db-password="${MYSQL_PASSWORD}" \
            --base-url="${BASE_URL:-http://localhost}" \
            --base-url-secure="${BASE_URL:-https://localhost}" \
            --admin-firstname="${ADMIN_FIRSTNAME:-Admin}" \
            --admin-lastname="${ADMIN_LASTNAME:-User}" \
            --admin-email="${ADMIN_EMAIL:-admin@example.com}" \
            --admin-user="${ADMIN_USER:-admin}" \
            --admin-password="${ADMIN_PASSWORD:-Admin123456}" \
            --language="${LANGUAGE:-en_US}" \
            --currency="${CURRENCY:-USD}" \
            --timezone="${TIMEZONE:-Asia/Ho_Chi_Minh}" \
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
    else
        log_info "Magento already installed. Running upgrade..."
        docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento setup:upgrade
        docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento cache:flush
    fi
}

deploy_static_content() {
    log_info "Deploying static content..."

    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento setup:static-content:deploy -f --jobs=4

    log_info "Setting production mode..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento deploy:mode:set production || true

    log_info "Reindexing catalog..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento indexer:reindex

    log_info "Flushing cache..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento cache:flush
}

fix_permissions() {
    log_info "Fixing file permissions..."

    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php bash -c "
        chown -R www-data:www-data /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated
        find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type d -exec chmod 775 {} \;
        find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type f -exec chmod 664 {} \;
    "
}

setup_cron() {
    log_info "Setting up cron jobs..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento cron:install || true
}

show_status() {
    echo ""
    log_info "=== Deployment Complete ==="
    echo ""
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" ps
    echo ""
    log_info "Access your site at: ${BASE_URL:-http://localhost}"
    log_info "Admin panel at: ${BASE_URL:-http://localhost}/admin"
    echo ""
}

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --no-backup    Skip backup before deployment"
    echo "  --help         Show this help message"
    echo ""
    echo "Environment variables:"
    echo "  BACKUP_ENABLED    Set to 'false' to skip backup"
    echo ""
}

main() {
    if [ "$1" = "--help" ]; then
        usage
        exit 0
    fi

    if [ "$1" = "--no-backup" ]; then
        BACKUP_ENABLED="false"
    fi

    log_info "Starting PeakGear deployment..."
    echo ""

    check_prerequisites
    backup_before_deploy
    stop_services
    pull_code
    build_images
    start_services
    install_magento
    deploy_static_content
    fix_permissions
    setup_cron
    show_status
}

main "$@"