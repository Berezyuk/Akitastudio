# Руководство разработчика и администратора — AkitaStudio

**Версия:** 1.0  
**Аудитория:** разработчики, системные администраторы

---

## Содержание

1. [Обзор архитектуры](#1-обзор-архитектуры)
2. [Быстрый старт](#2-быстрый-старт)
3. [Переменные окружения](#3-переменные-окружения)
4. [База данных](#4-база-данных)
5. [Backend — PHP API](#5-backend--php-api)
6. [Frontend — Vue 3](#6-frontend--vue-3)
7. [Хранилище файлов — MinIO](#7-хранилище-файлов--minio)
8. [SMS-интеграция](#8-sms-интеграция)
9. [Панель администратора](#9-панель-администратора)
10. [Добавление новых функций](#10-добавление-новых-функций)
11. [Развёртывание в продакшен](#11-развёртывание-в-продакшен)
12. [Типичные проблемы](#12-типичные-проблемы)

---

## 1. Обзор архитектуры

```
┌────────────────────────────────────────────────────────┐
│                      Клиентский браузер                │
│              Vue 3 SPA  (порт 5173 / Vite)             │
│   Pinia (стейт)  │  Vue Router  │  fetch + credentials │
└────────────────────────────────┬───────────────────────┘
                                 │ HTTP / JSON
                    ┌────────────▼───────────┐
                    │   Nginx (порт 8000)    │
                    │   реверс-прокси        │
                    └────────────┬───────────┘
                                 │ FastCGI
                    ┌────────────▼───────────┐
                    │   PHP-FPM              │
                    │   api/index.php        │  ← ручной роутер
                    │   Controllers / Models │
                    └──────┬──────────┬──────┘
                           │          │
              ┌────────────▼──┐  ┌────▼────────────────┐
              │  PostgreSQL   │  │  MinIO (S3)          │
              │  порт 5433    │  │  порт 9000 (API)     │
              │  (локально)   │  │  порт 9001 (консоль) │
              │  5432 Docker  │  │                      │
              └───────────────┘  └──────────────────────┘
```

### Стек технологий

| Слой | Технология | Версия |
|---|---|---|
| Frontend | Vue 3 + Vite | Node 20 |
| Состояние | Pinia (Composition API) | — |
| Роутинг | Vue Router 4 | — |
| Backend | PHP | 8.2+ |
| База данных | PostgreSQL | 16 |
| Веб-сервер | Nginx | 1.27 |
| Хранилище | MinIO (S3-совместимый) | latest |
| Контейнеризация | Docker + Docker Compose | — |
| SMS | zelenin/smsru (Composer) | — |

---

## 2. Быстрый старт

### 2.1 Запуск через Docker (рекомендуется)

```bash
# Клонировать репозиторий
git clone <repo-url>
cd Akitastudio

# Скопировать конфиг окружения
cp .env.example .env   # при необходимости создать вручную (см. раздел 3)

# Запустить все сервисы
docker compose up -d

# Убедиться, что все контейнеры запущены
docker compose ps
```

После запуска:
- Фронтенд: `http://localhost:5173`
- API: `http://localhost:8000/api`
- MinIO консоль: `http://localhost:9001`

База данных инициализируется автоматически из `docker/postgres/init.sql` при первом старте.

### 2.2 Локальный запуск без Docker

**Требования:** PHP 8.2+, PostgreSQL 16, Node.js 20, Composer.

```bash
# Установить зависимости PHP (SMS-интеграция)
cd api && composer install && cd ..

# Запустить PHP-сервер
php -S localhost:8000 -t api

# В отдельном терминале — фронтенд
npm install
npm run dev
```

Убедитесь, что в `api/config/database.php` (или в `.env`) указаны корректные реквизиты вашей локальной БД.

---

## 3. Переменные окружения

Файл `.env` в корне проекта. В Docker-окружении часть переменных переопределяется через `docker-compose.yml`.

```dotenv
# ── CORS ──────────────────────────────────────────────────────────────────────
CORS_ORIGIN=http://localhost:5173   # Адрес фронтенда (для заголовков CORS)

# ── База данных PostgreSQL ────────────────────────────────────────────────────
DB_HOST=localhost     # В Docker автоматически меняется на 'postgres'
DB_PORT=5433          # Локально: 5433, внутри Docker-сети: 5432
DB_NAME=AkitaStudio
DB_USER=postgres
DB_PASSWORD=<пароль>

# ── MinIO (S3-совместимое хранилище) ─────────────────────────────────────────
MINIO_ROOT_USER=akita_admin
MINIO_ROOT_PASSWORD=<пароль>
MINIO_ACCESS_KEY=akita_admin
MINIO_SECRET_KEY=<пароль>
MINIO_ENDPOINT=localhost   # В Docker меняется на 'minio'
MINIO_PORT=9000
MINIO_PUBLIC_URL=http://localhost:9000   # Публичный URL для доступа к файлам

# ── SMS.ru ────────────────────────────────────────────────────────────────────
SMS_API_KEY=<ваш-ключ-sms.ru>

# ── Frontend (Vite) ───────────────────────────────────────────────────────────
VITE_API_URL=http://localhost:8000/api
```

> В продакшене замените все `localhost` на реальные домены/IP и никогда не коммитьте `.env` с секретами в репозиторий.

---

## 4. База данных

### 4.1 Схема (таблицы)

| Таблица | Назначение |
|---|---|
| `users` | Аккаунты (логин, хэш пароля, роль: client/admin) |
| `clients` | Профили клиентов, связаны с `users` через `user_id` |
| `service_categories` | Категории услуг |
| `services` | Услуги (цена, длительность, признак активности) |
| `car_brands` | Марки автомобилей |
| `car_models` | Модели автомобилей, привязаны к марке |
| `order_statuses` | Справочник статусов заказа (Новый / В работе / Готово / Выдан / Отменён) |
| `orders` | Заказы (FK: client, brand, model, status) |
| `order_services` | Мост «заказ ↔ услуга» с ценой на момент заказа |
| `order_services_progress` | Прогресс выполнения каждой услуги в заказе (0–100%) |
| `order_photos` | Фотоотчёт по заказу (ссылки на MinIO) |
| `portfolio` | Медиа-контент портфолио (ссылки на MinIO) |
| `feedbacks` | Обращения из формы обратной связи |
| `employees` | Сотрудники студии |

ER-диаграмма: [`docs/er-diagram.md`](er-diagram.md)

### 4.2 Инициализация и seed-данные

Файл `docker/postgres/init.sql` выполняется автоматически при **первом** старте контейнера `postgres`. Он создаёт все таблицы и заполняет:
- Статусы заказов (5 записей)
- Категории услуг (4 категории)
- Примеры услуг (6 записей)
- Администратора: логин `admin`, пароль `admin123`

Повторный запуск `docker compose up` НЕ переинициализирует базу (данные хранятся в именованном volume `postgres_data`).

Для полного сброса:
```bash
docker compose down -v   # удаляет volumes!
docker compose up -d
```

### 4.3 Подключение к базе из хост-машины

```bash
psql -h localhost -p 5433 -U postgres -d AkitaStudio
# или через docker exec
docker exec -it akita_postgres psql -U postgres -d AkitaStudio
```

### 4.4 Важные ограничения схемы

- У `clients.phone_number` нет UNIQUE-ограничения — возможны дубликаты при некорректной нормализации номеров. Телефоны нормализуются в цифры (`preg_replace('/[^0-9]/', '', ...)`) перед сохранением в `api/models/User.php::register()`.
- Статусы заказа автоматически обновляются при изменении прогресса услуг (`AdminController::updateServiceProgress`): «Новый» → «В работе» → «Готово». Статусы «Выдан» (4) и «Отменён» (5) не перезаписываются автоматически.

---

## 5. Backend — PHP API

### 5.1 Точка входа и роутинг

Единственная точка входа — `api/index.php`. Роутер — ручная цепочка `if`-операторов:

```php
$path = trim(str_replace('/api/', '', parse_url($requestUri, PHP_URL_PATH)), '/');
// $path = 'user/orders', 'admin/services', ...

if ($path === 'user/orders' && $requestMethod === 'GET') {
    require_once __DIR__ . '/controllers/ProfileController.php';
    ProfileController::getOrders();
    exit;
}
```

Чтобы добавить новый эндпоинт — дописать блок в `index.php` и создать метод в нужном контроллере.

### 5.2 Структура директорий

```
api/
├── index.php              # роутер
├── config/
│   ├── database.php       # PDO-подключение к PostgreSQL
│   └── env.php            # загрузка .env (пропускается в Docker)
├── middleware/
│   └── auth.php           # authenticate(), requireRole($role)
├── controllers/           # HTTP-обработчики (статические методы)
│   ├── AdminController.php
│   ├── AuthController.php
│   ├── ProfileController.php
│   ├── OrderController.php
│   ├── FeedbackController.php
│   ├── ServiceController.php
│   ├── PortfolioController.php
│   ├── CarController.php
│   ├── CarValidationController.php
│   └── CategoryController.php
├── models/                # бизнес-логика и работа с БД
│   ├── User.php
│   ├── Order.php
│   ├── Client.php
│   ├── Service.php
│   ├── Portfolio.php
│   ├── CarBrand.php
│   ├── CarModel.php
│   ├── ServiceCategory.php
│   ├── OrderStatus.php
│   └── Employee.php
└── helpers/
    ├── MinioHelper.php    # S3-загрузка/удаление файлов
    └── SmsHelper.php      # отправка SMS через sms.ru
```

Диаграмма классов: [`docs/class-diagram.md`](class-diagram.md)

### 5.3 Аутентификация

Сессии PHP (`session_start()` в `index.php`). В сессии хранятся:

```php
$_SESSION['user_id']   // int
$_SESSION['client_id'] // int | null (null для admin без клиентского профиля)
$_SESSION['role']      // 'client' | 'admin'
$_SESSION['name']      // 'Имя Фамилия'
```

Middleware-функции в `api/middleware/auth.php`:

```php
authenticate()          // проверяет наличие сессии, возвращает данные пользователя
requireRole('admin')    // бросает 403 если роль не совпадает (admins проходят любой roleCheck)
```

### 5.4 Таблица эндпоинтов API

#### Публичные (без авторизации)

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/auth/login` | Вход |
| POST | `/api/auth/register` | Регистрация |
| POST | `/api/auth/logout` | Выход |
| GET | `/api/auth/me` | Текущий пользователь (по сессии) |
| GET | `/api/services` | Список активных услуг |
| GET | `/api/services?category_id=N` | Услуги по категории |
| GET | `/api/categories` | Категории услуг |
| GET | `/api/portfolio` | Элементы портфолио |
| GET | `/api/car-brands` | Марки автомобилей |
| GET | `/api/car-models?brand_name=X` | Модели по марке |
| POST | `/api/car-brand-suggest` | Автодополнение марок (DaData) |
| POST | `/api/validate-car` | Проверка автомобиля по гос. номеру |
| POST | `/api/order/create` | Создание заявки |
| POST | `/api/feedback` | Отправка обращения |

#### Клиентские (требуют авторизации, role: client или admin)

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/user/orders` | Мои заказы |
| POST | `/api/user/orders/:id/cancel` | Отмена заказа |
| POST | `/api/user/orders/:id/reschedule` | Перенос заказа |
| GET | `/api/user/orders/progress` | Прогресс выполнения |
| GET | `/api/user/orders/:id/photos` | Фотоотчёт заказа |
| GET | `/api/user/cars` | Мои автомобили |
| GET | `/api/user/profile` | Данные профиля |
| PUT | `/api/user/profile` | Обновление профиля |

#### Административные (требуют role: admin)

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/admin/dashboard` | Статистика и данные дашборда |
| GET | `/api/admin/orders` | Все заказы (с фильтрами) |
| GET | `/api/admin/orders/:id` | Один заказ |
| PUT | `/api/admin/orders/:id` | Обновление заказа |
| DELETE | `/api/admin/orders/:id` | Удаление заказа |
| GET | `/api/admin/orders/export` | Экспорт заказов CSV |
| GET | `/api/admin/orders/:id/progress` | Прогресс услуг заказа |
| PUT | `/api/admin/orders/:id/services/:sid/progress` | Обновление прогресса |
| POST | `/api/admin/orders/:id/photos` | Загрузка фото |
| GET | `/api/admin/orders/:id/photos` | Список фото |
| DELETE | `/api/admin/order-photos/:id` | Удаление фото |
| GET | `/api/admin/clients` | Список клиентов |
| GET | `/api/admin/clients/:id` | Детали клиента |
| POST | `/api/admin/clients` | Создание клиента |
| PUT | `/api/admin/clients/:id` | Редактирование клиента |
| DELETE | `/api/admin/clients/:id` | Удаление клиента |
| GET | `/api/admin/clients/export` | Экспорт клиентов CSV |
| GET | `/api/admin/services` | Все услуги |
| POST | `/api/admin/services` | Создание услуги |
| PUT | `/api/admin/services/:id` | Редактирование услуги |
| DELETE | `/api/admin/services/:id` | Удаление услуги |
| GET | `/api/admin/portfolio` | Портфолио |
| POST | `/api/admin/portfolio` | Добавление элемента |
| PUT | `/api/admin/portfolio/:id` | Редактирование элемента |
| DELETE | `/api/admin/portfolio/:id` | Удаление элемента |
| POST | `/api/admin/portfolio/upload` | Загрузка медиафайла |
| GET | `/api/admin/order-statuses` | Статусы заказов |
| GET | `/api/admin/service-categories` | Категории услуг |
| GET | `/api/admin/employees` | Сотрудники |
| POST | `/api/admin/change-password` | Смена пароля администратора |
| GET | `/api/admin/feedbacks` | Обращения |
| PUT | `/api/admin/feedbacks/:id/status` | Изменение статуса обращения |
| DELETE | `/api/admin/feedbacks/:id` | Удаление обращения |

### 5.5 Соглашения по формату ответов

Все ответы API — JSON. Успех:

```json
{ "success": true, "data": [...] }
```

Ошибка:

```json
{ "error": "Описание ошибки" }
```

HTTP-коды: `200` — успех, `401` — не авторизован, `403` — нет доступа, `500` — ошибка сервера.

### 5.6 Как добавить новый эндпоинт

1. Добавить блок роутинга в `api/index.php`:
   ```php
   if ($path === 'admin/my-feature' && $requestMethod === 'GET') {
       AdminController::myFeature();
       exit;
   }
   ```

2. Реализовать метод в контроллере:
   ```php
   public static function myFeature() {
       $admin = requireRole('admin');
       $db = (new Database())->getConnection();
       // ...
       echo json_encode(['success' => true, 'data' => $result]);
   }
   ```

3. Добавить запрос во фронтенд-сторе или компоненте.

---

## 6. Frontend — Vue 3

### 6.1 Структура

```
src/
├── main.js              # инициализация Vue + Pinia + Router
├── App.vue              # корневой компонент (шапка + контент + подвал)
├── config/
│   └── api.js           # export const API_BASE = import.meta.env.VITE_API_URL
├── router/
│   └── index.js         # маршруты и навигационный guard
├── stores/
│   ├── auth.js          # авторизация: user, isAuthenticated, login/logout/checkAuth
│   ├── profile.js       # профиль клиента (не используется напрямую в ClientProfileView)
│   ├── adminServices.js # CRUD услуг (используется в AdminServices.vue)
│   └── adminPortfolio.js
├── views/
│   ├── HomeView.vue
│   ├── ServicesView.vue
│   ├── PortfolioView.vue
│   ├── BookingView.vue
│   ├── LoginView.vue
│   ├── RegisterView.vue
│   ├── ClientProfileView.vue   # личный кабинет клиента
│   ├── ProfileView.vue         # панель администратора (sidebar + вкладки)
│   └── admin/
│       ├── AdminDashboard.vue
│       ├── AdminOrders.vue
│       ├── AdminClients.vue
│       ├── AdminServices.vue
│       ├── AdminPortfolio.vue
│       ├── AdminFeedbacks.vue
│       └── AdminSettings.vue
└── components/
    ├── TheHeader.vue
    ├── TheFooter.vue
    └── AdminOrderModal.vue
```

### 6.2 Стор авторизации

`src/stores/auth.js` хранит объект `user` в памяти (не в localStorage). При перезагрузке страницы `router/index.js` вызывает `authStore.checkAuth()`, который проверяет сессию через `/api/auth/me`.

Сессия хранится в cookie на стороне браузера. Все запросы к API делаются с `credentials: 'include'`.

### 6.3 Защита маршрутов

```javascript
// Клиентский маршрут
{ path: '/profile', meta: { requiresAuth: true, role: 'client' } }

// Административный маршрут
{ path: '/admin/profile', meta: { requiresAuth: true, role: 'admin' } }
```

Guard в `router/index.js` перенаправляет незалогиненных на `/login`, а пользователей с неверной ролью — на их «домашний» маршрут.

### 6.4 Как добавить новую страницу

1. Создать `.vue`-файл в `src/views/`.
2. Добавить маршрут в `src/router/index.js`.
3. Если страница требует данных — создать Pinia-стор в `src/stores/` или использовать `fetch` напрямую внутри компонента.

---

## 7. Хранилище файлов — MinIO

MinIO используется как S3-совместимое хранилище для:
- Фотоотчётов по заказам (бакет `orders`)
- Медиаконтента портфолио (бакет `portfolio`)

### 7.1 Бакеты

Создаются автоматически сервисом `minio-init` при первом запуске (скрипт `docker/minio/init.sh`).

| Бакет | Политика | Назначение |
|---|---|---|
| `orders` | public-read | Фото заказов |
| `portfolio` | public-read | Фото/видео портфолио |

### 7.2 Работа с MinioHelper

```php
// Загрузить файл
$url = MinioHelper::upload('orders', 'orders/123/photo.jpg', $_FILES['photo']['tmp_name'], 'image/jpeg');

// Удалить файл
MinioHelper::delete('orders', 'orders/123/photo.jpg');

// Разобрать URL на бакет + ключ
$parts = MinioHelper::parseUrl($url); // ['bucket' => 'orders', 'key' => 'orders/123/photo.jpg']

// Сгенерировать уникальный ключ
$key = MinioHelper::generateKey('orders/123', 'photo.jpg'); // 'orders/123/abc123_photo.jpg'
```

### 7.3 Веб-консоль MinIO

Доступна на `http://localhost:9001`. Логин/пароль — `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` из `.env`.

---

## 8. SMS-интеграция

Используется библиотека `zelenin/smsru` (устанавливается через Composer).

```bash
cd api && composer install
```

API-ключ указывается в `.env` как `SMS_API_KEY`. SMS отправляются из `api/helpers/SmsHelper.php`.

> Для тестирования без реальной отправки зарегистрируйтесь на [sms.ru](https://sms.ru) и используйте тестовый режим API.

---

## 9. Панель администратора

Доступна по адресу `/admin/profile` (только для пользователей с ролью `admin`). Состоит из боковой навигации и семи разделов. Активный раздел сохраняется между перезагрузками страницы (через `localStorage`).

### 9.1 Дашборд

Отображает:
- Карточки: заказов сегодня, активных заказов, выручка за месяц, всего клиентов.
- Уведомления: новые заказы сегодня, заказы ожидающие подтверждения.
- **График** (Chart.js): динамика заказов и выручки за последние 7 дней (двойная ось Y).
- Топ популярных услуг.
- Последние заказы.

### 9.2 Заказы

Функции:
- **Фильтры**: по клиенту, услуге, статусу, дате заявки (от/до).
- **Карточки статистики**: счётчики по статусам из отфильтрованного списка.
- **Таблица заказов**: статус меняется прямо в строке через `<select>`.
- **Модальное окно заказа** (клик по строке): управление прогрессом выполнения каждой услуги (слайдер 0–100%), загрузка фотоотчёта, редактирование заметок и суммы.
- **Экспорт CSV** (кнопка «Скачать CSV»): выгрузка с UTF-8 BOM для корректного открытия в Excel.

Статусы и их автоматическое присвоение при изменении прогресса:

| Условие прогресса | Статус |
|---|---|
| Все слайдеры = 0 | Новый (1) |
| Хотя бы один > 0 | В работе (2) |
| Все = 100% | Готово (3) |
| Вручную (через select) | Выдан (4) или Отменён (5) |

Статусы «Выдан» и «Отменён» не перезаписываются автоматически.

### 9.3 Клиенты

Функции:
- Поиск по имени, фамилии, телефону.
- Просмотр детальной карточки клиента с историей заказов.
- CRUD: добавление, редактирование, удаление клиентов.
- Экспорт базы клиентов в CSV с UTF-8 BOM.

### 9.4 Услуги

- Список всех услуг с ценами, длительностью, категорией и статусом.
- CRUD-операции.
- Переключатель «активна/неактивна» (неактивные услуги не отображаются в публичном каталоге).

### 9.5 Портфолио

- Загрузка изображений и видео (JPG, PNG, WEBP, GIF, MP4, WEBM) размером до 100 МБ.
- Файл загружается в MinIO при выборе — до сохранения карточки.
- Привязка к категории и услуге.
- Сортировка по полю `sort_order`.

### 9.6 Обратная связь

- Список обращений с формы контактов.
- Изменение статуса (новое / в обработке / закрыто).
- Добавление внутренних заметок.
- Удаление обращений.

### 9.7 Настройки

Смена пароля администратора:
1. Текущий пароль.
2. Новый пароль (не менее 6 символов).
3. Подтверждение нового пароля.

---

## 10. Добавление новых функций

### Сценарий: новый публичный раздел сайта

1. Создать компонент `src/views/MyNewView.vue`.
2. Добавить маршрут в `src/router/index.js`.
3. При необходимости — новый Pinia-стор в `src/stores/myNew.js`.
4. Если нужны данные с бэкенда — добавить эндпоинт в `api/index.php` и метод в нужный контроллер.

### Сценарий: новая таблица в БД

1. Написать SQL-миграцию и добавить в `docker/postgres/init.sql` (или выполнить вручную через `psql`).
2. Создать модель `api/models/MyModel.php`.
3. Добавить методы в нужный контроллер или создать новый.
4. Подключить через роутинг в `api/index.php`.

### Сценарий: новая колонка в существующей таблице

```sql
ALTER TABLE orders ADD COLUMN license_plate VARCHAR(20);
```

Затем добавить поле в соответствующие SQL-запросы в контроллерах и во фронтенд-форму/отображение.

### Соглашения по коду

| Аспект | Правило |
|---|---|
| Контроллеры | Статические методы, вся логика в одном методе |
| Модели | Инстанс-методы, каждый открывает собственное PDO-соединение |
| Запросы | PDO с параметризацией (только `bindParam`/`bindValue`, без интерполяции) |
| Ответы API | `echo json_encode([...])`, HTTP-коды через `http_response_code()` |
| Фронтенд | Composition API (`<script setup>`), `ref`/`computed` |
| API-запросы | `fetch` с `credentials: 'include'` и `${API_BASE}/endpoint` |

---

## 11. Развёртывание в продакшен

### 11.1 Минимальные изменения перед деплоем

1. **`.env`** — заменить все `localhost` на реальные домены/IP.
2. **Пароли** — сменить все дефолтные пароли (БД, MinIO, admin).
3. **`VITE_API_URL`** — установить публичный URL API.
4. **`CORS_ORIGIN`** — установить домен фронтенда.

### 11.2 Сборка фронтенда

```bash
npm run build   # создаёт dist/
```

Содержимое `dist/` отдаётся через Nginx как статика. В Nginx-конфиге для SPA добавьте `try_files $uri /index.html`.

### 11.3 HTTPS

Рекомендуется использовать Certbot + Let's Encrypt перед Nginx. MinIO и API должны также работать через HTTPS для безопасной передачи сессионных cookie.

### 11.4 PHP-сессии в production

По умолчанию PHP хранит сессии в файловой системе. При горизонтальном масштабировании (несколько PHP-контейнеров) вынесите хранилище сессий в Redis или memcached.

---

## 12. Типичные проблемы

### API недоступен

```bash
docker compose logs nginx
docker compose logs php
docker compose ps
```

Убедитесь, что `akita_nginx` и `akita_php` в статусе `running`.

### Ошибка подключения к БД

```bash
docker compose logs postgres
```

Проверьте переменные `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Внутри Docker `DB_HOST=postgres`, `DB_PORT=5432`.

### MinIO не принимает файлы

```bash
docker compose logs minio
docker compose logs minio-init
```

Убедитесь, что бакеты созданы: откройте `http://localhost:9001`, войдите, проверьте наличие бакетов `orders` и `portfolio`.

### Проблемы с CORS

Добавьте домен фронтенда в `CORS_ORIGIN` в `.env` и перезапустите PHP-контейнер:
```bash
docker compose restart php
```

### Сессия не сохраняется (пользователь разлогинивается)

Проверьте, что cookie настроены правильно. В разработке с localhost CORS работает по умолчанию. В продакшене убедитесь, что сессионный cookie имеет корректные атрибуты `Domain`, `SameSite`, `Secure`.

### Забытый пароль администратора

```bash
# Подключиться к БД и обновить хэш вручную
docker exec -it akita_postgres psql -U postgres -d AkitaStudio \
  -c "UPDATE users SET password_hash = '\$2y\$10\$...' WHERE login = 'admin';"
```

Хэш bcrypt можно сгенерировать через `password_hash('newpassword', PASSWORD_BCRYPT)` в PHP.

### CSV открывается с кракозябрами в Excel

Файлы экспортируются с UTF-8 BOM — это стандартный маркер для Excel. Если проблема сохраняется — откройте файл через «Данные → Из текста/CSV» в Excel и вручную выберите кодировку UTF-8.
