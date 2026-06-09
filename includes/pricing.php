<?php

function profile_linear_meters(float $widthCm, float $heightCm, int $frameCount = 1): float
{
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return 2 * ($widthCm + $heightCm) / 100 * $frameCount;
}

function glass_square_meters(float $widthCm, float $heightCm, int $frameCount = 1): float
{
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return ($widthCm * $heightCm / 10000) * $frameCount;
}

function passepartout_bill_square_meters(float $billWidthCm, float $billHeightCm, int $frameCount = 1): float
{
    if ($billWidthCm <= 0 || $billHeightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return ($billWidthCm * $billHeightCm / 10000) * $frameCount;
}

function cut_fits_request(float $cutW, float $cutH, float $reqW, float $reqH): bool
{
    return ($cutW >= $reqW && $cutH >= $reqH) || ($cutW >= $reqH && $cutH >= $reqW);
}

function cut_distance_sq(float $cutW, float $cutH, float $reqW, float $reqH): float
{
    $normal = ($cutW - $reqW) ** 2 + ($cutH - $reqH) ** 2;
    $rotated = ($cutW - $reqH) ** 2 + ($cutH - $reqW) ** 2;

    return min($normal, $rotated);
}

function find_closest_passepartout_cut(array $cuts, float $reqW, float $reqH): ?array
{
    if ($reqW <= 0 || $reqH <= 0 || empty($cuts)) {
        return null;
    }

    $fitting = array_values(array_filter(
        $cuts,
        fn($cut) => cut_fits_request((float)$cut['width_cm'], (float)$cut['height_cm'], $reqW, $reqH)
    ));

    $pool = $fitting ?: $cuts;
    $best = null;
    $bestDistance = PHP_FLOAT_MAX;

    foreach ($pool as $cut) {
        $distance = cut_distance_sq((float)$cut['width_cm'], (float)$cut['height_cm'], $reqW, $reqH);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $cut;
        }
    }

    return $best;
}

