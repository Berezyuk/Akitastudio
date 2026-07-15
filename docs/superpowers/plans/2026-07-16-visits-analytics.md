# Статистика посещений — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Показать администратору визиты и уников за день с разрезом по источнику перехода и типу устройства, без сторонних счётчиков.

**Architecture:** SPA шлёт beacon `POST /api/visit` раз в 30 минут неактивности; PHP пишет строку в таблицу `visits`, храня только хеш посетителя (IP + UA + суточная соль), без сырого IP. Админка читает агрегаты через `GET /api/admin/analytics` и показывает их новой вкладкой в `ProfileView.vue`.

**Tech Stack:** PHP 8.2 (без фреймворка, PDO), PostgreSQL, Vue 3 + Pinia, chart.js (уже в зависимостях), тесты — node:test (API-смоук) и vitest (юниты).

**Спека:** `docs/superpowers/specs/2026-07-16-visits-analytics-design.md` — читать до начала работы, там обоснование каждого решения.

## Global Constraints

- **Guard вручную.** Роутер авторизацию не навешивает. Каждый админский метод начинается с `requireAdmin()`. Забыл — открытый эндпоинт.
- **opcache.** Между правкой PHP и прогоном тестов ждать >2 секунд (`opcache.revalidate_freq=2`), иначе тест увидит старый байткод и соврёт зелёным.
- **Стенд поднимать через `docker compose down && docker compose up -d`**, не `stop/start`. Правки в `docker/nginx/*.conf` требуют пересоздания контейнера: конфиг примонтирован одним файлом, `sed -i` подменяет inode и контейнер продолжает читать старый.
- **Тесты только на dev-стенде.** Пишут реальные строки, за собой не убирают.
- **Тест без обратной проверки не считается.** Каждый тест обязан упасть на откаченном фиксе — проверять руками.
- **Сырой IP не хранится нигде.** Только `sha256(ip + ua + VISIT_SALT + дата)`.
- **Стиль:** Tailwind mobile-first утилитами (`p-5 md:p-8`), без scoped-`@media`. Ключи ответов API — snake_case, как в существующих контроллерах.
- **Тексты интерфейса — по-русски**, как во всей админке.

---

## Порядок задач и почему он такой

Задача 1 идёт первой и обязана быть первой: без неё `visitor_hash` одинаков у всех и уники всегда равны 1. Внутри задачи 1 порядок шагов тоже жёсткий — сначала закрыть порт, потом снимать затирание заголовка. Наоборот нельзя: снятое затирание при открытом наружу порте 8000 позволяет подделать `X-Real-IP` в обход хостового nginx, а это обход троттлинга логина.

---

### Task 1: Доверенный IP клиента

Чинит блокер фичи и заодно живой баг: `RateLimiter` сейчас считает попытки логина всем в одну корзину, потому что видит адрес докер-шлюза вместо адреса клиента. 10 неудачных попыток кого угодно блокируют вход остальным.

**Files:**
- Modify: `docker-compose.yml:47` (публикация порта nginx)
- Modify: `docker/nginx/default.conf` (удалить строку `fastcgi_param HTTP_X_REAL_IP`)
- Test: `tests/api.test.js` (новый describe в конец файла)

**Interfaces:**
- Consumes: ничего
- Produces: `$_SERVER['HTTP_X_REAL_IP']` содержит реальный IP клиента, если запрос пришёл через хостовой nginx; `null` при прямом обращении к контейнеру (тогда `RateLimiter::ip()` падает на `REMOTE_ADDR`, как и раньше)

- [ ] **Step 1: Закрыть порт 8000 наружу**

`docker-compose.yml`, блок `nginx` — заменить:

```yaml
    ports:
      - "8000:80"
```

на:

```yaml
    ports:
      # Только localhost: снаружи API доступен исключительно через хостовой nginx
      # (docs/nginx-host.conf) — он терминирует TLS и ПЕРЕЗАПИСЫВАЕТ X-Real-IP.
      # С открытым наружу портом любой ходит мимо него и подделывает X-Real-IP,
      # а на нём висит троттлинг логина. postgres и minio уже привязаны к 127.0.0.1.
      - "127.0.0.1:8000:80"
```

- [ ] **Step 2: Написать падающий тест**

В конец `tests/api.test.js`:

```js
// Троттлинг логина считает попытки по IP (RateLimiter::ip() -> HTTP_X_REAL_IP).
// Контейнерный nginx затирал этот заголовок адресом докер-шлюза, и корзина
// становилась общей на всех: 10 неудачных попыток кого угодно блокировали вход
// остальным. Тест ловит именно это — на откаченном фиксе IP-2 получает 429.
//
// ⚠️  Если тест падает, корзина докер-шлюза засорена на 15 минут и остальные
//     тесты с логином админа получат 429. Очистка:
//     docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB -c "DELETE FROM login_attempts;"'
describe('троттлинг логина: корзины по IP не общие', () => {
  const badLogin = (ip) =>
    fetch(API + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Real-IP': ip },
      body: JSON.stringify({ login: 'nosuchuser', password: 'wrongpassword' }),
    })

  test('исчерпание лимита одним IP не блокирует другой', async () => {
    // RateLimiter::MAX_ATTEMPTS = 10 за 15 минут
    for (let i = 0; i < 11; i++) await badLogin('198.51.100.1')

    const throttled = await badLogin('198.51.100.1')
    assert.equal(throttled.status, 429, 'лимит по своему же IP не сработал')

    const other = await badLogin('198.51.100.2')
    assert.notEqual(other.status, 429, 'чужой IP получил 429 — корзина общая на всех')
  })
})
```

- [ ] **Step 3: Прогнать тест — убедиться, что падает**

Run: `npm test 2>&1 | grep -A5 "корзины по IP"`
Expected: FAIL — `чужой IP получил 429 — корзина общая на всех`

Затем очистить корзину (тест её засорил):
`docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB -c "DELETE FROM login_attempts;"'`

- [ ] **Step 4: Удалить затирание заголовка**

`docker/nginx/default.conf`, блок `location /` — удалить строку:

```
        fastcgi_param        HTTP_X_REAL_IP  $remote_addr;
```

и добавить на её место комментарий:

```
        # HTTP_X_REAL_IP здесь НЕ выставляем: nginx и так передаёт заголовки запроса
        # в FastCGI как HTTP_*, а $remote_addr в этом контейнере — адрес хостового
        # nginx, а не посетителя. Директива затирала настоящий X-Real-IP от хостового
        # nginx (docs/nginx-host.conf:41), и PHP видел один адрес у всех: троттлинг
        # логина был общим на всех, статистика уников считала бы одного человека.
```

- [ ] **Step 5: Пересоздать стек и прогнать тест**

```bash
docker compose down && docker compose up -d && sleep 8
npm test 2>&1 | grep -A5 "корзины по IP"
```
Expected: PASS

`down`+`up` обязателен: конфиг примонтирован одним файлом, при `restart` контейнер читает старый inode.

- [ ] **Step 6: Проверить, что стенд жив и порт закрыт**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/services   # ожидание: 200
docker compose ps --format '{{.Service}} {{.Ports}}' | grep nginx             # ожидание: 127.0.0.1:8000->80/tcp
```

- [ ] **Step 7: Commit**

```bash
git add docker-compose.yml docker/nginx/default.conf tests/api.test.js
git commit -m "fix: PHP видит реальный IP клиента, троттлинг логина больше не общий на всех"
```

---

### Task 2: Таблица visits и соль

**Files:**
- Modify: `docker/postgres/init.sql` (в конец, до сидов — рядом с `login_attempts`)
- Modify: `.env.example` (переменная + прод-чеклист)
- Test: применение схемы проверяется руками, `psql`

**Interfaces:**
- Produces: таблица `visits (visit_id, visited_at, visitor_hash, source, referer_host, device)`; env-переменная `VISIT_SALT`

- [ ] **Step 1: Добавить таблицу в init.sql**

```sql
-- Визиты для статистики в админке.
-- Сырой IP не хранится: только visitor_hash = sha256(ip + user_agent + VISIT_SALT + дата).
-- IP по 152-ФЗ — персональные данные; соль не даёт перебрать диапазон адресов,
-- суточная компонента не даёт сшить визиты одного человека между днями.
CREATE TABLE IF NOT EXISTS visits (
    visit_id     BIGSERIAL PRIMARY KEY,
    visited_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    visitor_hash CHAR(64) NOT NULL,
    source       VARCHAR(20) NOT NULL,   -- search | social | direct | other
    referer_host VARCHAR(255),           -- только хост, без query: в query утекают поисковые запросы
    device       VARCHAR(10) NOT NULL    -- mobile | desktop
);
CREATE INDEX IF NOT EXISTS idx_visits_date ON visits (visited_at);
-- Второй индекс — под проверку накрутки: она бьёт по visitor_hash на каждом визите.
CREATE INDEX IF NOT EXISTS idx_visits_hash ON visits (visitor_hash, visited_at);
```

- [ ] **Step 2: Добавить VISIT_SALT в .env.example**

В секцию `Application`, после `SESSION_COOKIE_SECURE`:

```
# Соль для хеша посетителя в статистике (api/controllers/VisitController.php).
# Сырой IP не хранится — только sha256(ip + user_agent + VISIT_SALT + дата).
# Без соли хеш обращается перебором диапазона IP, поэтому пустое значение
# считается ошибкой: beacon вернёт 500 и статистика молча не пойдёт.
# Сгенерировать: openssl rand -hex 32
VISIT_SALT=
```

В блок «Прод-чеклист», к списку значений для прода:

```
#   VISIT_SALT=<openssl rand -hex 32>           # менять нельзя: смена = разрыв
#                                               # истории уников (старые хеши не сойдутся)
```

- [ ] **Step 3: Прописать соль в свой .env**

```bash
echo "VISIT_SALT=$(openssl rand -hex 32)" >> .env
```

`.env` в git не коммитится — это локальный шаг.

- [ ] **Step 4: Применить схему**

Таблицы нет в живой БД: `init.sql` отрабатывает только на пустом томе.

```bash
docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB' < docker/postgres/init.sql
```

`CREATE TABLE IF NOT EXISTS` и `INSERT ... ON CONFLICT` в этом файле делают повторный прогон безопасным.

- [ ] **Step 5: Проверить, что таблица есть**

Run: `docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB -c "\d visits"'`
Expected: описание таблицы с колонками `visit_id, visited_at, visitor_hash, source, referer_host, device` и двумя индексами.

- [ ] **Step 6: Commit**

```bash
git add docker/postgres/init.sql .env.example
git commit -m "feat: таблица visits и соль для хеша посетителя"
```

---

### Task 3: GET /api/admin/analytics

**Files:**
- Modify: `api/controllers/AdminSystemController.php` (хелпер `fillDays()`, правка `getDashboardStats()`, новый `getAnalytics()`)
- Modify: `api/index.php` (секция «Админ: дашборд/пароль»)
- Test: `tests/api.test.js`

**Interfaces:**
- Consumes: таблица `visits` (Task 2)
- Produces:
  - `private static function fillDays(array $rows, int $days, array $fields): array` — добивает пустые дни нулями. `$fields`: `['ключ_ответа' => ['sql_колонка', 'int'|'float']]`. Возвращает `['labels' => [...], 'ключ_ответа' => [...], ...]`
  - `GET /api/admin/analytics?days=7|30` →
    ```json
    { "success": true,
      "today": { "visits": 42, "uniques": 31 },
      "chart_data": { "labels": ["10.07"], "visits": [42], "uniques": [31] },
      "sources": [{ "source": "search", "count": 120 }],
      "devices": [{ "device": "mobile", "count": 200 }],
      "top_hosts": [{ "referer_host": "yandex.ru", "count": 90 }] }
    ```

`getDashboardStats()` переводится на тот же `fillDays()` — цикл добивки в нём дословно тот же. Это работающий код, поэтому перед правкой он накрывается характеризующим тестом: сейчас его график не покрыт ничем, тест `today_orders > 0` о `chart_data` не знает.

- [ ] **Step 1: Характеризующий тест на график дашборда**

Пишется ДО рефакторинга и обязан пройти на текущем коде — он фиксирует поведение, которое правка не должна изменить. В `tests/api.test.js`, в существующий describe про дашборд (рядом с проверкой `today_orders`):

```js
  test('chart_data: 7 точек, числа, а не строки из PDO', async () => {
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const data = await (await fetch(API + '/admin/dashboard', { headers: { Cookie: cookie } })).json()

    assert.equal(data.chart_data.labels.length, 7)
    assert.equal(data.chart_data.values.length, 7)
    assert.equal(data.chart_data.revenue.length, 7)
    // PDO отдаёт COUNT/SUM строками. Контроллер их приводит — если рефакторинг
    // приведение потеряет, JSON поедет со строками и тест поймает это здесь.
    for (const v of data.chart_data.values) assert.equal(typeof v, 'number')
    for (const r of data.chart_data.revenue) assert.equal(typeof r, 'number')
  })
```

Run: `npm test 2>&1 | grep -A3 "chart_data: 7 точек"`
Expected: PASS на нетронутом коде. Если падает — остановиться и разобраться: значит поведение не то, что мы собираемся сохранять.

- [ ] **Step 2: Написать падающие тесты на новый эндпоинт**

В `tests/api.test.js`, в массив `adminRoutes` (describe «guard: админские роуты без сессии») добавить:

```js
    '/admin/analytics',
```

И отдельный describe:

```js
describe('GET /admin/analytics', () => {
  let cookie
  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  const get = (qs) => fetch(API + '/admin/analytics' + qs, { headers: { Cookie: cookie } })

  test('days=7 отдаёт ряд ровно на 7 точек', async () => {
    const data = await (await get('?days=7')).json()
    assert.ok(data.success)
    assert.equal(data.chart_data.labels.length, 7)
    assert.equal(data.chart_data.visits.length, 7)
    assert.equal(data.chart_data.uniques.length, 7)
  })

  test('ряд отдаётся числами, а не строками из PDO', async () => {
    const data = await (await get('?days=7')).json()
    for (const v of data.chart_data.visits) assert.equal(typeof v, 'number')
    for (const u of data.chart_data.uniques) assert.equal(typeof u, 'number')
    assert.equal(typeof data.today.visits, 'number')
    assert.equal(typeof data.today.uniques, 'number')
  })

  test('days=30 отдаёт ряд ровно на 30 точек', async () => {
    const data = await (await get('?days=30')).json()
    assert.equal(data.chart_data.labels.length, 30)
  })

  test('days вне whitelist -> 400, а не подстановка в SQL', async () => {
    const res = await get('?days=99')
    assert.equal(res.status, 400)
  })

  test('без days работает как days=7', async () => {
    const data = await (await get('')).json()
    assert.equal(data.chart_data.labels.length, 7)
  })

  test('top_hosts отдаётся массивом и не содержит прямых заходов', async () => {
    const data = await (await get('?days=7')).json()
    assert.ok(Array.isArray(data.top_hosts))
    // У direct нет хоста (referer_host IS NULL) — в топ сайтов ему попадать нечем.
    for (const row of data.top_hosts) assert.ok(row.referer_host)
  })
})
```

Run: `npm test 2>&1 | grep -A3 "admin/analytics"`
Expected: FAIL — 404 на роуте, guard-тест тоже красный.

- [ ] **Step 3: Вынести хелпер и перевести на него getDashboardStats**

`api/controllers/AdminSystemController.php` — добавить private-метод (место: над `getDashboardStats()`):

```php
    // Дни без строк БД не возвращает, а график обязан иметь точку на каждый день —
    // иначе он врёт формой. Общий добивщик для дашборда и аналитики.
    // $fields: ['ключ_ответа' => ['колонка_в_выборке', 'int'|'float']].
    // Тип обязателен: PDO отдаёт COUNT/SUM строками, а в JSON нужны числа.
    private static function fillDays(array $rows, int $days, array $fields): array {
        $byDate = array_column($rows, null, 'day');
        $out = ['labels' => []];
        foreach ($fields as $key => $_) {
            $out[$key] = [];
        }
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $out['labels'][] = date('d.m', strtotime($date));
            foreach ($fields as $key => [$col, $type]) {
                $raw = $byDate[$date][$col] ?? 0;
                $out[$key][] = $type === 'int' ? (int)$raw : (float)$raw;
            }
        }
        return $out;
    }
```

