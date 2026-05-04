FROM node:25.9.0-bookworm

RUN install -d -o root -g root /var/www/html

WORKDIR /var/www/html

ENV npm_config_update_notifier=false

COPY docker/node/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER root
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["tail", "-f", "/dev/null"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD node --version >/dev/null || exit 1
