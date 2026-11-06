<?php

/**
 * Уведомяване на клиента с един бутон: имейл (от сървъра) или SMS.
 * SMS от сървъра изисква доставчик в .env (SMS_PROVIDER=smsapi_bg, SMS_API_TOKEN, SMS_SENDER);
 * без доставчик бутонът отваря SMS приложението (телефон / Phone Link) с готовия текст.
 */

function shop_display_name(): string
{
    return trim((string)($_ENV['SHOP_NAME'] ?? '')) ?: 'Ванина Арт';
}

/** „в Ванина Арт“ → „във Ванина Арт“ (пред в/ф се пише „във“). */
function shop_name_with_preposition(): string
{
    $shop = shop_display_name();
    $first = mb_strtolower(mb_substr($shop, 0, 1, 'UTF-8'), 'UTF-8');

    return (in_array($first, ['в', 'ф'], true) ? 'във ' : 'в ') . $shop;
}

function sms_provider_configured(): bool
{
    return ($_ENV['SMS_PROVIDER'] ?? '') === 'smsapi_bg' && trim((string)($_ENV['SMS_API_TOKEN'] ?? '')) !== '';
}

function order_number_label(array $order): string
{
    return (int)$order['order_number'] . ((int)($order['sub_order_number'] ?? 0) > 0 ? '.' . (int)$order['sub_order_number'] : '');
}

function order_amount_due(array $order): float
{
    if (!empty($order['paid'])) {
        return 0.0;
    }

    return max(0.0, round((float)($order['price'] ?? 0) - (float)($order['advance_payment'] ?? 0), 2));
}

/** Подходящ повод според статуса: готова / неплатена / неполучена. */
function default_notification_kind(array $order): string
{
    if (!empty($order['collected']) && empty($order['paid'])) {
        return 'payment';
    }
    $date = trim((string)($order['date'] ?? ''));
    if (empty($order['collected']) && $date !== '' && $date <= (new DateTime('today'))->modify('-7 days')->format('Y-m-d')) {
        return 'pickup';
    }

    return 'ready';
}

function notification_kind_labels(): array
{
    return [
        'ready' => 'Поръчката е готова',
        'pickup' => 'Напомняне за получаване',
        'payment' => 'Напомняне за плащане',
    ];
}

function build_notification_message(array $order, string $kind): string
{
    $name = trim((string)($order['customer_name'] ?? ''));
    $greeting = $name !== '' ? "Здравейте, {$name}!" : 'Здравейте!';
    $number = order_number_label($order);
    $shop = shop_name_with_preposition();
    $due = order_amount_due($order);
    $dueText = $due > 0 ? ' Сума за плащане: ' . number_format($due, 2, '.', '') . ' €.' : '';

    return match ($kind) {
        'payment' => "{$greeting} Напомняме, че по поръчка № {$number} {$shop} остава за плащане "
            . number_format($due, 2, '.', '') . ' €. Благодарим Ви!',
        'pickup' => "{$greeting} Поръчка № {$number} {$shop} Ви очаква от "
            . format_date_ddmmyyyy($order['date'] ?? '') . '. Заповядайте да я получите.' . $dueText . ' Благодарим Ви!',
        default => "{$greeting} Поръчка № {$number} {$shop} е готова и може да бъде получена.{$dueText} Благодарим Ви!",
    };
}

/** 0881234567 → 359881234567 */
function phone_to_international(string $phone): string
{
    $norm = normalize_phone($phone);
    if ($norm === '') {
        return '';
    }

    return str_starts_with($norm, '0') ? '359' . substr($norm, 1) : $norm;
}

function sms_app_link(string $phone, string $message): string
{
    $intl = phone_to_international($phone);

    return 'sms:' . ($intl !== '' ? '+' . $intl : '') . '?&body=' . rawurlencode($message);
}

