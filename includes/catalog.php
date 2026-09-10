<?php

function profile_lookup_key(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $replacements = [
        'а' => 'a', 'б' => 'b', 'в' => 'b', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'i',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'c', 'ш' => 's',
        'щ' => 's', 'ъ' => 'a', 'ь' => '', 'ю' => 'u', 'я' => 'a',
    ];

    $out = '';
    foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $out .= $replacements[$char] ?? $char;
    }

    return $out;
}

function get_catalog_item_by_name(PDO $conn, string $table, string $name): ?array
{
    $allowed = ['profiles', 'glasses', 'passepartouts', 'backs', 'hanging_options'];
    $name = trim($name);
    if ($name === '' || !in_array($table, $allowed, true)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE TRIM(name) = ?");
    $stmt->execute([$name]);
    $item = $stmt->fetch();
    if ($item) {
        return $item;
    }

    if ($table !== 'profiles') {
        return null;
    }

    $lookupKey = profile_lookup_key($name);
    foreach ($conn->query('SELECT * FROM profiles')->fetchAll() as $row) {
        if (profile_lookup_key($row['name'] ?? '') === $lookupKey) {
            return $row;
        }
    }

    return null;
}

function hanging_shares_hanger_pool(string $name): bool
{
    return in_array(trim($name), ['Закачалка', 'Две закачалки'], true);
}

function get_hanger_pool_stock(PDO $conn): float
{
    $item = get_catalog_item_by_name($conn, 'hanging_options', 'Закачалка');

    return $item ? (float)$item['stock'] : 0.0;
}

function resolve_hanging_stock_deduction(
    PDO $conn,
    string $hangingName,
    int $frameCount,
    float $widthCm,
    float $heightCm
): ?array {
    $hangingName = trim($hangingName);
    if ($hangingName === '' || $frameCount < 1) {
        return null;
    }

    if ($hangingName === 'Две закачалки') {
        return [
            'stock_name' => 'Закачалка',
            'amount' => 2 * $frameCount,
        ];
    }

    $item = get_catalog_item_by_name($conn, 'hanging_options', $hangingName);
    if (!$item) {
        return null;
    }

    if (($item['pricing_unit'] ?? 'piece') === 'lm') {
        return [
            'stock_name' => $hangingName,
            'amount' => round(profile_linear_meters($widthCm, $heightCm, $frameCount), 2),
        ];
    }

    return [
        'stock_name' => $hangingName,
        'amount' => $frameCount,
    ];
}

function get_pricing_setting(PDO $conn, string $key, float $default = 0): float
{
    $stmt = $conn->prepare('SELECT setting_value FROM pricing_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (float)$value : $default;
}

function get_all_pricing_settings(PDO $conn): array
{
    $rows = $conn->query('SELECT setting_key, setting_value, label, category FROM pricing_settings ORDER BY category, setting_key')->fetchAll();
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['category']][] = $row;
    }

    return $grouped;
}

function seed_price_list_catalog(PDO $conn): void
{
    seed_pricing_settings($conn);
    seed_glasses_from_price_list($conn);
    seed_backs_from_price_list($conn);
    seed_hanging_from_price_list($conn);
    seed_services_from_price_list($conn);
    sync_catalog_min_prices($conn);
}

function get_all_services(PDO $conn): array
{
    return $conn->query('SELECT * FROM services ORDER BY category, name')->fetchAll();
}

function parse_extra_service_ids($raw): array
{
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = explode(',', $raw);
        }
    }
    if (!is_array($raw)) {
        return [];
    }

    return array_values(array_filter(array_map('intval', $raw)));
}

function seed_services_from_price_list(PDO $conn): void
{
    $items = [
        ['Смяна/поставяне стъкло (до 0.5 кв.м.)', 3.06, 0, 'piece', 'glass_labor'],
        ['Смяна/поставяне стъкло (0.5–0.8 кв.м.)', 3.67, 0, 'piece', 'glass_labor'],
        ['Смяна/поставяне стъкло (над 0.8 кв.м.)', 9.18, 0, 'piece', 'glass_labor'],
        ['Поставяне в готова рамка (до 0.5 кв.м.)', 3.06, 0, 'piece', 'assembly'],
        ['Поставяне в готова рамка (0.5–0.8 кв.м.)', 4.90, 0, 'piece', 'assembly'],
        ['Поставяне в готова рамка (0.8–1.2 кв.м.)', 9.18, 0, 'piece', 'assembly'],
        ['Демонтаж от рамка на клиент', 1.85, 0, 'piece', 'assembly'],
        ['Опъване платно (странично)', 9.16, 3.05, 'sqm', 'stretching'],
        ['Опъване платно (приковаване)', 15.26, 6.10, 'sqm', 'stretching'],
        ['Каширане сив картон', 18.32, 4.88, 'sqm', 'mounting'],
        ['Каширане пенокартон', 25.64, 4.07, 'sqm', 'mounting'],
        ['Изправяне на преса', 7.33, 0, 'piece', 'general'],
        ['Монтаж на място', 9.18, 36.73, 'piece', 'general'],
        ['Транспорт', 1.83, 30.67, 'km', 'general'],
        ['Качване материали (етаж)', 10.22, 0, 'piece', 'general'],
        ['Клипс', 0.22, 0, 'piece', 'hardware'],
        ['Изработка клипсове', 3.54, 0, 'piece', 'frame_labor_svc'],
        ['Дървена лайсна дистанционер', 4.27, 0, 'piece', 'frame_labor_svc'],
        ['Опъване платно на подрамка (странично)', 3.05, 0, 'piece', 'stretching'],
        ['Рамкиране текстил (фланелка и др.)', 18.32, 0, 'piece', 'assembly'],
        ['Подлепване пъзел', 12.22, 3.05, 'sqm', 'mounting'],
        ['Изпъване гоблен с подлепяне', 15.26, 3.05, 'sqm', 'stretching'],
        ['Уплътнител метална рамка', 0.18, 0, 'piece', 'hardware'],
        ['Ъгъл/закачалка метален профил', 0.61, 0, 'piece', 'hardware'],
        ['Стойка (до 20/30 см)', 3.66, 0, 'piece', 'hardware'],
        ['Стойка (до 30/40 см)', 6.11, 0, 'piece', 'hardware'],
    ];

    foreach ($items as [$name, $price, $minPrice, $unit, $category]) {
        $check = $conn->prepare('SELECT COUNT(*) FROM services WHERE name = ?');
        $check->execute([$name]);
        if ((int)$check->fetchColumn() > 0) {
            continue;
        }
        $conn->prepare('INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $price, $minPrice, $unit, $category]);
    }
}

function sync_catalog_min_prices(PDO $conn): void
{
    $glassMins = [
        'Нормално' => 1.02,
        'Антирефлексно' => 1.22,
        'Консервационно' => 3.98,
        'Музейно' => 10.18,
        'Огледало' => 3.05,
        'Плексиглас' => 1.84,
    ];
    $stmt = $conn->prepare('UPDATE glasses SET min_price = ? WHERE name = ? AND (min_price IS NULL OR min_price = 0)');
    foreach ($glassMins as $name => $minPrice) {
        $stmt->execute([$minPrice, $name]);
    }

    $backMins = [
        'Велпапе' => 0.60,
        'Бирен картон' => 0.61,
        'Сив картон' => 0.61,
        'Пенокартон' => 1.84,
        'Фазер' => 1.84,
    ];
    $stmt = $conn->prepare('UPDATE backs SET min_price = ? WHERE name = ? AND (min_price IS NULL OR min_price = 0)');
    foreach ($backMins as $name => $minPrice) {
        $stmt->execute([$minPrice, $name]);
    }
}

function seed_pricing_settings(PDO $conn): void
{
    $settings = [
        ['frame_labor_base', 2.78, 'Базова цена изработка рамка (€/бр.)', 'frame_labor'],
        ['frame_labor_per_profile_cm', 0.50, 'Допълнение за всеки см ширина на профила (€/бр.)', 'frame_labor'],
        ['frame_labor_min', 3.54, 'Минимална цена изработка рамка (€/бр.)', 'frame_labor'],
        ['frame_surcharge_side_150', 2.54, 'Доплащане страна над 150 см (€/бр.)', 'frame_labor'],
        ['frame_surcharge_side_200', 6.09, 'Доплащане страна над 200 см (€/бр.)', 'frame_labor'],
        ['frame_labor_metal', 5.07, 'Изработка метална рамка (€/бр.)', 'frame_labor'],
        ['frame_labor_client_material', 15.27, 'Изработка с профил на клиент (€/бр.)', 'frame_labor'],
        ['frame_labor_box', 3.05, 'Рамка тип кутия (€/бр.)', 'frame_labor'],
        ['frame_nonstandard_min', 8.63, 'Минимум нестандартна форма (€/бр.)', 'frame_labor'],
        ['frame_shape_material_factor', 1.25, 'Коефициент профил елипса/кръг (материал)', 'frame_labor'],
        ['frame_shape_labor_ellipse', 3, 'Коефициент труд елипса/кръг 12 страни', 'frame_labor'],
        ['frame_shape_labor_circle', 4, 'Коефициент труд кръг 24 страни', 'frame_labor'],
        ['passepartout_cutting_labor', 1.84, 'Рязане паспарту (€/бр.)', 'passepartout_labor'],
        ['passepartout_multi_opening', 1.22, 'Допълнителен отвор паспарту (€/бр.)', 'passepartout_labor'],
        ['passepartout_complex_min', 6.10, 'Минимум сложно рязане паспарту (€/бр.)', 'passepartout_labor'],
        ['volume_discount_5_threshold', 255.64, 'Праг 5% отстъпка (€)', 'discounts'],
        ['volume_discount_10_threshold', 510.20, 'Праг 10% отстъпка (€)', 'discounts'],
        ['volume_discount_5_percent', 5, 'Отстъпка при еднократна поръчка 500–1000 лв (%)', 'discounts'],
        ['volume_discount_10_percent', 10, 'Отстъпка при поръчка над 1000 лв (%)', 'discounts'],
        ['student_discount_percent', 10, 'Отстъпка ученик/студент (%)', 'discounts'],
        ['urgent_surcharge_percent', 50, 'Доплащане спешна поръчка (%)', 'discounts'],
    ];

    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category)'
        : 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET label = excluded.label, category = excluded.category';

    $insert = $conn->prepare($sql);
    $updateLabel = $conn->prepare('UPDATE pricing_settings SET label = ?, category = ? WHERE setting_key = ?');

    foreach ($settings as [$key, $value, $label, $category]) {
        $check = $conn->prepare('SELECT COUNT(*) FROM pricing_settings WHERE setting_key = ?');
        $check->execute([$key]);
        if ((int)$check->fetchColumn() === 0) {
            $insert->execute([$key, $value, $label, $category]);
        } else {
            $updateLabel->execute([$label, $category, $key]);
        }
    }
}

