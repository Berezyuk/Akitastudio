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

# Дефолтный пул php-fpm — pm.max_children=5. Для публичного сайта мало: пара
# устройств, каждое шлёт пачку параллельных запросов на загрузке (categories/
# services/portfolio/settings/auth/me/visit) — 5 воркеров кончаются, и часть
# запросов не получает воркер: у другого клиента пропадают услуги/портфолио.
# Поднимаем пул. 25 воркеров при типичном ~30-40 МБ на JSON-запрос ≈ 1 ГБ —
# безопасно для VPS от 2 ГБ. Если RAM меньше — уменьшить max_children.
# pm.max_requests: перезапуск воркера каждые N запросов — страховка от утечек.
RUN sed -i \
        -e 's/^pm.max_children = .*/pm.max_children = 25/' \
        -e 's/^pm.start_servers = .*/pm.start_servers = 5/' \
        -e 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 3/' \
        -e 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 10/' \
        -e 's/^;pm.max_requests = .*/pm.max_requests = 500/' \
        /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/api

# ./api смонтирован поверх образа, поэтому vendor живёт на хосте и ставится при старте.
# Ставим только если vendor отсутствует — иначе быстрый старт.
# После правок composer.json: rm -rf api/vendor && docker compose restart php.
CMD ["sh", "-c", "[ -f vendor/autoload.php ] || composer install --no-interaction --quiet; php-fpm"]
