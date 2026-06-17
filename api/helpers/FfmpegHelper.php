<?php

class FfmpegHelper {
    // Перекодирует видео в H.264 Baseline + AAC (максимальная совместимость браузеров).
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

        $cmd = sprintf(
            'ffmpeg -i %s -vcodec libx264 -profile:v baseline -level 3.1 -pix_fmt yuv420p -acodec aac -movflags +faststart -y %s 2>/dev/null',
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
