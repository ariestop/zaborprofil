#!/bin/sh
set -eu

uid="${DOCKER_UID:-1000}"
gid="${DOCKER_GID:-1000}"

mkdir -p /var/www/html/node_modules
# Named volume node_modules is typically root:root on first mount; npm must write here.
chown -R "${uid}:${gid}" /var/www/html/node_modules

cd /var/www/html
exec setpriv --reuid="${uid}" --regid="${gid}" --clear-groups -- "$@"
