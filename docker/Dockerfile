# Multi-stage build:
#   1. frontend — compile Vue + SCSS via Vite (outputs /webroot/build)
#   2. base     — php-fpm + nginx + supervisord with PHP extensions + composer
#   3. prod     — composer --no-dev, optimized autoloader (Coolify prod app)
#   4. dev      — xdebug + dev composer deps (Coolify dev app + local dev)
#
# BUILD_TARGET (set via compose `target:`) picks `prod` or `dev`. Both ship
# nginx + php-fpm in a single container, behind Coolify's Traefik.
#
# linux-headers in base so pecl compiles needing <linux/rtnetlink.h>
# (xdebug 3.4+, etc.) work in any downstream stage.

# ───────────────────────── 1. Build frontend assets ─────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

# ───────────────────────── 2. Base runtime ─────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        nginx supervisor curl \
        git libzip-dev icu-dev oniguruma-dev mariadb-dev \
        linux-headers \
    && docker-php-ext-install \
        pdo_mysql mbstring zip intl opcache pcntl bcmath

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini          /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]

# ───────────────────────── 3. Production image ─────────────────────────
FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

COPY . .
COPY --from=frontend /build/webroot/build /var/www/html/webroot/build

RUN composer run-script post-install-cmd --no-interaction || true \
    && mkdir -p tmp logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs

# ───────────────────────── 4. Development image ─────────────────────────
FROM base AS dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

COPY . .
COPY --from=frontend /build/webroot/build /var/www/html/webroot/build

RUN composer run-script post-install-cmd --no-interaction || true \
    && mkdir -p tmp logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs
