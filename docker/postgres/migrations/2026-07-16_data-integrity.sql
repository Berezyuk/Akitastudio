-- Миграция целостности данных (Батч 1) для УЖЕ поднятого стенда.
-- init.sql применяется только на свежий volume (docker compose down -v), поэтому
-- существующей БД констрейнты нужно доложить вручную:
--   docker compose exec -T postgres psql -U <DB_USER> -d <DB_NAME> \
--     < docker/postgres/migrations/2026-07-16_data-integrity.sql
--
-- Безопасно перезапускать: всё в одной транзакции, констрейнты пересоздаются.
-- ВНИМАНИЕ: шаг 1 схлопывает дубли клиентов по телефону. Если у дублей разные
-- user_id (гость + зарегистрированный на тот же телефон) — оставляется строка
-- с минимальным client_id; проверь такие случаи до прогона на проде.

BEGIN;

-- 1. Дедуп клиентов по phone_number: заказы дублей переносим на минимальный
--    client_id той же группы, затем дубли удаляем. Иначе UNIQUE ниже не встанет.
UPDATE orders o
SET client_id = (
    SELECT MIN(c2.client_id) FROM clients c2 WHERE c2.phone_number = c.phone_number
)
FROM clients c
WHERE o.client_id = c.client_id
  AND c.phone_number IS NOT NULL
  AND c.client_id <> (SELECT MIN(c3.client_id) FROM clients c3 WHERE c3.phone_number = c.phone_number);

DELETE FROM clients c
WHERE c.phone_number IS NOT NULL
  AND c.client_id <> (SELECT MIN(c2.client_id) FROM clients c2 WHERE c2.phone_number = c.phone_number);

-- 2. UNIQUE на phone_number (нужно для ON CONFLICT в Client::findOrCreate).
DROP INDEX IF EXISTS idx_clients_phone_number;
ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_phone_number_key;
ALTER TABLE clients ADD  CONSTRAINT clients_phone_number_key UNIQUE (phone_number);

-- 3. CHECK: цены не могут быть отрицательными.
ALTER TABLE services       DROP CONSTRAINT IF EXISTS services_base_price_check;
ALTER TABLE services       ADD  CONSTRAINT services_base_price_check    CHECK (base_price >= 0);
ALTER TABLE orders         DROP CONSTRAINT IF EXISTS orders_total_price_check;
ALTER TABLE orders         ADD  CONSTRAINT orders_total_price_check     CHECK (total_price >= 0);
ALTER TABLE order_services DROP CONSTRAINT IF EXISTS order_services_price_check;
ALTER TABLE order_services ADD  CONSTRAINT order_services_price_check   CHECK (price_at_moment >= 0);

COMMIT;
