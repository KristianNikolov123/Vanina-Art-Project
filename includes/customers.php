<?php

/**
 * Клиенти: разпознават се по телефон (нормализиран), иначе по имейл.
 * Поръчките пазят и копие на името/телефона към момента на поръчката.
 */

/** „+359 88 123 4567“, „00359881234567“, „088-123-4567“ → „0881234567“. */
function normalize_phone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone) ?? '';
    if (str_starts_with($digits, '00359')) {
        $digits = '0' . substr($digits, 5);
    } elseif (str_starts_with($digits, '359') && strlen($digits) >= 11) {
        $digits = '0' . substr($digits, 3);
    }

    return strlen($digits) >= 6 ? $digits : '';
}

function normalize_email(?string $email): string
{
    $email = mb_strtolower(trim((string)$email), 'UTF-8');

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function get_customer(PDO $conn, int $customerId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);

    return $stmt->fetch() ?: null;
}

function find_customer_by_contact(PDO $conn, string $phoneNorm, string $email): ?array
{
    if ($phoneNorm !== '') {
        $stmt = $conn->prepare('SELECT * FROM customers WHERE phone_norm = ? ORDER BY id LIMIT 1');
        $stmt->execute([$phoneNorm]);
        if ($row = $stmt->fetch()) {
            return $row;
        }
    }
    if ($email !== '') {
        $stmt = $conn->prepare('SELECT * FROM customers WHERE LOWER(email) = ? ORDER BY id LIMIT 1');
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            return $row;
        }
    }

    return null;
}

/**
 * Свързва поръчката с клиент: избран от подсказките (customer_id) или намерен по телефон/имейл;
 * създава нов клиент, ако има телефон или имейл. Без контакт поръчката остава без клиент.
 */
