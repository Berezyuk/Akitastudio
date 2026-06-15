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
    phone_number VARCHAR(20),
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
    base_price       NUMERIC(10,2) NOT NULL DEFAULT 0,
    duration_minutes INT NOT NULL DEFAULT 60,
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
    total_price  NUMERIC(10,2) NOT NULL DEFAULT 0,
    prepayment   NUMERIC(10,2) NOT NULL DEFAULT 0,
    notes        TEXT
);

CREATE TABLE IF NOT EXISTS order_services (
    id              SERIAL PRIMARY KEY,
    order_id        INT NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    service_id      INT NOT NULL REFERENCES services(service_id) ON DELETE RESTRICT,
    price_at_moment NUMERIC(10,2) NOT NULL DEFAULT 0
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
    title        VARCHAR(255),
    description  TEXT,
    category_id  INT REFERENCES service_categories(category_id) ON DELETE SET NULL,
    service_id   INT REFERENCES services(service_id) ON DELETE SET NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    show_on_home BOOLEAN NOT NULL DEFAULT FALSE
);

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

CREATE TABLE IF NOT EXISTS employees (
    employee_id SERIAL PRIMARY KEY,
    position    VARCHAR(100),
    first_name  VARCHAR(100) NOT NULL,
    bio         TEXT
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

INSERT INTO service_categories (name, sort_order) VALUES
    ('Полировка', 1),
    ('Химчистка', 2),
    ('Детейлинг', 3),
    ('Бронирование', 4)
ON CONFLICT DO NOTHING;

-- ─── Seed: услуги ───────────────────────────────────────────────────────────

INSERT INTO services (category_id, name, description, base_price, duration_minutes, is_active, sort_order) VALUES
    (1, 'Полировка кузова (1 ступень)', 'Удаление лёгких царапин и голограмм', 8000, 240, TRUE, 1),
    (1, 'Полировка кузова (2 ступени)', 'Глубокая коррекция лакокрасочного покрытия', 14000, 480, TRUE, 2),
    (2, 'Химчистка салона (базовая)', 'Чистка сидений, ковров и пластика', 5000, 180, TRUE, 1),
    (2, 'Химчистка потолка', 'Профессиональная чистка потолочного покрытия', 3000, 120, TRUE, 2),
    (3, 'Детейлинг двигателя', 'Промывка и консервация моторного отсека', 4000, 120, TRUE, 1),
    (4, 'Антигравийная плёнка (капот)', 'Защитная PPF-плёнка на капот', 12000, 300, TRUE, 1)
ON CONFLICT DO NOTHING;

-- ─── Seed: администратор ────────────────────────────────────────────────────
-- Пароль: admin123  (bcrypt)

INSERT INTO users (login, password_hash, role) VALUES
    ('admin', '$2y$10$iuFR6jEK6.7uCuvRd7DGD.0sNCV/xW6LZDgveX93GYOIAlqasU1WC', 'admin')
ON CONFLICT (login) DO NOTHING;
