<?php

function order_material_quantity(?float $width, ?float $height, int $frameCount = 1): float
{
    if ($width <= 0 || $height <= 0 || $frameCount < 1) {
        return 0;
    }

    return ($width * $height / 10000) * $frameCount;
}

function order_stock_quantity(array $order): int
{
    $quantity = order_material_quantity(
        isset($order['width']) ? (float)$order['width'] : 0,
        isset($order['height']) ? (float)$order['height'] : 0,
        (int)($order['frame_count'] ?? 1)
    );

    if ($quantity <= 0) {
        return 0;
    }

    return (int)ceil($quantity);
}

function apply_stock_for_order(PDO $conn, array $order, int $direction): void
{
    $amount = order_stock_quantity($order);
    if ($amount <= 0) {
        return;
    }

    $change = $direction > 0 ? $amount : -$amount;
    apply_stock_by_name($conn, 'profiles', trim($order['profile'] ?? ''), $change);

    $additional = $order['additional_profiles'] ?? '';
    if ($additional !== '') {
        foreach (explode(',', $additional) as $name) {
            apply_stock_by_name($conn, 'profiles', trim($name), $change);
        }
    }

    apply_stock_by_name($conn, 'glasses', trim($order['glass'] ?? ''), $change);
    apply_stock_by_name($conn, 'passepartouts', trim($order['passepartout'] ?? ''), $change);
}

function deduct_stock_for_order(PDO $conn, array $order): void
{
    apply_stock_for_order($conn, $order, -1);
}

function restore_stock_for_order(PDO $conn, array $order): void
{
    apply_stock_for_order($conn, $order, 1);
}

function adjust_stock_for_order_edit(PDO $conn, array $oldOrder, array $newOrder): void
{
    restore_stock_for_order($conn, $oldOrder);
    deduct_stock_for_order($conn, $newOrder);
}

function apply_stock_by_name(PDO $conn, string $table, string $name, int $change): void
{
    if ($name === '' || $change === 0) {
        return;
    }

    $allowedTables = ['profiles', 'glasses', 'passepartouts'];
    if (!in_array($table, $allowedTables, true)) {
        return;
    }

    if ($change > 0) {
        $stmt = $conn->prepare("UPDATE {$table} SET stock = stock + ? WHERE name = ?");
        $stmt->execute([$change, $name]);
        return;
    }

    $deduct = abs($change);
    $stmt = $conn->prepare("
        UPDATE {$table}
        SET stock = CASE WHEN stock - ? < 0 THEN 0 ELSE stock - ? END
        WHERE name = ?
    ");
    $stmt->execute([$deduct, $deduct, $name]);
}
