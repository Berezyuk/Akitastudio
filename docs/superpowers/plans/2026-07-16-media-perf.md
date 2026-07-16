# Ускорение медиа на мобильных — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ужать видео портфолио с 12–22 МБ до ~4.5 МБ, чтобы сайт перестал ползти на мобильном канале.

**Architecture:** `FfmpegHelper` получает `scale` до 720 по короткой стороне, профиль `high`, потолок битрейта и `-an` — это чинит новые загрузки. Разовый скрипт переиспользует тот же хелпер и перекодирует 15 уже лежащих видео на месте, сохранив оригиналы в приватный бакет-бэкап. Плюс `Cache-Control` на публичную витрину и две мины с `autoplay`, которые заставляют браузер качать видео целиком в обход `preload="none"`.

**Tech Stack:** PHP 8.2 (ffmpeg через `exec`), MinIO (AWS SDK), Vue 3, тесты — node:test по живому стенду.

**Спека:** `docs/superpowers/specs/2026-07-16-media-perf-design.md` — читать до начала, там все замеры и обоснования.

## Global Constraints

- **Все числа в этом плане получены замером на живом файле.** Не «улучшай» параметры на глаз: `preset slow` уже отвергнут (1.4% веса за +20% времени), 1080p уже отвергнут (+60% веса ровно там, где болит).
- **`opcache.revalidate_freq=2`** — между правкой PHP и прогоном тестов ждать >2 секунд, иначе тест увидит старый байткод и соврёт зелёным.
- **Тесты только на dev-стенде.** `npm test` — node:test по живому стенду; пишут реальные объекты и не убирают за собой.
- **Тест без обратной проверки не считается.** Каждый обязан упасть на откаченном фиксе — проверять руками.
- **`node_modules` на хосте root-owned.** Всё node-шное, кроме `npm test`, гонять внутри контейнера: `docker compose exec -T frontend sh -c 'cd /app && npx vitest run'`.
- **Приватные бакеты не трогать.** `MinioHelper::PUBLIC_BUCKETS = ['portfolio', 'documents']`. `order-photos` — фото чужих машин, только по подписанной ссылке.
- **Комментарии по-русски**, объясняют «почему», а не «что».

---

## Порядок задач и почему он такой

Task 1 первая: скрипт из Task 2 переиспользует `FfmpegHelper`, и пока в хелпере старые параметры, перекодировать нечем.

Порядок **выката** отличается от порядка коммитов: `Cache-Control` (Task 3) вешается на прод **только после** того, как отработал скрипт перекодировки. Наоборот — жирный файл залипнет у вернувшихся клиентов на 30 дней. В репозитории Task 3 — правка эталонного конфига, она безопасна в любой момент; опасен именно порядок действий на сервере, и он расписан в разделе «Деплой».

---

### Task 1: Параметры кодирования

**Files:**
- Modify: `api/helpers/FfmpegHelper.php` (строка команды + комментарий класса)
- Create: `tests/fixtures/portrait-1080x1920.mp4` (фикстура, ~2 с, со звуком)
- Test: `tests/api.test.js` (новый describe в конец файла)

**Interfaces:**
- Consumes: ничего
- Produces: `FfmpegHelper::transcodeToH264(string $inputPath, string $mimeType): ?string` — сигнатура не меняется, меняется только команда внутри. Выход: короткая сторона ≤720, H.264 High, без звука.

- [ ] **Step 1: Сгенерировать фикстуру**

Фикстура обязана быть 1080×1920 **и со звуковой дорожкой** — иначе тест на `-an` ничего не проверит.

```bash
mkdir -p tests/fixtures
docker compose exec -T php sh -c 'ffmpeg -f lavfi -i testsrc=size=1080x1920:duration=2:rate=30 -f lavfi -i sine=frequency=440:duration=2 -c:v libx264 -profile:v baseline -pix_fmt yuv420p -c:a aac -shortest -y /tmp/fx.mp4' 2>/dev/null
docker compose cp php:/tmp/fx.mp4 tests/fixtures/portrait-1080x1920.mp4
docker compose exec -T php rm -f /tmp/fx.mp4
```

Проверить, что получилось то, что нужно:

```bash
docker compose cp tests/fixtures/portrait-1080x1920.mp4 php:/tmp/check.mp4
docker compose exec -T php ffprobe -v error -show_entries stream=codec_type,width,height -of csv=p=0 /tmp/check.mp4
docker compose exec -T php rm -f /tmp/check.mp4
ls -la tests/fixtures/portrait-1080x1920.mp4
```
Expected: две строки — `video,1080,1920` и `audio`. Размер файла — сотни килобайт, не мегабайты.

- [ ] **Step 2: Написать падающий тест**

В конец `tests/api.test.js`. Импорты `execSync` и `readFileSync` добавить к существующим в шапке файла (`readFileSync` там уже есть — проверить и не дублировать).

```js
import { execSync } from 'node:child_process'
```

```js
// Видео портфолио показываются в карточке шириной максимум 320 CSS-пикселей,
// без звука и без контролов. До этого фикса FfmpegHelper не масштабировал их
// вовсе: в бакет уезжало 1080x1920 на 4.7-7.2 Мбит/с, по 12-22 МБ за штуку —
// больше типичного мобильного канала. Тест ловит откат параметров обратно.
describe('загрузка видео: перекодирование под мобильный канал', () => {
  let cookie
  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  // ffprobe живёт в php-контейнере, на хосте его нет. Внутри контейнера
  // localhost:9000 — его собственный localhost, а не MinIO, поэтому ходим
  // по имени сервиса из compose-сети.
  const probe = (key, args) =>
    execSync(
      `docker compose exec -T php ffprobe -v error ${args} "http://minio:9000/${key}"`,
      { encoding: 'utf8' }
    ).trim()

  test('1080x1920 ужимается до ширины <=720 и лишается звука', async () => {
    const file = readFileSync(new URL('./fixtures/portrait-1080x1920.mp4', import.meta.url))
    const form = new FormData()
    form.append('media', new Blob([file], { type: 'video/mp4' }), 'portrait.mp4')

    const res = await fetch(API + '/admin/portfolio/upload', {
      method: 'POST',
      headers: { Cookie: cookie },
      body: form,
    })
    const data = await res.json()
    assert.ok(data.success, `загрузка не удалась: ${JSON.stringify(data)}`)

    const key = new URL(data.url).pathname.replace(/^\//, '')

    const width = probe(key, '-select_streams v:0 -show_entries stream=width -of csv=p=0')
    assert.ok(Number(width) <= 720, `ширина ${width} > 720 — scale не применился`)

    const audio = probe(key, '-select_streams a -show_entries stream=codec_type -of csv=p=0')
    assert.equal(audio, '', 'звуковая дорожка осталась — -an не применился')
  })
})
```

- [ ] **Step 3: Прогнать — убедиться, что падает**

Run: `npm test 2>&1 | grep -A5 "перекодирование под мобильный"`
Expected: FAIL — `ширина 1080 > 720 — scale не применился`

Если тест падает на самой загрузке (`загрузка не удалась`) — остановиться и разобраться, это другая проблема, не та, что мы чиним.

- [ ] **Step 4: Поменять команду**

`api/helpers/FfmpegHelper.php` — заменить блок `$cmd = sprintf(...)`:

```php
        // Видео показываются в карточке шириной максимум 320 CSS-пикселей
        // (.portfolio_card), без звука, без контролов и без полноэкранного
        // просмотра. Раньше тут не было ни scale, ни потолка битрейта, и в бакет
        // уезжал исходник с телефона: 1080x1920 на 7 Мбит/с, 23 МБ за 26 секунд —
        // больше, чем даёт мобильный канал, поэтому видео не успевало грузиться
        // быстрее, чем играет.
        //
        // scale='min(720,iw)':-2 — короткая сторона не больше 720; min запрещает
        //   апскейл мелких исходников, -2 держит высоту чётной (требование yuv420p).
        // high вместо baseline — возвращает CABAC и B-кадры, baseline жмёт заметно
        //   хуже и нужен был для телефонов десятилетней давности.
        // -crf 26 с -maxrate/-bufsize — capped CRF: качество с потолком веса.
        // -an — звука нет ни в одном потребителе: все теги <video> стоят muted.
        //   Понадобится звук — снимать осознанно, хелпер общий на портфолио,
        //   медиа категорий и видео «о нас».
        // -preset не задаём: замер дал medium 5 с / 4684 КБ против slow 6 с /
        //   4620 КБ — 1.4% веса за +20% времени.
        $cmd = sprintf(
            'ffmpeg -i %s -vf "scale=\'min(720,iw)\':-2" -c:v libx264 -profile:v high '
            . '-crf 26 -maxrate 2M -bufsize 4M -pix_fmt yuv420p -an '
            . '-movflags +faststart -y %s 2>/dev/null',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
```

