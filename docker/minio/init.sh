#!/bin/sh
# Ждём готовности MinIO и создаём бакеты с публичной политикой чтения.
# Запускается как одноразовый сервис при docker compose up.

echo "Waiting for MinIO to be ready..."
until mc alias set local "http://minio:9000" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" 2>/dev/null; do
  sleep 2
done

# Дополнительная пауза — MinIO иногда принимает соединение, но ещё не готов к операциям
sleep 3

echo "MinIO is ready. Creating buckets..."

mc mb --ignore-existing local/order-photos
mc mb --ignore-existing local/portfolio

# portfolio — публичный: его содержимое и так показывается всем на сайте.
mc anonymous set download local/portfolio

# order-photos — приватный: это фото чужих машин. Раньше здесь тоже стоял
# `anonymous set download`, и любой объект открывался по прямой ссылке без
# авторизации. Приложение отдаёт их временными подписанными ссылками
# (MinioHelper::presignedUrl). Снимаем политику, если осталась с прошлых запусков.
mc anonymous set none local/order-photos

echo "MinIO initialized: 'portfolio' (public), 'order-photos' (private)."