Затем в `getDashboardStats()` заменить блок от `$chartData = ['labels' => [], 'values' => [], 'revenue' => []];` и весь цикл `for ($i = 6; $i >= 0; $i--) {...}` на:

```php
        $chartData = self::fillDays(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            7,
            ['values' => ['cnt', 'int'], 'revenue' => ['revenue', 'float']]
        );
```

Строки `$rows = $stmt->fetchAll(...)` и `$byDate = array_column($rows, null, 'day');` там же удаляются — их работу делает хелпер. Переменная `$fromDate` и сам запрос остаются как есть.

Run:
```bash
sleep 3
npm test 2>&1 | grep -A3 "chart_data: 7 точек"
```
Expected: PASS — рефакторинг поведение не изменил.

- [ ] **Step 4: Написать getAnalytics()**

`api/controllers/AdminSystemController.php`, сразу после `getDashboardStats()`:

```php
    // ========== АНАЛИТИКА ПОСЕЩЕНИЙ ==========
    public static function getAnalytics() {
        self::checkAdmin();

        // Whitelist, а не подстановка: $_GET уходит в SQL-запросы ниже.
        $days = (int)($_GET['days'] ?? 7);
        if (!in_array($days, [7, 30], true)) {
            echo json_encode(['error' => 'Недопустимый период']);
            return;
        }

        $db = new Database();
        $conn = $db->getConnection();

        // Уники за день — COUNT(DISTINCT visitor_hash). За неделю так не считаются:
        // соль суточная, хеши одного человека между днями не совпадают. Это
        // осознанный размен приватности на точность, см. спеку.
        $stmt = $conn->query(
            "SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS uniques
             FROM visits WHERE visited_at::date = CURRENT_DATE"
        );
        $today = $stmt->fetch(PDO::FETCH_ASSOC);

        $fromDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stmt = $conn->prepare(
            "SELECT visited_at::date::text AS day, COUNT(*) AS visits,
                    COUNT(DISTINCT visitor_hash) AS uniques
             FROM visits WHERE visited_at::date >= :from
             GROUP BY visited_at::date ORDER BY visited_at::date ASC"
        );
        $stmt->execute([':from' => $fromDate]);
        $chartData = self::fillDays(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $days,
            ['visits' => ['visits', 'int'], 'uniques' => ['uniques', 'int']]
        );

        $stmt = $conn->prepare(
            "SELECT source, COUNT(*) AS count FROM visits
             WHERE visited_at::date >= :from GROUP BY source ORDER BY count DESC"
        );
        $stmt->execute([':from' => $fromDate]);
        $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare(
            "SELECT device, COUNT(*) AS count FROM visits
             WHERE visited_at::date >= :from GROUP BY device ORDER BY count DESC"
        );
        $stmt->execute([':from' => $fromDate]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Топ сайтов-источников. Прямые заходы отсеиваются сами: у них
        // referer_host IS NULL. Лимит 5 — список для беглого взгляда, не отчёт.
        $stmt = $conn->prepare(
            "SELECT referer_host, COUNT(*) AS count FROM visits
             WHERE visited_at::date >= :from AND referer_host IS NOT NULL
             GROUP BY referer_host ORDER BY count DESC LIMIT 5"
        );
        $stmt->execute([':from' => $fromDate]);
        $topHosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'today'      => ['visits' => (int)$today['visits'], 'uniques' => (int)$today['uniques']],
            'chart_data' => $chartData,
            'sources'    => $sources,
            'devices'    => $devices,
            'top_hosts'  => $topHosts,
        ]);
    }
```

`echo json_encode(['error' => ...])` без `success` роутер сам превратит в HTTP 400 — так устроен `api/index.php`, статус руками не трогаем.

- [ ] **Step 5: Добавить роут**

`api/index.php`, секция «Админ: дашборд/пароль», после строки `admin/dashboard`:

```php
    ['GET',    'admin/analytics',                   fn($m) => AdminSystemController::getAnalytics()],
```

- [ ] **Step 6: Прогнать тесты**

```bash
sleep 3
npm test
```
Expected: PASS — новый describe, guard-тест на `/admin/analytics`, характеризующий тест дашборда.

- [ ] **Step 7: Проверить guard обратной проверкой**

Временно закомментировать `self::checkAdmin();` в `getAnalytics()`, затем:

```bash
sleep 3
npm test 2>&1 | grep "admin/analytics -> 401"
```
Expected: FAIL — `/admin/analytics доступен без авторизации!`

Вернуть строку, прогнать снова, убедиться в PASS.

- [ ] **Step 8: Commit**

```bash
git add api/controllers/AdminSystemController.php api/index.php tests/api.test.js
git commit -m "feat: GET /api/admin/analytics — визиты, уники, источники, устройства, топ сайтов"
```

---

### Task 4: VisitController и роут POST /api/visit

**Files:**
- Create: `api/controllers/VisitController.php`
- Modify: `api/index.php` (одна строка в `$routes`, секция «Публичные»)
- Test: `tests/api.test.js`

**Interfaces:**
- Consumes: таблица `visits` (Task 2), `VISIT_SALT`, доверенный `HTTP_X_REAL_IP` (Task 1); тесты читают результат через `GET /api/admin/analytics` (Task 3)
- Produces: `POST /api/visit` → `204`, тело `{"referrer": "<document.referrer>"}`. Приватные static-методы `classifySource(string): array{0:string,1:?string}`, `classifyDevice(string): string`, `visitorHash(string): string`