И поправить комментарий класса (строка 4): `Перекодирует видео в H.264 Baseline + AAC (максимальная совместимость браузеров).` → `Перекодирует видео в H.264 High без звука, короткая сторона до 720 — под карточку 320 px на мобильном канале.`

- [ ] **Step 5: Прогнать тест**

```bash
sleep 3
npm test 2>&1 | grep -A5 "перекодирование под мобильный"
```
Expected: PASS

- [ ] **Step 6: Обратная проверка**

Временно убрать `-an` из команды, `sleep 3`, прогнать:
Expected: FAIL — `звуковая дорожка осталась`.

Затем вернуть `-an`, временно убрать `-vf "scale=..."`, `sleep 3`, прогнать:
Expected: FAIL — `ширина 1080 > 720`.

Вернуть всё, прогнать, убедиться в PASS. Тест без обратной проверки не считается.

- [ ] **Step 7: Прогнать весь набор**

Run: `npm test`
Expected: PASS целиком (загрузка видео ничего из существующего не задевает).

- [ ] **Step 8: Commit**

```bash
git add api/helpers/FfmpegHelper.php tests/api.test.js tests/fixtures/portrait-1080x1920.mp4
git commit -m "fix: видео ужимается до 720p без звука — 23 МБ превращались в 4.6 МБ"
```

---

### Task 2: Перекодировка 15 существующих видео

**Files:**
- Create: `scripts/reencode-portfolio.php`

**Interfaces:**
- Consumes: `FfmpegHelper::transcodeToH264()` из Task 1; `MinioHelper::parseUrl(string): ?array{bucket,key}`, `MinioHelper::upload(string $bucket, string $key, string $tmpPath, string $mimeType): string`, `MinioHelper::publicUrl(string $bucket, string $key): string`
- Produces: разовый скрипт, в рантайме не участвует

Скрипт **переиспользует `FfmpegHelper`**, а не копирует команду: иначе параметры разъедутся в первый же день.

Бакет `portfolio-originals` не входит в `MinioHelper::PUBLIC_BUCKETS`, поэтому `upload()` создаст его приватным — это то, что нужно для бэкапа.

- [ ] **Step 1: Написать скрипт**

Create `scripts/reencode-portfolio.php`:

