#!/bin/sh
set -eu

cd /var/www/html || exit 1

mkdir -p var/cache var/log public_html/uploads vendor
chown -R www-data:www-data var public_html/uploads vendor

# Локальный override монтирует named volume node_modules; до chown он root:root,
# иначе npm ci из PHP-FPM (www-data) получает EACCES.
if [ "${CHOWN_NODE_MODULES:-0}" = "1" ]; then
  mkdir -p node_modules
  chown -R www-data:www-data node_modules
fi

exec "$@"
