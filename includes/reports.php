<?php

/**
 * Справки: „Използвани материали“ (само администратор) и „Напомняния“.
 */

function require_admin(): void
{
    require_login();
    $user = current_user();
    if (!$user || !User::isAdminEmail($user->email)) {
        flash('Нямате достъп до тази страница!', 'danger');
        redirect('/');
    }
}

function current_user_is_admin(): bool
{
    $user = current_user();

    return $user !== null && User::isAdminEmail($user->email);
}

/**
 * Поръчки, чиито материали са отчислени от склада: всички активни и архивирани завършени,
 * плюс изтрити, които са били получени (при тях материалите не се връщат).
 */
function fetch_orders_with_consumed_materials(PDO $conn, ?string $fromIso = null, ?string $toIso = null): array
{
    $sql = '
        SELECT * FROM orders
        WHERE (deleted_at IS NULL OR collected = 1)
    ';
    $params = [];
    $dateExpr = "COALESCE(NULLIF(accepted_date, ''), date)";
    if ($fromIso !== null) {
        $sql .= " AND {$dateExpr} >= ?";
        $params[] = $fromIso;
    }
    if ($toIso !== null) {
        $sql .= " AND {$dateExpr} <= ?";
        $params[] = $toIso;
    }
    $sql .= ' ORDER BY order_number, sub_order_number';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/** Текуща цена за единица от каталога (€/л.м., €/кв.м, €/бр.). */
function catalog_unit_price_for_report(PDO $conn, string $table, string $name, array &$cache): float
{
    $key = $table . '|' . $name;
    if (!array_key_exists($key, $cache)) {
        $item = get_catalog_item_by_name($conn, $table, $name);
        $cache[$key] = $item ? (float)$item['price'] : 0.0;
    }

    return $cache[$key];
}

/**
 * Обобщение на отчислените материали по месеци (по дата на приемане на поръчката).
 * Количествата се изчисляват със същата функция, с която складът отчислява.
 * Стойността е количество × текущата цена от каталога (историческа цена не се пази).
 */
function build_used_materials_report(PDO $conn, int $year): array
{
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $orders = fetch_orders_with_consumed_materials($conn, $from, $to);

    $months = [];
    $priceCache = [];
    foreach ($orders as $order) {
        $dateIso = order_accepted_date_iso($order);
        $monthKey = substr($dateIso, 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            continue;
        }

        $requirements = collect_order_stock_requirements($conn, $order);
        foreach ($requirements['items'] as $item) {
            $name = resolve_catalog_alias($item['table'], $item['name']);
            $unitPrice = catalog_unit_price_for_report($conn, $item['table'], $name, $priceCache);
            $rowKey = $item['table'] . '|' . $name;
            $row = &$months[$monthKey]['rows'][$rowKey];
            $row['category'] = stock_table_label($item['table']);
            $row['name'] = $name;
            $row['unit'] = stock_table_unit($item['table']);
            $row['quantity'] = ($row['quantity'] ?? 0) + (float)$item['amount'];
            $row['value'] = ($row['value'] ?? 0) + (float)$item['amount'] * $unitPrice;
            unset($row);
        }

        if ($requirements['passepartouts'] !== []) {
            $pricing = calculate_passepartout_billing($conn, $order);
            $ppValueTotal = (float)($pricing['cost'] ?? 0);
            $usageTotal = array_sum(array_column($requirements['passepartouts'], 'usage'));
            foreach ($requirements['passepartouts'] as $action) {
                $pp = resolve_passepartout($conn, (int)$action['passepartout_id'], null);
                $sheet = get_passepartout_sheet_type($conn, (int)$action['sheet_type_id']);
                $name = ($pp['name'] ?? '#' . $action['passepartout_id']) . ($sheet ? ' (лист ' . $sheet['name'] . ')' : '');
                $share = $usageTotal > 0 ? (float)$action['usage'] / $usageTotal : 0;
                $rowKey = 'passepartouts|' . $name;
                $row = &$months[$monthKey]['rows'][$rowKey];
                $row['category'] = stock_table_label('passepartouts');
                $row['name'] = $name;
                $row['unit'] = stock_table_unit('passepartouts');
                $row['quantity'] = ($row['quantity'] ?? 0) + (float)$action['usage'];
                $row['value'] = ($row['value'] ?? 0) + $ppValueTotal * $share;
                unset($row);
            }
        }

        $months[$monthKey]['orders'] = ($months[$monthKey]['orders'] ?? 0) + 1;
    }

    krsort($months);
    $result = [];
    foreach ($months as $monthKey => $data) {
        $rows = array_values($data['rows'] ?? []);
        usort($rows, static fn (array $a, array $b): int => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);
        foreach ($rows as &$row) {
            $row['quantity'] = round($row['quantity'], 2);
            $row['value'] = round($row['value'], 2);
        }
        unset($row);
        $result[] = [
            'month' => $monthKey,
            'label' => format_month_label_bg($monthKey),
            'orders' => (int)($data['orders'] ?? 0),
            'rows' => $rows,
            'total_value' => round(array_sum(array_column($rows, 'value')), 2),
        ];
    }

    return $result;
}

function format_month_label_bg(string $monthKey): string
{
    $names = [
        1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април', 5 => 'Май', 6 => 'Юни',
        7 => 'Юли', 8 => 'Август', 9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември',
    ];
    [$year, $month] = array_map('intval', explode('-', $monthKey));

    return ($names[$month] ?? $monthKey) . ' ' . $year;
}

/** Предадени (получени от клиента), но неплатени поръчки. */
function fetch_collected_unpaid_orders(PDO $conn): array
{
    return $conn->query('
        SELECT * FROM orders
        WHERE deleted_at IS NULL AND collected = 1 AND paid = 0
        ORDER BY date ASC, order_number, sub_order_number
    ')->fetchAll();
}

/** Неполучени поръчки: минала е една седмица след датата за получаване (платени или не). */
function fetch_overdue_uncollected_orders(PDO $conn, ?string $todayIso = null): array
{
    $cutoff = (new DateTime($todayIso ?? 'today'))->modify('-7 days')->format('Y-m-d');
    $stmt = $conn->prepare("
        SELECT * FROM orders
        WHERE deleted_at IS NULL AND collected = 0
          AND date IS NOT NULL AND date <> '' AND date <= ?
        ORDER BY date ASC, order_number, sub_order_number
    ");
    $stmt->execute([$cutoff]);

    return $stmt->fetchAll();
}

function count_reminders(PDO $conn): int
{
    return count(fetch_collected_unpaid_orders($conn)) + count(fetch_overdue_uncollected_orders($conn));
}

function days_since_iso(string $dateIso, ?string $todayIso = null): int
{
    $date = DateTime::createFromFormat('Y-m-d', substr($dateIso, 0, 10));
    if (!$date) {
        return 0;
    }
    $today = new DateTime($todayIso ?? 'today');

    return (int)$date->diff($today)->format('%r%a');
}
