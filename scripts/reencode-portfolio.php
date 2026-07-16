<?php
// Разовый скрипт: перекодировать уже загруженные видео портфолио под мобильный
// канал. Отрабатывает один раз на проде, после чего удаляется. Вся конструкция
// (бэкап-бакет, гварды, перезапись существующего ключа) рассчитана именно на
// разовый прогон — если пережимка когда-нибудь станет регулярной, схему нужно
// переписать на "новый ключ + переключение video_url", а не латать гвардами.
//
// Зачем: до фикса FfmpegHelper не масштабировал видео вовсе, и в бакете лежат
// исходники с телефона — 1080x1920 на 4.7-7.2 Мбит/с, по 12-22 МБ. Новые
// загрузки уже ужимаются, а эти видео останутся тяжёлыми навсегда.
//
// Запуск (только внутри php-контейнера — там ffmpeg и сеть до MinIO):
// require_once ниже резолвится через __DIR__, так что копия скрипта должна
// лежать рядом со /var/www/api, а не в /tmp — иначе '../api/...' ведёт
// в несуществующий /api и require валится с "Failed opening required".
//   docker compose exec -T php mkdir -p /var/www/scripts
//   docker compose cp scripts/reencode-portfolio.php php:/var/www/scripts/reencode.php
//   docker compose exec -T php php /var/www/scripts/reencode.php --dry-run
//   docker compose exec -T php php /var/www/scripts/reencode.php
//   docker compose exec -T php rm -rf /var/www/scripts
//
// Идемпотентен: уже обработанное пропускает. Это не удобство, а требование —
// без пропуска второй прогон пережмёт пережатое и посадит качество ни за что.

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/MinioHelper.php';
require_once __DIR__ . '/../api/helpers/FfmpegHelper.php';

const BACKUP_BUCKET = 'portfolio-originals';
const SKIP_MAX_WIDTH   = 720;
const SKIP_MAX_BITRATE = 2_200_000;   // выход даёт ~1.5 Мбит/с при потолке 2М

$dryRun = in_array('--dry-run', $argv, true);

function probe(string $path): ?array {
    $cmd = sprintf(
        'ffprobe -v error -select_streams v:0 -show_entries stream=width '
        . '-show_entries format=bit_rate -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
        escapeshellarg($path)
    );
    exec($cmd, $out, $code);
    if ($code !== 0 || count($out) < 2) return null;
    $bitrateRaw = trim($out[1]);
    // ffprobe печатает "N/A" вместо битрейта, когда контейнер не хранит
    // общую длительность (типично для .webm). (int)"N/A" молча даёт 0 —
    // это читалось бы как "битрейт отличный", и тяжёлое видео пропускалось
    // бы необработанным. null — отдельный сигнал "неизвестно", не "ноль".
    return [
        'width'   => (int)$out[0],
        'bitrate' => is_numeric($bitrateRaw) ? (int)$bitrateRaw : null,
    ];
}

$db = (new Database())->getConnection();
$rows = $db->query("SELECT id, video_url FROM portfolio WHERE video_url IS NOT NULL AND video_url <> ''")
           ->fetchAll(PDO::FETCH_ASSOC);

echo count($rows) . " записей с медиа\n";
if ($dryRun) echo "--- РЕЖИМ ПРОСМОТРА: ничего не меняется ---\n";

