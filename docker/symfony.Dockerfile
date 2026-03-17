FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

COPY symfony/composer.json symfony/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY symfony/ ./
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/symfony/var
