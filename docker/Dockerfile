# Multi-stage build:
#   1. node    — compile Vue + SCSS via Vite (outputs /webroot/build)
#   2. runtime — php-fpm + nginx + supervisord with composer install run AFTER
#                PHP extensions are in place (so composer can verify the
#                lock file against actually-available extensions like intl).
#
# Used by both `app` (with supervisord) and `worker` (CMD override) services.

# ───────────────────────── 1. Build frontend assets ─────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

# ───────────────────────── 2. Runtime image ─────────────────────────
FROM php:8.3-fpm-alpine

# System deps + PHP extensions. linux-headers needed for any future pecl compile
# (xdebug, etc) that pulls in <linux/rtnetlink.h>.
RUN apk add --no-cache \
        nginx supervisor curl \
        git libzip-dev icu-dev oniguruma-dev mariadb-dev \
        linux-headers \
    && docker-php-ext-install \
        pdo_mysql mbstring zip intl opcache pcntl bcmath

# Redis extension via pecl (compiled, not stock)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer binary from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configs (early layer for cache friendliness)
COPY docker/nginx.conf      /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini          /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Composer install — separate layer cached when composer.lock unchanged
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

# App code + built frontend assets
COPY . .
COPY --from=frontend /build/webroot/build /var/www/html/webroot/build

# Run post-install scripts now that the full app is in place
RUN composer run-script post-install-cmd --no-interaction || true

RUN chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs

# Entrypoint waits for DB, runs migrations if RUN_MIGRATIONS=true, then exec's CMD.
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
