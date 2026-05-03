FROM php:8.5-fpm-bookworm

COPY --from=node:25.9.0-bookworm /usr/local/bin/node /usr/local/bin/node
COPY --from=node:25.9.0-bookworm /usr/local/lib/node_modules /usr/local/lib/node_modules

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PATH="/root/.symfony5/bin:${PATH}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        acl \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install intl pdo_pgsql zip \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && curl -1sLf https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh | bash \
    && apt-get install -y --no-install-recommends symfony-cli \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && groupmod -g "${GID}" www-data \
    && usermod -u "${UID}" -g "${GID}" www-data \
    && mkdir -p /var/www/html /tmp/composer \
    && chown -R www-data:www-data /var/www/html /tmp/composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-zaborprofil.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www/html

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 CMD ["php-fpm", "-t"]