function resolve_customer_for_order(PDO $conn, ?int $postedCustomerId, string $name, string $phone, string $email): ?int
{
    $name = trim($name);
    $phoneNorm = normalize_phone($phone);
    $email = normalize_email($email);
    $now = date('Y-m-d H:i:s');

    $customer = null;
    if ($postedCustomerId) {
        $customer = get_customer($conn, $postedCustomerId);
        // Ако телефонът е сменен с номер на друг клиент, важи телефонът.
        if ($customer && $phoneNorm !== '' && ($customer['phone_norm'] ?? '') !== '' && $customer['phone_norm'] !== $phoneNorm) {
            $customer = null;
        }
    }
    if (!$customer) {
        $customer = find_customer_by_contact($conn, $phoneNorm, $email);
    }

    if ($customer) {
        $conn->prepare('
            UPDATE customers SET
                name = CASE WHEN ? <> \'\' THEN ? ELSE name END,
                phone = CASE WHEN ? <> \'\' THEN ? ELSE phone END,
                phone_norm = CASE WHEN ? <> \'\' THEN ? ELSE phone_norm END,
                email = CASE WHEN ? <> \'\' THEN ? ELSE email END,
                updated_at = ?
            WHERE id = ?
        ')->execute([$name, $name, $phoneNorm, trim($phone), $phoneNorm, $phoneNorm, $email, $email, $now, (int)$customer['id']]);

        return (int)$customer['id'];
    }

    if ($phoneNorm === '' && $email === '') {
        return null;
    }

    $conn->prepare('INSERT INTO customers (name, phone, phone_norm, email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name !== '' ? $name : 'Без име', trim($phone) ?: null, $phoneNorm ?: null, $email ?: null, $now, $now]);

    return (int)$conn->lastInsertId();
}

/** Еднократно: създава клиенти от старите поръчки по телефон и ги свързва. */
function backfill_customers_from_orders(PDO $conn): void
{
    $rows = $conn->query("
        SELECT id, customer_name, customer_phone FROM orders
        WHERE customer_phone IS NOT NULL AND customer_phone <> ''
        ORDER BY id
    ")->fetchAll();

    $link = $conn->prepare('UPDATE orders SET customer_id = ? WHERE id = ?');
    $cache = [];
    foreach ($rows as $row) {
        $phoneNorm = normalize_phone($row['customer_phone']);
        if ($phoneNorm === '') {
            continue;
        }
        if (!isset($cache[$phoneNorm])) {
            $cache[$phoneNorm] = resolve_customer_for_order($conn, null, (string)$row['customer_name'], (string)$row['customer_phone'], '');
        } else {
            // По-новата поръчка носи по-актуалното име.
            if (trim((string)$row['customer_name']) !== '') {
                $conn->prepare('UPDATE customers SET name = ? WHERE id = ?')->execute([trim($row['customer_name']), $cache[$phoneNorm]]);
            }
        }
        $link->execute([$cache[$phoneNorm], (int)$row['id']]);
    }
}

/** Подсказки за формата: търсене по име, телефон или имейл. */
function search_customers(PDO $conn, string $query, int $limit = 8): array
{
    $query = trim($query);
    if (mb_strlen($query, 'UTF-8') < 2) {
        return [];
    }
    $like = '%' . mb_strtolower($query, 'UTF-8') . '%';
    $phoneLike = normalize_phone($query) !== '' ? '%' . normalize_phone($query) . '%' : '%' . preg_replace('/\D+/', '', $query) . '%';
    $hasDigits = preg_match('/\d{3,}/', $query) === 1;

    $sql = '
        SELECT c.*, COUNT(o.id) AS orders_count, MAX(o.accepted_date) AS last_order
        FROM customers c
        LEFT JOIN orders o ON o.customer_id = c.id AND o.deleted_at IS NULL
        WHERE LOWER(c.name) LIKE ? OR LOWER(COALESCE(c.email, \'\')) LIKE ?' . ($hasDigits ? ' OR c.phone_norm LIKE ?' : '') . '
        GROUP BY c.id
        ORDER BY last_order DESC, c.name
        LIMIT ' . max(1, $limit);
    $params = [$like, $like];
    if ($hasDigits) {
        $params[] = $phoneLike;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return array_map(static fn (array $row): array => [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'phone' => $row['phone'] ?? '',
        'email' => $row['email'] ?? '',
        'orders_count' => (int)$row['orders_count'],
        'last_order' => $row['last_order'] ? format_date_ddmmyyyy($row['last_order']) : '',
    ], $stmt->fetchAll());
}

/** Списък за страница „Клиенти“ с брой поръчки, оборот и дължимо. */
function fetch_customers_overview(PDO $conn, string $query = ''): array
{
    $where = '';
    $params = [];
    $query = trim($query);
    if ($query !== '') {
        $like = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $where = 'WHERE LOWER(c.name) LIKE ? OR LOWER(COALESCE(c.email, \'\')) LIKE ? OR COALESCE(c.phone_norm, \'\') LIKE ?';
        $digits = preg_replace('/\D+/', '', $query);
        $params = [$like, $like, '%' . ($digits !== '' ? normalize_phone($digits) ?: $digits : $query) . '%'];
    }
    $stmt = $conn->prepare("
        SELECT c.*,
            COUNT(o.id) AS orders_count,
            COALESCE(SUM(o.price), 0) AS total_price,
            COALESCE(SUM(CASE WHEN o.paid = 0 THEN COALESCE(o.price, 0) - COALESCE(o.advance_payment, 0) ELSE 0 END), 0) AS due,
            MAX(o.accepted_date) AS last_order
        FROM customers c
        LEFT JOIN orders o ON o.customer_id = c.id AND o.deleted_at IS NULL
        {$where}
        GROUP BY c.id
        ORDER BY last_order DESC, c.name
        LIMIT 500
    ");
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetch_customer_orders(PDO $conn, int $customerId): array
{
    $stmt = $conn->prepare('
        SELECT * FROM orders WHERE customer_id = ? AND deleted_at IS NULL
        ORDER BY order_number DESC, sub_order_number
    ');
    $stmt->execute([$customerId]);

    return $stmt->fetchAll();
}

function update_customer_from_post(PDO $conn, int $customerId, array $post): void
{
    $name = trim((string)($post['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Името е задължително.');
    }
    $phone = trim((string)($post['phone'] ?? ''));
    $email = normalize_email($post['email'] ?? '');
    if (trim((string)($post['email'] ?? '')) !== '' && $email === '') {
        throw new InvalidArgumentException('Невалиден имейл.');
    }
    $conn->prepare('UPDATE customers SET name = ?, phone = ?, phone_norm = ?, email = ?, notes = ?, updated_at = ? WHERE id = ?')
        ->execute([
            $name,
            $phone !== '' ? $phone : null,
            normalize_phone($phone) ?: null,
            $email !== '' ? $email : null,
            trim((string)($post['notes'] ?? '')) ?: null,
            date('Y-m-d H:i:s'),
            $customerId,
        ]);
}

/** Имейл за уведомяване: от поръчката, иначе от клиента. */
function order_contact(PDO $conn, array $order): array
{
    $customer = !empty($order['customer_id']) ? get_customer($conn, (int)$order['customer_id']) : null;
    $email = normalize_email($order['customer_email'] ?? '') ?: normalize_email($customer['email'] ?? '');
    $phone = trim((string)($order['customer_phone'] ?? '')) ?: trim((string)($customer['phone'] ?? ''));

    return [
        'name' => trim((string)($order['customer_name'] ?? '')) ?: ($customer['name'] ?? ''),
        'phone' => $phone,
        'email' => $email,
        'customer' => $customer,
    ];
}