/** Изпраща SMS през SMSAPI.bg (https://www.smsapi.bg). Връща [ok, грешка]. */
function send_sms_via_provider(string $phone, string $message): array
{
    $to = phone_to_international($phone);
    if ($to === '') {
        return [false, 'Невалиден телефон.'];
    }
    if (!function_exists('curl_init')) {
        return [false, 'Липсва cURL на сървъра.'];
    }

    $params = ['to' => $to, 'message' => $message, 'format' => 'json', 'encoding' => 'utf-8'];
    $sender = trim((string)($_ENV['SMS_SENDER'] ?? ''));
    if ($sender !== '') {
        $params['from'] = $sender;
    }
    $ch = curl_init('https://api.smsapi.bg/sms.do');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim((string)$_ENV['SMS_API_TOKEN'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [false, 'Няма връзка със SMS услугата: ' . $error];
    }
    $data = json_decode((string)$body, true);
    if ($status >= 400 || isset($data['error'])) {
        return [false, 'SMS услугата върна грешка: ' . ($data['message'] ?? $body)];
    }

    return [true, ''];
}

function log_order_notification(PDO $conn, int $orderId, string $channel, string $kind, string $recipient, string $message, string $status): int
{
    $user = current_user();
    $conn->prepare('
        INSERT INTO order_notifications (order_id, channel, kind, recipient, message, status, sent_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([$orderId, $channel, $kind, $recipient, $message, $status, $user?->username, date('Y-m-d H:i:s')]);

    return (int)$conn->lastInsertId();
}

/** Таен подпис за линка от QR кода (важи 24 часа). */
function sms_handoff_token(array $notification): string
{
    return substr(hash_hmac('sha256', $notification['id'] . '|' . $notification['created_at'] . '|' . $notification['recipient'], SECRET_KEY), 0, 32);
}

function sms_handoff_url(PDO $conn, int $notificationId): string
{
    $stmt = $conn->prepare('SELECT * FROM order_notifications WHERE id = ?');
    $stmt->execute([$notificationId]);
    $row = $stmt->fetch();

    return $row ? full_url('/sms/' . $notificationId . '/' . sms_handoff_token($row)) : '';
}

/** Уведомлението по линка от QR кода, ако подписът е валиден и не е изтекъл. */
function find_sms_handoff(PDO $conn, int $notificationId, string $token): ?array
{
    $stmt = $conn->prepare('SELECT * FROM order_notifications WHERE id = ? AND channel = ?');
    $stmt->execute([$notificationId, 'sms']);
    $row = $stmt->fetch();
    if (!$row || !hash_equals(sms_handoff_token($row), $token)) {
        return null;
    }
    if (time() - strtotime((string)$row['created_at']) > 24 * 3600) {
        return null;
    }

    return $row;
}

function fetch_order_notifications(PDO $conn, int $orderId): array
{
    $stmt = $conn->prepare('SELECT * FROM order_notifications WHERE order_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$orderId]);

    return $stmt->fetchAll();
}

/** Последно успешно уведомяване по поръчка (за етикет „уведомен“). */
function fetch_last_notifications(PDO $conn, array $orderIds): array
{
    $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
    if ($orderIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $conn->prepare("
        SELECT order_id, MAX(created_at) AS last_at FROM order_notifications
        WHERE order_id IN ({$placeholders}) AND status IN ('sent', 'opened')
        GROUP BY order_id
    ");
    $stmt->execute($orderIds);

    return array_column($stmt->fetchAll(), 'last_at', 'order_id');
}

/**
 * Изпраща уведомление. channel: email | sms.
 * @return array{ok: bool, message: string, sms_link?: string, status?: string}
 */
function send_order_notification(PDO $conn, array $order, string $channel, string $kind, ?string $customText = null): array
{
    $contact = order_contact($conn, $order);
    $kind = array_key_exists($kind, notification_kind_labels()) ? $kind : default_notification_kind($order);
    $text = trim((string)$customText) !== '' ? trim($customText) : build_notification_message($order, $kind);
    $orderId = (int)$order['id'];

    if ($channel === 'email') {
        if ($contact['email'] === '') {
            return ['ok' => false, 'message' => 'Клиентът няма имейл. Добавете го в поръчката или в „Клиенти“.'];
        }
        $subject = notification_kind_labels()[$kind] . ' — поръчка № ' . order_number_label($order) . ' — ' . shop_display_name();
        $flashBefore = $_SESSION['flash'] ?? [];
        $ok = send_app_email($contact['email'], $subject, $text);
        $mailFlash = array_slice($_SESSION['flash'] ?? [], count($flashBefore));
        $_SESSION['flash'] = $flashBefore; // грешките от пощата се връщат в отговора, не като flash
        log_order_notification($conn, $orderId, 'email', $kind, $contact['email'], $text, $ok ? 'sent' : 'failed');

        return [
            'ok' => $ok,
            'status' => $ok ? 'sent' : 'failed',
            'message' => $ok
                ? 'Имейлът е изпратен до ' . $contact['email'] . '.'
                : 'Имейлът не е изпратен. ' . implode(' ', array_column($mailFlash, 'message')),
        ];
    }

    if ($channel === 'sms') {
        if (normalize_phone($contact['phone']) === '') {
            return ['ok' => false, 'message' => 'Клиентът няма валиден телефон.'];
        }
        if (sms_provider_configured()) {
            [$ok, $error] = send_sms_via_provider($contact['phone'], $text);
            log_order_notification($conn, $orderId, 'sms', $kind, $contact['phone'], $text, $ok ? 'sent' : 'failed');

            return ['ok' => $ok, 'status' => $ok ? 'sent' : 'failed', 'message' => $ok ? 'SMS е изпратен до ' . $contact['phone'] . '.' : $error];
        }
        // Без SMS доставчик: отваря се SMS приложението с готовия текст.
        $notificationId = log_order_notification($conn, $orderId, 'sms', $kind, $contact['phone'], $text, 'opened');

        return [
            'ok' => true,
            'status' => 'opened',
            'sms_link' => sms_app_link($contact['phone'], $text),
            // За QR кода: обикновен уеб адрес, който всеки скенер отваря; страницата прехвърля към SMS.
            'sms_qr_url' => sms_handoff_url($conn, $notificationId),
            'message' => 'Отворено е SMS приложението с текста — натиснете „Изпрати“ там.',
        ];
    }

    return ['ok' => false, 'message' => 'Неизвестен канал.'];
}
