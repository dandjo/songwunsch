#!/bin/sh
set -e

# On the first start create a config.php from the template. The values come
# from the .env through environment variables, the file itself stays editable.
# The owner is taken from the template so the file in the bind mount remains
# editable on the host as well.
if [ ! -f /var/www/html/config.php ] && [ -f /var/www/html/config.example.php ]; then
    cp /var/www/html/config.example.php /var/www/html/config.php
    chown --reference=/var/www/html/config.example.php /var/www/html/config.php 2>/dev/null || true
    echo "[songwunsch] config.php created from config.example.php."
fi

exec "$@"
