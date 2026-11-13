<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile = dirname(__DIR__) . '/config/config.php';
$appConfig = is_file($configFile) ? require $configFile : [];

date_default_timezone_set((string) ($appConfig['app']['timezone'] ?? 'Europe/Moscow'));

function appConfig(?string $section = null): array
{
    global $appConfig;
    return $section === null ? $appConfig : (array) ($appConfig[$section] ?? []);
}

function db(): ?PDO
{
    static $connection = false;
    if ($connection instanceof PDO) return $connection;
    if ($connection === null) return null;

    $settings = appConfig('db');
    foreach (['host', 'name', 'user', 'password'] as $required) {
        if (!array_key_exists($required, $settings) || $settings[$required] === '') {
            $connection = null;
            return null;
        }
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $settings['host'],
            (int) ($settings['port'] ?? 3306),
            $settings['name'],
            $settings['charset'] ?? 'utf8mb4'
        );
        $connection = new PDO($dsn, $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $connection;
    } catch (Throwable $error) {
        error_log('Tripy database connection failed: ' . $error->getMessage());
        $connection = null;
        return null;
    }
}

function dbReady(): bool
{
    return db() instanceof PDO;
}

function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? appConfig('app')['default_user_id'] ?? 1);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool
{
    return is_string($token) && hash_equals(csrfToken(), $token);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectWithMessage(string $location, string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    header('Location: ' . $location);
    exit;
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}
