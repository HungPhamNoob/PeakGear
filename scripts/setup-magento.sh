#!/bin/bash
#
# PeakGear - Download Magento source code (first-time project creator only).
#
# NOTE: Partners do NOT need this script!
#       Magento source code is committed to the git repo.
#       Partners only need: bash scripts/setup-for-developer.sh
#
# This script is only useful if you need to:
#   - Create the project from scratch
#   - Re-download Magento source to a specific version
#
# Usage:
#   cp .env.example .env   # fill in Magento marketplace keys
#   bash scripts/setup-magento.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SRC_DIR="${PROJECT_DIR}/src"

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

echo "=========================================="
echo "  Magento Source Download"
echo "=========================================="

MAGENTO_VERSION=${MAGENTO_VERSION:-2.4.7}

# ---------- Check existing source ----------
if [ -d "$SRC_DIR" ] && [ -f "$SRC_DIR/composer.json" ]; then
    echo ""
    echo "WARNING: Magento source already exists in src/."
    echo "This will DELETE the existing source and re-download."
    echo ""
    read -r -p "Continue? (y/n): " response
    if [ "${response,,}" != "y" ]; then
        echo "Aborted."
        exit 0
    fi
    echo "Removing old source..."
    rm -rf "$SRC_DIR"
fi

mkdir -p "$SRC_DIR"

# ---------- Validate Magento keys ----------
PUBLIC_KEY="${MAGENTO_PUBLIC_KEY:-}"
PRIVATE_KEY="${MAGENTO_PRIVATE_KEY:-}"

if [ -z "$PUBLIC_KEY" ] || [ -z "$PRIVATE_KEY" ] \
   || [ "$PUBLIC_KEY" = "your_public_key_here" ] \
   || [ "$PRIVATE_KEY" = "your_private_key_here" ]; then
    echo ""
    echo "Magento marketplace keys are required to download source code."
    echo "Get free keys from: https://marketplace.magento.com > My Profile > Access Keys"
    echo ""
    read -r -p "Enter Public Key: " PUBLIC_KEY
    read -r -p "Enter Private Key: " PRIVATE_KEY

    if [ -z "$PUBLIC_KEY" ] || [ -z "$PRIVATE_KEY" ]; then
        echo "ERROR: Keys cannot be empty."
        exit 1
    fi
fi

# ---------- Build PHP image ----------
echo ""
echo "Building PHP Docker image..."
cd "$PROJECT_DIR"
docker compose build php

# ---------- Download Magento ----------
echo ""
echo "Downloading Magento ${MAGENTO_VERSION} (this may take several minutes)..."

# Clean up any previous temp directory
rm -rf "${PROJECT_DIR}/src_temp"

docker run --rm \
    -v "${PROJECT_DIR}:/var/www" \
    "$(docker compose images php --format '{{.Repository}}:{{.Tag}}')" \
    bash -c "
        mkdir -p /tmp/composer
        export COMPOSER_HOME=/tmp/composer
        cat > /tmp/composer/auth.json << 'EOFAUTH'
{
    \"http-basic\": {
        \"repo.magento.com\": {
            \"username\": \"$PUBLIC_KEY\",
            \"password\": \"$PRIVATE_KEY\"
        }
    }
}
EOFAUTH
        composer create-project \
            --repository-url=https://repo.magento.com/ \
            magento/project-community-edition=$MAGENTO_VERSION \
            /var/www/src_temp \
            --no-interaction \
            --prefer-dist \
            --no-scripts
    "

# Move temp to src
rm -rf "${SRC_DIR}"
mv "${PROJECT_DIR}/src_temp" "${SRC_DIR}"

# Create auth.json
cat > "${SRC_DIR}/auth.json" << EOF
{
    "http-basic": {
        "repo.magento.com": {
            "username": "$PUBLIC_KEY",
            "password": "$PRIVATE_KEY"
        }
    }
}
EOF

echo ""
echo "=========================================="
echo "  Magento ${MAGENTO_VERSION} downloaded!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  bash scripts/setup-for-developer.sh"
echo ""
echo "Or manually:"
echo "  1. docker compose up -d"
echo "  2. docker exec peakgear_php bash -c 'cd /var/www/html && composer install'"
echo "  3. bash scripts/install-magento.sh"
echo ""
