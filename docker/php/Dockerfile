FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        curl \
        ffmpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/api

# ./api смонтирован поверх образа, поэтому vendor живёт на хосте и ставится при старте.
# Ставим только если vendor отсутствует — иначе быстрый старт.
# После правок composer.json: rm -rf api/vendor && docker compose restart php.
CMD ["sh", "-c", "[ -f vendor/autoload.php ] || composer install --no-interaction --quiet; php-fpm"]