Контроллеры подхватываются через `glob(__DIR__ . '/controllers/*.php')` — регистрировать файл отдельно не нужно.

- [ ] **Step 1: Написать падающие тесты**

В `tests/api.test.js`. Хелперы кладём рядом с describe — существующий `post()` своих заголовков не умеет, а нам нужны `User-Agent` и `X-Real-IP`.

Также дописать в шапку файла, к команде очистки, `DELETE FROM visits;`.

```js
// Beacon визитов. Referer из заголовка тут бесполезен (на fetch из SPA он равен
// URL текущей страницы), поэтому источник приходит в теле — см. спеку.
// Каждый тест берёт свой X-Real-IP: hash = f(ip, ua), значит своя корзина
// антинакрутки и никакого влияния тестов друг на друга.
describe('POST /visit: сбор визитов', () => {
  let cookie

  const visit = (body, headers = {}) =>
    fetch(API + '/visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: JSON.stringify(body),
    })

  const analytics = async () =>
    (await fetch(API + '/admin/analytics?days=7', { headers: { Cookie: cookie } })).json()

  const countBy = (rows, key, val) => Number(rows?.find((r) => r[key] === val)?.count ?? 0)

  const UA_DESKTOP = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
  const UA_MOBILE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'

  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  test('отвечает 204 и увеличивает счётчик визитов', async () => {
    const before = await analytics()
    const res = await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.10', 'User-Agent': UA_DESKTOP })
    assert.equal(res.status, 204)
    const after = await analytics()
    assert.equal(after.today.visits - before.today.visits, 1)
  })

  test('переход с поисковика -> source=search', async () => {
    const before = await analytics()
    await visit(
      { referrer: 'https://yandex.ru/search/?text=детейлинг' },
      { 'X-Real-IP': '198.51.100.11', 'User-Agent': UA_DESKTOP }
    )
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'search') - countBy(before.sources, 'source', 'search'), 1)
  })

  test('пустой referrer -> source=direct', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.12', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'direct') - countBy(before.sources, 'source', 'direct'), 1)
  })

  test('переход со своего же сайта -> source=direct, а не other', async () => {
    const own = env.CORS_ORIGIN
    const before = await analytics()
    await visit({ referrer: `${own}/services` }, { 'X-Real-IP': '198.51.100.13', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'direct') - countBy(before.sources, 'source', 'direct'), 1)
  })

  test('iPhone -> device=mobile', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.14', 'User-Agent': UA_MOBILE })
    const after = await analytics()
    assert.equal(countBy(after.devices, 'device', 'mobile') - countBy(before.devices, 'device', 'mobile'), 1)
  })

  test('десктопный User-Agent -> device=desktop', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.15', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.devices, 'device', 'desktop') - countBy(before.devices, 'device', 'desktop'), 1)
  })
})
```

- [ ] **Step 2: Прогнать — убедиться, что падают**

Run: `npm test 2>&1 | grep -A3 "сбор визитов"`
Expected: FAIL — роут не существует, `POST /visit` отдаёт 404.

- [ ] **Step 3: Написать контроллер**

Create `api/controllers/VisitController.php`:

```php
<?php
// api/controllers/VisitController.php — счётчик визитов для статистики в админке.
// Дизайн и обоснования: docs/superpowers/specs/2026-07-16-visits-analytics-design.md

require_once __DIR__ . '/../config/database.php';

class VisitController {
    // Визит пишется раз в 30 минут неактивности, то есть у живого человека их
    // не больше двух в час. 30 — потолок с большим запасом против curl в цикле.
    private const MAX_PER_HOUR = 30;

    public static function track() {
        $body = json_decode(file_get_contents('php://input'), true);
        $referrer = is_array($body) ? (string)($body['referrer'] ?? '') : '';
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $conn = (new Database())->getConnection();
        $hash = self::visitorHash($ua);

        // Ответ 204 и при отказе: накрутчику незачем знать, где потолок,
        // а клиенту эта информация не нужна — beacon ответ не читает.
        if (!self::withinLimit($conn, $hash)) {
            http_response_code(204);
            return;
        }

        [$source, $refererHost] = self::classifySource($referrer);
        $stmt = $conn->prepare(
            'INSERT INTO visits (visitor_hash, source, referer_host, device) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$hash, $source, $refererHost, self::classifyDevice($ua)]);

        http_response_code(204);
    }

    private static function visitorHash(string $ua): string {
        // X-Real-IP перезаписывает хостовой nginx (docs/nginx-host.conf), клиент
        // подделать не может: порт 8000 наружу закрыт, мимо прокси не пройти.
        $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $salt = getenv('VISIT_SALT') ?: '';
        // Без соли хеш обращается перебором диапазона IP, а это персональные данные.
        // Падаем громко: лучше нет статистики, чем обратимые хеши IP в базе.
        if ($salt === '') {
            throw new RuntimeException('VISIT_SALT не задан — статистика посещений отключена');
        }
        return hash('sha256', $ip . $ua . $salt . date('Y-m-d'));
    }

    private static function classifyDevice(string $ua): string {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua) ? 'mobile' : 'desktop';
    }

    /** @return array{0: string, 1: ?string} [источник, хост реферера] */
    private static function classifySource(string $referrer): array {
        $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return ['direct', null];
        }
        // Свой же сайт — это не источник перехода. Хост берём из CORS_ORIGIN:
        // переменная уже есть, новой сущности ради этого не заводим.
        $ownHost = strtolower((string)parse_url(getenv('CORS_ORIGIN') ?: '', PHP_URL_HOST));
        if ($ownHost !== '' && ($host === $ownHost || str_ends_with($host, '.' . $ownHost))) {
            return ['direct', null];
        }
        if (preg_match('/(^|\.)(yandex|google|bing|mail|duckduckgo|rambler)\./', $host)) {
            return ['search', $host];
        }
        if (preg_match('/(^|\.)(vk|t|telegram|instagram|youtube|facebook|ok)\./', $host)) {
            return ['social', $host];
        }
        return ['other', $host];
    }

    private static function withinLimit(PDO $conn, string $hash): bool {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM visits WHERE visitor_hash = ? AND visited_at > NOW() - INTERVAL '1 hour'"
        );
        $stmt->execute([$hash]);
        return (int)$stmt->fetchColumn() < self::MAX_PER_HOUR;
    }
}
```

