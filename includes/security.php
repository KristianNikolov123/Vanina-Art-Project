<?php

/**
 * CSRF защита и показване на грешки.
 * Токенът се пази в сесията; формите го получават автоматично (static/js/session-guard.js),
 * а fetch заявките го пращат в хедър X-CSRF-Token.
 */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_request_is_valid(): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return is_string($expected) && $expected !== '' && is_string($sent) && hash_equals($expected, $sent);
}

/** Всяка POST заявка (освен вход/регистрация) трябва да носи валиден токен. */
function enforce_csrf(string $uri, string $method): void
{
    if ($method !== 'POST' || PHP_SAPI === 'cli') {
        return;
    }
    if (in_array($uri, ['/login', '/signup', '/api/login'], true)) {
        return;
    }
    if (csrf_request_is_valid()) {
        return;
    }

    if (is_json_request() || str_starts_with($uri, '/api/') || in_array($uri, ['/calculate_price', '/check_stock'], true)) {
        json_response(['ok' => false, 'error' => 'csrf', 'message' => 'Страницата е остаряла — презаредете я.'], 419);
    }
    flash('Действието не е изпълнено: страницата е остаряла. Презаредете я и опитайте отново.', 'warning');
    redirect('/');
}

/** Грешките не се показват на потребителите (освен APP_DEBUG=1 в .env); записват се в лога на сървъра. */
function configure_error_display(): void
{
    $debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: '0') === '1';
    if (PHP_SAPI === 'cli') {
        return;
    }
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Добавя скрито поле _csrf във всяка POST форма в HTML-а. */
function inject_csrf_fields(string $html): string
{
    $field = csrf_field();

    return preg_replace_callback(
        '/<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>/i',
        static fn (array $m): string => $m[0] . $field,
        $html
    ) ?? $html;
}

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/** Бисквитката на сесията: недостъпна за JavaScript, само по HTTPS, без изпращане от чужди сайтове. */
function session_cookie_options(): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/** Работи и със стар config.php, който стартира сесията преди да зареди този файл. */
function harden_session_cookie(): void
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }
    $options = session_cookie_options();
    unset($options['lifetime']);
    setcookie(session_name(), session_id(), $options + ['expires' => 0]);
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// --- Ограничение срещу налучкване на пароли ---

function ensure_login_attempts_table(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $conn->exec($isMysql
        ? 'CREATE TABLE IF NOT EXISTS login_attempts (
               id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
               ip VARCHAR(64) NOT NULL,
               email VARCHAR(255) NOT NULL,
               attempted_at DATETIME NOT NULL,
               KEY idx_login_attempts_ip (ip, attempted_at),
               KEY idx_login_attempts_email (email, attempted_at)
           ) ENGINE=InnoDB'
        : 'CREATE TABLE IF NOT EXISTS login_attempts (
               id INTEGER PRIMARY KEY AUTOINCREMENT,
               ip TEXT NOT NULL,
               email TEXT NOT NULL,
               attempted_at TEXT NOT NULL
           )');
    $done = true;
}

function login_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

/**
 * Минути до следващ опит (0 = може). За 15 мин.: 8 грешни за един имейл или 30 от един IP
 * (служителите в магазина са зад един IP — една сбъркана парола не заключва всички).
 */
function login_throttle_minutes(PDO $conn, string $email): int
{
    ensure_login_attempts_table($conn);
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $checks = [
        ['email', mb_strtolower(trim($email), 'UTF-8'), 8],
        ['ip', login_client_ip(), 30],
    ];
    foreach ($checks as [$column, $value, $limit]) {
        $stmt = $conn->prepare("SELECT COUNT(*), MIN(attempted_at) FROM login_attempts WHERE {$column} = ? AND attempted_at >= ?");
        $stmt->execute([$value, $since]);
        [$count, $oldest] = $stmt->fetch(PDO::FETCH_NUM);
        if ((int)$count >= $limit) {
            return max(1, (int)ceil((strtotime((string)$oldest) + 15 * 60 - time()) / 60));
        }
    }

    return 0;
}

function record_failed_login(PDO $conn, string $email): void
{
    ensure_login_attempts_table($conn);
    $conn->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?, ?, ?)')
        ->execute([login_client_ip(), mb_strtolower(trim($email), 'UTF-8'), date('Y-m-d H:i:s')]);
    $conn->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

function clear_failed_logins(PDO $conn, string $email): void
{
    ensure_login_attempts_table($conn);
    $conn->prepare('DELETE FROM login_attempts WHERE email = ?')
        ->execute([mb_strtolower(trim($email), 'UTF-8')]);
}
