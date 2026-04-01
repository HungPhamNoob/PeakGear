#!/usr/bin/env bash
set -euo pipefail

# Fix Magento writable directories for Docker development without using 777.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$ROOT_DIR"

docker compose exec --user root php sh -lc '
chown -R www-data:www-data /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated
find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type d -exec chmod 775 {} \;
find /var/www/html/var /var/www/html/pub/static /var/www/html/pub/media /var/www/html/generated -type f -exec chmod 664 {} \;

# setup:upgrade may need to update module list in app/etc/config.php
if [ -d /var/www/html/app/etc ]; then
	chgrp www-data /var/www/html/app/etc
	chmod 2775 /var/www/html/app/etc
fi

# setup:upgrade may need to update module list in app/etc/config.php
if [ -f /var/www/html/app/etc/config.php ]; then
	chgrp www-data /var/www/html/app/etc/config.php
	chmod 664 /var/www/html/app/etc/config.php
fi

# env.php is runtime config and should remain writable by Magento process
if [ -f /var/www/html/app/etc/env.php ]; then
	chgrp www-data /var/www/html/app/etc/env.php
	chmod 660 /var/www/html/app/etc/env.php
fi
'

echo "Magento writable permissions fixed (owner: www-data:www-data, dirs:775, files:664)."