$totalBefore = 0;
$totalAfter  = 0;
$skipped     = 0;
$backedUp    = 0;

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
    // Скрипт правит только portfolio: order-photos и documents лежат в
    // MinIO тоже, но их трогать нельзя. video_url сегодня всегда указывает
    // на portfolio, но это не проверенный контракт — одна строка защищает
    // необратимую операцию от чужого бакета, если он туда когда-нибудь попадёт.
    if ($parsed['bucket'] !== 'portfolio') {
        echo "  ПРОПУСК #{$row['id']}: чужой бакет — {$parsed['bucket']}\n";
        continue;
    }

    // tempnam() сам создаёт файл по возвращённому пути; раньше сюда
    // дописывалось ".mp4", и удалялся потом только путь с суффиксом —
    // сам файл от tempnam() оставался на диске мусором на каждый прогон.
    // Ни ffprobe, ни ffmpeg расширение не требуют.
    $orig = tempnam(sys_get_temp_dir(), 'orig_');
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
    // MinIO всегда отдаёт Content-Length. При обрыве соединения
    // file_get_contents() может вернуть не false, а честные усечённые
    // байты — ffprobe на таком файле часто всё равно даёт exit=0
    // (moov благодаря +faststart цел, врёт только длительность), и
    // усечённый файл молча проходит как "оригинал" в бэкап, а обрезанное
    // видео — поверх боевого объекта. Единственная копия в хранилище —
    // второго шанса нет, поэтому сверяем длину и не трогаем объект при
    // расхождении. Пропускаем только эту строку, а не весь скрипт: обрыв
    // сети — как правило разовая помеха, соседние записи от неё не зависят,
    // а прервать пакет из-за одного blip — тоже потерять доведённую до
    // конца работу без причины. Расхождение видно оператору по строке
    // ОШИБКА и не даёт скрипту тихо считать файл обработанным.
    $declaredLen = null;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/^Content-Length:\s*(\d+)/i', $h, $m)) {
            $declaredLen = (int)$m[1];
        }
    }
    // Живой MinIO Content-Length шлёт всегда — его отсутствие само по себе
    // аномалия, а не норма. Для необратимой операции правильная сторона по
    // умолчанию — отказ: без заголовка сверить длину нечем, а значит нечем
    // и отличить целый файл от обрыва. Раньше при $declaredLen === null
    // проверка целиком отключалась, и усечённое тело ехало дальше как
    // полноценный оригинал — ровно тот случай, ради которого гвард писался.
    if ($declaredLen === null) {
        echo "  ОШИБКА #{$row['id']}: сервер не прислал Content-Length,"
            . " целостность скачивания непроверяема — объект не тронут\n";
        @unlink($orig);
        continue;
    }
    if (strlen($bytes) !== $declaredLen) {
        echo "  ОШИБКА #{$row['id']}: скачано " . strlen($bytes) . " из {$declaredLen} байт"
            . " (обрыв соединения) — объект не тронут\n";
        @unlink($orig);
        continue;
    }
    // Возврат file_put_contents() не был проверен: на полном диске он
    // может записать меньше байт, чем передано, — тот же усечённый
    // "оригинал" и та же необратимая потеря.
    $written = file_put_contents($orig, $bytes);
    if ($written === false || $written !== strlen($bytes)) {
        echo "  ОШИБКА #{$row['id']}: временный файл записан не полностью"
            . " (" . ($written === false ? 0 : $written) . " из " . strlen($bytes) . " байт) — объект не тронут\n";
        @unlink($orig);
        continue;
    }

    $sizeBefore = filesize($orig);
    $totalBefore += $sizeBefore;

    $info = probe($orig);
    if (!$info) {
        echo "  ОШИБКА #{$row['id']}: ffprobe не разобрал {$parsed['key']}\n";
        @unlink($orig);
        // Файл дошёл до probe, но разобран не был — не обработан, но вес
        // уже учтён в totalBefore. Добавляем в totalAfter без изменений
        // для честности итоговой строки.
        $totalAfter += $sizeBefore;
        continue;
    }

    $bitrateKnown = $info['bitrate'] !== null;
    $bitrateLabel = $bitrateKnown ? sprintf('%.1f Мбит/с', $info['bitrate'] / 1e6) : 'битрейт N/A';
    // (int)"N/A" молча даёт 0, а 0 <= SKIP_MAX_BITRATE всегда истинно — это и
    // есть баг: условие пропуска фактически решалось по одной ширине, выдавая
    // 0.0 Мбит/с для видео с любым реальным битрейтом. Ширина одна не
    // доказывает, что видео уже прошло наш транскодер (тот всегда даёт
    // width<=720 И bitrate<=maxrate вместе) — узкий .webm с неизвестным
    // битрейтом может быть каким угодно тяжёлым. Раз битрейт неизвестен —
    // не пропускаем: пусть обработается. Идемпотентность не страдает — на
    // выходе transcodeToH264() всегда честный mp4 с валидной длительностью,
    // второй прогон уже увидит настоящий битрейт, а не N/A.
    $skip = $bitrateKnown
        && $info['width'] <= SKIP_MAX_WIDTH
        && $info['bitrate'] <= SKIP_MAX_BITRATE;

    if ($skip) {
        printf("  пропуск  #%-3d %s — уже %dpx / %s\n",
            $row['id'], $parsed['key'], $info['width'], $bitrateLabel);
        $totalAfter += $sizeBefore;
        $skipped++;
        @unlink($orig);
        continue;
    }

    printf("  обработка #%-3d %s — %dpx / %s / %.1f МБ\n",
        $row['id'], $parsed['key'], $info['width'], $bitrateLabel, $sizeBefore / 1048576);

    if ($dryRun) {
        // Ничего не меняем, но пишем в totalAfter тот же размер — иначе
        // итоговая строка врёт про сжатие, которого в dry-run не было.
        $totalAfter += $sizeBefore;
        @unlink($orig);
        continue;
    }

    $out = null;
    try {
        // Бэкап ДО перекодировки: в бакете единственная копия, исходника
        // загрузки не существует. Без этого шага откатиться будет некуда.
        // Если бэкап уже есть — не перезаписываем: это означает, что
        // предыдущий прогон уже сохранил настоящий оригинал; затирать его
        // текущим скачанным файлом (который мог быть закачан заново, уже
        // пережатым, либо после частично упавшего прошлого прогона) —
        // верный способ похоронить единственную копию исходника.
        if (!MinioHelper::exists(BACKUP_BUCKET, $parsed['key'])) {
            MinioHelper::upload(BACKUP_BUCKET, $parsed['key'], $orig, 'video/mp4');
        } else {
            echo "    бэкап уже существует, не перезаписываю\n";
        }

        $out = FfmpegHelper::transcodeToH264($orig, 'video/mp4');
        if (!$out) {
            echo "    ОШИБКА: перекодирование не удалось, объект не тронут\n";
            // Файл дошёл до транскода, но перекодирован не был — не обработан,
            // добавляем в totalAfter без изменений для честности итоговой строки.
            $totalAfter += $sizeBefore;
            continue;
        }

        $sizeAfter = filesize($out);
        // Тот же ключ: video_url в БД остаётся валидным, миграции не нужно.
        MinioHelper::upload($parsed['bucket'], $parsed['key'], $out, 'video/mp4');
        $totalAfter += $sizeAfter;
        $backedUp++;

        printf("    готово: %.1f МБ -> %.1f МБ\n", $sizeBefore / 1048576, $sizeAfter / 1048576);
    } catch (Exception $e) {
        // Любое исключение из MinioHelper (например, exists() теперь
        // пробрасывает не-404 ошибки headObject) или из AWS SDK — строка
        // ОШИБКА и переход к следующей записи, а не фатал со стеной
        // Guzzle/AWS-трейса. Ловим здесь, а не раньше: если exists() или
        // upload() в BACKUP_BUCKET бросили исключение ДО загрузки в
        // portfolio.$parsed['bucket'] (строка 219), витрина не тронута —
        // catch не должен и не пытается "продолжить" загрузку после
        // сбоя бэкапа, он просто пропускает запись целиком.
        echo "    ОШИБКА #{$row['id']}: {$e->getMessage()} — объект не тронут\n";
        // Файл не был обработан из-за исключения, добавляем в totalAfter
        // без изменений для честности итоговой строки.
        $totalAfter += $sizeBefore;
    } finally {
        // Если upload() в бэкап-бакет или в portfolio бросит исключение —
        // временные файлы всё равно не должны оставаться на диске.
        @unlink($orig);
        if ($out !== null) @unlink($out);
    }
}

printf("\nИтого: %.1f МБ -> %.1f МБ (пропущено уже готовых: %d, обработано: %d)\n",
    $totalBefore / 1048576, $totalAfter / 1048576, $skipped, $backedUp);
// Строка про сохранённые оригиналы — только если хоть один бэкап реально
// подтверждён (создан в этом прогоне или уже был цел от предыдущего) И
// запись дошла до перезаписи витрины. $backedUp считает именно такие
// случаи; при полном отказе (обе строки ОШИБКА, как в прогоне ревьюера)
// он остаётся нулевым, и врать тут больше нечему.
if (!$dryRun && $backedUp > 0) echo "Оригиналы сохранены в бакет " . BACKUP_BUCKET . "\n";
