#!/bin/bash
#
# PeakGear - Quick setup for a new developer who just cloned the repo.
#
# What this script does:
#   1. Checks .env file exists
#   2. Creates src/auth.json (needed for composer install)
#   3. Builds & starts Docker containers
#   4. Runs composer install (downloads vendor/ dependencies)
#   5. Runs Magento install (creates database, admin user, etc.)
#
# Prerequisites:
#   - Docker & Docker Compose installed
#   - .env file created (copy from .env.example)
#
# Usage:
#   cp .env.example .env   # then fill in your values
#   bash scripts/setup-for-developer.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=========================================="
echo "  PeakGear - Developer Setup"
echo "=========================================="

# ---------- Check .env ----------
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo ""
    echo "ERROR: .env file not found!"
    echo ""
    echo "  Run these commands first:"
    echo "    cp .env.example .env"
    echo "    # Then edit .env - fill in MAGENTO_PUBLIC_KEY and MAGENTO_PRIVATE_KEY"
    echo ""
    echo "  Get keys from: https://marketplace.magento.com > My Profile > Access Keys"
    echo ""
    exit 1
fi

# Load .env
set -a
. "$PROJECT_DIR/.env"
set +a

# ---------- Validate Magento keys ----------
if [ -z "${MAGENTO_PUBLIC_KEY:-}" ] || [ -z "${MAGENTO_PRIVATE_KEY:-}" ] \
   || [ "${MAGENTO_PUBLIC_KEY}" = "your_public_key_here" ] \
   || [ "${MAGENTO_PRIVATE_KEY}" = "your_private_key_here" ]; then
    echo ""
    echo "ERROR: Magento marketplace keys not configured in .env"
    echo ""
    echo "  These keys are needed to download Magento dependencies via Composer."
    echo "  Get free keys from: https://marketplace.magento.com > My Profile > Access Keys"
    echo "  Then set MAGENTO_PUBLIC_KEY and MAGENTO_PRIVATE_KEY in your .env file."
    echo ""
    exit 1
fi

# ---------- Check Magento source ----------
if [ ! -f "$PROJECT_DIR/src/composer.json" ]; then
    echo ""
    echo "ERROR: Magento source not found (src/composer.json missing)."
    echo ""
    echo "  The source code should be included in the git repository."
    echo "  Make sure you cloned the repo correctly: git clone <repo-url>"
    echo ""
    exit 1
fi

# ---------- Create auth.json (required by composer install) ----------
echo ""
echo "[1/4] Creating src/auth.json..."
cat > "$PROJECT_DIR/src/auth.json" << EOF
{
    "http-basic": {
        "repo.magento.com": {
            "username": "${MAGENTO_PUBLIC_KEY}",
            "password": "${MAGENTO_PRIVATE_KEY}"
        }
    }
}
EOF
echo "  Done."

# ---------- Docker build & up ----------
echo ""
echo "[2/4] Building and starting Docker containers..."
cd "$PROJECT_DIR"
docker compose build
docker compose up -d

# Wait for containers to be ready
echo ""
echo "Waiting for containers to start..."
sleep 10

# Verify PHP container is running
if ! docker ps --format '{{.Names}}' | grep -q "^peakgear_php$"; then
    echo ""
    echo "ERROR: peakgear_php container failed to start."
    echo "  Check logs: docker compose logs php"
    exit 1
fi

# ---------- Composer install ----------
echo ""
echo "[3/4] Installing Composer dependencies (this may take several minutes)..."
if [ ! -d "$PROJECT_DIR/src/vendor" ] || [ ! -f "$PROJECT_DIR/src/vendor/autoload.php" ]; then
    docker exec peakgear_php bash -c "cd /var/www/html && composer install --no-interaction --prefer-dist"
else
    echo "  Composer dependencies already installed, skipping."
fi

# ---------- Magento install ----------
echo ""
echo "[4/4] Installing Magento..."
bash "$SCRIPT_DIR/install-magento.sh"

echo ""
echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "  Storefront:  http://${MAGENTO_HOST:-localhost}/"
echo "  Admin Panel: http://${MAGENTO_HOST:-localhost}/admin"
echo "  Admin User:  ${MAGENTO_ADMIN_USER:-admin}"
echo "  Admin Pass:  ${MAGENTO_ADMIN_PASSWORD:-admin123}"
echo "  phpMyAdmin:  http://${MAGENTO_HOST:-localhost}:8080"
echo ""
