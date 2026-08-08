# syntax=docker/dockerfile:1

# =============================================================================
# Stage 1 — Composer dependencies
# Installed separately so the vendor layer is cached until the lock file
# actually changes.
# =============================================================================
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: artisan is not available yet (no app code copied), so package
# discovery must be deferred until the final stage.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev


# =============================================================================
# Stage 2 — Runtime (PHP-FPM)
# =============================================================================
FROM php:8.3-fpm-alpine AS runtime

LABEL org.opencontainers.image.title="server-manager-api" \
      org.opencontainers.image.description="Server Manager REST API (Laravel)"

# --- System + PHP extensions -------------------------------------------------
RUN apk add --no-cache \
        bash \
        icu-libs \
        libzip \
        libpng \
        freetype \
        libjpeg-turbo \
        oniguruma \
        mysql-client \
        gzip \
        tzdata \
        su-exec \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        sockets \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# --- Config ------------------------------------------------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html

# --- Application -------------------------------------------------------------
# Copy the fully-installed app (including vendor/) from the build stage.
COPY --from=vendor /app /var/www/html

# php-fpm's own user in the base image is www-data (uid 82 on alpine).
RUN mkdir -p \
        storage/framework/{cache/data,sessions,views} \
        storage/logs \
        storage/app/backups \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Health probe used by compose / orchestrators.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]
