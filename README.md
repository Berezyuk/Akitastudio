# AkitaStudio — Детейлинг студия

Веб-приложение детейлинг-студии: Vue 3 (SPA) + PHP REST API + PostgreSQL + MinIO.

---

## Требования

| Инструмент | Минимальная версия |
|---|---|
| Docker | 24+ |
| Docker Compose | v2.20+ (plugin, `docker compose`) |
| Git | любая |

Node.js и PHP устанавливать **не нужно** — всё работает внутри контейнеров.

---

## Быстрый старт (Docker)

### 1. Клонировать репозиторий

```bash
git clone <url> akitastudio
cd akitastudio
```

### 2. Создать файл `.env`

```bash
cp .env.example .env
```

Откройте `.env` и заполните обязательные переменные:

```dotenv
# Пароль для PostgreSQL
DB_PASSWORD=your_strong_password

# MinIO (S3-хранилище)
MINIO_ROOT_USER=akita_admin
MINIO_ROOT_PASSWORD=your_strong_minio_password
MINIO_ACCESS_KEY=akita_admin
MINIO_SECRET_KEY=your_strong_minio_password

# SMS.ru (необязательно — нужен только для SMS-уведомлений)
SMS_API_KEY=your_smsru_key

# DaData (необязательно — автодополнение VIN/авто)
DADATA_TOKEN=your_dadata_token
DADATA_SECRET=your_dadata_secret
```

> Остальные переменные (`DB_HOST`, `DB_PORT`, `VITE_API_URL` и т.д.) уже настроены в `.env.example` под Docker и менять их не нужно.

### 3. Запустить

```bash
docker compose up -d
```

При первом запуске Docker:
- соберёт образ PHP (≈1–2 минуты);
- создаст базу данных и применит начальную схему (`docker/postgres/init.sql`);
- создаст бакеты MinIO (`order-photos`, `portfolio`);
- установит Composer-зависимости и запустит PHP-FPM;
- установит npm-зависимости и поднимет Vite dev-сервер.

### 4. Открыть в браузере

| Сервис | URL |
|---|---|
| Приложение (фронт) | http://localhost:5173 |
| API | http://localhost:8000/api |
| MinIO консоль | http://localhost:9001 |

---

## Учётная запись администратора

После первого запуска в базе уже есть администратор:

| Поле | Значение |
|---|---|
| Логин | `admin` |
| Пароль | `admin123` |

Войти: http://localhost:5173 → кнопка «Войти» → ввести логин и пароль.

Раздел администратора доступен по адресу http://localhost:5173/admin

---

## Управление контейнерами

```bash
# Посмотреть статус
docker compose ps

# Остановить (данные сохраняются)
docker compose stop

# Запустить снова
docker compose start

# Полная остановка и удаление контейнеров (данные в volumes сохраняются)
docker compose down

# Удалить всё включая данные БД и MinIO
docker compose down -v
```

---

## Локальный запуск (без Docker)

Если Docker недоступен, можно запустить вручную.

### Требования

- PHP 8.2+ с расширениями `pdo`, `pdo_pgsql`
- Composer
- Node.js 20+
- PostgreSQL 16 (порт `5433`)

### Шаги

**1. База данных**

Создайте базу `AkitaStudio` и выполните схему:

```bash
psql -U postgres -c "CREATE DATABASE \"AkitaStudio\";"
psql -U postgres -d AkitaStudio -f docker/postgres/init.sql
```

**2. Переменные окружения**

```bash
cp .env.example .env
# Отредактируйте .env: укажите DB_PASSWORD, DB_HOST=localhost, DB_PORT=5433
```

**3. Backend (PHP)**

```bash
cd api
composer install
php -S localhost:8000 -t .
```

**4. Frontend (Vue)**

```bash
# В корне проекта
npm install
npm run dev
```

---

## Структура проекта

```
akitastudio/
├── api/                    # PHP REST API
│   ├── config/             # database.php, env.php
│   ├── controllers/        # AuthController, OrderController, ...
│   ├── models/             # User, CarModel
│   ├── helpers/            # SmsHelper, MinioHelper
│   └── index.php           # Единая точка входа (роутер)
├── src/                    # Vue 3 SPA
│   ├── views/              # Страницы (Home, Booking, Profile, Admin/*)
│   ├── stores/             # Pinia-сторы (auth, profile, adminServices, ...)
│   ├── components/         # Переиспользуемые компоненты
│   └── router/             # Vue Router
├── docker/
│   ├── postgres/init.sql   # Схема БД + seed-данные (в т.ч. admin/admin123)
│   ├── php/Dockerfile      # PHP 8.2-FPM + pdo_pgsql + Composer
│   ├── nginx/default.conf  # Nginx: проксирование PHP-FPM
│   └── minio/init.sh       # Создание бакетов при первом запуске
├── docker-compose.yml
└── .env.example
```

---

## Порты по умолчанию

| Контейнер | Порт хоста | Назначение |
|---|---|---|
| `akita_frontend` | 5173 | Vue / Vite dev-сервер |
| `akita_nginx` | 8000 | Nginx → PHP-FPM (API) |
| `akita_postgres` | 5433 | PostgreSQL (внешний доступ) |
| `akita_minio` | 9000 | MinIO S3 API |
| `akita_minio` | 9001 | MinIO Web-консоль |