- [ ] **Step 4: Добавить роут**

`api/index.php`, секция «Публичные», после строки `['POST', 'feedback', ...]`:

```php
    ['POST',   'visit',                             fn($m) => VisitController::track()],
```

- [ ] **Step 5: Прогнать тесты**

```bash
sleep 3   # opcache.revalidate_freq=2
npm test 2>&1 | grep -A3 "сбор визитов"
```
Expected: PASS (все шесть).

- [ ] **Step 6: Commit**

```bash
git add api/controllers/VisitController.php api/index.php tests/api.test.js
git commit -m "feat: beacon POST /api/visit — сбор визитов без сырого IP"
```

---

### Task 5: Проверка антинакрутки

Логика написана в Task 4, но без теста она не проверена: потолок 30/час никто не переступал.

**Files:**
- Test: `tests/api.test.js` (в describe «POST /visit: сбор визитов»)

**Interfaces:**
- Consumes: `POST /api/visit` (Task 4), `GET /api/admin/analytics` (Task 3)

- [ ] **Step 1: Написать тест**

```js
  test('накрутка: сверх 30 визитов в час с одного хеша не пишется', async () => {
    const ip = '198.51.100.99'
    const headers = { 'X-Real-IP': ip, 'User-Agent': UA_DESKTOP }
    for (let i = 0; i < 30; i++) await visit({ referrer: '' }, headers)

    const before = await analytics()
    const res = await visit({ referrer: '' }, headers)
    const after = await analytics()

    assert.equal(res.status, 204, 'отказ маскируется под успех — накрутчику незачем знать потолок')
    assert.equal(after.today.visits - before.today.visits, 0, '31-й визит записался')
  })
```

- [ ] **Step 2: Прогнать**

Run: `npm test 2>&1 | grep -A3 "накрутка"`
Expected: PASS

- [ ] **Step 3: Проверить обратной проверкой**

Временно поднять потолок в `api/controllers/VisitController.php`: `MAX_PER_HOUR = 1000`. Затем:

```bash
sleep 3
npm test 2>&1 | grep -A3 "накрутка"
```
Expected: FAIL — `31-й визит записался`.

Вернуть `30`, прогнать снова, убедиться в PASS. Тест без обратной проверки не считается.

⚠️ Хеш зависит от даты — тест переиспользует ту же корзину при повторных прогонах в тот же день и на второй прогон упадёт (30 визитов уже записаны, `before`/`after` не сдвинутся... собственно, тест на это и рассчитан: он проверяет ноль прироста). Корректно и при повторном прогоне.

- [ ] **Step 4: Commit**

```bash
git add tests/api.test.js
git commit -m "test: потолок 30 визитов в час с одного хеша"
```

---

### Task 6: Beacon во фронте

**Files:**
- Create: `src/config/visit.js`
- Create: `tests/unit/visit.test.js`
- Modify: `src/main.js`

**Interfaces:**
- Consumes: `apiFetch` из `src/config/api.js`; `POST /api/visit` (Task 4)
- Produces: `shouldTrackVisit(now: number, last: string|number|null): boolean`, `VISIT_WINDOW_MS: number`

- [ ] **Step 1: Написать падающий тест**

Create `tests/unit/visit.test.js`:

```js
// Окно визита — единственная арифметика в сборе статистики, и соврать она может
// тихо: слишком короткое окно надувает визиты каждой перезагрузкой, слишком
// длинное — теряет их. В main.js эту логику из теста не позвать, поэтому она
// вынесена в отдельный модуль.
import { test, describe, expect } from 'vitest'
import { shouldTrackVisit, VISIT_WINDOW_MS } from '../../src/config/visit'

const NOW = 1_800_000_000_000

describe('shouldTrackVisit', () => {
  test('первый заход — метки нет', () => {
    expect(shouldTrackVisit(NOW, null)).toBe(true)
  })

  test('29 минут назад — тот же визит', () => {
    expect(shouldTrackVisit(NOW, NOW - 29 * 60 * 1000)).toBe(false)
  })

  test('31 минуту назад — новый визит', () => {
    expect(shouldTrackVisit(NOW, NOW - 31 * 60 * 1000)).toBe(true)
  })

  test('ровно на границе окна — новый визит', () => {
    expect(shouldTrackVisit(NOW, NOW - VISIT_WINDOW_MS)).toBe(true)
  })

  test('метка из localStorage приходит строкой', () => {
    expect(shouldTrackVisit(NOW, String(NOW - 29 * 60 * 1000))).toBe(false)
    expect(shouldTrackVisit(NOW, String(NOW - 31 * 60 * 1000))).toBe(true)
  })

  test('мусор в localStorage не ломает счётчик', () => {
    expect(shouldTrackVisit(NOW, 'сломали руками')).toBe(true)
    expect(shouldTrackVisit(NOW, '')).toBe(true)
  })
})
```