```php
<?php
// Разовый скрипт: перекодировать уже загруженные видео портфолио под мобильный
// канал. В рантайме не участвует.
//
// Зачем: до фикса FfmpegHelper не масштабировал видео вовсе, и в бакете лежат
// исходники с телефона — 1080x1920 на 4.7-7.2 Мбит/с, по 12-22 МБ. Новые
// загрузки уже ужимаются, а эти 15 останутся тяжёлыми навсегда.
//
// Запуск (только внутри php-контейнера — там ffmpeg и сеть до MinIO):
//   docker compose exec -T php php /var/www/api/../scripts/reencode-portfolio.php --dry-run
//   docker compose exec -T php php /var/www/api/../scripts/reencode-portfolio.php
//
// Идемпотентен: уже обработанное пропускает. Это не удобство, а требование —
// без пропуска второй прогон пережмёт пережатое и посадит качество ни за что.

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/MinioHelper.php';
require_once __DIR__ . '/../api/helpers/FfmpegHelper.php';

const BACKUP_BUCKET = 'portfolio-originals';
const SKIP_MAX_WIDTH   = 720;
const SKIP_MAX_BITRATE = 2_200_000;   // выход даёт ~1.5 Мбит/с при потолке 2

$dryRun = in_array('--dry-run', $argv, true);

function probe(string $path): ?array {
    $cmd = sprintf(
        'ffprobe -v error -select_streams v:0 -show_entries stream=width '
        . '-show_entries format=bit_rate -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
        escapeshellarg($path)
    );
    exec($cmd, $out, $code);
    if ($code !== 0 || count($out) < 2) return null;
    return ['width' => (int)$out[0], 'bitrate' => (int)$out[1]];
}

$db = (new Database())->getConnection();
$rows = $db->query("SELECT id, video_url FROM portfolio WHERE video_url IS NOT NULL AND video_url <> ''")
           ->fetchAll(PDO::FETCH_ASSOC);

echo count($rows) . " записей с медиа\n";
if ($dryRun) echo "--- РЕЖИМ ПРОСМОТРА: ничего не меняется ---\n";

$totalBefore = 0;
$totalAfter  = 0;
$skipped     = 0;

foreach ($rows as $row) {
    $url    = $row['video_url'];
    $parsed = MinioHelper::parseUrl($url);

    if (!$parsed) {
        echo "  ПРОПУСК #{$row['id']}: URL не от MinIO — {$url}\n";
        continue;
    }
    if (!preg_match('/\.(mp4|webm|ogg)$/i', $parsed['key'])) {
        echo "  ПРОПУСК #{$row['id']}: не видео — {$parsed['key']}\n";
        continue;
    }

    $orig = tempnam(sys_get_temp_dir(), 'orig_') . '.mp4';
    // Ходим по внутреннему адресу: publicUrl() отдаёт боевой домен, из
    // контейнера он может быть недоступен.
    $internal = sprintf(
        'http://%s:%s/%s/%s',
        getenv('MINIO_ENDPOINT') ?: 'minio',
        getenv('MINIO_PORT') ?: '9000',
        $parsed['bucket'],
        $parsed['key']
    );
    $bytes = @file_get_contents($internal);
    if ($bytes === false) {
        echo "  ОШИБКА #{$row['id']}: не скачался — {$internal}\n";
        @unlink($orig);
        continue;
    }
    file_put_contents($orig, $bytes);

    $info = probe($orig);
    if (!$info) {
        echo "  ОШИБКА #{$row['id']}: ffprobe не разобрал {$parsed['key']}\n";
        @unlink($orig);
        continue;
    }

    $sizeBefore = filesize($orig);
    $totalBefore += $sizeBefore;

    if ($info['width'] <= SKIP_MAX_WIDTH && $info['bitrate'] <= SKIP_MAX_BITRATE) {
        printf("  пропуск  #%-3d %s — уже %dpx / %.1f Мбит/с\n",
            $row['id'], $parsed['key'], $info['width'], $info['bitrate'] / 1e6);
        $totalAfter += $sizeBefore;
        $skipped++;
        @unlink($orig);
        continue;
    }

    printf("  обработка #%-3d %s — %dpx / %.1f Мбит/с / %.1f МБ\n",
        $row['id'], $parsed['key'], $info['width'], $info['bitrate'] / 1e6, $sizeBefore / 1048576);

    if ($dryRun) {
        @unlink($orig);
        continue;
    }

    // Бэкап ДО перекодировки: в бакете единственная копия, исходника загрузки
    // не существует. Без этого шага откатиться будет некуда.
    MinioHelper::upload(BACKUP_BUCKET, $parsed['key'], $orig, 'video/mp4');

    $out = FfmpegHelper::transcodeToH264($orig, 'video/mp4');
    if (!$out) {
        echo "    ОШИБКА: перекодирование не удалось, объект не тронут\n";
        @unlink($orig);
        continue;
    }

    $sizeAfter = filesize($out);
    // Тот же ключ: video_url в БД остаётся валидным, миграции не нужно.
    MinioHelper::upload($parsed['bucket'], $parsed['key'], $out, 'video/mp4');
    $totalAfter += $sizeAfter;

    printf("    готово: %.1f МБ -> %.1f МБ\n", $sizeBefore / 1048576, $sizeAfter / 1048576);

    @unlink($orig);
    @unlink($out);
}

printf("\nИтого: %.1f МБ -> %.1f МБ (пропущено уже готовых: %d)\n",
    $totalBefore / 1048576, $totalAfter / 1048576, $skipped);
if (!$dryRun) echo "Оригиналы сохранены в бакет " . BACKUP_BUCKET . "\n";
```

