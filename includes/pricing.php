<?php

function profile_linear_meters(float $widthCm, float $heightCm, int $frameCount = 1): float
{
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return 2 * ($widthCm + $heightCm) / 100 * $frameCount;
}

function profile_type_includes_material_fir(string $profileType): bool
{
    return !in_array($profileType, ['metal', 'client_material'], true);
}

function profile_material_fir_cm(float $profileWidthCm): float
{
    if ($profileWidthCm <= 0) {
        return 0;
    }

    return $profileWidthCm * 8;
}

function profile_material_fir_linear_meters(float $profileWidthCm, int $frameCount = 1): float
{
    if ($profileWidthCm <= 0 || $frameCount < 1) {
        return 0;
    }

    return profile_material_fir_cm($profileWidthCm) / 100 * $frameCount;
}

/**
 * Л.м. за фирмен профил: 2×(ш+в)/100 + ширина×8/100 (фирата е извън скобите, не се умножава по 2).
 *
 * @return array{base_meters: float, fir_meters: float, total_meters: float}
 */
function profile_firm_material_linear_meters(
    float $widthCm,
    float $heightCm,
    float $profileWidthCm,
    int $frameCount = 1,
    string $profileType = 'wood'
): array {
    $baseMeters = profile_billing_linear_meters(
        $widthCm,
        $heightCm,
        $frameCount,
        $profileWidthCm > 0 ? $profileWidthCm : null,
        $profileType,
        true
    );
    $firMeters = profile_material_fir_linear_meters($profileWidthCm, $frameCount);

    return [
        'base_meters' => $baseMeters,
        'fir_meters' => $firMeters,
        'total_meters' => $baseMeters + $firMeters,
    ];
}

/**
 * Дълги страни: при добавка (фира = ширина × 8) от 80 см или повече страна над 240 см
 * се таксува по 290 см (пластмаса) или 300 см (дърво).
 */
function profile_long_side_billing_cm(float $sideCm, ?float $profileWidthCm, string $profileType, bool $hasFir): float
{
    if (!$hasFir || $profileWidthCm === null || profile_material_fir_cm($profileWidthCm) < 80 - 0.0001) {
        return $sideCm;
    }
    if ($sideCm <= 240) {
        return $sideCm;
    }

    return match ($profileType) {
        'plastic' => max($sideCm, 290.0),
        'wood' => max($sideCm, 300.0),
        default => $sideCm,
    };
}

function profile_billing_linear_meters(
    float $widthCm,
    float $heightCm,
    int $frameCount = 1,
    ?float $profileWidthCm = null,
    string $profileType = 'wood',
    bool $hasFir = false
): float {
    if ($widthCm <= 0 || $heightCm <= 0 || $frameCount < 1) {
        return 0;
    }

    $billWidth = profile_long_side_billing_cm($widthCm, $profileWidthCm, $profileType, $hasFir);
    $billHeight = profile_long_side_billing_cm($heightCm, $profileWidthCm, $profileType, $hasFir);

    return 2 * ($billWidth + $billHeight) / 100 * $frameCount;
}

/** '' / null → null; „2,5“ → 2.5 (не по-малко от 0). */
function parse_cm_value($value): ?float
{
    if ($value === null) {
        return null;
    }
    $text = trim(str_replace(',', '.', (string)$value));
    if ($text === '' || !is_numeric($text)) {
        return null;
    }

    return max(0.0, (float)$text);
}

/**
 * Четири страни [l, r, t, b]: попълнените отделни клетки имат предимство пред общата стойност.
 * $divideAll = true → общата стойност се разделя поравно на 4 (поле); false → важи за всяка страна (паспарту).
 */
function resolve_four_sides($all, $l, $r, $t, $b, bool $divideAll): array
{
    $sides = [parse_cm_value($l), parse_cm_value($r), parse_cm_value($t), parse_cm_value($b)];
    $hasIndividual = count(array_filter($sides, static fn ($v) => $v !== null)) > 0;
    if ($hasIndividual) {
        return array_map(static fn ($v) => $v ?? 0.0, $sides);
    }

    $allValue = parse_cm_value($all);
    if ($allValue === null) {
        return [0.0, 0.0, 0.0, 0.0];
    }
    $each = $divideAll ? $allValue / 4 : $allValue;

    return [$each, $each, $each, $each];
}

function order_field_margins(array $order): array
{
    return resolve_four_sides(
        $order['field_margin_all'] ?? null,
        $order['field_margin_l'] ?? null,
        $order['field_margin_r'] ?? null,
        $order['field_margin_t'] ?? null,
        $order['field_margin_b'] ?? null,
        true
    );
}

