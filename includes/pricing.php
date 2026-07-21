<?php

function profile_linear_meters(float $widthCm, float $heightCm, int $frameCount = 1): float
{
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return 2 * ($widthCm + $heightCm) / 100 * $frameCount;
}

function profile_billing_linear_meters(
    float $widthCm,
    float $heightCm,
    int $frameCount = 1,
    ?float $profileWidthCm = null
): float {
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    $billWidth = $widthCm;
    $billHeight = $heightCm;
    if ($profileWidthCm !== null && $profileWidthCm >= 10) {
        if ($widthCm > 250) {
            $billWidth = 300;
        }
        if ($heightCm > 250) {
            $billHeight = 300;
        }
    }

    return 2 * ($billWidth + $billHeight) / 100 * $frameCount;
}

function get_order_profile_names(array $order): array
{
    $names = [];
    $main = trim($order['profile'] ?? '');
    if ($main !== '') {
        $names[] = $main;
    }

    $additional = $order['additional_profiles'] ?? '';
    if ($additional !== '') {
        foreach (preg_split('/\s*,\s*/', $additional) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }

    return $names;
}

function calculate_stacked_profile_material(
    PDO $conn,
    float $width,
    float $height,
    int $frameCount,
    array $profileNames
): array {
    $totalCost = 0.0;
    $totalMeters = 0.0;
    $lines = [];
    $offset = 0.0;

    foreach ($profileNames as $name) {
        $profile = get_catalog_item_by_name($conn, 'profiles', $name);
        if (!$profile) {
            $lines[] = ['name' => $name, 'found' => false];
            continue;
        }

        $profileWidth = isset($profile['width_cm']) ? (float)$profile['width_cm'] : 0.0;
        $billW = $width + 2 * $offset;
        $billH = $height + 2 * $offset;
        $meters = profile_billing_linear_meters(
            $billW,
            $billH,
            $frameCount,
            $profileWidth > 0 ? $profileWidth : null
        );
        $cost = 0.0;
        if (($profile['profile_type'] ?? 'wood') !== 'client_material') {
            $cost = (float)$profile['price'] * $meters;
        }

        $totalCost += $cost;
        $totalMeters += $meters;
        $lines[] = [
            'name' => $name,
            'found' => true,
            'meters' => round($meters, 2),
            'bill_width' => $billW,
            'bill_height' => $billH,
            'cost' => round($cost, 2),
            'profile' => $profile,
        ];

        $offset += $profileWidth;
    }

    return [
        'material_cost' => round($totalCost, 2),
        'total_meters' => round($totalMeters, 2),
        'lines' => $lines,
    ];
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

    if (empty($fitting)) {
        return null;
    }

    $best = null;
    $bestDistance = PHP_FLOAT_MAX;

    foreach ($fitting as $cut) {
        $distance = cut_distance_sq((float)$cut['width_cm'], (float)$cut['height_cm'], $reqW, $reqH);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $cut;
        }
    }

    return $best;
}

