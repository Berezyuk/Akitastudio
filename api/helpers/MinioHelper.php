<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioHelper {
    private static ?S3Client $client = null;
    private static array $publicPolicySet = [];

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
                    'key'    => getenv('MINIO_ACCESS_KEY') ?: getenv('MINIO_ROOT_USER') ?: '',
                    'secret' => getenv('MINIO_SECRET_KEY') ?: getenv('MINIO_ROOT_PASSWORD') ?: '',
                ],
            ]);
        }
        return self::$client;
    }

    // ── Создать бакет если не существует ─────────────────────────────────────
    private static function ensureBucketExists(string $bucket): void {
        try {
            self::client()->headBucket(['Bucket' => $bucket]);
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404 || str_contains($e->getAwsErrorCode() ?? '', 'NoSuchBucket')) {
                self::client()->createBucket(['Bucket' => $bucket]);
            }
        }
    }

    // ── Установить политику публичного чтения на бакет (идемпотентно) ─────────
    // Создаёт бакет автоматически если он не существует.
    public static function ensurePublicRead(string $bucket): void {
        if (isset(self::$publicPolicySet[$bucket])) return;
        try {
            self::ensureBucketExists($bucket);
            $policy = json_encode([
                'Version'   => '2012-10-17',
                'Statement' => [[
                    'Effect'    => 'Allow',
                    'Principal' => ['AWS' => ['*']],
                    'Action'    => ['s3:GetObject'],
                    'Resource'  => ["arn:aws:s3:::{$bucket}/*"],
                ]],
            ]);
            self::client()->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => $policy,
            ]);
            self::$publicPolicySet[$bucket] = true;
        } catch (Exception $e) {
            error_log("MinioHelper::ensurePublicRead({$bucket}): " . $e->getMessage());
        }
    }

    // ── Загрузить файл из временного пути ─────────────────────────────────────
    // Возвращает публичный URL файла (доступен из браузера).
    public static function upload(string $bucket, string $key, string $tmpPath, string $mimeType): string {
        self::ensurePublicRead($bucket);
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