function decode_json_array($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function passepartout_offsets_from_entry(array $entry): array
{
    return resolve_four_sides(
        $entry['all'] ?? null,
        $entry['l'] ?? null,
        $entry['r'] ?? null,
        $entry['t'] ?? null,
        $entry['b'] ?? null,
        false
    );
}

/**
 * Слоеве материал (паспарту отгоре надолу, после фон) с отстоянията им.
 *
 * @return list<array{name: string, role: string, offsets: array}>
 */
function order_material_layers(array $order): array
{
    $use = normalize_passepartout_use($order['passepartout_use'] ?? '');
    if ($use === '') {
        $use = 'passepartout';
    }

    $stored = decode_json_array($order['passepartout_layers'] ?? null);
    $layers = [];

    if ($use !== 'background') {
        if ($stored !== []) {
            foreach ($stored as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $layers[] = [
                    'name' => trim((string)($entry['name'] ?? '')),
                    'role' => 'passepartout',
                    'offsets' => passepartout_offsets_from_entry($entry),
                ];
            }
        } else {
            $names = [];
            $primary = trim((string)($order['passepartout_primary_name'] ?? $order['passepartout'] ?? ''));
            if (str_contains($primary, ' + ')) {
                $primary = trim(explode(' + ', $primary, 2)[0]);
            }
            if ($primary !== '' && !str_starts_with($primary, 'Нулево')) {
                $names[] = $primary;
            }
            foreach (preg_split('/\s*,\s*/', trim((string)($order['additional_passepartouts'] ?? ''))) as $extra) {
                if (trim($extra) !== '') {
                    $names[] = trim($extra);
                }
            }
            foreach ($names as $name) {
                $layers[] = ['name' => $name, 'role' => 'passepartout', 'offsets' => [0.0, 0.0, 0.0, 0.0]];
            }
        }
    }

    if ($use === 'background' || $use === 'both') {
        $bgName = $use === 'background'
            ? trim((string)($order['passepartout'] ?? ''))
            : trim((string)($order['passepartout_background'] ?? ''));
        $bgOffsets = passepartout_offsets_from_entry(decode_json_array($order['passepartout_bg_offsets'] ?? null));
        if ($bgName !== '' || $bgOffsets !== [0.0, 0.0, 0.0, 0.0]) {
            $layers[] = ['name' => $bgName, 'role' => 'background', 'offsets' => $bgOffsets];
        }
    }

    return $layers;
}

/**
 * Размери за цена: картина (ред 1) − поле = основа; паспарту/фон = основа + отстояния;
 * рамката (профил, стъкло, гръб, окачване) обхваща най-голямото паспарту/фон.
 */
function order_pricing_geometry(array $order): array
{
    $pictureW = (float)str_replace(',', '.', (string)($order['width'] ?? 0));
    $pictureH = (float)str_replace(',', '.', (string)($order['height'] ?? 0));
    [$fl, $fr, $ft, $fb] = order_field_margins($order);

    $baseW = $pictureW > 0 ? max(0.0, $pictureW - $fl - $fr) : 0.0;
    $baseH = $pictureH > 0 ? max(0.0, $pictureH - $ft - $fb) : 0.0;

    $frameW = $baseW;
    $frameH = $baseH;
    $layers = [];
    foreach (order_material_layers($order) as $layer) {
        [$l, $r, $t, $b] = $layer['offsets'];
        $layer['width'] = $baseW > 0 ? round($baseW + $l + $r, 2) : 0.0;
        $layer['height'] = $baseH > 0 ? round($baseH + $t + $b, 2) : 0.0;
        $frameW = max($frameW, $layer['width']);
        $frameH = max($frameH, $layer['height']);
        $layers[] = $layer;
    }

    return [
        'picture_width' => $pictureW,
        'picture_height' => $pictureH,
        'field' => [$fl, $fr, $ft, $fb],
        'base_width' => round($baseW, 2),
        'base_height' => round($baseH, 2),
        'frame_width' => round($frameW, 2),
        'frame_height' => round($frameH, 2),
        'layers' => $layers,
    ];
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
    array $profileNames,
    string $frameMeasure = ''
): array {
    $totalCost = 0.0;
    $totalMeters = 0.0;
    $lines = [];
    $offset = 0.0;
    // „Външно“: мери се от външен ръб до външен ръб — няма фира, не се начислява добавка.
    $outerMeasure = $frameMeasure === 'outer';

    foreach ($profileNames as $name) {
        $profile = get_catalog_item_by_name($conn, 'profiles', $name);
        if (!$profile) {
            $lines[] = ['name' => $name, 'found' => false];
            continue;
        }

        $profileWidth = isset($profile['width_cm']) ? (float)$profile['width_cm'] : 0.0;
        $billW = $width + 2 * $offset;
        $billH = $height + 2 * $offset;
        $profileType = $profile['profile_type'] ?? 'wood';
        $isFirmMaterial = $profileType !== 'client_material';
        $applyFir = $isFirmMaterial && profile_type_includes_material_fir($profileType) && !$outerMeasure;
        $firCm = 0.0;
        $firMeters = 0.0;
        $baseMeters = 0.0;
        if ($applyFir && $profileWidth > 0) {
            $billing = profile_firm_material_linear_meters($billW, $billH, $profileWidth, $frameCount, $profileType);
            $baseMeters = $billing['base_meters'];
            $firMeters = $billing['fir_meters'];
            $meters = $billing['total_meters'];
            $firCm = profile_material_fir_cm($profileWidth);
        } else {
            $meters = profile_billing_linear_meters(
                $billW,
                $billH,
                $frameCount,
                $profileWidth > 0 ? $profileWidth : null,
                $profileType,
                false
            );
            $baseMeters = $meters;
        }
        $plainMeters = profile_linear_meters($billW, $billH, $frameCount);
        $longSideBilling = $baseMeters > $plainMeters + 0.0001;

        $cost = 0.0;
        if ($isFirmMaterial) {
            $cost = (float)$profile['price'] * $meters;
        }

        $totalCost += $cost;
        $totalMeters += $meters;
        $lines[] = [
            'name' => $name,
            'found' => true,
            'meters' => round($meters, 2),
            'base_meters' => round($baseMeters, 2),
            'fir_cm' => $firCm > 0 ? round($firCm, 2) : null,
            'fir_meters' => $firMeters > 0 ? round($firMeters, 2) : null,
            'bill_width' => $billW,
            'bill_height' => $billH,
            'cost' => round($cost, 2),
            'unit_price' => (float)$profile['price'],
            'firm_material' => $isFirmMaterial,
            'long_side_billing' => $longSideBilling,
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

function calculate_stacked_profile_labor(
    PDO $conn,
    float $width,
    float $height,
    int $frameCount,
    array $profileNames
): array {
    $totalLabor = 0.0;
    $lines = [];
    $offset = 0.0;
    $hasDimensions = $width > 0 && $height > 0;

    $outerBillW = 0.0;
    $outerBillH = 0.0;

    foreach ($profileNames as $name) {
        $profile = get_catalog_item_by_name($conn, 'profiles', $name);
        if (!$profile) {
            $lines[] = ['name' => $name, 'found' => false, 'cost' => 0.0];
            continue;
        }

        $profileWidth = isset($profile['width_cm']) ? (float)$profile['width_cm'] : 0.0;
        $billW = $width + 2 * $offset;
        $billH = $height + 2 * $offset;

        if ($hasDimensions) {
            $outerBillW = max($outerBillW, $billW);
            $outerBillH = max($outerBillH, $billH);
            $labor = calculate_frame_labor($conn, $billW, $billH, $profile, $frameCount, false);
        } else {
            $labor = calculate_frame_labor_minimum($conn, $profile, $frameCount);
        }

        $totalLabor += $labor;
        $lines[] = [
            'name' => $name,
            'found' => true,
            'cost' => round($labor, 2),
            'bill_width' => $hasDimensions ? $billW : null,
            'bill_height' => $hasDimensions ? $billH : null,
        ];

        $offset += $profileWidth;
    }

    if ($hasDimensions && $outerBillW > 0 && $outerBillH > 0) {
        $totalLabor += frame_labor_side_surcharge($conn, $outerBillW, $outerBillH) * $frameCount;
    }

    return [
        'labor_cost' => round($totalLabor, 2),
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

function frame_piece_square_meters(float $widthCm, float $heightCm): float
{
    if ($widthCm <= 0 || $heightCm <= 0) {
        return 0;
    }

    return $widthCm * $heightCm / 10000;
}

function assembly_area_tier_index(float $pieceSqm): int
{
    if ($pieceSqm <= 0) {
        return 0;
    }
    if ($pieceSqm <= 0.5) {
        return 1;
    }
    if ($pieceSqm <= 0.8) {
        return 2;
    }

    return 3;
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
    if (str_contains($name, ' + ')) {
        // „12 (паспарту) + 7 (фон)“ — първият лист е паспартуто.
        $name = trim(explode(' + ', $name, 2)[0]);
    }
    if ($name === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM passepartouts WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    if (preg_match('/^(.+?)\s+\([^)]+\)$/u', $name, $matches)) {
        $stmt->execute([trim($matches[1])]);

        return $stmt->fetch() ?: null;
    }

    return null;
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

/**
 * Надценка за големи рамки (веднъж на рамка, прилага се само най-високата приложима):
 * поне една страна ≥150 см → 3.00 €; и четирите ≥150 → 2 × 3.00 €;
 * поне една страна ≥200 см → 6.00 €; и четирите ≥200 → 2 × 6.00 €.
 */
function frame_labor_side_surcharge(PDO $conn, float $widthCm, float $heightCm): float
{
    if ($widthCm <= 0 || $heightCm <= 0) {
        return 0.0;
    }

    $s150 = get_pricing_setting($conn, 'frame_surcharge_side_150', 3.00);
    $s200 = get_pricing_setting($conn, 'frame_surcharge_side_200', 6.00);
    $longest = max($widthCm, $heightCm);
    $shortest = min($widthCm, $heightCm);

    $candidates = [0.0];
    if ($longest >= 150) {
        $candidates[] = $s150;
    }
    if ($shortest >= 150) {
        $candidates[] = 2 * $s150;
    }
    if ($longest >= 200) {
        $candidates[] = $s200;
    }
    if ($shortest >= 200) {
        $candidates[] = 2 * $s200;
    }

    return round(max($candidates), 2);
}

/**
 * Подрамка (основна дебелина 2.2 см; 2.6 см ×1.2, 3.3 см ×1.4 върху материал и труд).
 * Материал: до 50/70 — периметър; 50/70–70/100 — 3 × късата + 2 × дългата (една напречна летва);
 * 70/100–100/150 — 4 × късата + 2 × дългата (две летви). Над 100/150 — договорена цена.
 */
function calculate_subframe_pricing(PDO $conn, array $order, float $widthCm, float $heightCm, int $frameCount): ?array
{
    $thickness = trim((string)($order['subframe_thickness'] ?? ''));
    if (!array_key_exists($thickness, subframe_thickness_options()) || $thickness === '') {
        return null;
    }

    $factor = match ($thickness) {
        '2.6' => get_pricing_setting($conn, 'subframe_factor_26', 1.2),
        '3.3' => get_pricing_setting($conn, 'subframe_factor_33', 1.4),
        default => 1.0,
    };

    $short = min($widthCm, $heightCm);
    $long = max($widthCm, $heightCm);
    $manualPrice = parse_cm_value($order['subframe_manual_price'] ?? null);

    $tier = null;
    if ($short > 0 && $long > 0) {
        foreach ([1 => [30, 40], 2 => [50, 70], 3 => [70, 100], 4 => [100, 150]] as $index => [$maxShort, $maxLong]) {
            if ($short <= $maxShort + 0.001 && $long <= $maxLong + 0.001) {
                $tier = $index;
                break;
            }
        }
    }

    if ($tier === null) {
        $cost = $manualPrice !== null ? round($manualPrice, 2) : 0.0;

        return [
            'thickness' => $thickness,
            'tier' => null,
            'manual' => true,
            'needs_manual_price' => $manualPrice === null,
            'meters' => 0.0,
            'material_cost' => $cost,
            'labor_cost' => 0.0,
            'factor' => $factor,
            'cost' => $cost,
        ];
    }

    $metersPerPiece = match ($tier) {
        3 => (3 * $short + 2 * $long) / 100,
        4 => (4 * $short + 2 * $long) / 100,
        default => 2 * ($short + $long) / 100,
    };
    $pricePerLm = get_pricing_setting($conn, "subframe_lm_tier_{$tier}", [1 => 6.90, 2 => 6.90, 3 => 7.80, 4 => 9.00][$tier]);
    $laborPerPiece = $tier === 1 ? 0.0 : get_pricing_setting($conn, 'subframe_labor', 3.00);

    $material = round($metersPerPiece * $pricePerLm * $factor * $frameCount, 2);
    $labor = round($laborPerPiece * $factor * $frameCount, 2);

    return [
        'thickness' => $thickness,
        'tier' => $tier,
        'manual' => false,
        'needs_manual_price' => false,
        'meters' => round($metersPerPiece * $frameCount, 2),
        'price_per_lm' => $pricePerLm,
        'material_cost' => $material,
        'labor_cost' => $labor,
        'factor' => $factor,
        'cost' => round($material + $labor, 2),
    ];
}

function calculate_frame_labor(
    PDO $conn,
    float $widthCm,
    float $heightCm,
    ?array $mainProfile,
    int $frameCount,
    bool $applySideSurcharge = true
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

    $perFrame = $base + ($perCm * max(0, $profileWidthCm));
    $perFrame = max($perFrame, $min);

    if ($applySideSurcharge) {
        $perFrame += frame_labor_side_surcharge($conn, $widthCm, $heightCm);
    }

    return round($perFrame * $frameCount, 2);
}

/** {service_id: price} — договорени цени за услуги с клетка за цена. */
function parse_extra_services_manual_prices($raw): array
{
    $prices = [];
    foreach (decode_json_array($raw) as $id => $value) {
        $price = parse_cm_value($value);
        if ((int)$id > 0 && $price !== null) {
            $prices[(int)$id] = $price;
        }
    }

    return $prices;
}

/**
 * @param float $widthCm  ширина от ред 1 (за услуги по кв.м)
 * @param float $frameWidthCm ширина на рамката (за дистанционер)
 */
function calculate_extra_services_cost(
    PDO $conn,
    array $order,
    float $widthCm,
    float $heightCm,
    int $frameCount,
    ?float $frameWidthCm = null,
    ?float $frameHeightCm = null
): array {
    $ids = parse_extra_service_ids($order['extra_services'] ?? []);
    if (empty($ids)) {
        return ['total' => 0.0, 'lines' => []];
    }

    $sqm = glass_square_meters($widthCm, $heightCm, $frameCount);
    $manualPrices = parse_extra_services_manual_prices($order['extra_services_manual'] ?? null);
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
        $extraLabel = '';

        if ($unit === 'manual') {
            // Договорена/твърда цена за поръчката, въведена от служителя.
            $manual = $manualPrices[(int)$id] ?? null;
            $cost = $manual !== null ? round($manual, 2) : 0.0;
            if ($manual === null) {
                $extraLabel = ' (въведете цена)';
            }
        } elseif ($unit === 'sqm') {
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
            // €/бр. × брой рамки; минимумът (напр. „Монтаж на място — мин. 40 €“) е за поръчката.
            $cost = round($price * $frameCount, 2);
            if ($minPrice > 0 && $cost < $minPrice) {
                $cost = round($minPrice, 2);
            }
        }

        if (service_is_spacer($service)) {
            $spacer = calculate_spacer_profile_cost(
                $conn,
                (string)($order['spacer_profile'] ?? ''),
                $frameWidthCm ?? $widthCm,
                $frameHeightCm ?? $heightCm,
                $frameCount
            );
            if ($spacer !== null) {
                $cost = round($cost + $spacer['cost'], 2);
                $extraLabel = sprintf(' (+ дистанционер %s: %.2f л.м.)', $spacer['name'], $spacer['meters']);
            } else {
                $extraLabel = ' (изберете дистанционер)';
            }
        }

        $total += $cost;
        $lines[] = ['name' => $service['name'] . $extraLabel, 'cost' => round($cost, 2)];
    }

    return ['total' => round($total, 2), 'lines' => $lines];
}

/** Дистанционерът е профил от склада: цена €/л.м. × периметъра на рамката (без фира). */
function calculate_spacer_profile_cost(PDO $conn, string $profileName, float $widthCm, float $heightCm, int $frameCount): ?array
{
    $profileName = trim($profileName);
    if ($profileName === '' || $widthCm <= 0 || $heightCm <= 0) {
        return null;
    }
    $profile = get_catalog_item_by_name($conn, 'profiles', $profileName);
    if (!$profile) {
        return null;
    }
    $meters = profile_linear_meters($widthCm, $heightCm, $frameCount);

    return [
        'name' => $profile['name'],
        'meters' => round($meters, 2),
        'unit_price' => (float)$profile['price'],
        'stock' => (float)($profile['stock'] ?? 0),
        'cost' => round($meters * (float)$profile['price'], 2),
    ];
}

/** urgent: 0 — не; 1 — за същия ден (до 12:00); 2 — за следващия ден (до 15:00). */
function order_urgent_percent(PDO $conn, array $order): float
{
    return match ((int)($order['urgent'] ?? 0)) {
        1 => get_pricing_setting($conn, 'urgent_surcharge_percent', 50),
        2 => get_pricing_setting($conn, 'urgent_next_day_percent', 30),
        default => 0.0,
    };
}

function apply_order_price_modifiers(PDO $conn, array $order, float $subtotal, float $passepartoutCost, int $frameCount): array
{
    $complexSurcharge = 0.0;
    if (!empty($order['complex_passepartout']) && $passepartoutCost > 0) {
        $complexMin = get_pricing_setting($conn, 'passepartout_complex_min', 6.10) * $frameCount;
        $complexSurcharge = round(max($passepartoutCost * 0.5, $complexMin), 2);
    }

    $afterComplex = $subtotal + $complexSurcharge;

    // Отстъпките са само ръчни (автоматичната обемна и ученическата отпаднаха).
    $manualPercent = max(0, min(100, (float)($order['manual_discount'] ?? 0)));
    $manualDiscount = round($afterComplex * $manualPercent / 100, 2);
    $afterDiscounts = max(0, $afterComplex - $manualDiscount);

    $urgentPercent = order_urgent_percent($conn, $order);
    $urgentSurcharge = $urgentPercent > 0 ? round($afterDiscounts * $urgentPercent / 100, 2) : 0.0;

    $complexOrderPercent = max(0, min(100, (float)($order['complex_order_percent'] ?? 0)));
    $complexOrderSurcharge = $complexOrderPercent > 0 ? round($afterDiscounts * $complexOrderPercent / 100, 2) : 0.0;

    $total = round($afterDiscounts + $urgentSurcharge + $complexOrderSurcharge, 2);

    return [
        'complex_passepartout_surcharge' => $complexSurcharge,
        'volume_discount' => 0.0,
        'student_discount_amount' => 0.0,
        'manual_discount' => $manualDiscount,
        'urgent_surcharge' => $urgentSurcharge,
        'urgent_percent' => $urgentPercent,
        'complex_order_surcharge' => $complexOrderSurcharge,
        'complex_order_percent' => $complexOrderPercent,
        'subtotal' => round($subtotal, 2),
        'total' => $total,
    ];
}

function calculate_hanging_pricing(
    PDO $conn,
    string $name,
    float $widthCm,
    float $heightCm,
    int $frameCount,
    float $weightKg = 0.0
): array {
    $empty = [
        'cost' => 0.0,
        'unit_price' => 0.0,
        'linear_meters' => 0.0,
        'tier_label' => null,
        'weight_kg' => $weightKg > 0 ? round($weightKg, 2) : null,
        'uses_weight_tiers' => false,
        'min_per_piece' => 0.0,
    ];

    $item = get_catalog_item_by_name($conn, 'hanging_options', $name);
    if (!$item || $frameCount < 1) {
        return $empty;
    }

    $unitPrice = (float)$item['price'];
    $minPerPiece = (float)($item['min_price'] ?? 0);
    $tierLabel = null;
    $usesWeightTiers = false;

    if (is_vrazka_hanging($name) && ($item['pricing_unit'] ?? 'piece') === 'lm') {
        $tier = resolve_vrazka_weight_tier($conn, $weightKg);
        if ($tier) {
            $unitPrice = (float)$tier['price_per_lm'];
            $tierLabel = $tier['label'];
            $usesWeightTiers = true;
        }
    }

    if ($widthCm <= 0 || $heightCm <= 0) {
        $chargedPerPiece = ($minPerPiece > 0 && $unitPrice < $minPerPiece) ? $minPerPiece : $unitPrice;

        return array_merge($empty, [
            'cost' => round($chargedPerPiece * $frameCount, 2),
            'unit_price' => $unitPrice,
            'tier_label' => $tierLabel,
            'uses_weight_tiers' => $usesWeightTiers,
            'min_per_piece' => $minPerPiece,
        ]);
    }

    if (($item['pricing_unit'] ?? 'piece') === 'lm') {
        $lmPerPiece = profile_linear_meters($widthCm, $heightCm, 1);
        $calculatedPerPiece = $lmPerPiece * $unitPrice;
        $chargedPerPiece = ($minPerPiece > 0 && $calculatedPerPiece < $minPerPiece)
            ? $minPerPiece
            : $calculatedPerPiece;

        return array_merge($empty, [
            'cost' => round($chargedPerPiece * $frameCount, 2),
            'unit_price' => $unitPrice,
            'linear_meters' => round($lmPerPiece * $frameCount, 2),
            'tier_label' => $tierLabel,
            'uses_weight_tiers' => $usesWeightTiers,
            'min_per_piece' => $minPerPiece,
        ]);
    }

    $chargedPerPiece = ($minPerPiece > 0 && $unitPrice < $minPerPiece) ? $minPerPiece : $unitPrice;

    return array_merge($empty, [
        'cost' => round($chargedPerPiece * $frameCount, 2),
        'unit_price' => $unitPrice,
        'min_per_piece' => $minPerPiece,
    ]);
}

function calculate_hanging_cost(
    PDO $conn,
    string $name,
    float $widthCm,
    float $heightCm,
    int $frameCount,
    float $weightKg = 0.0
): float {
    return calculate_hanging_pricing($conn, $name, $widthCm, $heightCm, $frameCount, $weightKg)['cost'];
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
    $kind = trim($passepartout['price_kind'] ?? 'manual');
    if ($kind !== '' && $kind !== 'manual') {
        $definitions = get_passepartout_price_kind_definitions();
        if (isset($definitions[$kind])) {
            return (float)($definitions[$kind]['tiers'][$tierIndex - 1] ?? 0);
        }
    }

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

function order_has_passepartout_work(array $order, ?array $passepartoutBilling): bool
{
    if (trim((string)($order['passepartout'] ?? '')) !== '') {
        return true;
    }
    if (trim((string)($order['additional_passepartouts'] ?? '')) !== '') {
        return true;
    }
    if ($passepartoutBilling !== null && trim((string)($passepartoutBilling['passepartout_name'] ?? '')) !== '') {
        return true;
    }

    return false;
}

function calculate_passepartout_labor_cost(
    PDO $conn,
    array $order,
    ?array $passepartoutBilling,
    int $frameCount
): float {
    if ($frameCount < 1) {
        return 0.0;
    }

    $openings = max(1, (int)($order['passepartout_openings'] ?? 1));
    $extraOpening = get_pricing_setting($conn, 'passepartout_multi_opening', 1.22);
    $hasPassepartout = order_has_passepartout_work($order, $passepartoutBilling);
    $clientCutting = !empty($order['client_passepartout_cutting']);

    $labor = 0.0;

    if ($clientCutting) {
        $cutting = get_pricing_setting($conn, 'passepartout_cutting_labor', 1.84);
        $labor += $cutting * $frameCount;
    }

    if ($openings > 1 && ($hasPassepartout || $clientCutting)) {
        $labor += max(0, $openings - 1) * $extraOpening * $frameCount;
    }

    return round($labor, 2);
}

function calculate_passepartout_layer_billing(PDO $conn, array $order, int $passepartoutId): ?array
{
    if ($passepartoutId <= 0) {
        return null;
    }

    $passepartout = resolve_passepartout($conn, $passepartoutId, null);
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
        'sheet_usage' => round($sheetUsage, 4),
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

/** Поръчката с размерите на конкретен слой (паспарту/фон) — за фактуриране и склад. */
function order_with_layer_dimensions(array $order, ?array $layer): array
{
    if ($layer === null || ($layer['width'] ?? 0) <= 0 || ($layer['height'] ?? 0) <= 0) {
        return $order;
    }

    return array_merge($order, ['width' => $layer['width'], 'height' => $layer['height']]);
}

/** [първи слой паспарту, допълнителни слоеве паспарту, фон] с изчислени размери. */
function order_geometry_layer_groups(array $order): array
{
    $primary = null;
    $extra = [];
    $background = null;
    foreach (order_pricing_geometry($order)['layers'] as $layer) {
        if ($layer['role'] === 'background') {
            $background = $layer;
        } elseif ($primary === null) {
            $primary = $layer;
        } else {
            $extra[] = $layer;
        }
    }

    return [$primary, $extra, $background];
}

function calculate_passepartout_billing(PDO $conn, array $order): ?array
{
    $use = normalize_passepartout_use($order['passepartout_use'] ?? '');
    if ($use === '') {
        $use = 'passepartout';
    }

    [$primaryGeo, , $backgroundGeo] = order_geometry_layer_groups($order);
    $primaryOrder = order_with_layer_dimensions($order, $use === 'background' ? $backgroundGeo : $primaryGeo);

    $primaryId = (int)($order['passepartout_id'] ?? 0);
    if ($primaryId <= 0) {
        $resolved = resolve_passepartout($conn, null, $order['passepartout'] ?? null);
        $primaryId = $resolved ? (int)$resolved['id'] : 0;
    }

    if ($use === 'both') {
        $backgroundId = (int)($order['passepartout_background_id'] ?? 0);
        if ($backgroundId <= 0 && !empty($order['passepartout_background'])) {
            $resolvedBg = resolve_passepartout($conn, null, $order['passepartout_background']);
            $backgroundId = $resolvedBg ? (int)$resolvedBg['id'] : 0;
        }

        $primaryLayer = $primaryId > 0 ? calculate_passepartout_layer_billing($conn, $primaryOrder, $primaryId) : null;
        $backgroundLayer = $backgroundId > 0
            ? calculate_passepartout_layer_billing($conn, order_with_layer_dimensions($order, $backgroundGeo), $backgroundId)
            : null;

        if (!$primaryLayer && !$backgroundLayer) {
            return null;
        }

        $totalCost = round((float)($primaryLayer['cost'] ?? 0) + (float)($backgroundLayer['cost'] ?? 0), 2);
        $base = $primaryLayer ?? $backgroundLayer;

        return [
            'passepartout_id' => (int)($primaryLayer['passepartout_id'] ?? 0),
            'passepartout_name' => $primaryLayer['passepartout_name'] ?? '',
            'background_layer' => $backgroundLayer,
            'passepartout_use' => 'both',
            'material_layers' => ($primaryLayer ? 1 : 0) + ($backgroundLayer ? 1 : 0),
            'bill_width' => $base['bill_width'],
            'bill_height' => $base['bill_height'],
            'sheet_name' => $base['sheet_name'],
            'sheet_type_id' => $primaryLayer['sheet_type_id'] ?? null,
            'sheet_usage' => $primaryLayer['sheet_usage'] ?? 0,
            'square_meters' => $base['square_meters'],
            'unit_price' => $base['unit_price'],
            'pricing_mode' => $base['pricing_mode'],
            'tier_index' => $base['tier_index'],
            'tier_label' => $base['tier_label'],
            'cost' => $totalCost,
            'use_label' => 'паспарту + фон',
            'sheets_charged' => $base['sheets_charged'],
            'multi_sheet_billing' => !empty($base['multi_sheet_billing']),
            'oversize' => !empty($base['oversize']),
            'requested_width' => $base['requested_width'],
            'requested_height' => $base['requested_height'],
        ];
    }

    if ($primaryId <= 0) {
        return null;
    }

    $layer = calculate_passepartout_layer_billing($conn, $primaryOrder, $primaryId);
    if (!$layer) {
        return null;
    }

    return array_merge($layer, [
        'passepartout_use' => $use,
        'material_layers' => 1,
        'background_layer' => null,
        'use_label' => match ($use) {
            'background' => 'фон',
            default => 'паспарту',
        },
    ]);
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
    $geometry = order_pricing_geometry($order);
    // Размери на рамката: картина − поле, разширени до най-голямото паспарту/фон.
    $width = $geometry['frame_width'];
    $height = $geometry['frame_height'];
    // Размери от ред 1 — за услуги по кв.м и подрамка.
    $pictureWidth = $geometry['picture_width'];
    $pictureHeight = $geometry['picture_height'];
    $frameCount = max(1, (int)($order['frame_count'] ?? 1));
    $frameMeasure = in_array($order['frame_measure'] ?? '', ['clear', 'outer'], true) ? $order['frame_measure'] : '';

    $profileMaterialCost = 0.0;
    $profileMeters = 0.0;
    $profileLines = [];
    $mainProfile = null;
    $profileName = trim($order['profile'] ?? '');
    $profileNames = $profileName !== '' ? get_order_profile_names($order) : [];

    if ($profileName !== '') {
        $mainProfile = get_catalog_item_by_name($conn, 'profiles', $profileName);
    }

    $readyFrameCost = 0.0;
    $usesReadyFrame = !empty($order['profile_ready_frame']);

    if ($width > 0 && $height > 0 && !empty($profileNames) && !$usesReadyFrame) {
        $stacked = calculate_stacked_profile_material($conn, $width, $height, $frameCount, $profileNames, $frameMeasure);
        $profileMaterialCost = $stacked['material_cost'];
        $profileMeters = $stacked['total_meters'];
        $profileLines = $stacked['lines'];
    }

    if ($usesReadyFrame) {
        $profileMaterialCost = 0.0;
        $profileMeters = 0.0;
        $profileLines = [];
        $unitReady = max(0, (float)($order['profile_ready_frame_price'] ?? 0));
        $readyFrameCost = round($unitReady * $frameCount, 2);
    }

    $frameLaborCost = 0.0;
    if (!empty($profileNames)) {
        $stackedLabor = calculate_stacked_profile_labor($conn, $width, $height, $frameCount, $profileNames);
        $frameLaborCost = $stackedLabor['labor_cost'];
        foreach ($profileLines as $index => &$profileLine) {
            if (isset($stackedLabor['lines'][$index]['cost'])) {
                $profileLine['labor_cost'] = $stackedLabor['lines'][$index]['cost'];
            }
        }
        unset($profileLine);
    }

    // Нестандартна фигура: метраж (по правоъгълника) × 1.25 × цена на профила × коефициент; мин. 10 €/бр.
    $frameShape = (string)($order['frame_shape'] ?? '');
    $frameShapeLabel = '';
    $shapeCoefficient = frame_shape_coefficient($conn, $frameShape);
    if ($shapeCoefficient > 0 && $profileName !== '' && !$usesReadyFrame) {
        $meterFactor = get_pricing_setting($conn, 'frame_shape_material_factor', 1.25);
        $shapeMin = get_pricing_setting($conn, 'frame_shape_min_price', 10.00) * $frameCount;
        $shapedCost = 0.0;
        $shapedMeters = 0.0;
        foreach ($profileLines as &$profileLine) {
            if (empty($profileLine['found'])) {
                continue;
            }
            $profileLine['meters'] = round($profileLine['meters'] * $meterFactor, 2);
            $profileLine['cost'] = !empty($profileLine['firm_material'])
                ? round($profileLine['meters'] * $profileLine['unit_price'] * $shapeCoefficient, 2)
                : 0.0;
            $shapedCost += $profileLine['cost'];
            $shapedMeters += $profileLine['meters'];
        }
        unset($profileLine);
        $profileMeters = round($shapedMeters, 2);
        $profileMaterialCost = round(max($shapedCost, $shapeMin), 2);
        $frameShapeLabel = sprintf(
            '%s: метраж ×%s, цена ×%s, мин. %s €/бр.',
            frame_shape_label($frameShape),
            rtrim(rtrim(number_format($meterFactor, 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($shapeCoefficient, 2, '.', ''), '0'), '.'),
            number_format($shapeMin / $frameCount, 2, '.', '')
        );
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
        $frameExtrasLines[] = ['name' => 'Нестандартна изработка', 'cost' => $nonstandardCost];
    }
    if (!empty($order['frame_high_complexity'])) {
        $frameValue = $profileMaterialCost + $frameLaborCost;
        $complexityCost = $frameValue > 0
            ? round($frameValue * 0.5, 2)
            : round(get_pricing_setting($conn, 'frame_labor_min', 3.54) * 0.5 * $frameCount, 2);
        $frameExtrasCost += $complexityCost;
        $frameExtrasLines[] = ['name' => 'Висока сложност (+50%)', 'cost' => $complexityCost];
    }

    $profileCost = round($profileMaterialCost + $frameLaborCost + $frameExtrasCost + $readyFrameCost, 2);

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
    $hangingDetail = null;
    $hangingName = trim($order['hanging'] ?? '');
    if ($hangingName !== '') {
        $weightKg = max(0, (float)($order['weight_kg'] ?? 0));
        $hangingDetail = calculate_hanging_pricing($conn, $hangingName, $width, $height, $frameCount, $weightKg);
        $hangingCost = $hangingDetail['cost'];
    }

    $hangerName = trim($order['hanger'] ?? '');
    $hangerCount = 0;
    $hangerCost = 0.0;
    if ($hangerName !== '') {
        $hangerCount = order_hanger_count($order);
        $hangerCost = round(
            calculate_hanging_cost($conn, $hangerName, $width, $height, $frameCount, 0) * $hangerCount,
            2
        );
        $hangingCost = round($hangingCost + $hangerCost, 2);
    }

    $passepartout = calculate_passepartout_billing($conn, $order);
    $passepartoutMaterialCost = $passepartout['cost'] ?? 0.0;
    $passepartoutExtraLayers = [];
    [, $extraLayerGeometry] = order_geometry_layer_groups($order);
    $use = normalize_passepartout_use($order['passepartout_use'] ?? '') ?: 'passepartout';
    if ($use !== 'background') {
        foreach ($extraLayerGeometry as $extraGeo) {
            $resolvedExtra = resolve_passepartout($conn, null, $extraGeo['name']);
            if (!$resolvedExtra) {
                continue;
            }
            $extraLayer = calculate_passepartout_layer_billing(
                $conn,
                order_with_layer_dimensions($order, $extraGeo),
                (int)$resolvedExtra['id']
            );
            if ($extraLayer) {
                $passepartoutMaterialCost += (float)($extraLayer['cost'] ?? 0);
                $passepartoutExtraLayers[] = $extraLayer;
            }
        }
    }
    $openings = max(1, (int)($order['passepartout_openings'] ?? 1));
    $passepartoutLaborCost = calculate_passepartout_labor_cost($conn, $order, $passepartout, $frameCount);
    $passepartoutCost = round($passepartoutMaterialCost + $passepartoutLaborCost, 2);

    $extraServices = calculate_extra_services_cost(
        $conn,
        $order,
        $pictureWidth,
        $pictureHeight,
        $frameCount,
        $width,
        $height
    );
    $subframe = calculate_subframe_pricing($conn, $order, $pictureWidth, $pictureHeight, $frameCount);
    $subframeCost = $subframe['cost'] ?? 0.0;

    $materialsSubtotal = round(
        $profileCost + $glassCost + $backCost + $hangingCost + $passepartoutCost + $extraServices['total'] + $subframeCost,
        2
    );

    $modifiers = apply_order_price_modifiers($conn, $order, $materialsSubtotal, $passepartoutCost, $frameCount);

    $longSideBilling = false;
    foreach ($profileLines as $line) {
        $longSideBilling = $longSideBilling || !empty($line['long_side_billing']);
    }

    return [
        'geometry' => [
            'picture_width' => $pictureWidth,
            'picture_height' => $pictureHeight,
            'base_width' => $geometry['base_width'],
            'base_height' => $geometry['base_height'],
            'frame_width' => $width,
            'frame_height' => $height,
            'field' => $geometry['field'],
            'layers' => array_map(static fn (array $layer): array => [
                'name' => $layer['name'],
                'role' => $layer['role'],
                'width' => $layer['width'],
                'height' => $layer['height'],
            ], $geometry['layers']),
        ],
        'frame_measure' => $frameMeasure,
        'profile_material_cost' => round($profileMaterialCost, 2),
        'profile_ready_frame_cost' => round($readyFrameCost, 2),
        'frame_labor_cost' => $frameLaborCost,
        'frame_side_surcharge' => ($width > 0 && $height > 0 && !empty($profileNames))
            ? round(frame_labor_side_surcharge($conn, $width, $height) * $frameCount, 2)
            : 0.0,
        'profile_cost' => $profileCost,
        'profile_meters' => round($profileMeters, 2),
        'profile_lines' => array_map(static function (array $line): array {
            return [
                'name' => $line['name'],
                'found' => !empty($line['found']),
                'meters' => (float)($line['meters'] ?? 0),
                'cost' => (float)($line['cost'] ?? 0),
                'labor_cost' => (float)($line['labor_cost'] ?? 0),
                'bill_width' => isset($line['bill_width']) ? round((float)$line['bill_width'], 1) : null,
                'bill_height' => isset($line['bill_height']) ? round((float)$line['bill_height'], 1) : null,
                'base_meters' => isset($line['base_meters']) ? (float)$line['base_meters'] : null,
                'fir_cm' => isset($line['fir_cm']) ? (float)$line['fir_cm'] : null,
                'fir_meters' => isset($line['fir_meters']) ? (float)$line['fir_meters'] : null,
                'long_side_billing' => !empty($line['long_side_billing']),
            ];
        }, $profileLines),
        'profile_wide_billing' => $longSideBilling,
        'glass_cost' => $glassCost,
        'glass_sqm' => round($glassSqm, 4),
        'back_cost' => $backCost,
        'back_sqm' => round($backSqm, 4),
        'hanging_cost' => $hangingCost,
        'hanging' => $hangingDetail,
        'hanger_count' => $hangerCount,
        'hanger_cost' => $hangerCost,
        'passepartout' => $passepartout,
        'passepartout_extra_layers' => $passepartoutExtraLayers,
        'passepartout_openings' => $openings,
        'passepartout_material_cost' => round($passepartoutMaterialCost, 2),
        'passepartout_labor_cost' => round($passepartoutLaborCost, 2),
        'passepartout_cost' => $passepartoutCost,
        'extra_services' => $extraServices['lines'],
        'extra_services_cost' => $extraServices['total'],
        'subframe' => $subframe,
        'subframe_cost' => round($subframeCost, 2),
        'materials_subtotal' => $materialsSubtotal,
        'complex_passepartout_surcharge' => $modifiers['complex_passepartout_surcharge'],
        'volume_discount' => 0.0,
        'student_discount_amount' => 0.0,
        'manual_discount_amount' => $modifiers['manual_discount'],
        'manual_discount_percent' => max(0, min(100, (float)($order['manual_discount'] ?? 0))),
        'urgent_surcharge' => $modifiers['urgent_surcharge'],
        'urgent_percent' => $modifiers['urgent_percent'],
        'complex_order_surcharge' => $modifiers['complex_order_surcharge'],
        'complex_order_percent' => $modifiers['complex_order_percent'],
        'profile_found' => $profileName !== '' ? (bool)$mainProfile : null,
        'frame_shape' => $frameShapeLabel,
        'frame_extras' => $frameExtrasLines,
        'frame_extras_cost' => round($frameExtrasCost, 2),
        'subtotal' => $modifiers['subtotal'],
        'total' => $modifiers['total'],
    ];
}

/** Брой закачалки на рамка (по подразбиране 2). */
function order_hanger_count(array $order): int
{
    $raw = $order['hanger_count'] ?? null;
    if ($raw === null || $raw === '') {
        return 2;
    }

    return max(0, (int)$raw);
}

function enrich_order_pricing_fields(PDO $conn, array $order): array
{
    $pricing = calculate_order_pricing($conn, $order);
    $passepartout = $pricing['passepartout'];

    $order['passepartout_bill_width'] = $passepartout['bill_width'] ?? null;
    $order['passepartout_bill_height'] = $passepartout['bill_height'] ?? null;
    $order['passepartout_sheet_type_id'] = $passepartout['sheet_type_id'] ?? null;
    $order['passepartout_sheet_usage'] = $passepartout['sheet_usage'] ?? null;
    $use = normalize_passepartout_use($order['passepartout_use'] ?? ($passepartout['passepartout_use'] ?? 'passepartout'));
    $order['passepartout_use'] = $use;

    $bgLayer = $passepartout['background_layer'] ?? null;
    $order['passepartout_bg_sheet_type_id'] = $bgLayer['sheet_type_id'] ?? null;
    $order['passepartout_bg_sheet_usage'] = $bgLayer['sheet_usage'] ?? null;

    if ($use === 'both') {
        $ppName = trim($passepartout['passepartout_name'] ?? '');
        $bgName = trim($bgLayer['passepartout_name'] ?? '');
        $order['passepartout'] = format_passepartout_both_order_label($ppName, $bgName);
        $order['passepartout_background'] = $bgName;
    } elseif (!empty($passepartout['passepartout_name'])) {
        $order['passepartout'] = format_passepartout_order_label($passepartout['passepartout_name'], $use);
        $order['passepartout_background'] = '';
        $order['passepartout_bg_sheet_type_id'] = null;
        $order['passepartout_bg_sheet_usage'] = null;
    }

    if (!empty($passepartout['passepartout_id'])) {
        $order['passepartout_id'] = $passepartout['passepartout_id'];
    }

    // Допълнителните паспартута (2, 3…) се записват с поръчката, за да се отчислят и върнат точно.
    $order['passepartout_extra_stock'] = json_encode(resolve_extra_passepartout_stock_actions($conn, $order));

    return $order;
}

/** Склад за допълнителните слоеве паспарту (изчислено по размера на всеки слой). */
function resolve_extra_passepartout_stock_actions(PDO $conn, array $order): array
{
    $use = normalize_passepartout_use($order['passepartout_use'] ?? '') ?: 'passepartout';
    if ($use === 'background') {
        return [];
    }
    [, $extraLayers] = order_geometry_layer_groups($order);
    $actions = [];
    foreach ($extraLayers as $layer) {
        $pp = resolve_passepartout($conn, null, $layer['name']);
        if (!$pp) {
            continue;
        }
        $billing = calculate_passepartout_layer_billing($conn, order_with_layer_dimensions($order, $layer), (int)$pp['id']);
        if ($billing && (int)$billing['sheet_type_id'] > 0 && (float)$billing['sheet_usage'] > 0) {
            $actions[] = [
                'passepartout_id' => (int)$pp['id'],
                'sheet_type_id' => (int)$billing['sheet_type_id'],
                'usage' => (float)$billing['sheet_usage'],
            ];
        }
    }

    return $actions;
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
        $stmt = $conn->prepare($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)'
            : 'INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock) VALUES (?, ?, ?)
               ON CONFLICT(passepartout_id, sheet_type_id) DO UPDATE SET stock = stock + excluded.stock');
        $stmt->execute([$passepartoutId, $sheetTypeId, $change]);
        return;
    }

    // Без отрязване на 0: иначе връщането при изтриване/редакция добавя материал, който не е бил отчислен.
    // Отрицателна наличност = липсващо количество (виж „Материалът е наличен“).
    $deduct = abs($change);
    $stmt = $conn->prepare('
        INSERT INTO passepartout_sheet_stock (passepartout_id, sheet_type_id, stock)
        VALUES (?, ?, ?)
        ' . ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)'
            : 'ON CONFLICT(passepartout_id, sheet_type_id) DO UPDATE SET stock = stock + excluded.stock') . '
    ');
    $stmt->execute([$passepartoutId, $sheetTypeId, -$deduct]);
}

function resolve_passepartout_stock_actions(PDO $conn, array $order): array
{
    $actions = [];
    $use = normalize_passepartout_use($order['passepartout_use'] ?? '');

    $primary = resolve_passepartout(
        $conn,
        isset($order['passepartout_id']) ? (int)$order['passepartout_id'] : null,
        $order['passepartout'] ?? null
    );

    if ($primary) {
        $sheetTypeId = (int)($order['passepartout_sheet_type_id'] ?? 0);
        $usage = (float)($order['passepartout_sheet_usage'] ?? 0);

        if ($sheetTypeId <= 0 || $usage <= 0) {
            $billing = calculate_passepartout_billing($conn, array_merge($order, [
                'passepartout_id' => (int)$primary['id'],
            ]));
            if ($billing) {
                $sheetTypeId = (int)($billing['sheet_type_id'] ?? 0);
                $usage = (float)($billing['sheet_usage'] ?? 0);
            }
        }

        if ($sheetTypeId > 0 && $usage > 0) {
            $actions[] = [
                'passepartout_id' => (int)$primary['id'],
                'sheet_type_id' => $sheetTypeId,
                'usage' => $usage,
            ];
        }
    }

    if ($use === 'both') {
        $bgPp = resolve_passepartout(
            $conn,
            isset($order['passepartout_background_id']) ? (int)$order['passepartout_background_id'] : null,
            $order['passepartout_background'] ?? null
        );
        if ($bgPp) {
            $sheetTypeId = (int)($order['passepartout_bg_sheet_type_id'] ?? 0);
            $usage = (float)($order['passepartout_bg_sheet_usage'] ?? 0);

            if ($sheetTypeId <= 0 || $usage <= 0) {
                [, , $backgroundGeo] = order_geometry_layer_groups($order);
                $layer = calculate_passepartout_layer_billing(
                    $conn,
                    order_with_layer_dimensions($order, $backgroundGeo),
                    (int)$bgPp['id']
                );
                if ($layer) {
                    $sheetTypeId = (int)$layer['sheet_type_id'];
                    $usage = (float)$layer['sheet_usage'];
                }
            }

            if ($sheetTypeId > 0 && $usage > 0) {
                $actions[] = [
                    'passepartout_id' => (int)$bgPp['id'],
                    'sheet_type_id' => $sheetTypeId,
                    'usage' => $usage,
                ];
            }
        }
    }

    // Само за поръчки, записани след добавянето (старите не са отчислявали тези слоеве).
    foreach (decode_json_array($order['passepartout_extra_stock'] ?? null) as $extra) {
        if (is_array($extra) && (int)($extra['passepartout_id'] ?? 0) > 0 && (float)($extra['usage'] ?? 0) > 0) {
            $actions[] = [
                'passepartout_id' => (int)$extra['passepartout_id'],
                'sheet_type_id' => (int)$extra['sheet_type_id'],
                'usage' => (float)$extra['usage'],
            ];
        }
    }

    return $actions;
}

function resolve_passepartout_stock_action(PDO $conn, array $order): ?array
{
    $actions = resolve_passepartout_stock_actions($conn, $order);

    return $actions[0] ?? null;
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
