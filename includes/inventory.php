<?php

/**
 * Количества, които една поръчка отчислява от склада (същата логика за отчисляване,
 * проверка за наличност и справката „Използвани материали“).
 *
 * @return array{items: list<array{table: string, name: string, amount: float}>, passepartouts: list<array{passepartout_id: int, sheet_type_id: int, usage: float}>}
 */
function collect_order_stock_requirements(PDO $conn, array $order): array
{
    $items = [];
    $geometry = order_pricing_geometry($order);
    $width = $geometry['frame_width'];
    $height = $geometry['frame_height'];
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));

    if ($width > 0 && $height > 0) {
        $profileNames = get_order_profile_names($order);
        if (!empty($profileNames) && empty($order['profile_ready_frame'])) {
            $frameMeasure = in_array($order['frame_measure'] ?? '', ['clear', 'outer'], true) ? $order['frame_measure'] : '';
            $stacked = calculate_stacked_profile_material($conn, $width, $height, $frameCount, $profileNames, $frameMeasure);
            // Нестандартна фигура: към склада се отчислява метражът × 1.25.
            $shapeFactor = frame_shape_coefficient($conn, (string)($order['frame_shape'] ?? '')) > 0
                ? get_pricing_setting($conn, 'frame_shape_material_factor', 1.25)
                : 1.0;
            foreach ($stacked['lines'] as $line) {
                if (empty($line['found']) || ($line['meters'] ?? 0) <= 0) {
                    continue;
                }
                $items[] = [
                    'table' => 'profiles',
                    'name' => $line['name'],
                    'amount' => round($line['meters'] * $shapeFactor, 2),
                ];
            }
        }

        $glassSqm = glass_square_meters($width, $height, $frameCount);
        if ($glassSqm > 0 && trim($order['glass'] ?? '') !== '') {
            $items[] = ['table' => 'glasses', 'name' => trim($order['glass']), 'amount' => round($glassSqm, 2)];
        }

        $backSqm = glass_square_meters($width, $height, $frameCount);
        if ($backSqm > 0 && trim($order['back'] ?? '') !== '') {
            $items[] = ['table' => 'backs', 'name' => trim($order['back']), 'amount' => round($backSqm, 2)];
        }

        $hangingStock = resolve_hanging_stock_deduction($conn, $order['hanging'] ?? '', $frameCount, $width, $height);
        if ($hangingStock) {
            $items[] = [
                'table' => 'hanging_options',
                'name' => $hangingStock['stock_name'],
                'amount' => (float)$hangingStock['amount'],
            ];
        }
    }

    return [
        'items' => $items,
        'passepartouts' => resolve_passepartout_stock_actions($conn, $order),
    ];
}

function apply_stock_for_order(PDO $conn, array $order, int $direction): void
{
    $multiplier = $direction > 0 ? 1 : -1;
    $requirements = collect_order_stock_requirements($conn, $order);

    foreach ($requirements['items'] as $item) {
        apply_stock_by_name($conn, $item['table'], $item['name'], $multiplier * $item['amount']);
    }

    foreach ($requirements['passepartouts'] as $stockAction) {
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

    $name = resolve_catalog_alias($table, $name);

    if ($change > 0) {
        $stmt = $conn->prepare("UPDATE {$table} SET stock = stock + ? WHERE name = ?");
        $stmt->execute([$change, $name]);
        return;
    }

    // Без отрязване на 0 — иначе при изтриване/редакция се „връща“ материал, който не е бил отчислен.
    $deduct = abs($change);
    $stmt = $conn->prepare("UPDATE {$table} SET stock = stock - ? WHERE name = ?");
    $stmt->execute([$deduct, $name]);
}

function stock_table_label(string $table): string
{
    return match ($table) {
        'profiles' => 'Профил',
        'glasses' => 'Стъкло',
        'backs' => 'Гръб',
        'hanging_options' => 'Окачване',
        'passepartouts' => 'Паспарту',
        default => $table,
    };
}

function stock_table_unit(string $table): string
{
    return match ($table) {
        'profiles' => 'л.м.',
        'glasses', 'backs' => 'кв.м',
        'passepartouts' => 'листа',
        default => 'бр.',
    };
}

/** Сумира изискванията по артикул: ключ "table|name" → количество. */
function aggregate_stock_requirements(PDO $conn, array $requirements): array
{
    $totals = [];
    foreach ($requirements['items'] as $item) {
        $name = resolve_catalog_alias($item['table'], $item['name']);
        $key = $item['table'] . '|' . $name;
        $totals[$key] = ($totals[$key] ?? 0) + (float)$item['amount'];
    }
    foreach ($requirements['passepartouts'] as $action) {
        $key = 'passepartouts|' . (int)$action['passepartout_id'] . '|' . (int)$action['sheet_type_id'];
        $totals[$key] = ($totals[$key] ?? 0) + (float)$action['usage'];
    }

    return $totals;
}

/**
 * Недостатъчна наличност за поръчката. При редакция се отчита и количеството,
 * което старата версия на поръчката вече е взела (то се връща преди новото отчисляване).
 *
 * @return list<array{label: string, name: string, needed: float, available: float, missing: float, unit: string}>
 */
function find_order_stock_shortages(PDO $conn, array $order, ?array $previousOrder = null): array
{
    $needed = aggregate_stock_requirements($conn, collect_order_stock_requirements($conn, $order));
    $returned = $previousOrder !== null
        ? aggregate_stock_requirements($conn, collect_order_stock_requirements($conn, $previousOrder))
        : [];

    $shortages = [];
    foreach ($needed as $key => $amount) {
        $parts = explode('|', $key);
        $table = $parts[0];
        if ($amount <= 0.0001) {
            continue;
        }

        if ($table === 'passepartouts') {
            $passepartoutId = (int)$parts[1];
            $sheetTypeId = (int)$parts[2];
            $stocks = get_passepartout_sheet_stocks($conn, $passepartoutId);
            $available = (float)($stocks[$sheetTypeId] ?? 0);
            $pp = resolve_passepartout($conn, $passepartoutId, null);
            $sheet = get_passepartout_sheet_type($conn, $sheetTypeId);
            $name = ($pp['name'] ?? ('#' . $passepartoutId)) . ($sheet ? ' (лист ' . $sheet['name'] . ')' : '');
        } else {
            $name = $parts[1];
            $item = get_catalog_item_by_name($conn, $table, $name);
            if (!$item) {
                continue;
            }
            $available = (float)($item['stock'] ?? 0);
            if ($table === 'hanging_options' && hanging_shares_hanger_pool($name)) {
                $available = get_hanger_pool_stock($conn);
            }
        }

        $available = max(0.0, $available + (float)($returned[$key] ?? 0));
        if ($available + 0.0001 < $amount) {
            $shortages[] = [
                'label' => stock_table_label($table),
                'name' => $name,
                'needed' => round($amount, 2),
                'available' => round($available, 2),
                'missing' => round($amount - $available, 2),
                'unit' => stock_table_unit($table),
            ];
        }
    }

    return $shortages;
}

function format_stock_shortage_summary(array $shortages): string
{
    $parts = [];
    foreach ($shortages as $row) {
        $parts[] = sprintf(
            '%s %s: липсват %s %s',
            $row['label'] ?? '',
            $row['name'] ?? '',
            rtrim(rtrim(number_format((float)($row['missing'] ?? 0), 2, '.', ''), '0'), '.'),
            $row['unit'] ?? ''
        );
    }

    return implode('; ', $parts);
}
