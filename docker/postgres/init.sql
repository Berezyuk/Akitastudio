-- AkitaStudio — начальная схема и seed-данные
-- Запускается автоматически при первом старте контейнера postgres

-- ─── Таблицы ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    user_id       SERIAL PRIMARY KEY,
    login         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'client',
    created_at    TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS clients (
    client_id    SERIAL PRIMARY KEY,
    user_id      INT REFERENCES users(user_id) ON DELETE SET NULL,
    first_name   VARCHAR(100) NOT NULL,
    last_name    VARCHAR(100) NOT NULL DEFAULT '',
    patronymic   VARCHAR(100),
    phone_number VARCHAR(20) UNIQUE,
    email        VARCHAR(255),
    created_at   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS service_categories (
    category_id    SERIAL PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    sort_order     INT NOT NULL DEFAULT 0,
    icon           VARCHAR(100) NOT NULL DEFAULT '',
    show_on_home   BOOLEAN NOT NULL DEFAULT FALSE,
    home_media_url TEXT
);

CREATE TABLE IF NOT EXISTS services (
    service_id       SERIAL PRIMARY KEY,
    category_id      INT REFERENCES service_categories(category_id) ON DELETE CASCADE,
    name             VARCHAR(255) NOT NULL,
    description      TEXT,
    base_price       NUMERIC(10,2) DEFAULT NULL CHECK (base_price >= 0),
    duration_minutes INT DEFAULT NULL,
    is_active        BOOLEAN NOT NULL DEFAULT TRUE,
    icon_url         VARCHAR(255),
    sort_order       INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS car_brands (
    brand_id SERIAL PRIMARY KEY,
    name     VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS car_models (
    model_id SERIAL PRIMARY KEY,
    brand_id INT NOT NULL REFERENCES car_brands(brand_id) ON DELETE CASCADE,
    name     VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS order_statuses (
    status_id SERIAL PRIMARY KEY,
    name      VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
    order_id     SERIAL PRIMARY KEY,
    client_id    INT REFERENCES clients(client_id) ON DELETE SET NULL,
    brand_id     INT REFERENCES car_brands(brand_id) ON DELETE SET NULL,
    model_id     INT REFERENCES car_models(model_id) ON DELETE SET NULL,
    status_id    INT REFERENCES order_statuses(status_id) ON DELETE SET NULL DEFAULT 1,
    order_date   TIMESTAMP NOT NULL DEFAULT NOW(),
    desired_date DATE,
    desired_time TIME,
    client_notes TEXT,
    admin_notes  TEXT,
    total_price  NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (total_price >= 0),
    prepayment   NUMERIC(10,2) NOT NULL DEFAULT 0,
    notes        TEXT
);

CREATE TABLE IF NOT EXISTS order_services (
    id              SERIAL PRIMARY KEY,
    order_id        INT NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    service_id      INT NOT NULL REFERENCES services(service_id) ON DELETE RESTRICT,
    price_at_moment NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (price_at_moment >= 0)
);

CREATE TABLE IF NOT EXISTS order_services_progress (
    order_id         INT NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    service_id       INT NOT NULL REFERENCES services(service_id) ON DELETE CASCADE,
    progress_percent INT NOT NULL DEFAULT 0,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending',
    updated_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    PRIMARY KEY (order_id, service_id)
);

CREATE TABLE IF NOT EXISTS order_photos (
    id          SERIAL PRIMARY KEY,
    order_id    INT NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    photo_url   TEXT NOT NULL,
    caption     VARCHAR(255),
    uploaded_by VARCHAR(50) NOT NULL DEFAULT 'admin',
    sort_order  INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS portfolio (
    id           SERIAL PRIMARY KEY,
    video_url    TEXT,
    category_id  INT REFERENCES service_categories(category_id) ON DELETE SET NULL,
    service_id   INT REFERENCES services(service_id) ON DELETE SET NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    show_on_home BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS site_settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
INSERT INTO site_settings (key, value) VALUES ('about_video_url', '') ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS feedbacks (
    feedback_id  SERIAL PRIMARY KEY,
    name         VARCHAR(100),
    phone        VARCHAR(20),
    email        VARCHAR(255),
    message      TEXT NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'new',
    admin_notes  TEXT,
    created_at   TIMESTAMP NOT NULL DEFAULT NOW()
);


-- ─── Seed: статусы заказов ──────────────────────────────────────────────────

INSERT INTO order_statuses (status_id, name) VALUES
    (1, 'Новый'),
    (2, 'В работе'),
    (3, 'Готово'),
    (4, 'Выдан'),
    (5, 'Отменён')
ON CONFLICT DO NOTHING;

-- Сброс счётчика чтобы следующий INSERT не конфликтовал
SELECT setval('order_statuses_status_id_seq', 5);

-- ─── Seed: категории услуг ──────────────────────────────────────────────────

-- Уникального индекса на name нет, поэтому ON CONFLICT здесь не срабатывал
-- и повторный прогон файла (`make init`) дублировал категории.
INSERT INTO service_categories (name, sort_order)
SELECT t.name, t.sort_order FROM (VALUES
    ('Полировка', 1),
    ('Химчистка', 2),
    ('Детейлинг', 3),
    ('Бронирование', 4)
) AS t(name, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM service_categories c WHERE c.name = t.name);

-- ─── Seed: услуги ───────────────────────────────────────────────────────────

-- Категории берём по имени, а не по хардкоду id: при повторном прогоне
-- порядковые id могут не совпасть. WHERE NOT EXISTS — против дублей.
INSERT INTO services (category_id, name, description, base_price, duration_minutes, is_active, sort_order)
SELECT c.category_id, t.name, t.descr, t.price, t.mins, TRUE, t.sort_order
FROM (VALUES
    ('Полировка',    'Полировка кузова (1 ступень)', 'Удаление лёгких царапин и голограмм',         8000, 240, 1),
    ('Полировка',    'Полировка кузова (2 ступени)', 'Глубокая коррекция лакокрасочного покрытия', 14000, 480, 2),
    ('Химчистка',    'Химчистка салона (базовая)',   'Чистка сидений, ковров и пластика',            5000, 180, 1),
    ('Химчистка',    'Химчистка потолка',            'Профессиональная чистка потолочного покрытия', 3000, 120, 2),
    ('Детейлинг',    'Детейлинг двигателя',          'Промывка и консервация моторного отсека',      4000, 120, 1),
    ('Бронирование', 'Антигравийная плёнка (капот)', 'Защитная PPF-плёнка на капот',                12000, 300, 1)
) AS t(cat, name, descr, price, mins, sort_order)
JOIN service_categories c ON c.name = t.cat
WHERE NOT EXISTS (SELECT 1 FROM services s WHERE s.name = t.name);

-- ─── Seed: справочники ──────────────────────────────────────────────────────
-- Уникальных индексов на name у этих таблиц нет (кроме car_brands), поэтому
-- ON CONFLICT тут не сработал бы — идемпотентность через WHERE NOT EXISTS.
-- Категории и услуги связываются по имени, а не по хардкоду category_id.

-- Марки авто
INSERT INTO car_brands (name)
SELECT v FROM (VALUES
    ('Toyota'), ('BMW'), ('Mercedes-Benz'), ('Audi'), ('Lexus'),
    ('Nissan'), ('Honda'), ('Mazda'), ('Volkswagen'), ('Kia'),
    ('Hyundai'), ('Mitsubishi'), ('Subaru'), ('Porsche'), ('Land Rover')
) AS t(v)
WHERE NOT EXISTS (SELECT 1 FROM car_brands b WHERE b.name = t.v);

-- Модели авто
INSERT INTO car_models (brand_id, name)
SELECT b.brand_id, t.model FROM (VALUES
    ('Toyota',        'Camry'),
    ('Toyota',        'Land Cruiser 300'),
    ('Toyota',        'RAV4'),
    ('BMW',           'X5'),
    ('BMW',           '3 series'),
    ('Mercedes-Benz', 'E-Class'),
    ('Mercedes-Benz', 'GLE'),
    ('Audi',          'Q7'),
    ('Audi',          'A6'),
    ('Lexus',         'LX570'),
    ('Lexus',         'RX350'),
    ('Nissan',        'X-Trail'),
    ('Mazda',         'CX-5'),
    ('Porsche',       'Cayenne'),
    ('Land Rover',    'Range Rover Sport')
) AS t(brand, model)
JOIN car_brands b ON b.name = t.brand
WHERE NOT EXISTS (
    SELECT 1 FROM car_models m WHERE m.brand_id = b.brand_id AND m.name = t.model
);

-- Дополнительные категории услуг
INSERT INTO service_categories (name, sort_order, icon, show_on_home)
SELECT t.name, t.sort_order, t.icon, FALSE FROM (VALUES
    ('Керамика',        5,  '🛡️'),
    ('Оклейка плёнкой', 6,  '🎞️'),
    ('Тонирование',     7,  '🌓'),
    ('Шумоизоляция',    8,  '🔇'),
    ('Мойка',           9,  '💧'),
    ('Защита салона',   10, '🪑'),
    ('Антикор',         11, '⚙️'),
    ('Реставрация фар', 12, '💡')
) AS t(name, sort_order, icon)
WHERE NOT EXISTS (SELECT 1 FROM service_categories c WHERE c.name = t.name);

-- Дополнительные услуги
INSERT INTO services (category_id, name, description, base_price, duration_minutes, is_active, sort_order)
SELECT c.category_id, t.name, t.descr, t.price, t.mins, TRUE, t.sort_order
FROM (VALUES
    ('Керамика',        'Керамика 1 слой',       'Защитное керамическое покрытие кузова, 1 слой',      18000.00, 480,  1),
    ('Керамика',        'Керамика 3 слоя',       'Многослойное керамическое покрытие, срок до 3 лет',  35000.00, 960,  2),
    ('Оклейка плёнкой', 'Оклейка капота',        'Антигравийная полиуретановая плёнка на капот',       12000.00, 240,  1),
    ('Оклейка плёнкой', 'Полная оклейка кузова', 'Оклейка кузова антигравийной плёнкой целиком',      140000.00, 2880, 2),
    ('Тонирование',     'Тонировка стёкол',      'Тонирование боковых стёкол и заднего стекла',         7000.00, 180,  1),
    ('Шумоизоляция',    'Шумоизоляция дверей',   'Виброизоляция и шумоизоляция четырёх дверей',        16000.00, 420,  1),
    ('Реставрация фар', 'Полировка фар',         'Восстановление прозрачности фар с защитным лаком',    4500.00, 120,  1)
) AS t(cat, name, descr, price, mins, sort_order)
JOIN service_categories c ON c.name = t.cat
WHERE NOT EXISTS (SELECT 1 FROM services s WHERE s.name = t.name);

CREATE TABLE IF NOT EXISTS login_attempts (
    id           SERIAL PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts (ip_address, attempted_at);

-- Визиты для статистики в админке.
-- Сырой IP не хранится: два хеша, оба sha256(... + VISIT_SALT + дата).
-- visitor_hash включает user_agent — считает уников (без UA уники схлопнутся:
-- у мобильных операторов сотни абонентов сидят за одним IP через CGNAT).
-- ip_hash — без UA, только по нему считается потолок антинакрутки: раньше
-- ротация User-Agent с одного IP давала обойти лимит, так как корзина была
-- привязана к паре (IP, UA).
-- IP по 152-ФЗ — персональные данные; соль не даёт перебрать диапазон адресов,
-- суточная компонента не даёт сшить визиты одного человека между днями.
CREATE TABLE IF NOT EXISTS visits (
    visit_id     BIGSERIAL PRIMARY KEY,
    visited_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    visitor_hash CHAR(64) NOT NULL,
    ip_hash      CHAR(64) NOT NULL,
    source       VARCHAR(20) NOT NULL,   -- search | social | direct | other
    referer_host VARCHAR(255),           -- только хост, без query: в query утекают поисковые запросы
    device       VARCHAR(10) NOT NULL    -- mobile | desktop
);
CREATE INDEX IF NOT EXISTS idx_visits_date ON visits (visited_at);
-- Аналитика фильтрует и группирует по visited_at::date. Индекс на сырой столбце
-- каст не использует → seq scan (visits — самая растущая таблица). Expression-индекс
-- по (visited_at::date) обслуживает эти запросы напрямую.
CREATE INDEX IF NOT EXISTS idx_visits_date_day ON visits ((visited_at::date));
-- Второй индекс — под проверку накрутки: она бьёт по ip_hash на каждом визите.
-- По visitor_hash точечных выборок нет — уники считаются агрегатом
-- COUNT(DISTINCT visitor_hash) за диапазон дат, такой индекс не ускоряет.
CREATE INDEX IF NOT EXISTS idx_visits_hash ON visits (ip_hash, visited_at);

-- ─── Индексы ────────────────────────────────────────────────────────────────
-- Postgres индексирует только PRIMARY KEY и UNIQUE — внешние ключи НЕ индексирует.
-- Без этих индексов каждый JOIN и фильтр по FK идёт seq scan'ом, а ON DELETE CASCADE
-- на order_services/order_photos сканирует таблицу целиком при удалении заказа.
-- На текущих объёмах (сотни строк) разницы нет — заведены на вырост, чтобы не
-- мигрировать живую БД потом.

-- Заказы: кабинет клиента (WHERE client_id), дашборд (WHERE status_id), списки (ORDER BY order_date)
CREATE INDEX IF NOT EXISTS idx_orders_client_id  ON orders (client_id);
CREATE INDEX IF NOT EXISTS idx_orders_status_id  ON orders (status_id);
CREATE INDEX IF NOT EXISTS idx_orders_order_date ON orders (order_date DESC);
-- Дашборд считает заказы по order_date::date (= сегодня, >= from). Как и для
-- visits, каст не берёт индекс на сыром столбце — expression-индекс по дню.
CREATE INDEX IF NOT EXISTS idx_orders_order_date_day ON orders ((order_date::date));

-- Состав заказа и фото: JOIN по order_id + ON DELETE CASCADE
CREATE INDEX IF NOT EXISTS idx_order_services_order_id ON order_services (order_id);
CREATE INDEX IF NOT EXISTS idx_order_photos_order_id   ON order_photos (order_id);

-- Справочники: выбор модели по марке, услуг по категории
CREATE INDEX IF NOT EXISTS idx_car_models_brand_id   ON car_models (brand_id);
CREATE INDEX IF NOT EXISTS idx_services_category_id  ON services (category_id);

-- Клиенты: findOrCreate ищет по нормализованному телефону при каждой заявке
-- phone_number теперь UNIQUE (см. таблицу clients) — уникальный констрейнт сам
-- создаёт индекс, отдельный idx_clients_phone_number не нужен.
CREATE INDEX IF NOT EXISTS idx_clients_user_id      ON clients (user_id);

-- ─── Seed: администратор ────────────────────────────────────────────────────
-- ВНИМАНИЕ: дефолтный пароль. Сразу сменить на проде через `make init`
-- (берёт ADMIN_PASSWORD из .env) или сменой пароля в админке.

INSERT INTO users (login, password_hash, role) VALUES
    ('admin', '$2y$10$iuFR6jEK6.7uCuvRd7DGD.0sNCV/xW6LZDgveX93GYOIAlqasU1WC', 'admin')
ON CONFLICT (login) DO NOTHING;
