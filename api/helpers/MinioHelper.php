<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioHelper {
    private static ?S3Client $client = null;

    // ── Внутренний клиент (соединение к MinIO внутри Docker-сети) ────────────
    private static function client(): S3Client {
        if (self::$client === null) {
            $endpoint = sprintf(
                'http://%s:%s',
                getenv('MINIO_ENDPOINT') ?: 'localhost',
                getenv('MINIO_PORT')     ?: '9000'
            );
            self::$client = new S3Client([
                'version'                 => 'latest',
                'region'                  => 'us-east-1',
                'endpoint'                => $endpoint,
                'use_path_style_endpoint' => true,
                'credentials'             => [
                    'key'    => getenv('MINIO_ACCESS_KEY') ?: '',
                    'secret' => getenv('MINIO_SECRET_KEY') ?: '',
                ],
            ]);
        }
        return self::$client;
    }

    // ── Загрузить файл из временного пути ─────────────────────────────────────
    // Возвращает публичный URL файла (доступен из браузера).
    public static function upload(string $bucket, string $key, string $tmpPath, string $mimeType): string {
        self::client()->putObject([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'SourceFile'  => $tmpPath,
            'ContentType' => $mimeType,
        ]);
        return self::publicUrl($bucket, $key);
    }

    // ── Удалить объект ────────────────────────────────────────────────────────
    public static function delete(string $bucket, string $key): void {
        self::client()->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
    }

    // ── Публичный URL для браузера (MINIO_PUBLIC_URL, не внутренний хост) ─────
    public static function publicUrl(string $bucket, string $key): string {
        $base = rtrim(getenv('MINIO_PUBLIC_URL') ?: 'http://localhost:9000', '/');
        return "{$base}/{$bucket}/{$key}";
    }

    // ── Разобрать MinIO URL обратно в bucket + key ────────────────────────────
    // Возвращает ['bucket' => '...', 'key' => '...'] или null если URL не от MinIO.
    public static function parseUrl(string $url): ?array {
        $base = rtrim(getenv('MINIO_PUBLIC_URL') ?: 'http://localhost:9000', '/') . '/';
        if (!str_starts_with($url, $base)) {
            return null;
        }
        $path   = substr($url, strlen($base));
        $slash  = strpos($path, '/');
        if ($slash === false) {
            return null;
        }
        return [
            'bucket' => substr($path, 0, $slash),
            'key'    => substr($path, $slash + 1),
        ];
    }

    // ── Генерировать уникальный ключ объекта ──────────────────────────────────
    public static function generateKey(string $prefix, string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return rtrim($prefix, '/') . '/' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    }
}
