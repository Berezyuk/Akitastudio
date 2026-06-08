<?php
// Загружает переменные из .env только если они ещё не установлены (т.е. не в Docker).
// В Docker-контейнере переменные приходят через env_file/environment в docker-compose.

if (defined('ENV_LOADED')) {
    return;
}
define('ENV_LOADED', true);

if (getenv('DB_HOST') !== false) {
    // Переменные уже установлены (Docker или shell), загружать .env не нужно
    return;
}

$envFile = dirname(__DIR__, 2) . '/.env';
if (!file_exists($envFile)) {
    return;
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $value = trim($value, '"\'');
    if (getenv($key) === false) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}
