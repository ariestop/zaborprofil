# Copy values from this file to /var/www/zaborprofil/shared/.env.local on the VPS.
# Do not commit real production or staging secrets.

APP_ENV=prod
APP_DEBUG=0
APP_SECRET=change-this-secret
APP_SHARE_DIR=/var/www/zaborprofil/shared

DATABASE_URL="postgresql://zaborprofil:change-me@127.0.0.1:5432/zaborprofil?serverVersion=18&charset=utf8"
REDIS_URL="redis://:change-me@127.0.0.1:6379/0"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=smtp://127.0.0.1:25

SITE_URL="https://zaborprofil.ru"
DEFAULT_URI="https://zaborprofil.ru"
