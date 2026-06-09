<?php

function apply_stock_for_order(PDO $conn, array $order, int $direction): void
{
    $multiplier = $direction > 0 ? 1 : -1;
    $width = (float)($order['width'] ?? 0);
    $height = (float)($order['height'] ?? 0);
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));

    if ($width > 0 && $height > 0) {
        $profileMeters = profile_linear_meters($width, $height, $frameCount);
        if ($profileMeters > 0) {
            $profileAmount = round($profileMeters, 2);
            apply_stock_by_name($conn, 'profiles', trim($order['profile'] ?? ''), $multiplier * $profileAmount);

            $additional = $order['additional_profiles'] ?? '';
            if ($additional !== '') {
                foreach (explode(',', $additional) as $name) {
                    apply_stock_by_name($conn, 'profiles', trim($name), $multiplier * $profileAmount);
                }
            }
        }

        $glassSqm = glass_square_meters($width, $height, $frameCount);
        if ($glassSqm > 0) {
            apply_stock_by_name($conn, 'glasses', trim($order['glass'] ?? ''), $multiplier * round($glassSqm, 2));
        }
    }

    $passepartoutName = trim($order['passepartout'] ?? '');
    if ($passepartoutName !== '' && $frameCount > 0) {
        apply_stock_by_name($conn, 'passepartouts', $passepartoutName, $multiplier * $frameCount);
    }
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

function apply_stock_by_name(PDO $conn, string $table, string $name, float $change): void
{
    if ($name === '' || abs($change) < 0.0001) {
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
