# Multi-stage Dockerfile. BUILD_TARGET (set via compose `target:`) picks the stage:
#   prod → no xdebug, composer --no-dev, optimized autoloader
#   dev  → xdebug + dev composer deps for local + Coolify dev environment
#
# linux-headers lives in `base` so any future pecl compile that needs
# <linux/rtnetlink.h> (xdebug 3.4+, etc.) just works.

FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git curl \
        libpng-dev libzip-dev zip unzip \
        icu-dev oniguruma-dev \
        mysql-client \
        linux-headers

RUN docker-php-ext-install \
        pdo_mysql mbstring zip intl opcache pcntl

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini       /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]


FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p tmp logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs


FROM base AS dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p tmp logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs
