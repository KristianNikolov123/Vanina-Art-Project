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

/**
 * Old catalog names that were renamed (2026-11). Orders keep the name they were saved with,
 * so lookups (price, stock) resolve the old name to the current article.
 */
function catalog_name_aliases(string $table): array
{
    return match ($table) {
        'glasses' => [
            'Нормално' => 'Нормално 2 мм',
        ],
        'hanging_options' => [
            'Връзка до 1 m, до 7 kg + 2 закачалки' => 'Връзка до 1 м, до 7 кг, с 2 закачалки',
            'Връзка до 9 kg' => 'Връзка до 1 м, до 9 кг, с 2 закачалки',
            'Връзка до 11 kg' => 'Връзка до 1 м, до 11 кг, с 2 закачалки',
            'Връзка до 1 m, до 19 kg (без закачалки)' => 'Връзка до 1 м, до 19 кг, без закачалки',
            'Връзка до 27 kg' => 'Връзка до 1 м, до 27 кг, без закачалки',
            'Закачалка тип крокодил' => 'Закачалка тип „крокодил“',
            'Закачалка голяма (1 дупка)' => 'Закачалка голяма с една дупка',
            'Закачалка с 2 дупки' => 'Закачалка голяма с две дупки',
            'Закачалка с 3 дупки' => 'Закачалка голяма с три дупки',
        ],
        default => [],
    };
}

function resolve_catalog_alias(string $table, string $name): string
{
    $name = trim($name);

    return catalog_name_aliases($table)[$name] ?? $name;
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
    if (!$item) {
        $aliased = resolve_catalog_alias($table, $name);
        if ($aliased !== $name) {
            $stmt->execute([$aliased]);
            $item = $stmt->fetch();
        }
    }
    if ($item) {
        if ($table === 'passepartouts') {
            return refresh_passepartout_row_pricing($item, $conn);
        }

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

function is_vrazka_hanging(string $name): bool
{
    return trim($name) === 'Връзка';
}

function get_hanging_weight_tier_defaults(): array
{
    return [
        ['max_weight_kg' => 7.0, 'price_per_lm' => 1.22],
        ['max_weight_kg' => 10.0, 'price_per_lm' => 1.83],
        ['max_weight_kg' => 15.0, 'price_per_lm' => 3.05],
        ['max_weight_kg' => 20.0, 'price_per_lm' => 4.30],
    ];
}

function seed_hanging_weight_tiers(PDO $conn): void
{
    ensure_hanging_weight_tiers_schema($conn);

    $count = (int)$conn->query('SELECT COUNT(*) FROM hanging_weight_tiers')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $insert = $conn->prepare('INSERT INTO hanging_weight_tiers (max_weight_kg, price_per_lm, sort_order) VALUES (?, ?, ?)');
    foreach (get_hanging_weight_tier_defaults() as $index => $tier) {
        $insert->execute([$tier['max_weight_kg'], $tier['price_per_lm'], $index + 1]);
    }
}

function get_hanging_weight_tiers(PDO $conn): array
{
    seed_hanging_weight_tiers($conn);
    $rows = $conn->query('SELECT id, max_weight_kg, price_per_lm, sort_order FROM hanging_weight_tiers ORDER BY sort_order, max_weight_kg')->fetchAll();
    $tiers = [];
    $previousMax = 0.0;

    foreach ($rows as $row) {
        $maxWeight = (float)$row['max_weight_kg'];
        $label = $previousMax <= 0
            ? sprintf('до %.0f kg', $maxWeight)
            : sprintf('%.0f–%.0f kg', $previousMax, $maxWeight);

        $tiers[] = [
            'id' => (int)$row['id'],
            'max_weight_kg' => $maxWeight,
            'price_per_lm' => (float)$row['price_per_lm'],
            'sort_order' => (int)$row['sort_order'],
            'label' => $label,
        ];
        $previousMax = $maxWeight;
    }

    return $tiers;
}

function resolve_vrazka_weight_tier(PDO $conn, float $weightKg): ?array
{
    $tiers = get_hanging_weight_tiers($conn);
    if (empty($tiers)) {
        return null;
    }

    if ($weightKg <= 0) {
        return $tiers[0];
    }

    foreach ($tiers as $tier) {
        if ($weightKg <= $tier['max_weight_kg'] + 0.0001) {
            return $tier;
        }
    }

    return $tiers[count($tiers) - 1];
}

function save_hanging_weight_tiers_from_post(PDO $conn, array $post): int
{
    ensure_hanging_weight_tiers_schema($conn);

    if (empty($post['hanging_weight_tiers']) || !is_array($post['hanging_weight_tiers'])) {
        return 0;
    }

    $update = $conn->prepare('UPDATE hanging_weight_tiers SET max_weight_kg = ?, price_per_lm = ? WHERE id = ?');
    $updated = 0;

    foreach ($post['hanging_weight_tiers'] as $id => $row) {
        $tierId = (int)$id;
        if ($tierId <= 0 || !is_array($row)) {
            continue;
        }
        $maxWeight = (float)str_replace(',', '.', trim((string)($row['max_weight_kg'] ?? 0)));
        $pricePerLm = (float)str_replace(',', '.', trim((string)($row['price_per_lm'] ?? 0)));
        if ($maxWeight <= 0 || $pricePerLm < 0) {
            continue;
        }
        $update->execute([$maxWeight, $pricePerLm, $tierId]);
        $updated++;
    }

    return $updated;
}

function get_pricing_setting(PDO $conn, string $key, float $default = 0): float
{
    $stmt = $conn->prepare('SELECT setting_value FROM pricing_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (float)$value : $default;
}

/** Tariffs shown in order extras UI (same keys as Услуги → автоматични). */
function get_order_automatic_pricing_hints(PDO $conn): array
{
    return [
        'passepartout_multi_opening' => get_pricing_setting($conn, 'passepartout_multi_opening', 1.50),
        'passepartout_cutting_labor' => get_pricing_setting($conn, 'passepartout_cutting_labor', 3.00),
        'frame_labor_box' => get_pricing_setting($conn, 'frame_labor_box', 3.05),
        'frame_surcharge_side_150' => get_pricing_setting($conn, 'frame_surcharge_side_150', 3.00),
        'frame_surcharge_side_200' => get_pricing_setting($conn, 'frame_surcharge_side_200', 6.00),
        'urgent_surcharge_percent' => get_pricing_setting($conn, 'urgent_surcharge_percent', 50),
        'urgent_next_day_percent' => get_pricing_setting($conn, 'urgent_next_day_percent', 30),
        'frame_shape_min_price' => get_pricing_setting($conn, 'frame_shape_min_price', 10.00),
        'frame_shape_coef_ellipse_12' => get_pricing_setting($conn, 'frame_shape_coef_ellipse_12', 3),
        'frame_shape_coef_circle_12' => get_pricing_setting($conn, 'frame_shape_coef_circle_12', 2),
        'frame_shape_coef_triangle' => get_pricing_setting($conn, 'frame_shape_coef_triangle', 4),
        'subframe_labor' => get_pricing_setting($conn, 'subframe_labor', 3.00),
        'subframe_factor_26' => get_pricing_setting($conn, 'subframe_factor_26', 1.2),
        'subframe_factor_33' => get_pricing_setting($conn, 'subframe_factor_33', 1.4),
        'subframe_lm_tier_1' => get_pricing_setting($conn, 'subframe_lm_tier_1', 6.90),
        'subframe_lm_tier_2' => get_pricing_setting($conn, 'subframe_lm_tier_2', 6.90),
        'subframe_lm_tier_3' => get_pricing_setting($conn, 'subframe_lm_tier_3', 7.80),
        'subframe_lm_tier_4' => get_pricing_setting($conn, 'subframe_lm_tier_4', 9.00),
    ];
}

/** Ориентировъчни ръчни отстъпки (не се прилагат автоматично). */
function manual_discount_guidance(): array
{
    return [
        ['label' => 'Поръчки от 500 до 1000 €', 'percent' => 5],
        ['label' => 'Поръчки над 1000 €', 'percent' => 10],
        ['label' => 'Ученици и преподаватели в художествената гимназия', 'percent' => 10],
        ['label' => 'Студенти и преподаватели в художествената академия', 'percent' => 10],
        ['label' => 'Художници', 'percent' => 10],
    ];
}

function urgent_level_options(PDO $conn): array
{
    return [
        0 => ['label' => 'Не', 'percent' => 0],
        2 => [
            'label' => 'Спешна за следващия ден (направена до 15:00 ч.)',
            'percent' => get_pricing_setting($conn, 'urgent_next_day_percent', 30),
        ],
        1 => [
            'label' => 'Спешна за същия ден (направена до 12:00 ч.)',
            'percent' => get_pricing_setting($conn, 'urgent_surcharge_percent', 50),
        ],
    ];
}

function frame_measure_options(): array
{
    // „Нормално“ е стандартът — празна стойност, без етикет.
    return [
        '' => '',
        'clear' => 'Светло (от шлиц до шлиц)',
        'outer' => 'Външно (от външен ръб до външен ръб)',
    ];
}

function frame_shape_options(): array
{
    return [
        '' => 'Правоъгълна',
        'ellipse_12' => 'Елипса, 12 страни',
        'circle_12' => 'Кръг, 12 страни',
        'triangle' => 'Триъгълник',
    ];
}

function frame_shape_label(?string $shape): string
{
    if ($shape === 'circle_24') {
        return 'Кръг 24 страни';
    }
    $options = frame_shape_options();

    return $shape !== null && $shape !== '' ? ($options[$shape] ?? '') : '';
}

function frame_shape_coefficient(PDO $conn, string $shape): float
{
    return match ($shape) {
        'ellipse_12' => get_pricing_setting($conn, 'frame_shape_coef_ellipse_12', 3),
        'circle_12' => get_pricing_setting($conn, 'frame_shape_coef_circle_12', 2),
        'triangle' => get_pricing_setting($conn, 'frame_shape_coef_triangle', 4),
        'circle_24' => 4.0,
        default => 0.0,
    };
}

function subframe_thickness_options(): array
{
    return [
        '' => 'Без подрамка',
        '2.2' => '2.2 см (основна)',
        '2.6' => '2.6 см (×1.2)',
        '3.3' => '3.3 см (×1.4)',
    ];
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

function pricing_setting_unit_suffix(string $key): string
{
    if (str_contains($key, 'percent')) {
        return '%';
    }
    if (str_starts_with($key, 'frame_shape_coef_') || str_starts_with($key, 'subframe_factor_')
        || $key === 'frame_shape_material_factor') {
        return '×';
    }
    if (str_starts_with($key, 'subframe_lm_tier_')) {
        return '€/л.м.';
    }

    return '€';
}

function update_pricing_setting_value(PDO $conn, string $key, float $value): void
{
    $stmt = $conn->prepare('UPDATE pricing_settings SET setting_value = ? WHERE setting_key = ?');
    $stmt->execute([$value, $key]);
    if ($stmt->rowCount() > 0) {
        return;
    }

    seed_passepartout_kind_tier_settings($conn);
    $stmt->execute([$value, $key]);
}

function sync_passepartout_items_to_price_kind_settings(PDO $conn): int
{
    $definitions = get_passepartout_price_kind_definitions($conn);
    $stmt = $conn->prepare('
        UPDATE passepartouts
        SET tier_scheme = ?, price_tier_1 = ?, price_tier_2 = ?, price_tier_3 = ?, price_tier_4 = ?, price = ?
        WHERE price_kind = ?
    ');
    $updated = 0;

    foreach ($definitions as $kindKey => $definition) {
        if ($kindKey === 'manual') {
            continue;
        }

        $stmt->execute([
            $definition['scheme'],
            (float)$definition['tiers'][0],
            (float)$definition['tiers'][1],
            (float)$definition['tiers'][2],
            (float)$definition['tiers'][3],
            (float)$definition['tiers'][0],
            $kindKey,
        ]);
        $updated += $stmt->rowCount();
    }

    return $updated;
}

/** Tier columns for display/JSON — live values from Услуги when price_kind is not manual. */
function refresh_passepartout_row_pricing(array $row, ?PDO $conn = null): array
{
    $kind = trim($row['price_kind'] ?? 'manual');
    if ($kind === '' || $kind === 'manual') {
        return $row;
    }

    $definitions = get_passepartout_price_kind_definitions($conn);
    if (!isset($definitions[$kind])) {
        return $row;
    }

    $definition = $definitions[$kind];
    for ($i = 1; $i <= 4; $i++) {
        $row["price_tier_{$i}"] = (float)($definition['tiers'][$i - 1] ?? 0);
    }
    $row['tier_scheme'] = $definition['scheme'];
    $row['price'] = (float)($definition['tiers'][0] ?? 0);

    return $row;
}

function passepartout_kind_tier_setting_key(string $kindKey, int $tierIndex): string
{
    return "pp_kind_{$kindKey}_tier_{$tierIndex}";
}

function get_price_list_rule_definitions(): array
{
    return [
        ['key' => 'fir', 'sort' => 1],
        ['key' => 'wide_profile', 'sort' => 2],
        ['key' => 'frame_labor', 'sort' => 3],
        ['key' => 'metal_client', 'sort' => 4],
        ['key' => 'passepartout', 'sort' => 5],
        ['key' => 'discounts', 'sort' => 6],
        ['key' => 'urgent', 'sort' => 7],
    ];
}

function format_price_list_rule_amount(float $value): string
{
    return number_format($value, 2, '.', '');
}

function build_default_price_list_rule_content(PDO $conn, string $ruleKey): string
{
    switch ($ruleKey) {
        case 'fir':
            return 'Фира (материал) — 2×(ш+в)/100 + ширина×8/100 л.м. на рамка (фирата е извън скобите); дърво/пластмаса; без фира при метал и при мерене „Външно“';
        case 'wide_profile':
            return 'Дълги страни — при добавка (фира) от 80 см или повече и страна над 240 см тази страна се таксува по 290 см (пластмаса) / 300 см (дърво)';
        case 'frame_labor':
            return sprintf(
                'Труд (дърво) — %s + %s × ширина на профила (см), мин. %s €/бр.; големи рамки: поне една страна от 150 см +%s €, и четирите +%s €; поне една от 200 см +%s €, и четирите +%s € (само най-високата)',
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_labor_base', 2.78)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_labor_per_profile_cm', 0.50)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_labor_min', 3.54)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_surcharge_side_150', 3.00)),
                format_price_list_rule_amount(2 * get_pricing_setting($conn, 'frame_surcharge_side_150', 3.00)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_surcharge_side_200', 6.00)),
                format_price_list_rule_amount(2 * get_pricing_setting($conn, 'frame_surcharge_side_200', 6.00))
            );
        case 'metal_client':
            return sprintf(
                'Метален профил — фиксиран труд %s €/бр.; материал на клиент — %s €/бр.',
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_labor_metal', 5.07)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'frame_labor_client_material', 15.27))
            );
        case 'passepartout':
            return sprintf(
                'Паспарту — 6 ценови вида (виж таблицата по-горе). Първият отвор е включен; всеки следващ +%s €/бр.; рязане на паспарту, донесено от клиента, %s €/бр.; сложно рязане +50%% (мин. %s €/бр.)',
                format_price_list_rule_amount(get_pricing_setting($conn, 'passepartout_multi_opening', 1.50)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'passepartout_cutting_labor', 3.00)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'passepartout_complex_min', 6.10))
            );
        case 'discounts':
            return 'Отстъпки — само ръчно (поле „Ръчна отстъпка“). Ориентир: 500–1000 € −5%; над 1000 € −10%; ученици/студенти и преподаватели в худ. гимназия/академия −10%; художници −10%';
        case 'urgent':
            return sprintf(
                'Спешна — за следващия ден (до 15:00 ч.) +%s%%; за същия ден (до 12:00 ч.) +%s%%; сложна поръчка +50 до 100%% (договаря се); върху сумата след отстъпките',
                format_price_list_rule_amount(get_pricing_setting($conn, 'urgent_next_day_percent', 30)),
                format_price_list_rule_amount(get_pricing_setting($conn, 'urgent_surcharge_percent', 50))
            );
        default:
            return '';
    }
}

function seed_price_list_rules(PDO $conn): void
{
    ensure_price_list_rules_schema($conn);

    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO price_list_rules (rule_key, sort_order, content) VALUES (?, ?, ?)'
        : 'INSERT INTO price_list_rules (rule_key, sort_order, content) VALUES (?, ?, ?)';

    $insert = $conn->prepare($sql);
    $check = $conn->prepare('SELECT COUNT(*) FROM price_list_rules WHERE rule_key = ?');

    foreach (get_price_list_rule_definitions() as $definition) {
        $check->execute([$definition['key']]);
        if ((int)$check->fetchColumn() > 0) {
            continue;
        }

        $insert->execute([
            $definition['key'],
            $definition['sort'],
            build_default_price_list_rule_content($conn, $definition['key']),
        ]);
    }
}

function get_price_list_rules(PDO $conn): array
{
    seed_price_list_rules($conn);
    $rows = $conn->query('SELECT rule_key, sort_order, content FROM price_list_rules ORDER BY sort_order, rule_key')->fetchAll();

    return array_map(static function (array $row): array {
        return [
            'key' => $row['rule_key'],
            'sort_order' => (int)$row['sort_order'],
            'content' => (string)$row['content'],
        ];
    }, $rows);
}

function save_price_list_rules_from_post(PDO $conn, array $post): int
{
    if (empty($post['price_list_rules']) || !is_array($post['price_list_rules'])) {
        return 0;
    }

    $allowedKeys = array_column(get_price_list_rule_definitions(), 'key');
    $stmt = $conn->prepare('UPDATE price_list_rules SET content = ? WHERE rule_key = ?');
    $updated = 0;

    foreach ($post['price_list_rules'] as $ruleKey => $content) {
        if (!in_array($ruleKey, $allowedKeys, true)) {
            continue;
        }
        $text = trim((string)$content);
        if ($text === '') {
            continue;
        }
        $stmt->execute([$text, $ruleKey]);
        $updated++;
    }

    return $updated;
}

function save_services_pricing_from_post(PDO $conn, array $post): array
{
    $counts = ['settings' => 0, 'services' => 0, 'pp_tiers' => 0, 'rules' => 0];

    if (!empty($post['pricing']) && is_array($post['pricing'])) {
        foreach ($post['pricing'] as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }
            update_pricing_setting_value($conn, $key, (float)str_replace(',', '.', trim((string)$value)));
            $counts['settings']++;
        }
    }

    if (!empty($post['services']) && is_array($post['services'])) {
        $stmt = $conn->prepare('UPDATE services SET price = ?, min_price = ? WHERE id = ?');
        foreach ($post['services'] as $id => $row) {
            $serviceId = (int)$id;
            if ($serviceId <= 0 || !is_array($row)) {
                continue;
            }
            $price = (float)str_replace(',', '.', trim((string)($row['price'] ?? 0)));
            $minPrice = (float)str_replace(',', '.', trim((string)($row['min_price'] ?? 0)));
            $stmt->execute([$price, $minPrice, $serviceId]);
            $counts['services']++;
        }
    }

    if (!empty($post['pp_kind']) && is_array($post['pp_kind'])) {
        $allowedKinds = array_keys(get_passepartout_price_kind_definition_defaults());
        foreach ($post['pp_kind'] as $kindKey => $tiers) {
            if (!in_array($kindKey, $allowedKinds, true) || !is_array($tiers)) {
                continue;
            }
            for ($tierIndex = 1; $tierIndex <= 4; $tierIndex++) {
                if (!array_key_exists($tierIndex, $tiers) && !array_key_exists((string)$tierIndex, $tiers)) {
                    continue;
                }
                $raw = $tiers[$tierIndex] ?? $tiers[(string)$tierIndex] ?? null;
                if ($raw === null) {
                    continue;
                }
                $settingKey = passepartout_kind_tier_setting_key($kindKey, $tierIndex);
                update_pricing_setting_value(
                    $conn,
                    $settingKey,
                    (float)str_replace(',', '.', trim((string)$raw))
                );
                $counts['pp_tiers']++;
            }
        }
    }

    $counts['rules'] = save_price_list_rules_from_post($conn, $post);

    $counts['pp_synced'] = sync_passepartout_items_to_price_kind_settings($conn);

    return $counts;
}

