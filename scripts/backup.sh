#!/bin/bash
set -e

# ===========================================
# PeakGear Backup Script
# For Single Droplet Production
# ===========================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${BACKUP_DIR:-/opt/peakgear/backups}"
COMPOSE_FILE="docker-compose.prod.yaml"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS="${RETENTION_DAYS:-7}"

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

create_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
        log_info "Created backup directory: $BACKUP_DIR"
    fi
}

backup_database() {
    log_info "Backing up database..."

    local db_backup="$BACKUP_DIR/peakgear_db_$DATE.sql.gz"

    docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T mysql mysqldump \
        --single-transaction \
        --quick \
        --lock-tables=false \
        -u root \
        -p"${MYSQL_ROOT_PASSWORD}" \
        "${MYSQL_DATABASE:-peakgear}" | gzip > "$db_backup"

    if [ -f "$db_backup" ]; then
        log_info "Database backup created: $db_backup"
    else
        log_error "Database backup failed!"
        return 1
    fi
}

backup_media() {
    log_info "Backing up media files..."

    local media_backup="$BACKUP_DIR/peakgear_media_$DATE.tar.gz"

    tar -czf "$media_backup" -C "$PROJECT_DIR/src/pub" media 2>/dev/null || {
        log_warn "Media backup failed or media directory empty"
        return 0
    }

    if [ -f "$media_backup" ]; then
        log_info "Media backup created: $media_backup ($(du -h "$media_backup" | cut -f1))"
    fi
}

backup_config() {
    log_info "Backing up configuration files..."

    local config_backup="$BACKUP_DIR/peakgear_config_$DATE.tar.gz"

    tar -czf "$config_backup" \
        -C "$PROJECT_DIR" \
        --exclude='src/app/etc/env.php' \
        --exclude='src/auth.json' \
        docker/nginx/ssl \
        docker/php/php.ini \
        .env \
        2>/dev/null || true

    if [ -f "$config_backup" ]; then
        log_info "Config backup created: $config_backup"
    fi
}

cleanup_old_backups() {
    log_info "Cleaning up backups older than $RETENTION_DAYS days..."

    find "$BACKUP_DIR" -name "peakgear_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    find "$BACKUP_DIR" -name "peakgear_*.tar.gz" -mtime +$RETENTION_DAYS -delete

    log_info "Cleanup complete."
}

list_backups() {
    log_info "Available backups:"
    echo ""
    ls -lh "$BACKUP_DIR"/peakgear_* 2>/dev/null || log_info "No backups found."
    echo ""
}

restore_database() {
    local backup_file="$1"

    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        return 1
    fi

    log_info "Restoring database from: $backup_file"

    gunzip < "$backup_file" | docker compose -f "$PROJECT_DIR/$COMPOSE_FILE" exec -T mysql mysql \
        -u root \
        -p"${MYSQL_ROOT_PASSWORD}" \
        "${MYSQL_DATABASE:-peakgear}"

    log_info "Database restored successfully."
}

usage() {
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  backup           Create a new backup (default)"
    echo "  list             List available backups"
    echo "  restore <file>   Restore database from backup"
    echo ""
    echo "Options:"
    echo "  --help           Show this help message"
    echo ""
    echo "Environment variables:"
    echo "  BACKUP_DIR       Backup directory (default: /opt/peakgear/backups)"
    echo "  RETENTION_DAYS   Days to keep backups (default: 7)"
    echo ""
}

main() {
    case "${1:-backup}" in
        backup)
            log_info "Starting PeakGear backup..."
            create_backup_dir
            backup_database
            backup_media
            backup_config
            cleanup_old_backups
            list_backups
            log_info "Backup complete!"
            ;;
        list)
            list_backups
            ;;
        restore)
            if [ -z "$2" ]; then
                log_error "Please specify a backup file to restore."
                echo ""
                usage
                exit 1
            fi
            restore_database "$2"
            ;;
        --help)
            usage
            ;;
        *)
            log_error "Unknown command: $1"
            usage
            exit 1
            ;;
    esac
}

main "$@"