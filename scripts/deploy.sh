#!/bin/bash
set -e

# ===========================================
# PeakGear Deployment Script - Optimized
# Zero-downtime deploy when possible
# ===========================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="docker-compose.prod.yaml"
BACKUP_ENABLED="${BACKUP_ENABLED:-true}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

check_prerequisites() {
    if ! command -v docker &> /dev/null || ! docker compose version &> /dev/null; then
        log_error "Docker Compose is required."
        exit 1
    fi
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        log_error ".env file not found."
        exit 1
    fi
}

reload_php() {
    log_info "Reloading PHP-FPM (zero downtime)..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php kill -USR2 1 2>/dev/null || true
}

restart_containers() {
    log_info "Restarting containers..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" restart
}

flush_cache() {
    log_info "Flushing Magento cache..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento cache:flush
}

check_code_changes() {
    if [ ! -d "$PROJECT_DIR/.git" ]; then
        echo "none"
        return
    fi

    cd "$PROJECT_DIR"
    if git diff --quiet HEAD~1 HEAD -- '*.php' '*.phtml' '*/layout/*.xml' '*/etc/*.xml' '*/di.xml' 2>/dev/null; then
        if git diff --quiet HEAD~1 HEAD -- 'composer.json' 'composer.lock' 2>/dev/null; then
            echo "code-only"
        else
            echo "composer"
        fi
    elif git diff --quiet HEAD~1 HEAD -- 'composer.json' 'composer.lock' 2>/dev/null; then
        echo "composer"
    else
        echo "config"
    fi
}

pull_code() {
    if [ ! -d "$PROJECT_DIR/.git" ]; then
        log_warn "Not a git repository."
        return
    fi

    cd "$PROJECT_DIR"
    log_info "Pulling main..."
    git pull origin main
    cd - > /dev/null
}

backup_if_needed() {
    if [ "$BACKUP_ENABLED" = "true" ]; then
        log_info "Creating backup..."
        bash "$SCRIPT_DIR/backup.sh" || log_warn "Backup failed."
    fi
}

install_composer() {
    if [ -d "$PROJECT_DIR/src/vendor" ]; then
        log_info "vendor already exists, skipping composer install"
        return
    fi
    log_info "Installing composer dependencies..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php sh -c "cd /var/www/html && composer install --no-interaction --prefer-dist --no-dev"
}

setup_upgrade() {
    log_info "Running setup:upgrade..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento setup:upgrade
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento cache:flush
}

deploy_static() {
    log_info "Deploying theme static content via Makefile..."
    make -C "$PROJECT_DIR" themes DOCKER_COMPOSE="docker compose -f $PROJECT_DIR/$COMPOSE_FILE exec -T"
}

reindex() {
    log_info "Reindexing..."
    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T php php /var/www/html/bin/magento indexer:reindex
}

health_check() {
    local max_attempts=15
    local attempt=1
    while [ $attempt -le $max_attempts ]; do
        if curl -sf http://localhost/ > /dev/null 2>&1; then
            log_info "Health check passed."
            return 0
        fi
        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done
    echo ""
    log_error "Health check failed."
    return 1
}

main() {
    if [ "$1" = "--help" ]; then
        echo "Usage: $0 [--no-backup] [--force]"
        echo "  --no-backup  Skip backup"
        echo "  --force      Force full deploy (skip smart deploy)"
        exit 0
    fi

    local force=false
    [ "$1" = "--force" ] && force=true

    if [ "$1" = "--no-backup" ] || [ "$2" = "--no-backup" ]; then
        BACKUP_ENABLED="false"
    fi

    log_info "Starting deployment..."
    check_prerequisites
    load_env

    pull_code
    backup_if_needed

    local change_type
    if [ "$force" = "true" ]; then
        change_type="config"
    else
        change_type=$(check_code_changes)
    fi
    log_info "Change type: $change_type"

    case "$change_type" in
        "code-only")
            reload_php
            flush_cache
            ;;
        "composer")
            install_composer
            reload_php
            flush_cache
            ;;
        "config")
            setup_upgrade
            restart_containers
            reindex
            ;;
        *)
            log_warn "Unknown change type, doing full deploy."
            restart_containers
            flush_cache
            ;;
    esac

    deploy_static
    health_check
    log_info "Deployment complete."
}

load_env() {
    set -a
    source "$PROJECT_DIR/.env"
    set +a
}

main "$@"
