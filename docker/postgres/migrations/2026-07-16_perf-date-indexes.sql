-- Миграция производительности (Батч 3) для УЖЕ поднятого стенда.
-- Expression-индексы под запросы аналитики/дашборда, которые фильтруют по
-- visited_at::date / order_date::date (каст не берёт индекс на сыром столбце).
-- init.sql уже содержит их для свежего volume; здесь — для существующей БД.
--
--   docker compose exec -T postgres psql -U <DB_USER> -d <DB_NAME> \
--     < docker/postgres/migrations/2026-07-16_perf-date-indexes.sql
--
-- CREATE INDEX IF NOT EXISTS идемпотентен, лок короткий (таблицы небольшие).

CREATE INDEX IF NOT EXISTS idx_visits_date_day      ON visits ((visited_at::date));
CREATE INDEX IF NOT EXISTS idx_orders_order_date_day ON orders ((order_date::date));
