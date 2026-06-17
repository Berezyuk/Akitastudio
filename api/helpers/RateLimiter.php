<?php
// api/helpers/RateLimiter.php

class RateLimiter {
    private const MAX_ATTEMPTS  = 10;
    private const WINDOW_SECONDS = 900; // 15 минут

    private static function ip(): string {
        // X-Real-IP выставляется nginx'ом upstream; не доверяем X-Forwarded-For от клиента
        return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function tooManyAttempts(PDO $db): bool {
        $ip    = self::ip();
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $stmt  = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?'
        );
        $stmt->execute([$ip, $since]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function hit(PDO $db): void {
        $ip = self::ip();
        $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$ip]);
        // Удаляем записи старше двух окон, чтобы таблица не росла бесконтрольно
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS * 2);
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
    }
}
