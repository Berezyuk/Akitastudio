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

# Зависимости устанавливаются при старте контейнера (см. docker-compose command),
# чтобы изменения composer.json сразу отражались при пересборке.
CMD ["sh", "-c", "composer install --no-interaction --quiet && php-fpm"]
