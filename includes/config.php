<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', dirname(__DIR__));

$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

define('BASE_PATH', rtrim($_ENV['BASE_PATH'] ?? '', '/'));
define('SECRET_KEY', $_ENV['SECRET_KEY'] ?? 'change-me-in-production');
define('ADMIN_EMAIL', $_ENV['ADMIN_EMAIL'] ?? '');
define('ADMIN_EMAIL_PASSWORD', $_ENV['ADMIN_EMAIL_PASSWORD'] ?? '');
define('SMTP_SERVER', 'smtp.gmail.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 465));

require_once ROOT_PATH . '/includes/database.php';
require_once ROOT_PATH . '/includes/User.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/mail.php';

init_db();