- [ ] **Step 2: Прогнать в режиме просмотра**

```bash
docker compose exec -T php php /scripts-check 2>/dev/null || true
docker compose exec -T php sh -c 'cd /var/www && php ../scripts/reencode-portfolio.php --dry-run'
```

Если путь не сходится — найти, куда примонтирован репозиторий в php-контейнере (`docker compose config | grep -A3 'php:' | grep volumes -A3`), и запускать оттуда. `docker-compose.yml` монтирует `./api:/var/www/api`, то есть `scripts/` внутрь контейнера **не примонтирован** — это ожидаемо. Тогда копировать скрипт внутрь на время прогона:

```bash
docker compose exec -T php mkdir -p /var/www/scripts
docker compose cp scripts/reencode-portfolio.php php:/var/www/scripts/reencode.php
docker compose exec -T php php /var/www/scripts/reencode.php --dry-run
```

Expected: список записей с их текущими размерами; строка «РЕЖИМ ПРОСМОТРА»; ничего не изменилось.

- [ ] **Step 3: Прогнать по-настоящему**

```bash
docker compose exec -T php php /var/www/scripts/reencode.php
```
Expected: для каждого видео строка `готово: X МБ -> Y МБ`, итог вида `~230 МБ -> ~45 МБ`.

⚠️ На дев-стенде в бакете может не быть тех же 15 видео, что на проде (стенд поднимался с нуля). Если бакет пуст — залить пару видео через админку и проверить на них; в отчёте это отметить.

- [ ] **Step 4: Проверить идемпотентность**

```bash
docker compose exec -T php php /var/www/scripts/reencode.php
```
Expected: **каждая** строка — `пропуск ... уже 720px / 1.5 Мбит/с`, ни одной `обработка`. Итог: «пропущено уже готовых: N» равно числу видео.

Это главная проверка этой задачи: без неё второй прогон пережмёт всё по второму разу.

- [ ] **Step 5: Проверить, что бэкап реально лежит и сайт не сломался**

Ключ берём из БД, а не глазами из вывода:

```bash
KEY=$(docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -t -A -c "SELECT video_url FROM portfolio WHERE video_url LIKE '"'"'%.mp4'"'"' LIMIT 1;"' | tr -d '\r' | sed 's|.*/portfolio/||')
echo "ключ: $KEY"
echo -n "витрина       : "; curl -s -o /dev/null -w "%{http_code}, %{size_download} байт\n" "http://localhost:9000/portfolio/$KEY"
echo -n "бэкап анониму : "; curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:9000/portfolio-originals/$KEY"
```

Expected: витрина — `200` и новый (меньший) размер; бэкап анониму — `403`.

Если бэкап отдаёт `200` — остановиться, это находка: приватный бакет стал публичным, а там лежат оригиналы. Проверить, не попал ли `portfolio-originals` в `MinioHelper::PUBLIC_BUCKETS`.

- [ ] **Step 6: Убрать временную копию скрипта**

```bash
docker compose exec -T php rm -rf /var/www/scripts
```

- [ ] **Step 7: Commit**

```bash
git add scripts/reencode-portfolio.php
git commit -m "chore: разовый скрипт перекодировки уже загруженных видео портфолио"
```

---

### Task 3: Cache-Control на витрину

**Files:**
- Modify: `docs/nginx-host.conf` (эталонная копия конфига, живёт вне докера)

**Interfaces:**
- Consumes: ничего
- Produces: ничего в коде. Правка эталона + памятка на выкат.

На объектах MinIO нет `Cache-Control` вообще — только `ETag`/`Last-Modified`, поэтому браузер кеширует эвристически и передёргивает файлы на повторных заходах.

- [ ] **Step 1: Добавить location в эталонный конфиг**

`docs/nginx-host.conf`, в `server`-блок `media.akita-studio.ru`, **перед** существующим `location /`:

```nginx
    # Витрина портфолио: публичный бакет, ссылки не подписаны — кешировать можно.
    # 30 дней, как уже выбрано для медиа во frontend.conf: год + immutable не
    # прощает перекодировку на месте (жирный файл залипнет у клиента на год).
    #
    # ТОЛЬКО /portfolio/. Этот же домен отдаёт /order-photos/ — приватные фото
    # чужих машин по подписанным ссылкам. Cache-Control: public на них означает,
    # что их сможет держать любой общий прокси.
    location /portfolio/ {
        proxy_pass         http://127.0.0.1:9000;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_buffering    off;

        expires 30d;
        add_header Cache-Control "public";
        # add_header в location гасит родительские — nosniff проставляем заново,
        # иначе он пропадёт ровно с пользовательского контента.
        add_header X-Content-Type-Options "nosniff" always;
    }
```

`proxy_pass` без слеша на конце и неизменный `Host` — обязательны: подпись SigV4 считается по хосту и пути, слеш срежет префикс и приватные ссылки перестанут сходиться. Это уже задокументировано в шапке файла — не «упрощай».

- [ ] **Step 2: Проверить синтаксис**

Конфиг хостовой, в докере его nginx не читает. Синтаксис проверяем чужим nginx:

```bash
docker run --rm -v "$PWD/docs/nginx-host.conf:/etc/nginx/conf.d/host.conf:ro" nginx:1.27-alpine nginx -t 2>&1 | tail -3
```
Expected: `syntax is ok` / `test is successful`. Ошибки про недоступные сертификаты (`cannot load certificate`) — ожидаемы, это не синтаксис; если кроме них ничего нет, считать пройденным.

- [ ] **Step 3: Commit**

```bash
git add docs/nginx-host.conf
git commit -m "docs: Cache-Control на витрину портфолио в эталоне хостового nginx"
```

---

### Task 4: Мины с autoplay

**Files:**
- Modify: `src/views/HomeView.vue` (видео «о нас», ~строка 530)
- Modify: `src/components/ServiceCard.vue` (видео категории, ~строка 65)

**Interfaces:**
- Consumes: ничего
- Produces: ничего для других задач

`autoplay` заставляет браузер скачать файл целиком и молча игнорирует `preload="none"`. В проекте это уже задокументировано — `AboutView.vue:51`: «страница тянула ~35 МБ на телефоне». Там же лежит готовый паттерн: атрибут `data-lazy-video` + `querySelectorAll` + `IntersectionObserver`. Копируем его, четвёртый раз — осознанно (общий композабл решено вынести отдельным PR).

- [ ] **Step 1: Починить HomeView**

`src/views/HomeView.vue` — у видео «о нас» убрать `autoplay`, добавить `data-lazy-video`:

```vue
              <video
                :key="aboutVideoUrl"
                data-lazy-video
                muted
                loop
                playsinline
                preload="none"
                aria-hidden="true"
                class="w-full h-full object-cover"
              >
```

В `<script setup>`, рядом с существующим `portfolioObserver`, добавить отдельный наблюдатель. К `portfolioObserver` это видео не подключить: тот собирается из `portfolioItems`, а видео «о нас» к ним не относится.

```js
// Ленивая загрузка видео «о нас» — паттерн из AboutView.
// Раньше тут стоял autoplay вместе с preload="none": autoplay побеждает, и
// браузер качал файл сразу, на первом же экране.
let aboutVideoObserver = null

const setupAboutVideoObserver = () => {
  const videos = document.querySelectorAll('video[data-lazy-video]')
  if (!videos.length) return
  aboutVideoObserver?.disconnect()
  aboutVideoObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) entry.target.play().catch(() => {})
        else entry.target.pause()
      })
    },
    { threshold: 0.25 }
  )
  videos.forEach((v) => aboutVideoObserver.observe(v))
}
```

Вызвать `setupAboutVideoObserver()` в существующем `onMounted` этого файла и добавить `aboutVideoObserver?.disconnect()` в существующий `onUnmounted`. Найти их (`grep -n "onMounted\|onUnmounted" src/views/HomeView.vue`) и дописать в них, новых хуков не заводить.

- [ ] **Step 2: Починить ServiceCard**

`src/components/ServiceCard.vue` — у видео убрать `autoplay`, добавить `preload="none"` и `data-lazy-video`:

```vue
    <video v-if="isVideo" :src="imageUrl" data-lazy-video class="services_img" muted loop playsinline preload="none"></video>
```

В `<script setup>` (там уже импортированы `onMounted` и `onUnmounted`):

