FROM node:25.9.0-bookworm

RUN install -d -o node -g node /var/www/html

WORKDIR /var/www/html

ENV npm_config_update_notifier=false

USER node

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD node --version >/dev/null || exit 1
