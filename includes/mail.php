<?php

function send_verification_email_to_admin(string $newUserEmail, string $newUserUsername): bool
{
    if (!ADMIN_EMAIL || !ADMIN_EMAIL_PASSWORD) {
        return false;
    }

    if (ADMIN_EMAIL_PASSWORD === 'your-app-password') {
        flash('SMTP не е конфигуриран: сменете ADMIN_EMAIL_PASSWORD в .env с Gmail App Password.', 'warning');
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
            flash("Грешка при връзка със SMTP: {$errstr}", 'error');
            return false;
        }

        if (!smtp_expect(smtp_read($socket), 220)) {
            flash('Грешка при SMTP: неочакван отговор от сървъра.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'EHLO localhost');
        if (!smtp_expect(smtp_read($socket), 250)) {
            flash('Грешка при SMTP EHLO.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'AUTH LOGIN');
        if (!smtp_expect(smtp_read($socket), 334)) {
            flash('Грешка при SMTP AUTH.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, base64_encode(ADMIN_EMAIL));
        if (!smtp_expect(smtp_read($socket), 334)) {
            flash('Грешка при SMTP: невалиден имейл.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, base64_encode(ADMIN_EMAIL_PASSWORD));
        if (!smtp_expect(smtp_read($socket), 235)) {
            flash('Грешка при SMTP: Gmail отхвърли паролата. Използвайте App Password, не обикновената парола.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'MAIL FROM:<' . ADMIN_EMAIL . '>');
        if (!smtp_expect(smtp_read($socket), 250)) {
            flash('Грешка при SMTP MAIL FROM.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'RCPT TO:<' . $to . '>');
        if (!smtp_expect(smtp_read($socket), 250)) {
            flash('Грешка при SMTP RCPT TO.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'DATA');
        if (!smtp_expect(smtp_read($socket), 354)) {
            flash('Грешка при SMTP DATA.', 'error');
            fclose($socket);
            return false;
        }

        $message = "From: " . ADMIN_EMAIL . "\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $body . "\r\n.";

        smtp_write($socket, $message);
        if (!smtp_expect(smtp_read($socket), 250)) {
            flash('Грешка при изпращане на имейла.', 'error');
            fclose($socket);
            return false;
        }

        smtp_write($socket, 'QUIT');
        fclose($socket);
        return true;
    } catch (Exception $e) {
        flash('Грешка при изпращане на имейл: ' . $e->getMessage(), 'error');
        return false;
    }
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