```js
// Ленивая загрузка видео категории — паттерн из AboutView.
// Раньше стоял autoplay без preload вовсе: каждая карточка с видео качала файл
// целиком сразу. Сейчас не стреляло только потому, что медиа категорий не залито
// ни одной — мина заряжалась в тот день, когда админ загрузит первое видео.
let videoObserver = null

onMounted(() => {
  const video = document.querySelector('video[data-lazy-video]')
  if (!video) return
  videoObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) entry.target.play().catch(() => {})
        else entry.target.pause()
      })
    },
    { threshold: 0.25 }
  )
  videoObserver.observe(video)
})

onUnmounted(() => videoObserver?.disconnect())
```

⚠️ `document.querySelector` внутри компонента, который рендерится списком, найдёт **чужое** видео. Проверить, как `ServiceCard` используется (`grep -rn "ServiceCard" src/views/`), и если он рендерится в `v-for` — брать элемент через `ref` компонента, а не глобальным селектором. Решение принять по факту и описать в отчёте.

- [ ] **Step 3: Проверить в браузере**

В контейнере `frontend` стоит chromium (`/usr/bin/chromium-browser`) и есть puppeteer. Изнутри контейнера `localhost:8000` — его собственный localhost, поэтому браузер запускать с `--host-resolver-rules=MAP localhost:8000 nginx:80`.

Скрипт положить во временный файл, скопировать в контейнер, прогнать, убрать за собой.

Проверить на `http://localhost:5173/`:
- при загрузке страницы запроса за видео «о нас» **нет** (смотреть `page.on('request')` по `.mp4`);
- после `page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))` и паузы — запрос появляется;
- ошибок в консоли нет (`page.on('pageerror')`).

Если медиа категорий на стенде нет — `ServiceCard` проверить нечем; отметить в отчёте честно, не выдумывать результат.

- [ ] **Step 4: Прогнать тесты**

```bash
npm test
docker compose exec -T frontend sh -c 'cd /app && npx vitest run'
```
Expected: оба набора зелёные. Вёрстку юнит-тестами не покрываем — соглашение проекта.

- [ ] **Step 5: Commit**

```bash
git add src/views/HomeView.vue src/components/ServiceCard.vue
git commit -m "fix: autoplay качал видео целиком в обход preload=none"
```

---

## Деплой на прод

Не шаг плана — памятка на момент выката. **Порядок обязателен.**

1. **Выкатить код** (Task 1, 4): `git pull`, `docker compose down && docker compose up -d`, `npm run build:prerender`.
2. **Перекодировать существующие** (Task 2) — до кеша.

   Копию скрипта класть **рядом с `/var/www/api`, а не в `/tmp`**: `require_once` внутри
   резолвится через `__DIR__`, и из `/tmp` путь `../api/...` ведёт в несуществующий `/api`
   — прогон падает с `Failed opening required`. Те же команды продублированы в шапке
   самого скрипта.

   ```bash
   docker compose exec -T php mkdir -p /var/www/scripts
   docker compose cp scripts/reencode-portfolio.php php:/var/www/scripts/reencode.php
   docker compose exec -T php php /var/www/scripts/reencode.php --dry-run   # посмотреть
   docker compose exec -T php php /var/www/scripts/reencode.php             # сделать
   docker compose exec -T php rm -rf /var/www/scripts
   ```
   Проверить глазами: сайт открывается, видео в портфолио играют.
3. **Только теперь — `Cache-Control`** (Task 3): перенести `location /portfolio/` из `docs/nginx-host.conf` в конфиг на сервере, `nginx -t`, `systemctl reload nginx`.
   Наоборот нельзя: повесив кеш до перекодировки, раздадим жирный файл с `max-age=30d` — вернувшиеся клиенты будут держать его месяц.
4. **Проверить результат:**
   ```bash
   curl -sI https://media.akita-studio.ru/portfolio/media/<ключ>.mp4 | grep -iE "content-length|cache-control"
   ```
   Ожидание: `Content-Length` в районе 4–5 МБ вместо 12–22, `Cache-Control: public` + `Expires`.
5. **Оригиналы** остаются в бакете `portfolio-originals`. Это страховка на случай «качество не устроило». Удалять — отдельным осознанным решением, не в этот выкат.