function seed_glasses_from_price_list(PDO $conn): void
{
    $items = [
        ['Нормално', 15.95, 1.02],
        ['Антирефлексно', 22.70, 1.22],
        ['Консервационно', 63.20, 3.98],
        ['Музейно', 161.32, 10.18],
        ['Огледало', 24.43, 3.05],
        ['Плексиглас', 21.37, 1.84],
    ];

    upsert_catalog_items($conn, 'glasses', $items, true);
}

function seed_backs_from_price_list(PDO $conn): void
{
    $items = [
        ['Велпапе', 3.05, 0.60],
        ['Бирен картон', 6.10, 0.61],
        ['Сив картон', 6.10, 0.61],
        ['Пенокартон', 21.47, 1.84],
        ['Фазер', 9.16, 1.84],
    ];

    upsert_catalog_items($conn, 'backs', $items, true);
}

function seed_hanging_from_price_list(PDO $conn): void
{
    $items = [
        ['Закачалка', 0.61, 0, 'piece'],
        ['Две закачалки', 1.22, 0, 'piece'],
        ['Връзка', 1.22, 1.83, 'lm'],
    ];

    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES (?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE price = VALUES(price), min_price = VALUES(min_price), pricing_unit = VALUES(pricing_unit)'
        : 'INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES (?, ?, ?, ?, 0) ON CONFLICT(name) DO UPDATE SET price = excluded.price, min_price = excluded.min_price, pricing_unit = excluded.pricing_unit';

    $stmt = $conn->prepare($sql);
    foreach ($items as [$name, $price, $minPrice, $unit]) {
        $check = $conn->prepare('SELECT COUNT(*) FROM hanging_options WHERE name = ?');
        $check->execute([$name]);
        if ((int)$check->fetchColumn() === 0) {
            $stmt->execute([$name, $price, $minPrice, $unit]);
        }
    }
}

function get_passepartout_tier_scheme_labels(): array
{
    return [
        '80x120' => [
            'до 30×40',
            'до 40×60',
            'до 60×80',
            'до 81×120',
        ],
        '80x100' => [
            'до 25×40',
            'до 40×50',
            'до 51×81',
            'до 81×102',
        ],
    ];
}

function get_passepartout_price_kind_definitions(): array
{
    return [
        'type1' => [
            'label' => 'Вид 1',
            'scheme' => '80x120',
            'sheet_label' => 'лист 80×120',
            'summary' => 'Стандартна ценова група за лист 80×120.',
            'tiers' => [2.15, 3.99, 7.36, 14.11],
        ],
        'type2' => [
            'label' => 'Вид 2',
            'scheme' => '80x100',
            'sheet_label' => 'лист 80×100',
            'summary' => 'Икономична ценова група за лист 80×100 (най-ниски тарифи).',
            'tiers' => [1.84, 3.68, 6.75, 12.27],
        ],
        'type3' => [
            'label' => 'Вид 3',
            'scheme' => '80x100',
            'sheet_label' => 'лист 80×100',
            'summary' => 'Висок клас за лист 80×100 (най-високи тарифи).',
            'tiers' => [4.91, 9.82, 18.38, 34.97],
        ],
        'type4' => [
            'label' => 'Вид 4',
            'scheme' => '80x100',
            'sheet_label' => 'лист 80×100',
            'summary' => 'Средна ценова група за лист 80×100.',
            'tiers' => [2.45, 4.91, 9.11, 16.57],
        ],
        'type5' => [
            'label' => 'Вид 5',
            'scheme' => '80x120',
            'sheet_label' => 'лист 80×120',
            'summary' => 'Висок клас за лист 80×120.',
            'tiers' => [3.68, 7.36, 14.11, 27.00],
        ],
        'type6' => [
            'label' => 'Вид 6',
            'scheme' => '80x100',
            'sheet_label' => 'лист 80×100',
            'summary' => 'Средна ценова група за лист 80×100 (алтернативна тарифа).',
            'tiers' => [2.76, 4.60, 11.04, 21.47],
        ],
    ];
}

function describe_passepartout_price_kind(string $kindKey): string
{
    $definitions = get_passepartout_price_kind_definitions();
    if (!isset($definitions[$kindKey])) {
        return '';
    }

    $definition = $definitions[$kindKey];
    $tierLabels = get_passepartout_tier_scheme_labels()[$definition['scheme']] ?? [];
    $parts = [];
    foreach ($definition['tiers'] as $index => $price) {
        $sizeLabel = $tierLabels[$index] ?? ('ниво ' . ($index + 1));
        $parts[] = sprintf('%s → %s €', $sizeLabel, number_format((float)$price, 2, '.', ''));
    }

    return ($definition['summary'] ?? '') . ' ' . implode('; ', $parts);
}

function get_passepartout_price_kinds_for_display(): array
{
    $rows = [];
    foreach (get_passepartout_price_kind_definitions() as $kindKey => $definition) {
        $tierLabels = get_passepartout_tier_scheme_labels()[$definition['scheme']] ?? [];
        $tiers = [];
        foreach ($definition['tiers'] as $index => $price) {
            $tiers[] = [
                'size_label' => $tierLabels[$index] ?? ('Ниво ' . ($index + 1)),
                'price' => (float)$price,
            ];
        }

        $rows[] = [
            'key' => $kindKey,
            'label' => $definition['label'],
            'sheet_label' => $definition['sheet_label'] ?? $definition['scheme'],
            'summary' => $definition['summary'] ?? '',
            'scheme' => $definition['scheme'],
            'tiers' => $tiers,
        ];
    }

    return $rows;
}

function passepartout_uses_tier_pricing(array $passepartout): bool
{
    $kind = trim($passepartout['price_kind'] ?? '');
    if ($kind !== '' && $kind !== 'manual') {
        return true;
    }

    for ($i = 1; $i <= 4; $i++) {
        if ((float)($passepartout["price_tier_{$i}"] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

function resolve_passepartout_pricing_from_input(array $input): array
{
    $kind = trim($input['price_kind'] ?? 'manual');
    $definitions = get_passepartout_price_kind_definitions();

    if ($kind !== 'manual' && isset($definitions[$kind])) {
        $definition = $definitions[$kind];

        return [
            'price_kind' => $kind,
            'tier_scheme' => $definition['scheme'],
            'price_tier_1' => (float)$definition['tiers'][0],
            'price_tier_2' => (float)$definition['tiers'][1],
            'price_tier_3' => (float)$definition['tiers'][2],
            'price_tier_4' => (float)$definition['tiers'][3],
            'price' => (float)$definition['tiers'][0],
        ];
    }

    $tiers = [];
    for ($i = 1; $i <= 4; $i++) {
        $tiers[$i] = (float)($input["price_tier_{$i}"] ?? 0);
    }

    $scheme = trim($input['tier_scheme'] ?? '80x100');
    if (!isset(get_passepartout_tier_scheme_labels()[$scheme])) {
        $scheme = '80x100';
    }

    $fallbackPrice = (float)($input['price'] ?? 0);
    if ($fallbackPrice <= 0) {
        $fallbackPrice = $tiers[1] > 0 ? $tiers[1] : max($tiers);
    }

    return [
        'price_kind' => 'manual',
        'tier_scheme' => $scheme,
        'price_tier_1' => $tiers[1],
        'price_tier_2' => $tiers[2],
        'price_tier_3' => $tiers[3],
        'price_tier_4' => $tiers[4],
        'price' => $fallbackPrice,
    ];
}

function format_passepartout_price_summary(array $passepartout): string
{
    $definitions = get_passepartout_price_kind_definitions();
    $kind = trim($passepartout['price_kind'] ?? 'manual');

    if ($kind !== 'manual' && isset($definitions[$kind])) {
        $tiers = $definitions[$kind]['tiers'];
        $min = min($tiers);
        $max = max($tiers);

        return $definitions[$kind]['label'] . ' (' . number_format($min, 2, '.', '') . '–' . number_format($max, 2, '.', '') . ' €)';
    }

    if (!passepartout_uses_tier_pricing($passepartout)) {
        return number_format((float)($passepartout['price'] ?? 0), 2, '.', '') . ' €/кв.м.';
    }

    $tierValues = [];
    for ($i = 1; $i <= 4; $i++) {
        $tierValues[] = (float)($passepartout["price_tier_{$i}"] ?? 0);
    }
    $positive = array_values(array_filter($tierValues, fn($value) => $value > 0));
    if (empty($positive)) {
        return 'Ръчно';
    }

    $min = min($positive);
    $max = max($positive);

    return 'Ръчно (' . number_format($min, 2, '.', '') . '–' . number_format($max, 2, '.', '') . ' €)';
}

function upsert_catalog_items(PDO $conn, string $table, array $items, bool $hasMinPrice): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    foreach ($items as $item) {
        [$name, $price, $minPrice] = $item;
        $check = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE name = ?");
        $check->execute([$name]);
        if ((int)$check->fetchColumn() > 0) {
            continue;
        }

        if ($hasMinPrice) {
            $conn->prepare("INSERT INTO {$table} (name, price, min_price, stock) VALUES (?, ?, ?, 0)")
                ->execute([$name, $price, $minPrice]);
        }
    }
}

function is_inventory_page(string $page): bool
{
    return in_array($page, ['warehouse', 'profiles', 'glasses', 'passepartouts', 'backs', 'hanging', 'services'], true);
}