- [ ] **Step 2: Прогнать — убедиться, что падает**

Run: `npm run test:unit -- visit`
Expected: FAIL — `Failed to resolve import "../../src/config/visit"`

- [ ] **Step 3: Написать модуль**

Create `src/config/visit.js`:

```js
// Граница визита для статистики посещений: 30 минут неактивности.
// Живёт отдельно от main.js, чтобы быть вызываемой из теста.

export const VISIT_WINDOW_MS = 30 * 60 * 1000

// last приходит из localStorage — это строка, null или что угодно, если её
// правили руками. Любое нечисло трактуем как «визита не было»: лишний визит
// в статистике честнее потерянного.
export function shouldTrackVisit(now, last) {
    const prev = Number(last)
    if (!Number.isFinite(prev) || prev <= 0) return true
    return now - prev >= VISIT_WINDOW_MS
}
```

- [ ] **Step 4: Прогнать тест**

Run: `npm run test:unit -- visit`
Expected: PASS (6 тестов)

- [ ] **Step 5: Подключить beacon в main.js**

`src/main.js` — добавить импорты и блок после `app.mount('#app')`:

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createUnhead, headSymbol } from '@unhead/vue'
import App from './App.vue'
import router from './router'
import { apiFetch } from './config/api'
import { shouldTrackVisit } from './config/visit'

const app = createApp(App)
const pinia = createPinia()
const head = createUnhead()

app.use(pinia)
app.use(router)
app.use({ install: (a) => { a.provide(headSymbol, head) } })
app.mount('#app')

// Счётчик визитов для админки. document.referrer, а не заголовок Referer: на
// fetch из SPA тот равен URL текущей страницы и внешний источник не показывает.
const LAST_VISIT_KEY = 'last_visit_at'
if (shouldTrackVisit(Date.now(), localStorage.getItem(LAST_VISIT_KEY))) {
    // Метку ставим до запроса и независимо от исхода: иначе при лежащем API
    // beacon уходил бы на каждой навигации.
    localStorage.setItem(LAST_VISIT_KEY, String(Date.now()))
    apiFetch('/visit', {
        method: 'POST',
        body: JSON.stringify({ referrer: document.referrer }),
    }).catch(() => {})   // статистика не должна ронять сайт
}
```

- [ ] **Step 6: Проверить руками в браузере**

```bash
docker compose logs -f frontend    # убедиться, что Vite собрал без ошибок
```

Открыть `http://localhost:5173`, вкладка Network: один `POST /api/visit` → `204`. Перезагрузить страницу — второго запроса нет (метка свежая). Выполнить в консоли `localStorage.removeItem('last_visit_at')` и перезагрузить — запрос снова один.

- [ ] **Step 7: Commit**

```bash
git add src/config/visit.js src/main.js tests/unit/visit.test.js
git commit -m "feat: beacon визита из SPA, окно 30 минут"
```

---

### Task 7: Вкладка «Аналитика» в админке

**Files:**
- Create: `src/views/admin/AdminAnalytics.vue`
- Modify: `src/views/ProfileView.vue` (импорт, `validTabs`, `adminTabs`, блок `v-if`)

**Interfaces:**
- Consumes: `GET /api/admin/analytics?days=7|30` (Task 3)

Админка не на роутах: `ProfileView.vue` держит вкладки через `activeAdminTab`, компоненты под `v-if`. Делаем девятую вкладку по образцу восьми существующих — новых механизмов не заводим.

- [ ] **Step 1: Создать компонент**

Create `src/views/admin/AdminAnalytics.vue`:

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Chart from 'chart.js/auto'
import { API_BASE } from '@/config/api'

const stats = ref({ today: { visits: 0, uniques: 0 }, sources: [], devices: [], top_hosts: [] })
const days = ref(7)
const loading = ref(false)
const error = ref('')
const chartCanvas = ref(null)
let chartInstance = null

const SOURCE_NAMES = {
  search: 'Поисковики',
  social: 'Соцсети',
  direct: 'Прямые заходы',
  other: 'Другие сайты',
}
const DEVICE_NAMES = { mobile: 'Телефон', desktop: 'Компьютер' }

const total = (rows) => rows.reduce((sum, r) => sum + Number(r.count), 0)
const percent = (rows, count) => {
  const all = total(rows)
  return all === 0 ? 0 : Math.round((Number(count) / all) * 100)
}

