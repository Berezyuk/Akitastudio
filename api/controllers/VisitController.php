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