function get_all_passepartout_catalog_cuts(PDO $conn): array
{
    $stmt = $conn->query('
        SELECT cs.width_cm, cs.height_cm, st.name AS sheet_name, st.id AS sheet_type_id
        FROM passepartout_cut_sizes cs
        JOIN passepartout_sheet_types st ON cs.sheet_type_id = st.id
        ORDER BY cs.width_cm * cs.height_cm
    ');

    return $stmt->fetchAll();
}

function get_passepartout_cuts(PDO $conn, int $passepartoutId): array
{
    $stmt = $conn->prepare('
        SELECT cs.width_cm, cs.height_cm, st.name AS sheet_name, st.id AS sheet_type_id
        FROM passepartout_cut_sizes cs
        JOIN passepartout_sheet_types st ON cs.sheet_type_id = st.id
        JOIN passepartout_sheet_availability psa ON psa.sheet_type_id = st.id
        WHERE psa.passepartout_id = ?
    ');
    $stmt->execute([$passepartoutId]);
    $cuts = $stmt->fetchAll();

    $stmt = $conn->prepare('
        SELECT st.width_cm, st.height_cm, st.name AS sheet_name, st.id AS sheet_type_id
        FROM passepartout_sheet_types st
        JOIN passepartout_sheet_availability psa ON psa.sheet_type_id = st.id
        WHERE psa.passepartout_id = ?
    ');
    $stmt->execute([$passepartoutId]);

    $seen = [];
    $all = [];
    foreach (array_merge($cuts, $stmt->fetchAll()) as $cut) {
        $key = $cut['sheet_type_id'] . ':' . $cut['width_cm'] . 'x' . $cut['height_cm'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $all[] = $cut;
        }
    }

    usort($all, fn($a, $b) => ($a['width_cm'] * $a['height_cm']) <=> ($b['width_cm'] * $b['height_cm']));

    return $all;
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
    $item = get_catalog_item_by_name($conn, $table, $name);

    return $item ? (float)$item['price'] : null;
}

function calculate_area_price(float $sqm, float $pricePerSqm, float $minPrice, int $pieces): float
{
    if ($sqm <= 0 || $pricePerSqm <= 0 || $pieces < 1) {
        return 0;
    }

    $calculatedTotal = $sqm * $pricePerSqm;
    if ($minPrice <= 0) {
        return round($calculatedTotal, 2);
    }

    $sqmPerPiece = $sqm / $pieces;
    $calculatedPerPiece = $sqmPerPiece * $pricePerSqm;
    $chargedPerPiece = $calculatedPerPiece >= $minPrice ? $calculatedPerPiece : $minPrice;

    return round($chargedPerPiece * $pieces, 2);
}

function calculate_frame_labor(
    PDO $conn,
    float $widthCm,
    float $heightCm,
    ?array $mainProfile,
    int $frameCount
): float {
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    $profileType = $mainProfile['profile_type'] ?? 'wood';
    if ($profileType === 'metal') {
        return round(get_pricing_setting($conn, 'frame_labor_metal', 5.07) * $frameCount, 2);
    }
    if ($profileType === 'client_material') {
        return round(get_pricing_setting($conn, 'frame_labor_client_material', 15.27) * $frameCount, 2);
    }

    $profileWidthCm = isset($mainProfile['width_cm']) ? (float)$mainProfile['width_cm'] : 0;
    $base = get_pricing_setting($conn, 'frame_labor_base', 2.78);
    $perCm = get_pricing_setting($conn, 'frame_labor_per_profile_cm', 0.50);
    $min = get_pricing_setting($conn, 'frame_labor_min', 3.54);
    $surcharge150 = get_pricing_setting($conn, 'frame_surcharge_side_150', 2.54);
    $surcharge200 = get_pricing_setting($conn, 'frame_surcharge_side_200', 6.09);

    $perFrame = $base + ($perCm * max(0, $profileWidthCm));
    $perFrame = max($perFrame, $min);

    $maxSide = max($widthCm, $heightCm);
    if ($maxSide > 200) {
        $perFrame += $surcharge200;
    } elseif ($maxSide > 150) {
        $perFrame += $surcharge150;
    }

    return round($perFrame * $frameCount, 2);
}

function calculate_extra_services_cost(PDO $conn, array $order, float $widthCm, float $heightCm, int $frameCount): array
{
    $ids = parse_extra_service_ids($order['extra_services'] ?? []);
    if (empty($ids)) {
        return ['total' => 0.0, 'lines' => []];
    }

    $sqm = glass_square_meters($widthCm, $heightCm, $frameCount);
    $lines = [];
    $total = 0.0;

    foreach ($ids as $id) {
        $stmt = $conn->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        if (!$service) {
            continue;
        }

        $unit = $service['pricing_unit'] ?? 'piece';
        $price = (float)$service['price'];
        $minPrice = (float)($service['min_price'] ?? 0);

        if ($unit === 'sqm') {
            if ($sqm > 0) {
                $cost = calculate_area_price($sqm, $price, $minPrice, $frameCount);
            } else {
                $perPiece = $minPrice > 0 ? max($price, $minPrice) : $price;
                $cost = round($perPiece * $frameCount, 2);
            }
        } elseif ($unit === 'km') {
            $km = max(0, (float)($order['transport_km'] ?? 0));
            if ($km > 0) {
                $cost = round(max($km * $price, $minPrice > 0 ? $minPrice : 0), 2);
            } else {
                $cost = round($minPrice > 0 ? $minPrice : $price, 2);
            }
        } else {
            $perPiece = $price;
            if ($minPrice > 0 && $perPiece < $minPrice) {
                $perPiece = $minPrice;
            }
            $cost = round($perPiece * $frameCount, 2);
        }

        $total += $cost;
        $lines[] = ['name' => $service['name'], 'cost' => round($cost, 2)];
    }

    return ['total' => round($total, 2), 'lines' => $lines];
}

function apply_order_price_modifiers(PDO $conn, array $order, float $subtotal, float $passepartoutCost, int $frameCount): array
{
    $complexSurcharge = 0.0;
    if (!empty($order['complex_passepartout']) && $passepartoutCost > 0) {
        $complexMin = get_pricing_setting($conn, 'passepartout_complex_min', 6.10) * $frameCount;
        $complexSurcharge = round(max($passepartoutCost * 0.5, $complexMin), 2);
    }

    $afterComplex = $subtotal + $complexSurcharge;

    $volumeDiscount = 0.0;
    $threshold10 = get_pricing_setting($conn, 'volume_discount_10_threshold', 510.20);
    $threshold5 = get_pricing_setting($conn, 'volume_discount_5_threshold', 255.64);
    if ($afterComplex >= $threshold10) {
        $volumeDiscount = round($afterComplex * get_pricing_setting($conn, 'volume_discount_10_percent', 10) / 100, 2);
    } elseif ($afterComplex >= $threshold5) {
        $volumeDiscount = round($afterComplex * get_pricing_setting($conn, 'volume_discount_5_percent', 5) / 100, 2);
    }

    $studentDiscount = 0.0;
    if (!empty($order['student_discount'])) {
        $studentDiscount = round(
            $afterComplex * get_pricing_setting($conn, 'student_discount_percent', 10) / 100,
            2
        );
    }

    $manualDiscount = max(0, (float)($order['manual_discount'] ?? 0));
    $afterDiscounts = max(0, $afterComplex - $volumeDiscount - $studentDiscount - $manualDiscount);

    $urgentSurcharge = 0.0;
    if (!empty($order['urgent'])) {
        $urgentPercent = get_pricing_setting($conn, 'urgent_surcharge_percent', 50);
        $urgentSurcharge = round($afterDiscounts * $urgentPercent / 100, 2);
    }

    $total = round($afterDiscounts + $urgentSurcharge, 2);

    return [
        'complex_passepartout_surcharge' => $complexSurcharge,
        'volume_discount' => $volumeDiscount,
        'student_discount_amount' => $studentDiscount,
        'manual_discount' => $manualDiscount,
        'urgent_surcharge' => $urgentSurcharge,
        'subtotal' => round($subtotal, 2),
        'total' => $total,
    ];
}

function calculate_hanging_cost(PDO $conn, string $name, float $widthCm, float $heightCm, int $frameCount): float
{
    $item = get_catalog_item_by_name($conn, 'hanging_options', $name);
    if (!$item || $frameCount < 1) {
        return 0;
    }

    $unitPrice = (float)$item['price'];
    $minPerPiece = (float)($item['min_price'] ?? 0);

    if ($widthCm <= 0 || $heightCm <= 0) {
        $chargedPerPiece = ($minPerPiece > 0 && $unitPrice < $minPerPiece) ? $minPerPiece : $unitPrice;
        return round($chargedPerPiece * $frameCount, 2);
    }

    if (($item['pricing_unit'] ?? 'piece') === 'lm') {
        $lmPerPiece = profile_linear_meters($widthCm, $heightCm, 1);
        $calculatedPerPiece = $lmPerPiece * $unitPrice;
        $chargedPerPiece = ($minPerPiece > 0 && $calculatedPerPiece < $minPerPiece)
            ? $minPerPiece
            : $calculatedPerPiece;

        return round($chargedPerPiece * $frameCount, 2);
    }

    $chargedPerPiece = ($minPerPiece > 0 && $unitPrice < $minPerPiece) ? $minPerPiece : $unitPrice;

    return round($chargedPerPiece * $frameCount, 2);
}

function calculate_frame_labor_minimum(PDO $conn, ?array $mainProfile, int $frameCount): float
{
    if ($frameCount < 1) {
        return 0;
    }

    $profileType = $mainProfile['profile_type'] ?? 'wood';
    if ($profileType === 'metal') {
        return round(get_pricing_setting($conn, 'frame_labor_metal', 5.07) * $frameCount, 2);
    }
    if ($profileType === 'client_material') {
        return round(get_pricing_setting($conn, 'frame_labor_client_material', 15.27) * $frameCount, 2);
    }

    $profileWidthCm = isset($mainProfile['width_cm']) ? (float)$mainProfile['width_cm'] : 0;
    $base = get_pricing_setting($conn, 'frame_labor_base', 2.78);
    $perCm = get_pricing_setting($conn, 'frame_labor_per_profile_cm', 0.50);
    $min = get_pricing_setting($conn, 'frame_labor_min', 3.54);
    $perFrame = max($base + ($perCm * max(0, $profileWidthCm)), $min);

    return round($perFrame * $frameCount, 2);
}

function passepartout_cut_fits_sheet(float $cutW, float $cutH, float $sheetW, float $sheetH): bool
{
    $cutMin = min($cutW, $cutH);
    $cutMax = max($cutW, $cutH);
    $sheetMin = min($sheetW, $sheetH);
    $sheetMax = max($sheetW, $sheetH);

    return $cutMin <= $sheetMin + 0.001 && $cutMax <= $sheetMax + 0.001;
}

function passepartout_sheets_to_charge(float $billW, float $billH, float $sheetW, float $sheetH): int
{
    if (passepartout_cut_fits_sheet($billW, $billH, $sheetW, $sheetH)) {
        return 1;
    }

    $sheetArea = $sheetW * $sheetH;
    if ($sheetArea <= 0) {
        return 2;
    }

    return max(2, (int)ceil(($billW * $billH) / $sheetArea));
}

function get_passepartout_available_sheets(PDO $conn, int $passepartoutId): array
{
    $stmt = $conn->prepare('
        SELECT st.id, st.name, st.width_cm, st.height_cm
        FROM passepartout_sheet_types st
        JOIN passepartout_sheet_availability psa ON psa.sheet_type_id = st.id
        WHERE psa.passepartout_id = ?
    ');
    $stmt->execute([$passepartoutId]);

    return $stmt->fetchAll();
}

function prefer_80x100_sheet(float $billW, float $billH): bool
{
    $w = $billW;
    $h = $billH;

    if ($w > 100.001 && $h > 100.001) {
        return false;
    }

    if ($h > 100.001 && $w <= 100.001) {
        return false;
    }

    if ($w > 100.001 && $h <= 100.001) {
        return true;
    }

    return true;
}

function passepartout_sheet_selection_score(
    float $billW,
    float $billH,
    float $sheetW,
    float $sheetH,
    int $sheetsNeeded
): array {
    $prefer100Sheet = prefer_80x100_sheet($billW, $billH);
    $sheetIs100Type = max($sheetW, $sheetH) <= 100.001;
    $typeMismatch = ($prefer100Sheet !== $sheetIs100Type) ? 1000 : 0;
    $areaPenalty = abs(($sheetW * $sheetH) - ($billW * $billH));

    return [$sheetsNeeded, $typeMismatch, $areaPenalty];
}

function select_best_passepartout_sheet(PDO $conn, int $passepartoutId, float $billW, float $billH): ?array
{
    $sheets = get_all_sheet_types($conn);
    if (empty($sheets)) {
        $sheets = get_passepartout_available_sheets($conn, $passepartoutId);
    }
    if (empty($sheets)) {
        return null;
    }

    $stockedIds = get_passepartout_stocked_sheet_type_ids($conn, $passepartoutId);
    $stockedLookup = empty($stockedIds) ? null : array_flip($stockedIds);

    $availableIds = array_flip(array_map(
        'intval',
        array_column(get_passepartout_available_sheets($conn, $passepartoutId), 'id')
    ));

    $best = null;
    $bestScore = null;

    foreach ($sheets as $sheet) {
        $sheetId = (int)$sheet['id'];
        if ($stockedLookup !== null && !isset($stockedLookup[$sheetId])) {
            continue;
        }

        $sheetW = (float)$sheet['width_cm'];
        $sheetH = (float)$sheet['height_cm'];
        $needed = passepartout_sheets_to_charge($billW, $billH, $sheetW, $sheetH);
        $score = passepartout_sheet_selection_score($billW, $billH, $sheetW, $sheetH, $needed);

        if (!isset($availableIds[$sheetId])) {
            $score = [$score[0], $score[1], $score[2] + 1];
        }

        if ($bestScore === null || $score < $bestScore) {
            $bestScore = $score;
            $best = $sheet;
        }
    }

    return $best;
}

function get_passepartout_sheet_type(PDO $conn, int $sheetTypeId): ?array
{
    $stmt = $conn->prepare('SELECT id, name, width_cm, height_cm FROM passepartout_sheet_types WHERE id = ?');
    $stmt->execute([$sheetTypeId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function is_passepartout_oversize_catalog_cut(array $cut): bool
{
    return ($cut['sheet_name'] ?? '') === '80x100'
        && (float)$cut['width_cm'] >= 99.999
        && (float)$cut['height_cm'] >= 99.999;
}

function get_passepartout_subdivision_cuts(array $catalogCuts): array
{
    return array_values(array_filter(
        $catalogCuts,
        fn($cut) => !is_passepartout_oversize_catalog_cut($cut)
    ));
}

function resolve_passepartout_billing_dimensions(
    PDO $conn,
    int $passepartoutId,
    array $catalogCuts,
    float $reqW,
    float $reqH
): ?array {
    $subdivisionCuts = get_passepartout_subdivision_cuts($catalogCuts);
    $stockedSubdivisionCuts = filter_passepartout_cuts_by_stock($conn, $passepartoutId, $subdivisionCuts);
    $closest = find_closest_passepartout_cut($stockedSubdivisionCuts, $reqW, $reqH);

    if ($closest) {
        $sheet = get_passepartout_sheet_type($conn, (int)$closest['sheet_type_id']);
        if (!$sheet) {
            return null;
        }

        return [
            'bill_width' => (float)$closest['width_cm'],
            'bill_height' => (float)$closest['height_cm'],
            'sheet_type_id' => (int)$closest['sheet_type_id'],
            'sheet_name' => $closest['sheet_name'],
            'sheet_width_cm' => (float)$sheet['width_cm'],
            'sheet_height_cm' => (float)$sheet['height_cm'],
            'oversize' => false,
        ];
    }

    $best = select_best_passepartout_sheet($conn, $passepartoutId, $reqW, $reqH);
    if (!$best) {
        return null;
    }

    $sheetW = (float)$best['width_cm'];
    $sheetH = (float)$best['height_cm'];
    $fitsOneSheet = passepartout_cut_fits_sheet($reqW, $reqH, $sheetW, $sheetH);
    $sheetsNeeded = passepartout_sheets_to_charge($reqW, $reqH, $sheetW, $sheetH);

    return [
        'bill_width' => $reqW,
        'bill_height' => $reqH,
        'sheet_type_id' => (int)$best['id'],
        'sheet_name' => $best['name'],
        'sheet_width_cm' => $sheetW,
        'sheet_height_cm' => $sheetH,
        'oversize' => !$fitsOneSheet || $sheetsNeeded > 1,
    ];
}

function get_passepartout_tier_cut_sizes(string $scheme): array
{
    if ($scheme === '80x120') {
        return [[30, 40], [40, 60], [60, 80], [81, 120]];
    }

    return [[25, 40], [40, 50], [51, 81], [81, 102]];
}

function passepartout_cut_dimensions_match(float $billW, float $billH, float $tierW, float $tierH, float $tolerance = 0.01): bool
{
    return (abs($billW - $tierW) <= $tolerance && abs($billH - $tierH) <= $tolerance)
        || (abs($billW - $tierH) <= $tolerance && abs($billH - $tierW) <= $tolerance);
}

function resolve_passepartout_tier_index(
    float $billW,
    float $billH,
    string $scheme,
    bool $oversize = false
): int {
    if ($oversize) {
        return 4;
    }

    $aliases = [
        '80x100' => [
            [50, 80, 3],
            [100, 100, 4],
        ],
    ];

    foreach ($aliases[$scheme] ?? [] as [$aliasW, $aliasH, $tierIndex]) {
        if (passepartout_cut_dimensions_match($billW, $billH, $aliasW, $aliasH)) {
            return $tierIndex;
        }
    }

    $tierCuts = get_passepartout_tier_cut_sizes($scheme);
    foreach ($tierCuts as $index => [$tierW, $tierH]) {
        if (passepartout_cut_dimensions_match($billW, $billH, $tierW, $tierH)) {
            return $index + 1;
        }
    }

    foreach ($tierCuts as $index => [$tierW, $tierH]) {
        if (cut_fits_request($tierW, $tierH, $billW, $billH)) {
            return $index + 1;
        }
    }

    return 4;
}

function get_passepartout_tier_price(array $passepartout, int $tierIndex): float
{
    $tierIndex = max(1, min(4, $tierIndex));
    $column = "price_tier_{$tierIndex}";

    return (float)($passepartout[$column] ?? 0);
}

function resolve_passepartout_material_cost(
    array $passepartout,
    float $billW,
    float $billH,
    string $scheme,
    bool $oversize,
    float $sqm,
    int $frameCount,
    int $sheetsToCharge
): array {
    if (!passepartout_uses_tier_pricing($passepartout)) {
        $unitPrice = (float)$passepartout['price'];

        return [
            'mode' => 'sqm',
            'unit_price' => $unitPrice,
            'tier_index' => null,
            'tier_label' => null,
            'cost' => round($unitPrice * $sqm, 2),
        ];
    }

    $tierIndex = resolve_passepartout_tier_index($billW, $billH, $scheme, $oversize);
    $tierPrice = get_passepartout_tier_price($passepartout, $tierIndex);
    $labels = get_passepartout_tier_scheme_labels()[$scheme] ?? [];
    $tierLabel = $labels[$tierIndex - 1] ?? "ниво {$tierIndex}";

    return [
        'mode' => 'tier',
        'unit_price' => $tierPrice,
        'tier_index' => $tierIndex,
        'tier_label' => $tierLabel,
        'cost' => round($tierPrice * $frameCount * $sheetsToCharge, 2),
    ];
}

function calculate_passepartout_labor_cost(
    PDO $conn,
    array $order,
    ?array $passepartoutBilling,
    int $frameCount
): float {
    $openings = max(1, (int)($order['passepartout_openings'] ?? 1));
    $cutting = get_pricing_setting($conn, 'passepartout_cutting_labor', 1.84);
    $extraOpening = get_pricing_setting($conn, 'passepartout_multi_opening', 1.22);
    $clientCutting = !empty($order['client_passepartout_cutting']);
    $firmPassepartout = $passepartoutBilling !== null;
    $labor = 0.0;

    if ($clientCutting && !$firmPassepartout) {
        $labor += $cutting * $frameCount;
        if ($openings > 1) {
            $labor += max(0, $openings - 1) * $extraOpening * $frameCount;
        }
    } elseif ($firmPassepartout && $openings > 1) {
        $labor += max(0, $openings - 1) * $extraOpening * $frameCount;
    }

    return round($labor, 2);
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

    $catalogCuts = get_all_passepartout_catalog_cuts($conn);
    if (empty($catalogCuts)) {
        return null;
    }

    $billingDims = resolve_passepartout_billing_dimensions(
        $conn,
        (int)$passepartout['id'],
        $catalogCuts,
        $width,
        $height
    );

    if (!$billingDims) {
        return null;
    }

    $frameCount = max(1, (int)($order['frame_count'] ?? 1));
    $billWidth = $billingDims['bill_width'];
    $billHeight = $billingDims['bill_height'];
    $sheetW = $billingDims['sheet_width_cm'];
    $sheetH = $billingDims['sheet_height_cm'];
    $sheetsToCharge = passepartout_sheets_to_charge($billWidth, $billHeight, $sheetW, $sheetH);

    if ($sheetsToCharge <= 1) {
        $sqm = passepartout_bill_square_meters($billWidth, $billHeight, $frameCount);
        $sheetUsage = calculate_passepartout_sheet_usage(
            $billWidth,
            $billHeight,
            $billingDims['sheet_type_id'],
            $frameCount,
            $conn
        );
    } else {
        $sqm = passepartout_bill_square_meters($sheetW, $sheetH, $frameCount) * $sheetsToCharge;
        $sheetUsage = calculate_passepartout_sheet_usage(
            $sheetW,
            $sheetH,
            $billingDims['sheet_type_id'],
            $frameCount,
            $conn
        ) * $sheetsToCharge;
    }

    $tierScheme = trim($passepartout['tier_scheme'] ?? '80x100');
    if (!isset(get_passepartout_tier_scheme_labels()[$tierScheme])) {
        $tierScheme = '80x100';
    }

    $materialPricing = resolve_passepartout_material_cost(
        $passepartout,
        $billWidth,
        $billHeight,
        $tierScheme,
        !empty($billingDims['oversize']),
        $sqm,
        $frameCount,
        $sheetsToCharge
    );

    return [
        'passepartout_id' => (int)$passepartout['id'],
        'passepartout_name' => $passepartout['name'],
        'bill_width' => $billWidth,
        'bill_height' => $billHeight,
        'sheet_name' => $billingDims['sheet_name'],
        'sheet_type_id' => $billingDims['sheet_type_id'],
        'sheet_usage' => $sheetUsage,
        'square_meters' => round($sqm, 4),
        'unit_price' => $materialPricing['unit_price'],
        'pricing_mode' => $materialPricing['mode'],
        'tier_index' => $materialPricing['tier_index'],
        'tier_label' => $materialPricing['tier_label'],
        'cost' => $materialPricing['cost'],
        'sheets_charged' => $sheetsToCharge,
        'multi_sheet_billing' => $sheetsToCharge > 1,
        'oversize' => !empty($billingDims['oversize']),
        'requested_width' => $width,
        'requested_height' => $height,
    ];
}

function calculate_passepartout_sheet_usage(
    float $billWidthCm,
    float $billHeightCm,
    int $sheetTypeId,
    int $frameCount,
    PDO $conn
): float {
    $stmt = $conn->prepare('SELECT width_cm, height_cm FROM passepartout_sheet_types WHERE id = ?');
    $stmt->execute([$sheetTypeId]);
    $sheet = $stmt->fetch();

    if (!$sheet || $billWidthCm <= 0 || $billHeightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    $sheetArea = (float)$sheet['width_cm'] * (float)$sheet['height_cm'];
    if ($sheetArea <= 0) {
        return 0;
    }

    $usedArea = $billWidthCm * $billHeightCm * $frameCount;

    return round($usedArea / $sheetArea, 4);
}

function calculate_order_pricing(PDO $conn, array $order): array
{
    $width = (float)($order['width'] ?? 0);
    $height = (float)($order['height'] ?? 0);
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));

    $profileMaterialCost = 0.0;
    $profileMeters = 0.0;
    $profileLines = [];
    $mainProfile = null;
    $profileName = trim($order['profile'] ?? '');

    if ($profileName !== '') {
        $mainProfile = get_catalog_item_by_name($conn, 'profiles', $profileName);
    }

    if ($width > 0 && $height > 0) {
        $profileNames = get_order_profile_names($order);
        if (!empty($profileNames)) {
            $stacked = calculate_stacked_profile_material($conn, $width, $height, $frameCount, $profileNames);
            $profileMaterialCost = $stacked['material_cost'];
            $profileMeters = $stacked['total_meters'];
            $profileLines = $stacked['lines'];
        }
    }

    $frameLaborCost = 0.0;
    if ($profileName !== '') {
        if ($width > 0 && $height > 0) {
            $frameLaborCost = calculate_frame_labor($conn, $width, $height, $mainProfile, $frameCount);
        } else {
            $frameLaborCost = calculate_frame_labor_minimum($conn, $mainProfile, $frameCount);
        }
    }

    $frameShape = $order['frame_shape'] ?? '';
    $frameShapeLabel = '';
    if (in_array($frameShape, ['ellipse_12', 'circle_24'], true)) {
        if ($profileName !== '' && $width > 0 && $height > 0) {
            $profileMeters = round($profileMeters * 1.25, 2);
            $profileMaterialCost = round($profileMaterialCost * 1.25, 2);
            foreach ($profileLines as &$profileLine) {
                if (!empty($profileLine['found'])) {
                    $profileLine['meters'] = round($profileLine['meters'] * 1.25, 2);
                    $profileLine['cost'] = round($profileLine['cost'] * 1.25, 2);
                }
            }
            unset($profileLine);
            if ($frameShape === 'ellipse_12') {
                $frameLaborCost = round($frameLaborCost * 3, 2);
                $frameShapeLabel = 'елипса/кръг 12';
            } else {
                $frameLaborCost = round($frameLaborCost * 4, 2);
                $frameShapeLabel = 'кръг 24';
            }
        } else {
            $minLabor = get_pricing_setting($conn, 'frame_labor_min', 3.54) * $frameCount;
            if ($frameShape === 'ellipse_12') {
                $frameLaborCost += round($minLabor * 3, 2);
                $frameShapeLabel = 'елипса/кръг 12';
            } else {
                $frameLaborCost += round($minLabor * 4, 2);
                $frameShapeLabel = 'кръг 24';
            }
        }
    }

    $frameExtrasCost = 0.0;
    $frameExtrasLines = [];
    if (!empty($order['frame_box'])) {
        $boxCost = round(get_pricing_setting($conn, 'frame_labor_box', 3.05) * $frameCount, 2);
        $frameExtrasCost += $boxCost;
        $frameExtrasLines[] = ['name' => 'Рамка тип кутия', 'cost' => $boxCost];
    }
    if (!empty($order['frame_nonstandard'])) {
        $frameValue = $profileMaterialCost + $frameLaborCost;
        $minNonstandard = get_pricing_setting($conn, 'frame_nonstandard_min', 8.63) * $frameCount;
        $nonstandardCost = $frameValue > 0
            ? round(max($frameValue * 0.3, $minNonstandard), 2)
            : round($minNonstandard, 2);
        $frameExtrasCost += $nonstandardCost;
        $frameExtrasLines[] = ['name' => 'Нестандартна форма', 'cost' => $nonstandardCost];
    }
    if (!empty($order['frame_high_complexity'])) {
        $frameValue = $profileMaterialCost + $frameLaborCost;
        $complexityCost = $frameValue > 0
            ? round($frameValue * 0.5, 2)
            : round(get_pricing_setting($conn, 'frame_labor_min', 3.54) * 0.5 * $frameCount, 2);
        $frameExtrasCost += $complexityCost;
        $frameExtrasLines[] = ['name' => 'Висока сложност (+50%)', 'cost' => $complexityCost];
    }

    $profileCost = round($profileMaterialCost + $frameLaborCost + $frameExtrasCost, 2);

    $glassCost = 0.0;
    $glassSqm = 0.0;
    $glassName = trim($order['glass'] ?? '');
    if ($glassName !== '') {
        $glass = get_catalog_item_by_name($conn, 'glasses', $glassName);
        if ($glass) {
            if ($width > 0 && $height > 0) {
                $glassSqm = glass_square_meters($width, $height, $frameCount);
                $glassCost = calculate_area_price(
                    $glassSqm,
                    (float)$glass['price'],
                    (float)($glass['min_price'] ?? 0),
                    $frameCount
                );
            } else {
                $perPiece = max((float)$glass['price'], (float)($glass['min_price'] ?? 0));
                $glassCost = round($perPiece * $frameCount, 2);
            }
        }
    }

    $backCost = 0.0;
    $backSqm = 0.0;
    $backName = trim($order['back'] ?? '');
    if ($backName !== '') {
        $back = get_catalog_item_by_name($conn, 'backs', $backName);
        if ($back) {
            if ($width > 0 && $height > 0) {
                $backSqm = glass_square_meters($width, $height, $frameCount);
                $backCost = calculate_area_price(
                    $backSqm,
                    (float)$back['price'],
                    (float)($back['min_price'] ?? 0),
                    $frameCount
                );
            } else {
                $perPiece = max((float)$back['price'], (float)($back['min_price'] ?? 0));
                $backCost = round($perPiece * $frameCount, 2);
            }
        }
    }

    $hangingCost = 0.0;
    $hangingName = trim($order['hanging'] ?? '');
    if ($hangingName !== '') {
        $hangingCost = calculate_hanging_cost($conn, $hangingName, $width, $height, $frameCount);
    }

    $passepartout = calculate_passepartout_billing($conn, $order);
    $passepartoutMaterialCost = $passepartout['cost'] ?? 0.0;
    $openings = max(1, (int)($order['passepartout_openings'] ?? 1));
    $passepartoutLaborCost = calculate_passepartout_labor_cost($conn, $order, $passepartout, $frameCount);
    $passepartoutCost = round($passepartoutMaterialCost + $passepartoutLaborCost, 2);

    $extraServices = calculate_extra_services_cost($conn, $order, $width, $height, $frameCount);
    $materialsSubtotal = round(
        $profileCost + $glassCost + $backCost + $hangingCost + $passepartoutCost + $extraServices['total'],
        2
    );

    $modifiers = apply_order_price_modifiers($conn, $order, $materialsSubtotal, $passepartoutCost, $frameCount);

    return [
        'profile_material_cost' => round($profileMaterialCost, 2),
        'frame_labor_cost' => $frameLaborCost,
        'profile_cost' => $profileCost,
        'profile_meters' => round($profileMeters, 2),
        'profile_lines' => array_map(static function (array $line): array {
            return [
                'name' => $line['name'],
                'found' => !empty($line['found']),
                'meters' => (float)($line['meters'] ?? 0),
                'cost' => (float)($line['cost'] ?? 0),
                'bill_width' => isset($line['bill_width']) ? round((float)$line['bill_width'], 1) : null,
                'bill_height' => isset($line['bill_height']) ? round((float)$line['bill_height'], 1) : null,
            ];
        }, $profileLines),
        'profile_wide_billing' => $mainProfile && isset($mainProfile['width_cm']) && (float)$mainProfile['width_cm'] >= 10
            && (max($width, $height) > 250),
        'glass_cost' => $glassCost,
        'glass_sqm' => round($glassSqm, 4),
        'back_cost' => $backCost,
        'back_sqm' => round($backSqm, 4),
        'hanging_cost' => $hangingCost,
        'passepartout' => $passepartout,
        'passepartout_openings' => $openings,
        'passepartout_material_cost' => round($passepartoutMaterialCost, 2),
        'passepartout_labor_cost' => round($passepartoutLaborCost, 2),
        'passepartout_cost' => $passepartoutCost,
        'extra_services' => $extraServices['lines'],
        'extra_services_cost' => $extraServices['total'],
        'materials_subtotal' => $materialsSubtotal,
        'complex_passepartout_surcharge' => $modifiers['complex_passepartout_surcharge'],
        'volume_discount' => $modifiers['volume_discount'],
        'student_discount_amount' => $modifiers['student_discount_amount'],
        'urgent_surcharge' => $modifiers['urgent_surcharge'],
        'profile_found' => $profileName !== '' ? (bool)$mainProfile : null,
        'frame_shape' => $frameShapeLabel,
        'frame_extras' => $frameExtrasLines,
        'frame_extras_cost' => round($frameExtrasCost, 2),
        'subtotal' => $modifiers['subtotal'],
        'total' => $modifiers['total'],
    ];
}

function enrich_order_pricing_fields(PDO $conn, array $order): array
{
    $pricing = calculate_order_pricing($conn, $order);
    $passepartout = $pricing['passepartout'];

    $order['passepartout_bill_width'] = $passepartout['bill_width'] ?? null;
    $order['passepartout_bill_height'] = $passepartout['bill_height'] ?? null;
    $order['passepartout_sheet_type_id'] = $passepartout['sheet_type_id'] ?? null;
    $order['passepartout_sheet_usage'] = $passepartout['sheet_usage'] ?? null;
    if (!empty($passepartout['passepartout_name'])) {
        $order['passepartout'] = $passepartout['passepartout_name'];
    }
    if (!empty($passepartout['passepartout_id'])) {
        $order['passepartout_id'] = $passepartout['passepartout_id'];
    }

    return $order;
}

function get_passepartout_sheet_stocks(PDO $conn, int $passepartoutId): array
{
    $stmt = $conn->prepare('
        SELECT sheet_type_id, stock
        FROM passepartout_sheet_stock
        WHERE passepartout_id = ?
    ');
    $stmt->execute([$passepartoutId]);
    $stocks = [];

    foreach ($stmt->fetchAll() as $row) {
        $stocks[(int)$row['sheet_type_id']] = (float)$row['stock'];
    }

    return $stocks;
}

function passepartout_sheet_in_stock(PDO $conn, int $passepartoutId, int $sheetTypeId): bool
{
    $stocks = get_passepartout_sheet_stocks($conn, $passepartoutId);

    return ($stocks[$sheetTypeId] ?? 0) > 0;
}

function get_passepartout_stocked_sheet_type_ids(PDO $conn, int $passepartoutId): array
{
    $stocks = get_passepartout_sheet_stocks($conn, $passepartoutId);

    return array_map(
        'intval',
        array_keys(array_filter($stocks, fn($stock) => (float)$stock > 0))
    );
}

function filter_passepartout_cuts_by_stock(PDO $conn, int $passepartoutId, array $cuts): array
{
    $stockedIds = get_passepartout_stocked_sheet_type_ids($conn, $passepartoutId);
    if (empty($stockedIds)) {
        return $cuts;
    }

    $stockedLookup = array_flip($stockedIds);

    return array_values(array_filter(
        $cuts,
        fn($cut) => isset($stockedLookup[(int)$cut['sheet_type_id']])
    ));
}

function save_passepartout_sheet_stocks(PDO $conn, int $passepartoutId, array $stocksBySheetType): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)'
        : 'INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock) VALUES (?, ?, ?) ON CONFLICT(passepartout_id, sheet_type_id) DO UPDATE SET stock = excluded.stock';
    $stmt = $conn->prepare($sql);

    foreach ($stocksBySheetType as $sheetTypeId => $stock) {
        $sheetTypeId = (int)$sheetTypeId;
        if ($sheetTypeId > 0) {
            $stmt->execute([$passepartoutId, $sheetTypeId, max(0, (float)$stock)]);
        }
    }

    $inStockTypeIds = [];
    foreach ($stocksBySheetType as $sheetTypeId => $stock) {
        if ((float)$stock > 0) {
            $inStockTypeIds[] = (int)$sheetTypeId;
        }
    }
    save_passepartout_sheet_types($conn, $passepartoutId, $inStockTypeIds);
}

function apply_passepartout_sheet_stock(
    PDO $conn,
    int $passepartoutId,
    int $sheetTypeId,
    float $usage,
    int $direction
): void {
    if ($passepartoutId <= 0 || $sheetTypeId <= 0 || $usage <= 0) {
        return;
    }

    $change = $direction > 0 ? $usage : -$usage;

    if ($change > 0) {
        $stmt = $conn->prepare('
            INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)
        ');
        if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $stmt = $conn->prepare('
                INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock)
                VALUES (?, ?, ?)
                ON CONFLICT(passepartout_id, sheet_type_id) DO UPDATE SET stock = stock + excluded.stock
            ');
        }
        $stmt->execute([$passepartoutId, $sheetTypeId, $change]);
        return;
    }

    $deduct = abs($change);
    $stmt = $conn->prepare('
        UPDATE passepartout_sheet_stock
        SET stock = CASE WHEN stock - ? < 0 THEN 0 ELSE stock - ? END
        WHERE passepartout_id = ? AND sheet_type_id = ?
    ');
    $stmt->execute([$deduct, $deduct, $passepartoutId, $sheetTypeId]);
}

function resolve_passepartout_stock_action(PDO $conn, array $order): ?array
{
    $passepartout = resolve_passepartout(
        $conn,
        isset($order['passepartout_id']) ? (int)$order['passepartout_id'] : null,
        $order['passepartout'] ?? null
    );

    if (!$passepartout) {
        return null;
    }

    $sheetTypeId = (int)($order['passepartout_sheet_type_id'] ?? 0);
    $usage = (float)($order['passepartout_sheet_usage'] ?? 0);

    if ($sheetTypeId <= 0 || $usage <= 0) {
        $billing = calculate_passepartout_billing($conn, array_merge($order, [
            'passepartout_id' => (int)$passepartout['id'],
        ]));
        if (!$billing) {
            return null;
        }
        $sheetTypeId = (int)$billing['sheet_type_id'];
        $usage = (float)$billing['sheet_usage'];
    }

    if ($sheetTypeId <= 0 || $usage <= 0) {
        return null;
    }

    return [
        'passepartout_id' => (int)$passepartout['id'],
        'sheet_type_id' => $sheetTypeId,
        'usage' => $usage,
    ];
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
        '80x100' => [[50, 80], [40, 50], [25, 40], [100, 100]],
    ];

    $cutStmt = $conn->prepare('INSERT INTO passepartout_cut_sizes (sheet_type_id, width_cm, height_cm) VALUES (?, ?, ?)');
    foreach ($cuts as $sheetName => $sizes) {
        foreach ($sizes as [$w, $h]) {
            $cutStmt->execute([$sheetIds[$sheetName], $w, $h]);
        }
    }
}

function ensure_oversize_passepartout_cuts(PDO $conn): void
{
    $delete = $conn->prepare('
        DELETE FROM passepartout_cut_sizes
        WHERE sheet_type_id = (
            SELECT id FROM passepartout_sheet_types WHERE name = ?
        ) AND width_cm = ? AND height_cm = ?
    ');
    $delete->execute(['80x120', 100, 120]);

    $check = $conn->prepare('
        SELECT COUNT(*) FROM passepartout_cut_sizes cs
        JOIN passepartout_sheet_types st ON st.id = cs.sheet_type_id
        WHERE st.name = ? AND cs.width_cm = ? AND cs.height_cm = ?
    ');
    $insert = $conn->prepare('
        INSERT INTO passepartout_cut_sizes (sheet_type_id, width_cm, height_cm)
        SELECT st.id, ?, ? FROM passepartout_sheet_types st WHERE st.name = ?
    ');

    $check->execute(['80x100', 100, 100]);
    if ((int)$check->fetchColumn() === 0) {
        $insert->execute([100, 100, '80x100']);
    }
}