function get_passepartout_cuts(PDO $conn, int $passepartoutId): array
{
    $stmt = $conn->prepare('
        SELECT cs.width_cm, cs.height_cm, st.name AS sheet_name, st.id AS sheet_type_id
        FROM passepartout_cut_sizes cs
        JOIN passepartout_sheet_types st ON cs.sheet_type_id = st.id
        JOIN passepartout_sheet_availability psa ON psa.sheet_type_id = st.id
        WHERE psa.passepartout_id = ?
        ORDER BY cs.width_cm * cs.height_cm
    ');
    $stmt->execute([$passepartoutId]);

    return $stmt->fetchAll();
}

function resolve_passepartout(PDO $conn, ?int $passepartoutId, ?string $passepartoutName): ?array
{
    if ($passepartoutId) {
        $stmt = $conn->prepare('SELECT * FROM passepartouts WHERE id = ?');
        $stmt->execute([$passepartoutId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $name = trim($passepartoutName ?? '');
    if ($name === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM passepartouts WHERE name = ?');
    $stmt->execute([$name]);

    return $stmt->fetch() ?: null;
}

function get_catalog_price_by_name(PDO $conn, string $table, string $name): ?float
{
    $allowed = ['profiles', 'glasses', 'passepartouts'];
    if ($name === '' || !in_array($table, $allowed, true)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT price FROM {$table} WHERE name = ?");
    $stmt->execute([$name]);
    $price = $stmt->fetchColumn();

    return $price !== false ? (float)$price : null;
}

function calculate_passepartout_billing(PDO $conn, array $order): ?array
{
    $passepartout = resolve_passepartout(
        $conn,
        isset($order['passepartout_id']) ? (int)$order['passepartout_id'] : null,
        $order['passepartout'] ?? null
    );

    if (!$passepartout) {
        return null;
    }

    $width = (float)($order['width'] ?? 0);
    $height = (float)($order['height'] ?? 0);
    if ($width <= 0 || $height <= 0) {
        return null;
    }

    $cuts = get_passepartout_cuts($conn, (int)$passepartout['id']);
    if (empty($cuts)) {
        return null;
    }

    $closest = find_closest_passepartout_cut($cuts, $width, $height);
    if (!$closest) {
        return null;
    }

    $frameCount = max(1, (int)($order['frame_count'] ?? 1));
    $billWidth = (float)$closest['width_cm'];
    $billHeight = (float)$closest['height_cm'];
    $sqm = passepartout_bill_square_meters($billWidth, $billHeight, $frameCount);
    $cost = (float)$passepartout['price'] * $sqm;

    return [
        'passepartout_id' => (int)$passepartout['id'],
        'passepartout_name' => $passepartout['name'],
        'bill_width' => $billWidth,
        'bill_height' => $billHeight,
        'sheet_name' => $closest['sheet_name'],
        'square_meters' => round($sqm, 4),
        'unit_price' => (float)$passepartout['price'],
        'cost' => round($cost, 2),
    ];
}

function calculate_order_pricing(PDO $conn, array $order): array
{
    $width = (float)($order['width'] ?? 0);
    $height = (float)($order['height'] ?? 0);
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));

    $profileCost = 0.0;
    $profileMeters = 0.0;
    $profileName = trim($order['profile'] ?? '');
    if ($profileName !== '' && $width > 0 && $height > 0) {
        $profileMeters = profile_linear_meters($width, $height, $frameCount);
        $price = get_catalog_price_by_name($conn, 'profiles', $profileName);
        if ($price !== null) {
            $profileCost += $price * $profileMeters;
        }
    }

    $additional = $order['additional_profiles'] ?? '';
    if ($additional !== '' && $width > 0 && $height > 0) {
        foreach (explode(',', $additional) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $price = get_catalog_price_by_name($conn, 'profiles', $name);
            if ($price !== null) {
                $profileCost += $price * profile_linear_meters($width, $height, $frameCount);
            }
        }
    }

    $glassCost = 0.0;
    $glassSqm = 0.0;
    $glassName = trim($order['glass'] ?? '');
    if ($glassName !== '' && $width > 0 && $height > 0) {
        $glassSqm = glass_square_meters($width, $height, $frameCount);
        $price = get_catalog_price_by_name($conn, 'glasses', $glassName);
        if ($price !== null) {
            $glassCost = $price * $glassSqm;
        }
    }

    $passepartout = calculate_passepartout_billing($conn, $order);
    $passepartoutCost = $passepartout['cost'] ?? 0.0;

    $total = round($profileCost + $glassCost + $passepartoutCost, 2);

    return [
        'profile_cost' => round($profileCost, 2),
        'profile_meters' => round($profileMeters, 2),
        'glass_cost' => round($glassCost, 2),
        'glass_sqm' => round($glassSqm, 4),
        'passepartout' => $passepartout,
        'passepartout_cost' => round($passepartoutCost, 2),
        'total' => $total,
    ];
}

function enrich_order_pricing_fields(PDO $conn, array $order): array
{
    $pricing = calculate_order_pricing($conn, $order);
    $passepartout = $pricing['passepartout'];

    $order['passepartout_bill_width'] = $passepartout['bill_width'] ?? null;
    $order['passepartout_bill_height'] = $passepartout['bill_height'] ?? null;
    if (!empty($passepartout['passepartout_name'])) {
        $order['passepartout'] = $passepartout['passepartout_name'];
    }

    return $order;
}

function save_passepartout_sheet_types(PDO $conn, int $passepartoutId, array $sheetTypeIds): void
{
    $conn->prepare('DELETE FROM passepartout_sheet_availability WHERE passepartout_id = ?')
        ->execute([$passepartoutId]);

    $stmt = $conn->prepare('INSERT INTO passepartout_sheet_availability (passepartout_id, sheet_type_id) VALUES (?, ?)');
    foreach ($sheetTypeIds as $sheetTypeId) {
        $sheetTypeId = (int)$sheetTypeId;
        if ($sheetTypeId > 0) {
            $stmt->execute([$passepartoutId, $sheetTypeId]);
        }
    }
}

function get_passepartout_sheet_type_ids(PDO $conn, int $passepartoutId): array
{
    $stmt = $conn->prepare('SELECT sheet_type_id FROM passepartout_sheet_availability WHERE passepartout_id = ?');
    $stmt->execute([$passepartoutId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_all_sheet_types(PDO $conn): array
{
    return $conn->query('SELECT * FROM passepartout_sheet_types ORDER BY width_cm, height_cm')->fetchAll();
}

function seed_passepartout_sheet_catalog(PDO $conn): void
{
    $count = (int)$conn->query('SELECT COUNT(*) FROM passepartout_sheet_types')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $sheets = [
        ['80x120', 80, 120],
        ['80x100', 80, 100],
    ];

    $stmt = $conn->prepare('INSERT INTO passepartout_sheet_types (name, width_cm, height_cm) VALUES (?, ?, ?)');
    foreach ($sheets as [$name, $w, $h]) {
        $stmt->execute([$name, $w, $h]);
    }

    $sheetIds = [];
    foreach ($conn->query('SELECT id, name FROM passepartout_sheet_types')->fetchAll() as $row) {
        $sheetIds[$row['name']] = (int)$row['id'];
    }

    $cuts = [
        '80x120' => [[60, 80], [40, 60], [30, 40]],
        '80x100' => [[50, 80], [40, 50], [25, 40]],
    ];

    $cutStmt = $conn->prepare('INSERT INTO passepartout_cut_sizes (sheet_type_id, width_cm, height_cm) VALUES (?, ?, ?)');
    foreach ($cuts as $sheetName => $sizes) {
        foreach ($sizes as [$w, $h]) {
            $cutStmt->execute([$sheetIds[$sheetName], $w, $h]);
        }
    }
}
