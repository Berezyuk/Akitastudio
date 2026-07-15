<?php
// api/controllers/VisitController.php — счётчик визитов для статистики в админке.
// Дизайн и обоснования: docs/superpowers/specs/2026-07-16-visits-analytics-design.md

require_once __DIR__ . '/../config/database.php';

class VisitController {
    // Потолок общий на IP (ip_hash, без UA) — не на пару (IP, UA): иначе он
    // обходится ротацией User-Agent (curl в цикле пишет неограниченно).
    // 300/час с запасом режет накрутку, но не рубит адрес с кучей живых людей
    // за одним IP (мобильные операторы, CGNAT).
    private const MAX_PER_HOUR = 300;

    public static function track() {
        $body = json_decode(file_get_contents('php://input'), true);
        $referrerRaw = is_array($body) ? ($body['referrer'] ?? '') : '';
        // Тело — от клиента, доверять типу нельзя: referrer может прийти
        // массивом/числом/null и уронить (string)-каст варнингом в лог.
        $referrer = is_string($referrerRaw) ? $referrerRaw : '';
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $conn = (new Database())->getConnection();
        $hashes = self::hashes($ua);

        // Ответ 204 и при отказе: накрутчику незачем знать, где потолок,
        // а клиенту эта информация не нужна — beacon ответ не читает.
        if (!self::withinLimit($conn, $hashes['ip'])) {
            http_response_code(204);
            return;
        }

        [$source, $refererHost] = self::classifySource($referrer);
        $stmt = $conn->prepare(
            'INSERT INTO visits (visitor_hash, ip_hash, source, referer_host, device) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$hashes['visitor'], $hashes['ip'], $source, $refererHost, self::classifyDevice($ua)]);

        http_response_code(204);
    }

    /** @return array{visitor: string, ip: string} */
    private static function hashes(string $ua): array {
        // X-Real-IP перезаписывает хостовой nginx (docs/nginx-host.conf), клиент
        // подделать не может: порт 8000 наружу закрыт, мимо прокси не пройти.
        $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $salt = getenv('VISIT_SALT');
        // Без соли хеш обращается перебором диапазона IP, а это персональные данные.
        // Строгая проверка: getenv() возвращает false, если переменной нет вообще,
        // а сама соль "0" — валидная строка и не должна считаться пустой.
        if ($salt === false || $salt === '') {
            throw new RuntimeException('VISIT_SALT не задан — статистика посещений отключена');
        }
        $date = date('Y-m-d');
        return [
            // Уники считаются по visitor_hash — UA в нём нужен: за одним IP
            // мобильного оператора (CGNAT) сотни разных абонентов.
            'visitor' => hash('sha256', $ip . $ua . $salt . $date),
            // Потолок антинакрутки — по ip_hash, UA сюда не входит специально.
            'ip' => hash('sha256', $ip . $salt . $date),
        ];
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
        // referer_host — VARCHAR(255); публичный эндпоинт обязан пережить любой
        // мусор от клиента, а не падать PDOException'ом на INSERT.
        if (strlen($host) > 255) {
            return ['other', null];
        }
        // Свой же сайт — это не источник перехода. Хост берём из CORS_ORIGIN:
        // переменная уже есть, новой сущности ради этого не заводим.
        $ownHost = strtolower((string)parse_url(getenv('CORS_ORIGIN') ?: '', PHP_URL_HOST));
        if ($ownHost !== '' && ($host === $ownHost || str_ends_with($host, '.' . $ownHost))) {
            return ['direct', null];
        }
        // Домен заякорен в конце хоста ($): иначе "yandex.ru.evil.com" или
        // "mail.mycompany.com" ложно засчитываются как переход с поисковика.
        if (preg_match('/(^|\.)(yandex|google|bing|mail|duckduckgo|rambler)\.[a-z]{2,3}(\.[a-z]{2})?$/', $host)) {
            return ['search', $host];
        }
        if (preg_match('/(^|\.)(vk|t|telegram|instagram|youtube|facebook|ok)\.[a-z]{2,3}(\.[a-z]{2})?$/', $host)) {
            return ['social', $host];
        }
        return ['other', $host];
    }

    private static function withinLimit(PDO $conn, string $ipHash): bool {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM visits WHERE ip_hash = ? AND visited_at > NOW() - INTERVAL '1 hour'"
        );
        $stmt->execute([$ipHash]);
        return (int)$stmt->fetchColumn() < self::MAX_PER_HOUR;
    }
}
