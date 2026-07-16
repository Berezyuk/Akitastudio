-- Миграция идемпотентности заказов для УЖЕ поднятого стенда.
-- Колонка + UNIQUE под defense от дублей заказа на double-submit/ретрай.
-- init.sql уже содержит это для свежего volume; здесь — для существующей БД.
--
--   docker compose exec -T postgres psql -U <DB_USER> -d <DB_NAME> \
--     < docker/postgres/migrations/2026-07-16_order-idempotency.sql
--
-- Идемпотентна: колонка и констрейнт добавляются только если их нет.

ALTER TABLE orders ADD COLUMN IF NOT EXISTS idempotency_key UUID;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'orders_idempotency_key_key'
    ) THEN
        ALTER TABLE orders ADD CONSTRAINT orders_idempotency_key_key UNIQUE (idempotency_key);
    END IF;
END $$;
