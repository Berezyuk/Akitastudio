<?php

class FfmpegHelper {
    // Перекодирует видео в H.264 High без звука, короткая сторона до 720 — под карточку 320 px на мобильном канале.
    // Возвращает путь к временному .mp4 файлу — caller обязан удалить его после загрузки.
    // Возвращает null если FFmpeg недоступен или входной файл не является видео.
    public static function transcodeToH264(string $inputPath, string $mimeType): ?string {
        if (!str_starts_with($mimeType, 'video/')) {
            return null;
        }

        exec('which ffmpeg 2>/dev/null', $out, $code);
        if ($code !== 0) {
            error_log('FfmpegHelper: ffmpeg not found in PATH');
            return null;
        }

        $outputPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.mp4';

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

        exec($cmd, $cmdOut, $exitCode);

        if ($exitCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            error_log('FfmpegHelper: transcoding failed (exit ' . $exitCode . ') for ' . $inputPath);
            if (file_exists($outputPath)) unlink($outputPath);
            return null;
        }

        return $outputPath;
    }
}
