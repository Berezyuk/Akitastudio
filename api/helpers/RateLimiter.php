<?php
// api/helpers/RateLimiter.php

class RateLimiter {
    private const MAX_ATTEMPTS  = 10;
    private const WINDOW_SECONDS = 900; // 15 минут

    private static function ip(): string {
        // X-Real-IP выставляется nginx'ом upstream; не доверяем X-Forwarded-For от клиента
        return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // Ключ корзины: "action:md5(ip)". md5 нужен чтобы влезть в VARCHAR(45)
    // (полный IPv6 = 45, любой префикс переполнил бы столбец). Разные action —
    // разные корзины (login/register/feedback/...), не мешаются между собой;
    // по префиксу корзину видно глазами (LIKE 'register:%').
    private static function key(string $action): string {
        return $action . ':' . md5(self::ip());
    }

    public static function tooManyAttempts(PDO $db, string $action = 'login', int $max = self::MAX_ATTEMPTS, int $window = self::WINDOW_SECONDS): bool {
        $since = date('Y-m-d H:i:s', time() - $window);
        $stmt  = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?'
        );
        $stmt->execute([self::key($action), $since]);
        return (int)$stmt->fetchColumn() >= $max;
    }

    public static function hit(PDO $db, string $action = 'login'): void {
        $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([self::key($action)]);
        // Удаляем записи старше двух окон, чтобы таблица не росла бесконтрольно.
        // Все корзины используют window <= WINDOW_SECONDS, поэтому cutoff 2x
        // безопасен — не удалит запись, ещё активную в своём окне.
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS * 2);
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
    }
}
