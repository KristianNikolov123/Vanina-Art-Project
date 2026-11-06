<?php

/**
 * Idle session timeout.
 *
 * Срокът се променя с SESSION_IDLE_TIMEOUT_MINUTES в .env (по подразбиране 240 мин = 4 часа).
 * Само реална активност удължава сесията: заявки, маркирани с хедър X-Session-Passive
 * (автоматичното опресняване на списъка, проверката за вход), не я удължават.
 */
function session_idle_timeout_seconds(): int
{
    $minutes = (int)($_ENV['SESSION_IDLE_TIMEOUT_MINUTES'] ?? 240);

    return max(5, $minutes) * 60;
}

function configure_session_lifetime(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $timeout = session_idle_timeout_seconds();
    // Garbage collector must not delete the session file before our own idle limit.
    ini_set('session.gc_maxlifetime', (string)($timeout + 600));
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.use_strict_mode', '1');
    if (function_exists('session_cookie_options')) {
        session_set_cookie_params(session_cookie_options());
    }
}

function is_passive_request(): bool
{
    return ($_SERVER['HTTP_X_SESSION_PASSIVE'] ?? '') === '1';
}

function is_json_request(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return str_contains($accept, 'application/json') || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
}

/** Logs the user out when the idle limit has passed; refreshes the activity stamp otherwise. */
function enforce_session_idle_timeout(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $last = (int)($_SESSION['last_activity'] ?? $now);
    if ($now - $last > session_idle_timeout_seconds()) {
        unset($_SESSION['user_id'], $_SESSION['last_activity']);
        $_SESSION['session_expired'] = true;

        return;
    }

    if (!is_passive_request()) {
        $_SESSION['last_activity'] = $now;
    }
}

function session_seconds_remaining(): int
{
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }
    $last = (int)($_SESSION['last_activity'] ?? time());

    return max(0, session_idle_timeout_seconds() - (time() - $last));
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