function seed_price_list_catalog(PDO $conn): void
{
    seed_pricing_settings($conn);
    seed_price_list_rules($conn);
    seed_passepartout_kind_tier_settings($conn);
    seed_glasses_from_price_list($conn);
    seed_backs_from_price_list($conn);
    seed_hanging_from_price_list($conn);
    seed_hanging_weight_tiers($conn);
    seed_services_from_price_list($conn);
    migrate_services_price_list_2026($conn);
    migrate_hanging_options_2026($conn);
    sync_catalog_min_prices($conn);
    sync_passepartout_items_to_price_kind_settings($conn);
}

function upsert_service_catalog_row(PDO $conn, string $name, float $price, float $minPrice, string $unit, string $category): void
{
    $check = $conn->prepare('SELECT id FROM services WHERE name = ?');
    $check->execute([$name]);
    $id = $check->fetchColumn();
    if ($id) {
        $conn->prepare('UPDATE services SET price = ?, min_price = ?, pricing_unit = ?, category = ? WHERE id = ?')
            ->execute([$price, $minPrice, $unit, $category, (int)$id]);
    } else {
        $conn->prepare('INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $price, $minPrice, $unit, $category]);
    }
}

/** Подразделения на „Допълнителни услуги“ — в реда от ценоразписа (2026-11). */
function service_category_definitions(): array
{
    return [
        'mounting' => 'Каширане',
        'stretching' => 'Изпъване на платно и гоблен',
        'subframes' => 'Подрамки',
        'small' => 'Дребни (фиксирани цени)',
        'frame_making' => 'Изработване на рамки',
        'assembly' => 'Монтаж или демонтаж на произведение или стъкло',
        'boxes' => 'Кутии от велпапе',
        'other' => 'Други',
    ];
}

/**
 * Канонични услуги от ценоразписа 2026-11: [име, цена, минимум, единица, категория, ред, бележка].
 * Единица „manual“ = клетка за договорена/твърда цена, въведена в поръчката.
 */
function get_service_catalog_2026(): array
{
    return [
        ['Каширане на пенокартон', 26.0, 6.0, 'sqm', 'mounting', 10, null],
        ['Каширане на сив или бирен картон', 20.0, 5.0, 'sqm', 'mounting', 20, 'Максимален размер 70/100 см'],
        ['Подлепване на пъзел', 13.0, 3.0, 'sqm', 'mounting', 30, null],

        ['Странично изпъване на платно', 10.0, 3.0, 'sqm', 'stretching', 10, null],
        ['Задно изпъване на платно', 16.0, 5.0, 'sqm', 'stretching', 20, null],
        ['Изпъване/подлепване на гоблен', 16.0, 4.0, 'sqm', 'stretching', 30, null],
        ['Опъване платно на подрамка (винтове)', 3.05, 0.0, 'piece', 'stretching', 40, null],

        ['Рамкиране на текстил/фланелка', 20.0, 0.0, 'piece', 'small', 10, null],
        ['Рамкиране на други предмети', 0.0, 0.0, 'manual', 'small', 20, 'Цена, определена в магазина'],
        ['Изправяне на вакуум преса', 8.0, 0.0, 'piece', 'small', 30, null],
        ['Стойка до 20/30 см', 4.0, 0.0, 'piece', 'small', 40, null],
        ['Стойка от 20/30 до 30/40 см', 7.0, 0.0, 'piece', 'small', 50, null],
        ['Клипс', 0.5, 0.0, 'piece', 'small', 60, null],
        ['Ъгъл или зак. метален профил', 0.6, 0.0, 'piece', 'small', 70, null],
        ['Транспорт', 0.0, 0.0, 'manual', 'small', 80, 'Ориентир 2.00 €/км или минимум 20 € на доставка'],
        ['Качване на материали на етаж', 0.0, 0.0, 'manual', 'small', 90, 'Ориентир 10 € на етаж за брой'],
        ['Монтаж на място', 10.0, 40.0, 'piece', 'small', 100, 'Минимум 40 € на поръчка'],

        ['Поставяне на дистанционер на рамка', 3.0, 0.0, 'piece', 'frame_making', 10, '+ цената на дистанционера от склада'],
        ['Монтаж на подрамка на рамка тип „каса“ с винтове', 3.0, 0.0, 'piece', 'frame_making', 20, null],
        ['Изработка на фазер с клипсове', 5.0, 0.0, 'piece', 'frame_making', 50, null],

        ['Поставяне в готова рамка до 0.5 кв.м', 4.0, 0.0, 'piece', 'assembly', 10, null],
        ['Поставяне в готова рамка от 0.5 до 0.8 кв.м', 8.0, 0.0, 'piece', 'assembly', 20, null],
        ['Поставяне в готова рамка от 0.8 до 1.2 кв.м', 10.0, 0.0, 'piece', 'assembly', 30, null],
        ['Поставяне в готова рамка над 1.2 кв.м', 0.0, 0.0, 'manual', 'assembly', 40, 'Договорена цена'],
        ['Демонтаж на рамка на клиент', 5.0, 0.0, 'piece', 'assembly', 50, null],

        ['Кутия от велпапе 20/50/70 см', 7.0, 0.0, 'piece', 'boxes', 10, null],
        ['Кутия от велпапе над 20/50/70 до 20/50/100 см', 10.0, 0.0, 'piece', 'boxes', 20, null],
        ['Кутия от велпапе над 20/50/100 до 30/100/150 см', 18.0, 0.0, 'piece', 'boxes', 30, null],
        ['Кутия от велпапе — по-голям размер или допълнителни изисквания', 0.0, 0.0, 'manual', 'boxes', 40, 'Договорена цена'],
    ];
}

/** Стари имена → имена от ценоразписа 2026-11. */
function get_service_legacy_renames(): array
{
    return [
        'Каширане пенокартон' => 'Каширане на пенокартон',
        'Каширане сив или бирен картон (макс. 70×100)' => 'Каширане на сив или бирен картон',
        'Каширане сив картон' => 'Каширане на сив или бирен картон',
        'Опъване платно (странично)' => 'Странично изпъване на платно',
        'Опъване платно (задно опъване)' => 'Задно изпъване на платно',
        'Опъване платно (приковаване)' => 'Задно изпъване на платно',
        'Рамкиране текстил (фланелка и др.)' => 'Рамкиране на текстил/фланелка',
        'Рамкиране на текстил / фланелка' => 'Рамкиране на текстил/фланелка',
        'Подлепване пъзел' => 'Подлепване на пъзел',
        'Изпъване гоблен с подлепяне' => 'Изпъване/подлепване на гоблен',
        'Стойка (до 20/30 см)' => 'Стойка до 20/30 см',
        'Стойка (до 30/40 см)' => 'Стойка от 20/30 до 30/40 см',
        'Качване материали (етаж)' => 'Качване на материали на етаж',
        'Качване материали на етаж' => 'Качване на материали на етаж',
        'Ъгъл/закачалка метален профил' => 'Ъгъл или зак. метален профил',
        'Изработка клипсове' => 'Изработка на фазер с клипсове',
        'Изработка фазер с клипсове' => 'Изработка на фазер с клипсове',
        'Изправяне на преса' => 'Изправяне на вакуум преса',
        'Поставяне дистанционер на рамка' => 'Поставяне на дистанционер на рамка',
        'Монтаж подрамка (рамка тип каса)' => 'Монтаж на подрамка на рамка тип „каса“ с винтове',
        'Поставяне в готова рамка (до 0.5 кв.м.)' => 'Поставяне в готова рамка до 0.5 кв.м',
        'Поставяне в готова рамка (0.5–0.8 кв.м.)' => 'Поставяне в готова рамка от 0.5 до 0.8 кв.м',
        'Поставяне в готова рамка (0.8–1.2 кв.м.)' => 'Поставяне в готова рамка от 0.8 до 1.2 кв.м',
        'Демонтаж от рамка на клиент' => 'Демонтаж на рамка на клиент',
    ];
}

/**
 * Еднократно: преименува/подрежда услугите по ценоразписа 2026-11 и задава новите цени.
 * Не се изпълнява при всяка заявка, за да не презаписва цени, редактирани в „Услуги“.
 */
function migrate_services_price_list_2026(PDO $conn): void
{
    if (!table_has_column($conn, 'services', 'sort_order') || schema_migration_applied($conn, 'services_catalog_2026_11')) {
        return;
    }

    $find = $conn->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
    $findId = static function (string $name) use ($find): ?int {
        $find->execute([$name]);
        $id = $find->fetchColumn();

        return $id ? (int)$id : null;
    };

    // Rename legacy names; a duplicate is deactivated (not deleted) so old orders keep their label.
    foreach (get_service_legacy_renames() as $oldName => $newName) {
        $oldId = $findId($oldName);
        if ($oldId === null) {
            continue;
        }
        if ($findId($newName) === null) {
            $conn->prepare('UPDATE services SET name = ? WHERE id = ?')->execute([$newName, $oldId]);
        } else {
            $conn->prepare('UPDATE services SET is_active = 0, category = ? WHERE id = ?')->execute(['other', $oldId]);
        }
    }

    // Old categories → new subsections (for rows outside the canonical list).
    $categoryMap = [
        'frame_labor_svc' => 'frame_making',
        'general' => 'small',
        'hardware' => 'small',
        'glass_labor' => 'other',
    ];
    foreach ($categoryMap as $old => $new) {
        $conn->prepare('UPDATE services SET category = ? WHERE category = ?')->execute([$new, $old]);
    }

    $update = $conn->prepare('
        UPDATE services SET price = ?, min_price = ?, pricing_unit = ?, category = ?, sort_order = ?, note = ?, is_active = 1
        WHERE id = ?
    ');
    $insert = $conn->prepare('
        INSERT INTO services (name, price, min_price, pricing_unit, category, sort_order, note, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ');
    foreach (get_service_catalog_2026() as [$name, $price, $minPrice, $unit, $category, $sort, $note]) {
        $id = $findId($name);
        if ($id !== null) {
            $update->execute([$price, $minPrice, $unit, $category, $sort, $note, $id]);
        } else {
            $insert->execute([$name, $price, $minPrice, $unit, $category, $sort, $note]);
        }
    }

    // „Опъване платно на подрамка (странично)“ дублира „Странично изпъване на платно“ — скрива се.
    $conn->prepare('UPDATE services SET is_active = 0, category = ? WHERE name = ?')
        ->execute(['other', 'Опъване платно на подрамка (странично)']);

    // Услуги извън новия лист остават в „Други“.
    $legacyOther = [
        'Смяна/поставяне стъкло (до 0.5 кв.м.)' => 10,
        'Смяна/поставяне стъкло (0.5–0.8 кв.м.)' => 20,
        'Смяна/поставяне стъкло (над 0.8 кв.м.)' => 30,
        'Уплътнител метална рамка' => 40,
        'Дървена лайсна дистанционер' => 50,
    ];
    foreach ($legacyOther as $name => $sort) {
        $conn->prepare('UPDATE services SET category = ?, sort_order = ?, is_active = 1 WHERE name = ?')
            ->execute(['other', $sort, $name]);
    }

    mark_schema_migration_applied($conn, 'services_catalog_2026_11');
}

/** Пакети „Връзка“ и закачалки по ценоразписа 2026-11 (еднократно). */
function migrate_hanging_options_2026(PDO $conn): void
{
    if (!table_has_column($conn, 'hanging_options', 'sort_order') || schema_migration_applied($conn, 'hanging_catalog_2026_11')) {
        return;
    }

    $find = $conn->prepare('SELECT id FROM hanging_options WHERE name = ? LIMIT 1');
    $exists = static function (string $name) use ($find): bool {
        $find->execute([$name]);

        return (bool)$find->fetchColumn();
    };

    foreach (catalog_name_aliases('hanging_options') as $oldName => $newName) {
        if ($exists($oldName) && !$exists($newName)) {
            $conn->prepare('UPDATE hanging_options SET name = ? WHERE name = ?')->execute([$newName, $oldName]);
        }
    }

    $items = [
        ['Връзка до 1 м, до 7 кг, с 2 закачалки', 1.50, 10],
        ['Връзка до 1 м, до 9 кг, с 2 закачалки', 2.00, 20],
        ['Връзка до 1 м, до 11 кг, с 2 закачалки', 2.50, 30],
        ['Връзка до 1 м, до 19 кг, без закачалки', 3.00, 40],
        ['Връзка до 1 м, до 27 кг, без закачалки', 4.00, 50],
        ['Закачалка малка', 0.30, 110],
        ['Закачалка тип „крокодил“', 0.60, 120],
        ['Закачалка голяма с една дупка', 1.00, 130],
        ['Закачалка голяма с две дупки', 2.00, 140],
        ['Закачалка голяма с три дупки', 3.00, 150],
    ];
    foreach ($items as [$name, $price, $sort]) {
        if ($exists($name)) {
            $conn->prepare('UPDATE hanging_options SET price = ?, min_price = 0, pricing_unit = ?, sort_order = ? WHERE name = ?')
                ->execute([$price, 'piece', $sort, $name]);
        } else {
            $conn->prepare('INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock, sort_order) VALUES (?, ?, 0, ?, 0, ?)')
                ->execute([$name, $price, 'piece', $sort]);
        }
    }

    mark_schema_migration_applied($conn, 'hanging_catalog_2026_11');
}

/** Пакети за падащото меню „Окачване“ (по големина) и закачалки (по цена). */
function split_hanging_options_for_form(array $hangingOptions): array
{
    $packages = [];
    $hangers = [];
    foreach ($hangingOptions as $option) {
        $name = trim($option['name'] ?? '');
        if ($name === '' || in_array($name, ['Връзка', 'Две закачалки', 'Закачалка'], true)) {
            continue;
        }
        if (str_starts_with($name, 'Закачалка')) {
            $hangers[] = $option;
        } else {
            $packages[] = $option;
        }
    }
    $bySort = static fn (array $a, array $b): int =>
        [(int)($a['sort_order'] ?? 100), (float)($a['price'] ?? 0), $a['name']]
        <=> [(int)($b['sort_order'] ?? 100), (float)($b['price'] ?? 0), $b['name']];
    usort($packages, $bySort);
    usort($hangers, $bySort);

    return ['packages' => $packages, 'hangers' => $hangers];
}

/** „Закачалка голяма с една дупка“ → „Голяма с една дупка“. */
function hanger_display_label(string $name): string
{
    $label = trim(preg_replace('/^Закачалка\s*/u', '', $name) ?? $name);
    if ($label === '') {
        return $name;
    }

    return mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($label, 1, null, 'UTF-8');
}

function hanging_package_requires_hangers(string $name): bool
{
    return str_contains(mb_strtolower($name, 'UTF-8'), 'без закачалки');
}

function service_pricing_unit_label(string $unit): string
{
    return match ($unit) {
        'sqm' => '€/кв.м',
        'lm' => '€/л.м.',
        'km' => '€/км',
        'manual' => 'договорена цена',
        default => '€/бр.',
    };
}

function service_area_tier_rank(string $name): int
{
    if (preg_match('/до\s*0[,.]5\s*кв/u', $name)) {
        return 1;
    }
    if (preg_match('/0[,.]5\s*(?:[–\-]|до)\s*0[,.]8/u', $name)) {
        return 2;
    }
    if (preg_match('/(0[,.]8\s*(?:[–\-]|до)\s*1[,.]2|над\s*0[,.]8)/u', $name)) {
        return 3;
    }
    if (preg_match('/над\s*1[,.]2/u', $name)) {
        return 4;
    }

    return 99;
}

function sort_services_for_display(array $services): array
{
    $categoryOrder = array_flip(array_keys(service_category_definitions()));
    usort($services, static function (array $a, array $b) use ($categoryOrder): int {
        $catA = $categoryOrder[$a['category'] ?? 'other'] ?? 99;
        $catB = $categoryOrder[$b['category'] ?? 'other'] ?? 99;

        return [$catA, (int)($a['sort_order'] ?? 100), $a['name'] ?? '']
            <=> [$catB, (int)($b['sort_order'] ?? 100), $b['name'] ?? ''];
    });

    return $services;
}

function get_all_services(PDO $conn, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM services';
    if ($activeOnly && table_has_column($conn, 'services', 'is_active')) {
        $sql .= ' WHERE is_active = 1';
    }
    $rows = $conn->query($sql)->fetchAll();

    return sort_services_for_display($rows);
}

function service_is_spacer(array $service): bool
{
    return str_contains($service['name'] ?? '', 'дистанционер на рамка');
}

function upsert_hanging_option(PDO $conn, string $name, float $price, float $minPrice, string $unit): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES (?, ?, ?, ?, 0)
           ON DUPLICATE KEY UPDATE price = VALUES(price), min_price = VALUES(min_price), pricing_unit = VALUES(pricing_unit)'
        : 'INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES (?, ?, ?, ?, 0)
           ON CONFLICT(name) DO UPDATE SET price = excluded.price, min_price = excluded.min_price, pricing_unit = excluded.pricing_unit';
    $conn->prepare($sql)->execute([$name, $price, $minPrice, $unit]);
}

function migrate_service_display_names(PDO $conn): void
{
    $oldName = 'Опъване платно (приковаване)';
    $newName = 'Опъване платно (задно опъване)';

    $lookup = $conn->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');

    $lookup->execute([$newName]);
    $newRow = $lookup->fetch();

    $lookup->execute([$oldName]);
    $oldRow = $lookup->fetch();

    if (!$oldRow) {
        return;
    }

    if ($newRow) {
        $conn->prepare('DELETE FROM services WHERE id = ?')->execute([(int)$oldRow['id']]);

        return;
    }

    $conn->prepare('UPDATE services SET name = ? WHERE id = ?')->execute([$newName, (int)$oldRow['id']]);
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
    // Seeds only an empty table; names/prices are aligned by migrate_services_price_list_2026().
    if ((int)$conn->query('SELECT COUNT(*) FROM services')->fetchColumn() > 0) {
        return;
    }

    $legacyOther = [
        ['Смяна/поставяне стъкло (до 0.5 кв.м.)', 3.06, 0, 'piece', 'other'],
        ['Смяна/поставяне стъкло (0.5–0.8 кв.м.)', 3.67, 0, 'piece', 'other'],
        ['Смяна/поставяне стъкло (над 0.8 кв.м.)', 9.18, 0, 'piece', 'other'],
        ['Уплътнител метална рамка', 0.18, 0, 'piece', 'other'],
        ['Дървена лайсна дистанционер', 4.27, 0, 'piece', 'other'],
    ];
    $insert = $conn->prepare('INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES (?, ?, ?, ?, ?)');
    foreach ($legacyOther as $row) {
        $insert->execute($row);
    }
}

function sync_catalog_min_prices(PDO $conn): void
{
    $glassMins = [
        'Нормално 2 мм' => 1.02,
        'Антирефлексно' => 1.22,
        'Консервационно' => 3.98,
        'Музейно' => 10.18,
        'Огледало' => 3.05,
        'Плексиглас 2 mm' => 1.84,
        'Плексиглас 3 mm' => 1.84,
        'Антирефлексен плексиглас' => 1.84,
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
        ['frame_surcharge_side_150', 3.00, 'Голяма рамка: поне една страна от 150 см (€/бр.; и четирите — ×2)', 'frame_labor'],
        ['frame_surcharge_side_200', 6.00, 'Голяма рамка: поне една страна от 200 см (€/бр.; и четирите — ×2)', 'frame_labor'],
        ['frame_labor_metal', 5.07, 'Изработка метална рамка (€/бр.)', 'frame_labor'],
        ['frame_labor_client_material', 15.27, 'Изработка с профил на клиент (€/бр.)', 'frame_labor'],
        ['frame_labor_box', 3.05, 'Рамка тип кутия (€/бр.)', 'frame_labor'],
        ['frame_nonstandard_min', 8.63, 'Минимум нестандартна изработка (€/бр.)', 'frame_labor'],
        ['frame_shape_material_factor', 1.25, 'Нестандартна фигура: коефициент на метража', 'frame_labor'],
        ['frame_shape_coef_ellipse_12', 3, 'Нестандартна фигура: елипса, 12 страни (× цена профил)', 'frame_labor'],
        ['frame_shape_coef_circle_12', 2, 'Нестандартна фигура: кръг, 12 страни (× цена профил)', 'frame_labor'],
        ['frame_shape_coef_triangle', 4, 'Нестандартна фигура: триъгълник (× цена профил)', 'frame_labor'],
        ['frame_shape_min_price', 10.00, 'Нестандартна фигура: минимум (€/бр.)', 'frame_labor'],
        ['passepartout_cutting_labor', 3.00, 'Рязане на паспарту, донесено от клиента (€/бр.)', 'passepartout_labor'],
        ['passepartout_multi_opening', 1.50, 'Всеки следващ отвор на паспарту (€/бр.)', 'passepartout_labor'],
        ['passepartout_complex_min', 6.10, 'Минимум сложно рязане паспарту (€/бр.)', 'passepartout_labor'],
        ['subframe_lm_tier_1', 6.90, 'Подрамка до 30/40 см (€/л.м.), без труд', 'subframes'],
        ['subframe_lm_tier_2', 6.90, 'Подрамка от 30/40 до 50/70 см (€/л.м.)', 'subframes'],
        ['subframe_lm_tier_3', 7.80, 'Подрамка от 50/70 до 70/100 см (€/л.м.)', 'subframes'],
        ['subframe_lm_tier_4', 9.00, 'Подрамка от 100/70 до 100/150 см (€/л.м.)', 'subframes'],
        ['subframe_labor', 3.00, 'Труд подрамка над 30/40 см (€/бр.)', 'subframes'],
        ['subframe_factor_26', 1.2, 'Подрамка 2.6 см: коефициент за материал и труд', 'subframes'],
        ['subframe_factor_33', 1.4, 'Подрамка 3.3 см: коефициент за материал и труд', 'subframes'],
        ['urgent_next_day_percent', 30, 'Спешна за следващия ден, направена до 15:00 ч. (%)', 'discounts'],
        ['urgent_surcharge_percent', 50, 'Спешна за същия ден, направена до 12:00 ч. (%)', 'discounts'],
    ];

    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category)'
        : 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET label = excluded.label, category = excluded.category';

    $insert = $conn->prepare($sql);
    $updateLabel = $conn->prepare('UPDATE pricing_settings SET label = ?, category = ? WHERE setting_key = ?');
    $check = $conn->prepare('SELECT COUNT(*) FROM pricing_settings WHERE setting_key = ?');

    foreach ($settings as [$key, $value, $label, $category]) {
        $check->execute([$key]);
        if ((int)$check->fetchColumn() === 0) {
            $insert->execute([$key, $value, $label, $category]);
        } else {
            $updateLabel->execute([$label, $category, $key]);
        }
    }
}

/**
 * migration_009_order_form_2026 — еднократни промени по ценоразписа (ноември 2026).
 * Не пипа записаните поръчки и техните цени.
 */
function migrate_order_form_2026(PDO $conn): void
{
    if (schema_migration_applied($conn, 'order_form_2026_11')) {
        return;
    }

    $setValue = $conn->prepare('UPDATE pricing_settings SET setting_value = ? WHERE setting_key = ?');
    foreach ([
        'frame_surcharge_side_150' => 3.00,
        'frame_surcharge_side_200' => 6.00,
        'passepartout_multi_opening' => 1.50,
        'passepartout_cutting_labor' => 3.00,
    ] as $key => $value) {
        $setValue->execute([$value, $key]);
    }

    // Автоматичните отстъпки (обемна, ученик) и старите коефициенти за труд при фигура отпадат.
    $obsolete = [
        'volume_discount_5_threshold',
        'volume_discount_10_threshold',
        'volume_discount_5_percent',
        'volume_discount_10_percent',
        'student_discount_percent',
        'frame_shape_labor_ellipse',
        'frame_shape_labor_circle',
    ];
    $delete = $conn->prepare('DELETE FROM pricing_settings WHERE setting_key = ?');
    foreach ($obsolete as $key) {
        $delete->execute([$key]);
    }

    // Текстовете на правилата се обновяват по новия ценоразпис.
    seed_price_list_rules($conn);
    $ruleUpdate = $conn->prepare('UPDATE price_list_rules SET content = ? WHERE rule_key = ?');
    foreach (['fir', 'wide_profile', 'frame_labor', 'passepartout', 'discounts', 'urgent'] as $ruleKey) {
        $ruleUpdate->execute([build_default_price_list_rule_content($conn, $ruleKey), $ruleKey]);
    }

    // Подредба: стъкло — „Нормално 2 мм“ първо; гръб — велпапе, сив картон, пенокартон, бирен картон, фазер.
    $glassOrder = [
        'Нормално 2 мм' => 10,
        'Антирефлексно' => 20,
        'Консервационно' => 30,
        'Музейно' => 40,
        'Огледало' => 50,
        'Плексиглас 2 mm' => 60,
        'Плексиглас 3 mm' => 70,
        'Антирефлексен плексиглас' => 80,
    ];
    $stmt = $conn->prepare('UPDATE glasses SET sort_order = ? WHERE name = ?');
    foreach ($glassOrder as $name => $sort) {
        $stmt->execute([$sort, $name]);
    }

    $backOrder = ['Велпапе' => 10, 'Сив картон' => 20, 'Пенокартон' => 30, 'Бирен картон' => 40, 'Фазер' => 50];
    $stmt = $conn->prepare('UPDATE backs SET sort_order = ? WHERE name = ?');
    foreach ($backOrder as $name => $sort) {
        $stmt->execute([$sort, $name]);
    }

    mark_schema_migration_applied($conn, 'order_form_2026_11');
}

function migrate_plexiglass_glass_names(PDO $conn): void
{
    $legacyName = 'Плексиглас';
    $twoMmName = 'Плексиглас 2 mm';

    $check = $conn->prepare('SELECT COUNT(*) FROM glasses WHERE name = ?');
    $check->execute([$legacyName]);
    $hasLegacy = (int)$check->fetchColumn() > 0;

    $check->execute([$twoMmName]);
    $hasTwoMm = (int)$check->fetchColumn() > 0;

    if ($hasLegacy && !$hasTwoMm) {
        $conn->prepare('UPDATE glasses SET name = ? WHERE name = ?')->execute([$twoMmName, $legacyName]);
        $conn->prepare('UPDATE orders SET glass = ? WHERE glass = ?')->execute([$twoMmName, $legacyName]);
    }
}

function migrate_normal_glass_name(PDO $conn): void
{
    $check = $conn->prepare('SELECT COUNT(*) FROM glasses WHERE name = ?');
    $check->execute(['Нормално']);
    $hasOld = (int)$check->fetchColumn() > 0;
    $check->execute(['Нормално 2 мм']);
    $hasNew = (int)$check->fetchColumn() > 0;

    // Старите поръчки пазят „Нормално“ — намират артикула чрез catalog_name_aliases().
    if ($hasOld && !$hasNew) {
        $conn->prepare('UPDATE glasses SET name = ? WHERE name = ?')->execute(['Нормално 2 мм', 'Нормално']);
    }

    // Връща временното име „Анти плекс“ (то е само съкращение в таблицата с поръчки).
    $check->execute(['Анти плекс']);
    $hasShort = (int)$check->fetchColumn() > 0;
    $check->execute(['Антирефлексен плексиглас']);
    if ($hasShort && (int)$check->fetchColumn() === 0) {
        $conn->prepare('UPDATE glasses SET name = ? WHERE name = ?')->execute(['Антирефлексен плексиглас', 'Анти плекс']);
        $conn->prepare('UPDATE orders SET glass = ? WHERE glass = ?')->execute(['Антирефлексен плексиглас', 'Анти плекс']);
    }
}

function seed_glasses_from_price_list(PDO $conn): void
{
    migrate_plexiglass_glass_names($conn);
    migrate_normal_glass_name($conn);

    $items = [
        ['Нормално 2 мм', 15.95, 1.02],
        ['Антирефлексно', 22.70, 1.22],
        ['Консервационно', 63.20, 3.98],
        ['Музейно', 161.32, 10.18],
        ['Огледало', 24.43, 3.05],
        ['Плексиглас 2 mm', 21.37, 1.84],
        ['Плексиглас 3 mm', 25.64, 1.84],
    ];

    upsert_catalog_items($conn, 'glasses', $items, true);

    // „Антирефлексен плексиглас“ — същата цена като „Плексиглас 2 mm“.
    $check = $conn->prepare('SELECT COUNT(*) FROM glasses WHERE name = ?');
    $check->execute(['Антирефлексен плексиглас']);
    if ((int)$check->fetchColumn() === 0) {
        $plexi = $conn->prepare('SELECT price, min_price FROM glasses WHERE name = ?');
        $plexi->execute(['Плексиглас 2 mm']);
        $row = $plexi->fetch() ?: ['price' => 21.37, 'min_price' => 1.84];
        $hasSort = table_has_column($conn, 'glasses', 'sort_order');
        $conn->prepare($hasSort
            ? 'INSERT INTO glasses (name, price, min_price, stock, sort_order) VALUES (?, ?, ?, 0, 80)'
            : 'INSERT INTO glasses (name, price, min_price, stock) VALUES (?, ?, ?, 0)')
            ->execute(['Антирефлексен плексиглас', (float)$row['price'], (float)$row['min_price']]);
    }
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

function get_passepartout_price_kind_definition_defaults(): array
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

function seed_passepartout_kind_tier_settings(PDO $conn): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $isMysql
        ? 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category)'
        : 'INSERT INTO pricing_settings (setting_key, setting_value, label, category) VALUES (?, ?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET label = excluded.label, category = excluded.category';

    $insert = $conn->prepare($sql);
    $check = $conn->prepare('SELECT COUNT(*) FROM pricing_settings WHERE setting_key = ?');

    foreach (get_passepartout_price_kind_definition_defaults() as $kindKey => $definition) {
        for ($tierIndex = 1; $tierIndex <= 4; $tierIndex++) {
            $settingKey = passepartout_kind_tier_setting_key($kindKey, $tierIndex);
            $defaultPrice = (float)$definition['tiers'][$tierIndex - 1];
            $label = sprintf(
                '%s — %s (€/бр.)',
                $definition['label'],
                (get_passepartout_tier_scheme_labels()[$definition['scheme']] ?? [])[$tierIndex - 1] ?? ('ниво ' . $tierIndex)
            );

            $check->execute([$settingKey]);
            if ((int)$check->fetchColumn() === 0) {
                $insert->execute([$settingKey, $defaultPrice, $label, 'passepartout_material']);
            }
        }
    }
}

function get_passepartout_price_kind_definitions(?PDO $conn = null): array
{
    if ($conn === null) {
        $conn = get_db_connection();
    }

    $definitions = [];
    foreach (get_passepartout_price_kind_definition_defaults() as $kindKey => $definition) {
        $tiers = [];
        for ($tierIndex = 1; $tierIndex <= 4; $tierIndex++) {
            $settingKey = passepartout_kind_tier_setting_key($kindKey, $tierIndex);
            $defaultPrice = (float)$definition['tiers'][$tierIndex - 1];
            $tiers[] = get_pricing_setting($conn, $settingKey, $defaultPrice);
        }

        $definitions[$kindKey] = array_merge($definition, ['tiers' => $tiers]);
    }

    return $definitions;
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

function get_passepartout_price_kinds_for_display(?PDO $conn = null): array
{
    $rows = [];
    foreach (get_passepartout_price_kind_definitions($conn) as $kindKey => $definition) {
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
