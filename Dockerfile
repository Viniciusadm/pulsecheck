FROM node:24.22-alpine AS frontend
WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY web/ ./
RUN npm run build

FROM composer:2.10 AS vendor
WORKDIR /app
COPY back/composer.json back/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY back/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache icu-libs libpq fcgi \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl opcache intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-pulsecheck.ini
COPY docker/app/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pulsecheck.conf
COPY docker/app/entrypoint.sh /usr/local/bin/pulsecheck-entrypoint
WORKDIR /var/www/html
COPY --from=vendor /app ./
RUN mkdir -p storage/app storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/pulsecheck-entrypoint
USER www-data
RUN php artisan package:discover --ansi
ENTRYPOINT ["pulsecheck-entrypoint"]
CMD ["php-fpm"]
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

FROM nginx:1.31-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /web/dist /usr/share/nginx/html
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1