const loadStats = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${API_BASE}/admin/analytics?days=${days.value}`, {
      credentials: 'include',
    })
    const data = await res.json()
    if (!data.success) throw new Error(data.error || 'Не удалось загрузить статистику')
    stats.value = data
    renderChart(data.chart_data)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const renderChart = (chartData) => {
  if (!chartCanvas.value) return
  if (chartInstance) chartInstance.destroy()
  chartInstance = new Chart(chartCanvas.value.getContext('2d'), {
    type: 'line',
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: 'Визиты',
          data: chartData.visits,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Уники',
          data: chartData.uniques,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: true,
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

const setDays = (value) => {
  days.value = value
  loadStats()
}

onMounted(loadStats)
onUnmounted(() => {
  if (chartInstance) chartInstance.destroy()
})
</script>

<template>
  <div>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-white">Аналитика</h2>
        <p class="text-sm text-gray-400 mt-1">Посещаемость сайта. Боты не считаются.</p>
      </div>
      <div class="flex gap-2">
        <button
          v-for="value in [7, 30]"
          :key="value"
          @click="setDays(value)"
          class="px-4 py-2 rounded-lg text-sm transition-colors"
          :class="days === value ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'"
        >
          {{ value }} дней
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-red-900/40 p-4 text-sm text-red-300">{{ error }}</p>
    <p v-if="loading" class="text-sm text-gray-500">Загрузка…</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
      <div class="rounded-xl bg-gray-900 p-5">
        <p class="text-sm text-gray-400">Визитов сегодня</p>
        <p class="text-3xl font-bold text-white mt-1">{{ stats.today.visits }}</p>
      </div>
      <div class="rounded-xl bg-gray-900 p-5">
        <p class="text-sm text-gray-400">Уникальных посетителей сегодня</p>
        <p class="text-3xl font-bold text-white mt-1">{{ stats.today.uniques }}</p>
      </div>
    </div>

    <div class="rounded-xl bg-gray-900 p-5 mb-6">
      <h3 class="text-white font-semibold mb-4">Динамика</h3>
      <div class="h-64"><canvas ref="chartCanvas"></canvas></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-xl bg-gray-900 p-5">
        <h3 class="text-white font-semibold mb-4">Откуда приходят</h3>
        <p v-if="!stats.sources.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.sources" :key="row.source" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300">{{ SOURCE_NAMES[row.source] || row.source }}</span>
          <span class="text-gray-500">{{ row.count }} · {{ percent(stats.sources, row.count) }}%</span>
        </div>
      </div>
      <div class="rounded-xl bg-gray-900 p-5">
        <h3 class="text-white font-semibold mb-4">Устройства</h3>
        <p v-if="!stats.devices.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.devices" :key="row.device" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300">{{ DEVICE_NAMES[row.device] || row.device }}</span>
          <span class="text-gray-500">{{ row.count }} · {{ percent(stats.devices, row.count) }}%</span>
        </div>
      </div>

      <div class="rounded-xl bg-gray-900 p-5 md:col-span-2">
        <h3 class="text-white font-semibold mb-1">Топ сайтов</h3>
        <p class="text-xs text-gray-500 mb-4">Откуда именно переходили. Прямых заходов тут нет — у них источника нет.</p>
        <p v-if="!stats.top_hosts.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.top_hosts" :key="row.referer_host" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300 truncate">{{ row.referer_host }}</span>
          <span class="text-gray-500 shrink-0 ml-4">{{ row.count }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Подключить вкладку в ProfileView.vue**

Четыре точки, все по образцу существующих вкладок.

Импорт, после `import AdminDashboard from './admin/AdminDashboard.vue'`:

```js
import AdminAnalytics from './admin/AdminAnalytics.vue'
```

`validTabs` — добавить `'analytics'` после `'dashboard'`:

```js
const validTabs = ['dashboard', 'analytics', 'services', 'portfolio', 'orders', 'clients', 'feedbacks', 'general', 'settings']
```

`adminTabs` — вторым пунктом, после «Дашборд»:

```js
  { id: 'analytics', name: 'Аналитика', icon: '📈' },
```

В `<template>`, после строки с `AdminDashboard`:

```vue
        <div v-if="activeAdminTab === 'analytics'"><AdminAnalytics /></div>
```

- [ ] **Step 3: Проверить руками**

Открыть `http://localhost:5173/login`, войти как `admin` / `admin123`. Вкладка «Аналитика» в левом меню.

Проверить:
- плитки показывают числа (не `undefined`), визиты ≥ уников
- график рисует ровно 7 точек; переключение на «30 дней» перерисовывает в 30
- разделы «Откуда приходят» и «Устройства» заполнены, проценты в сумме дают ~100
- «Топ сайтов» показывает хосты (`yandex.ru`), прямых заходов среди них нет
- на пустой таблице (`DELETE FROM visits;`) — «Пока нет данных», без падений в консоли
- окно браузера в 375px: плитки в одну колонку, горизонтального скролла нет

- [ ] **Step 4: Прогнать всё**

```bash
npm test && npm run test:unit
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/views/admin/AdminAnalytics.vue src/views/ProfileView.vue
git commit -m "feat: вкладка «Аналитика» в админке"
```

---

## Деплой на прод

Не шаг плана — памятка на момент выката. Ничего из этого не делается автоматически.

1. **`VISIT_SALT` в прод-`.env`**: `openssl rand -hex 32`. Без неё beacon отдаёт 500 и статистики нет. Менять потом нельзя — разорвёт историю уников.
2. **Таблица `visits`**: `init.sql` на живой БД не отрабатывает, применить руками:
   `docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB' < docker/postgres/init.sql`
   (файл идемпотентен: `IF NOT EXISTS` + `ON CONFLICT`).
3. **`docker compose down && docker compose up -d`** — иначе новый `default.conf` и новая привязка порта не подхватятся.
4. **Пересобрать фронт**: `npm run build:prerender`. `VITE_API_URL` вшивается в бандл на сборке.
5. **Проверить после выката**: `curl -sI https://<домен>` → 200; зайти на сайт, в Network увидеть `POST /api/visit` → 204; в админке на вкладке «Аналитика» увидеть свой визит.
6. **Хостовой nginx не трогаем** — он уже шлёт `X-Real-IP` (`docs/nginx-host.conf:41`, `:50`). Если конфиг на сервере разошёлся с этим файлом, сверить до выката: без `proxy_set_header X-Real-IP $remote_addr` уники не заработают, а `X-Real-IP` станет подделываемым.
