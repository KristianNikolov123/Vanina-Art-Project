<?php

/**
 * Статуси на поръчката (Готова / Платена / Получена), номериране без дублиране,
 * клиенти и уведомления — схема (migration_010).
 */

function ensure_workflow_schema(PDO $conn): void
{
    if (schema_migration_applied($conn, 'workflow_2026_11_schema')) {
        return;
    }
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $orderColumns = [
        'ready' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'ready_at' => $isMysql ? 'DATETIME NULL' : 'TEXT',
        'paid_at' => $isMysql ? 'DATETIME NULL' : 'TEXT',
        'collected_at' => $isMysql ? 'DATETIME NULL' : 'TEXT',
        'customer_id' => $isMysql ? 'INT UNSIGNED NULL' : 'INTEGER',
        'customer_email' => $isMysql ? 'VARCHAR(255) NULL' : 'TEXT',
        'passepartout_extra_stock' => $isMysql ? 'TEXT NULL' : 'TEXT',
    ];
    foreach ($orderColumns as $column => $type) {
        if (!table_has_column($conn, 'orders', $column)) {
            $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$type}");
        }
    }
    if (!table_has_column($conn, 'users', 'verify_token')) {
        $conn->exec('ALTER TABLE users ADD COLUMN verify_token ' . ($isMysql ? 'VARCHAR(64) NULL' : 'TEXT'));
    }

    if ($isMysql) {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS customers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NULL,
                phone_norm VARCHAR(30) NULL,
                email VARCHAR(255) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_customers_phone_norm (phone_norm),
                KEY idx_customers_email (email),
                KEY idx_customers_name (name)
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS order_notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL,
                channel VARCHAR(10) NOT NULL,
                kind VARCHAR(20) NOT NULL,
                recipient VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL,
                sent_by VARCHAR(100) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_notifications_order (order_id)
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS order_counters (
                name VARCHAR(50) PRIMARY KEY,
                value INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB
        ');
    } else {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT,
                phone_norm TEXT,
                email TEXT,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS order_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                channel TEXT NOT NULL,
                kind TEXT NOT NULL,
                recipient TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT NOT NULL,
                sent_by TEXT,
                created_at TEXT NOT NULL
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS order_counters (
                name TEXT PRIMARY KEY,
                value INTEGER NOT NULL
            )
        ');
    }

    // Получените поръчки са и готови.
    $conn->exec('UPDATE orders SET ready = 1 WHERE collected = 1');

    // Старите линкове за потвърждение съдържаха само имейла — чакащите получават токен
    // и се одобряват от „Влизания“ → „Чакащи регистрации“.
    $pending = $conn->query('SELECT id FROM users WHERE is_verified = 0 AND (verify_token IS NULL OR verify_token = \'\')')->fetchAll(PDO::FETCH_COLUMN);
    $setToken = $conn->prepare('UPDATE users SET verify_token = ? WHERE id = ?');
    foreach ($pending as $userId) {
        $setToken->execute([random_token(), (int)$userId]);
    }

    backfill_customers_from_orders($conn);

    mark_schema_migration_applied($conn, 'workflow_2026_11_schema');
}

/**
 * Следващ № на поръчка без дублиране при двама служители едновременно.
 * Извиква се в транзакция: редът на брояча се заключва до commit.
 */
function next_order_number(PDO $conn): int
{
    $max = (int)$conn->query('SELECT COALESCE(MAX(order_number), 0) FROM orders')->fetchColumn();
    $insert = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'INSERT IGNORE INTO order_counters (name, value) VALUES (?, ?)'
        : 'INSERT OR IGNORE INTO order_counters (name, value) VALUES (?, ?)';
    $conn->prepare($insert)->execute(['order_number', $max]);

    $conn->prepare('UPDATE order_counters SET value = value + 1 WHERE name = ?')->execute(['order_number']);
    $stmt = $conn->prepare('SELECT value FROM order_counters WHERE name = ?');
    $stmt->execute(['order_number']);
    $next = (int)$stmt->fetchColumn();

    if ($next <= $max) {
        $next = $max + 1;
        $conn->prepare('UPDATE order_counters SET value = ? WHERE name = ?')->execute([$next, 'order_number']);
    }

    return $next;
}

/** Заключва реда на поръчката (MySQL) — за подпоръчки и смяна на статус. */
function lock_order_row(PDO $conn, int $orderId): ?array
{
    $sql = 'SELECT * FROM orders WHERE id = ?';
    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute([$orderId]);

    return $stmt->fetch() ?: null;
}

function order_status_fields(): array
{
    return [
        'ready' => ['on' => 'Готова', 'off' => 'В работа'],
        'paid' => ['on' => 'Платена', 'off' => 'Неплатена'],
        'collected' => ['on' => 'Получена', 'off' => 'Неполучена'],
    ];
}

/**
 * Смяна на статус с един клик. „Получена“ прави поръчката и „Готова“;
 * махането на „Готова“ маха и „Получена“.
 */
function update_order_status(PDO $conn, int $orderId, string $field, bool $value): ?array
{
    if (!array_key_exists($field, order_status_fields())) {
        return null;
    }

    $conn->beginTransaction();
    try {
        $order = lock_order_row($conn, $orderId);
        if (!$order || !empty($order['deleted_at'])) {
            $conn->rollBack();

            return null;
        }

        $now = date('Y-m-d H:i:s');
        $changes = [$field => $value ? 1 : 0];
        $changes[$field . '_at'] = $value ? (($order[$field . '_at'] ?? null) ?: $now) : null;
        if ($field === 'collected' && $value) {
            $changes['ready'] = 1;
            $changes['ready_at'] = ($order['ready_at'] ?? null) ?: $now;
        }
        if ($field === 'ready' && !$value) {
            $changes['collected'] = 0;
            $changes['collected_at'] = null;
        }

        $assign = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($changes)));
        $conn->prepare("UPDATE orders SET {$assign} WHERE id = ?")->execute(array_merge(array_values($changes), [$orderId]));
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);

    return $stmt->fetch() ?: null;
}

/** Колони за дата на смяна на статус при запис от формата. */
function apply_status_timestamps(array $order, ?array $previous): array
{
    $now = date('Y-m-d H:i:s');
    if (!empty($order['collected'])) {
        $order['ready'] = 1;
    }
    foreach (['ready', 'paid', 'collected'] as $field) {
        $was = $previous !== null && !empty($previous[$field]);
        if (!empty($order[$field])) {
            $order[$field . '_at'] = $was ? (($previous[$field . '_at'] ?? null) ?: $now) : $now;
        } else {
            $order[$field . '_at'] = null;
        }
    }

    return $order;
}
