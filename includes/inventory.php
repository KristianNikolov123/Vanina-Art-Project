<?php

function apply_stock_for_order(PDO $conn, array $order, int $direction): void
{
    $multiplier = $direction > 0 ? 1 : -1;
    $width = (float)($order['width'] ?? 0);
    $height = (float)($order['height'] ?? 0);
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));

    if ($width > 0 && $height > 0) {
        $profileNames = get_order_profile_names($order);
        if (!empty($profileNames)) {
            $stacked = calculate_stacked_profile_material($conn, $width, $height, $frameCount, $profileNames);
            foreach ($stacked['lines'] as $line) {
                if (empty($line['found']) || ($line['meters'] ?? 0) <= 0) {
                    continue;
                }
                apply_stock_by_name($conn, 'profiles', $line['name'], $multiplier * $line['meters']);
            }
        }

        $glassSqm = glass_square_meters($width, $height, $frameCount);
        if ($glassSqm > 0) {
            apply_stock_by_name($conn, 'glasses', trim($order['glass'] ?? ''), $multiplier * round($glassSqm, 2));
        }

        $backSqm = glass_square_meters($width, $height, $frameCount);
        if ($backSqm > 0) {
            apply_stock_by_name($conn, 'backs', trim($order['back'] ?? ''), $multiplier * round($backSqm, 2));
        }

        $hangingStock = resolve_hanging_stock_deduction($conn, $order['hanging'] ?? '', $frameCount, $width, $height);
        if ($hangingStock) {
            apply_stock_by_name(
                $conn,
                'hanging_options',
                $hangingStock['stock_name'],
                $multiplier * $hangingStock['amount']
            );
        }
    }

    $stockAction = resolve_passepartout_stock_action($conn, $order);
    if ($stockAction) {
        apply_passepartout_sheet_stock(
            $conn,
            $stockAction['passepartout_id'],
            $stockAction['sheet_type_id'],
            $stockAction['usage'],
            $multiplier
        );
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

    $allowedTables = ['profiles', 'glasses', 'backs', 'hanging_options'];
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
