<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioHelper {
    private static ?S3Client $client = null;
    private static ?S3Client $signingClient = null;
    private static array $publicPolicySet = [];

    // Бакеты с публичным чтением: их содержимое и так показывается всем
    // на сайте. order-photos сюда НЕ входит — это фото чужих машин,
    // они отдаются только по временной подписанной ссылке.
    private const PUBLIC_BUCKETS = ['portfolio', 'documents'];

    private static function credentials(): array {
        return [
            'key'    => getenv('MINIO_ACCESS_KEY') ?: getenv('MINIO_ROOT_USER') ?: '',
            'secret' => getenv('MINIO_SECRET_KEY') ?: getenv('MINIO_ROOT_PASSWORD') ?: '',
        ];
    }

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
                'credentials'             => self::credentials(),
                // Без таймаута зависший MinIO вешает запрос (и FPM-воркер)
                // навсегда. connect — MinIO в той же Docker-сети, отвечает
                // мгновенно; timeout щедрый под заливку крупного видео (до 200 МБ).
                'http'                    => ['connect_timeout' => 3, 'timeout' => 60],
                'retries'                 => 1,
            ]);
        }
        return self::$client;
    }

    // ── Клиент для подписи ссылок ────────────────────────────────────────────
    // Подпись SigV4 включает хост, поэтому ссылку нужно подписывать сразу на
    // публичный адрес (MINIO_PUBLIC_URL). Ссылка, подписанная на внутренний
    // http://minio:9000, из браузера недоступна, а подменить хост нельзя —
    // подпись перестанет сходиться.
    private static function signingClient(): S3Client {
        if (self::$signingClient === null) {
            self::$signingClient = new S3Client([
                'version'                 => 'latest',
                'region'                  => 'us-east-1',
                'endpoint'                => rtrim(getenv('MINIO_PUBLIC_URL') ?: 'http://localhost:9000', '/'),
                'use_path_style_endpoint' => true,
                'credentials'             => self::credentials(),
            ]);
        }
        return self::$signingClient;
    }

    // ── Временная подписанная ссылка на приватный объект ─────────────────────
    public static function presignedUrl(string $bucket, string $key, string $expires = '+30 minutes'): string {
        $cmd = self::signingClient()->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
        return (string)self::signingClient()->createPresignedRequest($cmd, $expires)->getUri();
    }

    // ── Ссылка для показа в браузере ─────────────────────────────────────────
    // Публичные бакеты — прямой URL, приватные — подписанная временная ссылка.
    public static function viewUrl(string $bucket, string $key): string {
        return in_array($bucket, self::PUBLIC_BUCKETS, true)
            ? self::publicUrl($bucket, $key)
            : self::presignedUrl($bucket, $key);
    }

    // Пересобрать ссылку из сохранённого в БД URL (там лежит прямой публичный URL).
    // Для приватного бакета вернёт свежую подписанную ссылку.
    public static function refreshUrl(?string $storedUrl): ?string {
        if (!$storedUrl) return $storedUrl;
        $parsed = self::parseUrl($storedUrl);
        if (!$parsed) return $storedUrl;   // не из MinIO — отдаём как есть
        return self::viewUrl($parsed['bucket'], $parsed['key']);
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
    // Возвращает URL файла. В БД сохраняется прямой (непубличный для приватных
    // бакетов) URL — он служит адресом объекта; на чтении из него собирается
    // подписанная ссылка через refreshUrl().
    public static function upload(string $bucket, string $key, string $tmpPath, string $mimeType): string {
        // Публичную политику вешаем только на публичные бакеты. Раньше это
        // делалось безусловно, из-за чего order-photos с фото чужих машин
        // становился доступен анониму по прямой ссылке.
        if (in_array($bucket, self::PUBLIC_BUCKETS, true)) {
            self::ensurePublicRead($bucket);
        } else {
            self::ensureBucketExists($bucket);
        }
        self::client()->putObject([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'SourceFile'  => $tmpPath,
            'ContentType' => $mimeType,
        ]);
        return self::publicUrl($bucket, $key);
    }

    // ── Проверить существование объекта ───────────────────────────────────────
    // false — только для честного "объекта нет" (404/NoSuchKey). Всё остальное
    // (таймаут, 500, 403) пробрасываем: вызывающий код (скрипт перекодировки)
    // использует exists() как гвард "не перезаписывать существующий бэкап", и
    // транзиентная ошибка headObject, молча превращённая в false, читалась бы
    // как "бэкапа нет" — и он был бы затёрт поверх реального.
    public static function exists(string $bucket, string $key): bool {
        try {
            self::client()->headObject(['Bucket' => $bucket, 'Key' => $key]);
            return true;
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404 || str_contains($e->getAwsErrorCode() ?? '', 'NotFound')) {
                return false;
            }
            throw $e;
        }
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
