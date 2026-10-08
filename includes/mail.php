<?php

function send_verification_email_to_admin(string $newUserEmail, string $newUserUsername): bool
{
    if (!ADMIN_EMAIL) {
        return false;
    }

    if (MAIL_TRANSPORT === 'smtp' && !mail_smtp_configured()) {
        flash('SMTP не е конфигуриран: ADMIN_EMAIL и ADMIN_EMAIL_PASSWORD в .env.', 'warning');
        return false;
    }

    $verifyUrl = full_url('/verify/' . urlencode($newUserEmail));
    $subject = 'Нова заявка за регистрация - Ванина Арт';
    $body = "Нов потребител иска да се регистрира:\n"
        . "Потребителско име: {$newUserUsername}\n"
        . "Имейл: {$newUserEmail}\n\n"
        . "За да активирате акаунта, кликнете тук:\n"
        . $verifyUrl;

    return send_app_email(ADMIN_EMAIL, $subject, $body);
}

function mail_smtp_configured(): bool
{
    if (!SMTP_USER || !ADMIN_EMAIL_PASSWORD) {
        return false;
    }
    if (ADMIN_EMAIL_PASSWORD === 'your-app-password') {
        return false;
    }
    return true;
}

function mail_sender_address(): string
{
    if (MAIL_FROM !== '') {
        return MAIL_FROM;
    }
    if (SMTP_USER !== '') {
        return SMTP_USER;
    }
    return ADMIN_EMAIL;
}

/**
 * smtp = external SMTP only; php = hosting mail(); auto = SMTP then mail() fallback.
 */
function send_app_email(string $to, string $subject, string $body): bool
{
    $mode = MAIL_TRANSPORT;

    if ($mode === 'php') {
        return send_php_mail($to, $subject, $body);
    }

    if ($mode === 'smtp') {
        return send_smtp_email($to, $subject, $body, true);
    }

    if (mail_smtp_configured()) {
        $smtpError = '';
        if (send_smtp_email($to, $subject, $body, false, $smtpError)) {
            return true;
        }
        if (send_php_mail($to, $subject, $body)) {
            flash(
                'Уведомлението е изпратено чрез пощата на хостинга (Gmail SMTP е блокиран на сървъра).',
                'warning'
            );
            return true;
        }
        flash(
            'Имейлът не може да се изпрати. SMTP: ' . $smtpError
            . ' Опитайте MAIL_TRANSPORT=php и MAIL_FROM=имейл@vanina-art.com в .env.',
            'error'
        );
        return false;
    }

    return send_php_mail($to, $subject, $body);
}

function send_php_mail(string $to, string $subject, string $body): bool
{
    $from = MAIL_FROM !== '' ? MAIL_FROM : ADMIN_EMAIL;
    if ($from === '') {
        flash('Задайте MAIL_FROM или ADMIN_EMAIL в .env.', 'error');
        return false;
    }

    $headers = [
        'From: ' . $from,
        'Reply-To: ' . ADMIN_EMAIL,
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $envelope = mail_sender_address();
    $params = $envelope !== '' ? '-f' . escapeshellarg($envelope) : '';
    $ok = $params !== ''
        ? @mail($to, $encodedSubject, $body, implode("\r\n", $headers), $params)
        : @mail($to, $encodedSubject, $body, implode("\r\n", $headers));

    if (!$ok) {
        flash(
            'PHP mail() не успя. Създайте noreply@vanina-art.com в cPanel или ползвайте MAIL_TRANSPORT=smtp '
            . 'с SMTP_USER=noreply@vanina-art.com и паролата на този имейл в ADMIN_EMAIL_PASSWORD.',
            'error'
        );
    }

    return $ok;
}

function smtp_connect(): array
{
    $errno = 0;
    $errstr = '';
    $useSsl = SMTP_PORT === 465;
    $target = ($useSsl ? 'ssl://' : 'tcp://') . SMTP_SERVER . ':' . SMTP_PORT;
    $context = stream_context_create([
        'socket' => [
            'bindto' => '0.0.0.0:0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => SMTP_SERVER,
        ],
    ]);

    $socket = @stream_socket_client(
        $target,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    return [$socket, $errno, $errstr];
}

function send_smtp_email(string $to, string $subject, string $body, bool $flashErrors = true, ?string &$errorOut = null): bool
{
    $errorOut = null;

    try {
        [$socket, $errno, $errstr] = smtp_connect();
        if (!$socket) {
            $detail = $errstr !== '' ? $errstr : 'unknown error';
            if ($errno) {
                $detail .= " (errno {$errno})";
            }
            $errorOut = $detail;
            if ($flashErrors) {
                flash(
                    'Имейлът не може да се изпрати: сървърът не се свърза със SMTP. '
                    . 'На споделен хостинг често са блокирани портове 465/587 — задайте MAIL_TRANSPORT=php '
                    . 'или SMTP_SERVER=mail.vanina-art.com. Технически: ' . $detail,
                    'error'
                );
            }
            return false;
        }

        if (!smtp_expect(smtp_read($socket), 220)) {
            smtp_fail($socket, 'неочакван отговор от сървъра', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'EHLO localhost');
        if (!smtp_expect(smtp_read($socket), 250)) {
            smtp_fail($socket, 'EHLO', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'AUTH LOGIN');
        if (!smtp_expect(smtp_read($socket), 334)) {
            smtp_fail($socket, 'AUTH', $flashErrors, $errorOut);
            return false;
        }

        $sender = mail_sender_address();
        smtp_write($socket, base64_encode(SMTP_USER));
        if (!smtp_expect(smtp_read($socket), 334)) {
            smtp_fail($socket, 'невалиден SMTP потребител', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, base64_encode(ADMIN_EMAIL_PASSWORD));
        if (!smtp_expect(smtp_read($socket), 235)) {
            smtp_fail($socket, 'отхвърлена SMTP парола', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'MAIL FROM:<' . $sender . '>');
        if (!smtp_expect(smtp_read($socket), 250)) {
            smtp_fail($socket, 'MAIL FROM', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'RCPT TO:<' . $to . '>');
        if (!smtp_expect(smtp_read($socket), 250)) {
            smtp_fail($socket, 'RCPT TO', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'DATA');
        if (!smtp_expect(smtp_read($socket), 354)) {
            smtp_fail($socket, 'DATA', $flashErrors, $errorOut);
            return false;
        }

        $message = "From: " . $sender . "\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $body . "\r\n.";

        smtp_write($socket, $message);
        if (!smtp_expect(smtp_read($socket), 250)) {
            smtp_fail($socket, 'изпращане', $flashErrors, $errorOut);
            return false;
        }

        smtp_write($socket, 'QUIT');
        fclose($socket);
        return true;
    } catch (Exception $e) {
        $errorOut = $e->getMessage();
        if ($flashErrors) {
            flash('Грешка при изпращане на имейл: ' . $e->getMessage(), 'error');
        }
        return false;
    }
}

function smtp_fail($socket, string $step, bool $flashErrors, ?string &$errorOut): void
{
    $errorOut = $step;
    if ($flashErrors) {
        flash('Грешка при SMTP: ' . $step . '.', 'error');
    }
    fclose($socket);
}

function smtp_expect(string $response, int $code): bool
{
    return str_starts_with(trim($response), (string)$code);
}

function smtp_write($socket, string $data): void
{
    fwrite($socket, $data . "\r\n");
}

function smtp_read($socket): string
{
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}
