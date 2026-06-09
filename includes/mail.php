<?php

function send_verification_email_to_admin(string $newUserEmail, string $newUserUsername): bool
{
    if (!ADMIN_EMAIL || !ADMIN_EMAIL_PASSWORD) {
        return false;
    }

    $verifyUrl = full_url('/verify/' . urlencode($newUserEmail));
    $subject = 'Нова заявка за регистрация - Ванина Арт';
    $body = "Нов потребител иска да се регистрира:\n"
        . "Потребителско име: {$newUserUsername}\n"
        . "Имейл: {$newUserEmail}\n\n"
        . "За да активирате акаунта, кликнете тук:\n"
        . $verifyUrl;

    return send_smtp_email(ADMIN_EMAIL, $subject, $body);
}

function send_smtp_email(string $to, string $subject, string $body): bool
{
    try {
        $socket = stream_socket_client(
            'ssl://' . SMTP_SERVER . ':' . SMTP_PORT,
            $errno,
            $errstr,
            30
        );
        if (!$socket) {
            flash("Грешка при изпращане на имейл: {$errstr}", 'error');
            return false;
        }

        smtp_read($socket);
        smtp_write($socket, 'EHLO localhost');
        smtp_read($socket);

        smtp_write($socket, 'AUTH LOGIN');
        smtp_read($socket);
        smtp_write($socket, base64_encode(ADMIN_EMAIL));
        smtp_read($socket);
        smtp_write($socket, base64_encode(ADMIN_EMAIL_PASSWORD));
        smtp_read($socket);

        smtp_write($socket, 'MAIL FROM:<' . ADMIN_EMAIL . '>');
        smtp_read($socket);
        smtp_write($socket, 'RCPT TO:<' . $to . '>');
        smtp_read($socket);
        smtp_write($socket, 'DATA');
        smtp_read($socket);

        $message = "From: " . ADMIN_EMAIL . "\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $body . "\r\n.";

        smtp_write($socket, $message);
        smtp_read($socket);
        smtp_write($socket, 'QUIT');
        fclose($socket);
        return true;
    } catch (Exception $e) {
        flash('Грешка при изпращане на имейл: ' . $e->getMessage(), 'error');
        return false;
    }
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
