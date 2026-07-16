<?php
// Разовый скрипт: перекодировать уже загруженные видео портфолио под мобильный
// канал. В рантайме не участвует.
//
// Зачем: до фикса FfmpegHelper не масштабировал видео вовсе, и в бакете лежат
// исходники с телефона — 1080x1920 на 4.7-7.2 Мбит/с, по 12-22 МБ. Новые
// загрузки уже ужимаются, а эти видео останутся тяжёлыми навсегда.
//
// Запуск (только внутри php-контейнера — там ffmpeg и сеть до MinIO):
//   docker compose cp scripts/reencode-portfolio.php php:/tmp/reencode.php
//   docker compose exec -T php php /tmp/reencode.php --dry-run
//   docker compose exec -T php php /tmp/reencode.php
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
