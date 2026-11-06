<?php
/**
 * Тестове за ценообразуването и правилата от заданието (ноември 2026).
 * Изпълнение:  php tests/run_tests.php
 * Работят върху временна SQLite база — не пипат реалната база данни.
 */

$tmpDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vanina_test_' . getmypid() . '.db';
@unlink($tmpDb);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $tmpDb);
putenv('SESSION_IDLE_TIMEOUT_MINUTES=240');
$_SERVER['REQUEST_METHOD'] = 'GET';

require __DIR__ . '/../includes/config.php';

$conn = get_db_connection();
$failures = 0;
$passes = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures, $passes;
    if ($condition) {
        $passes++;
        echo "  ok   {$name}\n";
    } else {
        $failures++;
        echo "  FAIL {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function near(float $a, float $b, float $eps = 0.005): bool
{
    return abs($a - $b) < $eps;
}

function seed_profile(PDO $conn, string $name, float $price, float $width, string $type, float $stock = 100): void
{
    $conn->prepare('INSERT INTO profiles (name, price, stock, width_cm, profile_type) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $price, $stock, $width, $type]);
}

function seed_passepartout(PDO $conn, string $name, string $kind, array $stockBySheet): int
{
    $conn->prepare('INSERT INTO passepartouts (name, price, stock, price_kind) VALUES (?, 0, 0, ?)')->execute([$name, $kind]);
    $id = (int)$conn->lastInsertId();
    $sheetIds = array_column(get_all_sheet_types($conn), 'id', 'name');
    $stocks = [];
    foreach ($stockBySheet as $sheetName => $qty) {
        $stocks[(int)$sheetIds[$sheetName]] = $qty;
    }
    save_passepartout_sheet_stocks($conn, $id, $stocks);

    return $id;
}

function service_id(PDO $conn, string $name): int
{
    $stmt = $conn->prepare('SELECT id FROM services WHERE name = ?');
    $stmt->execute([$name]);

    return (int)$stmt->fetchColumn();
}

seed_profile($conn, 'Д100', 10.0, 2.0, 'wood');
seed_profile($conn, 'П200', 8.0, 3.0, 'plastic');
seed_profile($conn, 'М300', 6.0, 1.0, 'metal');
seed_profile($conn, 'ДШ12', 20.0, 12.0, 'wood');
seed_profile($conn, 'ПШ10', 15.0, 10.0, 'plastic');
seed_profile($conn, 'ДИСТ1', 4.0, 1.0, 'wood', 0);
seed_passepartout($conn, '501', 'type2', ['80x100' => 5]);
seed_passepartout($conn, '777', 'type2', ['80x100' => 0]);

echo "Надценка за големи рамки\n";
foreach ([[100, 160, 3.00], [160, 160, 6.00], [100, 210, 6.00], [210, 210, 12.00], [150, 100, 3.00], [149, 149, 0.0], [160, 210, 6.00]] as [$w, $h, $expected]) {
    $actual = frame_labor_side_surcharge($conn, $w, $h);
    check("Рамка {$w}×{$h} → +" . number_format($expected, 2) . ' €', near($actual, $expected), "получено {$actual}");
}
$pricing = calculate_order_pricing($conn, ['width' => 210, 'height' => 210, 'profile' => 'М300', 'frame_count' => 1]);
check('Надценката влиза в цената на поръчката (210×210)', near($pricing['frame_side_surcharge'], 12.0), (string)$pricing['frame_side_surcharge']);
$small = calculate_order_pricing($conn, ['width' => 50, 'height' => 50, 'profile' => 'М300', 'frame_count' => 1]);
check('Метален труд + надценка = разлика 12 €', near($pricing['frame_labor_cost'] - $small['frame_labor_cost'], 12.0));

echo "Поле\n";
$geo = order_pricing_geometry(['width' => 50, 'height' => 70, 'field_margin_all' => 4]);
check('50×70 с поле 4 см → 48×68', near($geo['frame_width'], 48) && near($geo['frame_height'], 68), "{$geo['frame_width']}×{$geo['frame_height']}");
$geo = order_pricing_geometry(['width' => 50, 'height' => 70, 'field_margin_all' => 4, 'field_margin_t' => 3, 'field_margin_l' => 2, 'field_margin_r' => 1, 'field_margin_b' => 0]);
check('Отделните клетки имат предимство: горе 3, ляво 2, дясно 1, долу 0 → 47×67', near($geo['frame_width'], 47) && near($geo['frame_height'], 67), "{$geo['frame_width']}×{$geo['frame_height']}");
$geo = order_pricing_geometry(['width' => 50, 'height' => 70, 'field_margin_l' => '1,5']);
check('Дробни стойности (1,5 см)', near($geo['frame_width'], 48.5) && near($geo['frame_height'], 70));
$withField = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'field_margin_all' => 4, 'glass' => 'Нормално 2 мм', 'frame_count' => 1]);
$plain = calculate_order_pricing($conn, ['width' => 48, 'height' => 68, 'glass' => 'Нормално 2 мм', 'frame_count' => 1]);
check('Стъклото при поле 4 см е като при 48×68', near($withField['glass_cost'], $plain['glass_cost']));

echo "Паспарту — отстояния и размер на рамката\n";
$geo = order_pricing_geometry([
    'width' => 50, 'height' => 70, 'passepartout_use' => 'passepartout',
    'passepartout_layers' => json_encode([['name' => '501', 'all' => 5]]),
]);
check('Паспарту 5 см отвсякъде → лист 60×80, рамка 60×80', near($geo['layers'][0]['width'], 60) && near($geo['frame_height'], 80));
$geo = order_pricing_geometry([
    'width' => 50, 'height' => 70, 'passepartout_use' => 'both', 'passepartout_background' => '777',
    'passepartout_layers' => json_encode([['name' => '501', 'all' => 5, 'l' => 6]]),
    'passepartout_bg_offsets' => json_encode(['all' => 8]),
]);
check('Отделна клетка има предимство пред „отвсякъде“ (Л 6, другите 0)', near($geo['layers'][0]['width'], 56) && near($geo['layers'][0]['height'], 70));
check('Рамката обхваща най-голямото (фон 66×86)', near($geo['frame_width'], 66) && near($geo['frame_height'], 86));

echo "Отвори и рязане на паспарту\n";
$base = ['width' => 30, 'height' => 40, 'passepartout' => '501', 'passepartout_use' => 'passepartout', 'frame_count' => 1];
$one = calculate_order_pricing($conn, $base);
$three = calculate_order_pricing($conn, array_merge($base, ['passepartout_openings' => 3]));
check('Всеки следващ отвор +1.50 € (3 отвора → +3.00 €)', near($three['passepartout_cost'] - $one['passepartout_cost'], 3.00));
$client = calculate_order_pricing($conn, ['width' => 30, 'height' => 40, 'client_passepartout_cutting' => 1, 'frame_count' => 2]);
check('Рязане на паспарту на клиента 3.00 €/бр.', near($client['passepartout_labor_cost'], 6.00));

echo "Подрамки\n";
$sub22 = calculate_subframe_pricing($conn, ['subframe_thickness' => '2.2'], 40, 60, 1);
$sub33 = calculate_subframe_pricing($conn, ['subframe_thickness' => '3.3'], 40, 60, 1);
check('Подрамка 40×60 (30/40–50/70): 2.0 л.м. × 6.90 + 3.00 труд', near($sub22['cost'], 2.0 * 6.90 + 3.00), (string)$sub22['cost']);
check('Подрамка 3.3 см: материал ×1.4', near($sub33['material_cost'], round(2.0 * 6.90 * 1.4, 2)));
check('Подрамка 3.3 см: труд ×1.4', near($sub33['labor_cost'], 3.00 * 1.4));
$subSmall = calculate_subframe_pricing($conn, ['subframe_thickness' => '2.2'], 30, 40, 1);
check('До 30/40 см — без труд', near($subSmall['labor_cost'], 0.0) && near($subSmall['cost'], 1.4 * 6.90));
$sub3 = calculate_subframe_pricing($conn, ['subframe_thickness' => '2.2'], 70, 100, 1);
check('70/100: 3 × 70 + 2 × 100 = 4.10 л.м. × 7.80', near($sub3['meters'], 4.10) && near($sub3['material_cost'], round(4.10 * 7.80, 2)));
$sub4 = calculate_subframe_pricing($conn, ['subframe_thickness' => '2.2'], 100, 150, 1);
check('100/150: 4 × 100 + 2 × 150 = 7.00 л.м. × 9.00', near($sub4['meters'], 7.00) && near($sub4['material_cost'], 63.0));
$subBig = calculate_subframe_pricing($conn, ['subframe_thickness' => '2.2', 'subframe_manual_price' => '55'], 120, 160, 1);
check('Над 100/150 — ръчно въведена цена', $subBig['manual'] && near($subBig['cost'], 55.0));

echo "Нестандартна фигура\n";
$rect = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1]);
$ellipse = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1, 'frame_shape' => 'ellipse_12']);
$meters = $rect['profile_lines'][0]['meters'];
check('Елипса: метраж × 1.25 × цена × 3', near($ellipse['profile_material_cost'], round($meters * 1.25 * 10 * 3, 2)), (string)$ellipse['profile_material_cost']);
check('Трудът остава стандартен', near($ellipse['frame_labor_cost'], $rect['frame_labor_cost']));
$tiny = calculate_order_pricing($conn, ['width' => 5, 'height' => 5, 'profile' => 'М300', 'frame_count' => 2, 'frame_shape' => 'circle_12']);
check('Под 10 € за брой → 10.00 €/бр.', near($tiny['profile_material_cost'], 20.0), (string)$tiny['profile_material_cost']);
$req = collect_order_stock_requirements($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1, 'frame_shape' => 'triangle']);
check('Към склада: метраж × 1.25', near($req['items'][0]['amount'], round($meters * 1.25, 2)));

echo "Изисквания към рамката и дълги страни\n";
$outer = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1, 'frame_measure' => 'outer']);
check('„Външно“ — без фира', near($outer['profile_lines'][0]['meters'], 2.4) && $outer['profile_lines'][0]['fir_meters'] === null);
$clear = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1, 'frame_measure' => 'clear']);
check('„Светло“ — цената без промяна', near($clear['total'], $rect['total']));
$woodLong = calculate_order_pricing($conn, ['width' => 250, 'height' => 100, 'profile' => 'ДШ12', 'frame_count' => 1]);
check('Дърво, добавка 96 см, страна 250 → таксува се по 300', near($woodLong['profile_lines'][0]['base_meters'], 2 * (300 + 100) / 100));
$plasticLong = calculate_order_pricing($conn, ['width' => 250, 'height' => 100, 'profile' => 'ПШ10', 'frame_count' => 1]);
check('Пластмаса, добавка 80 см, страна 250 → таксува се по 290', near($plasticLong['profile_lines'][0]['base_meters'], 2 * (290 + 100) / 100));
$notLong = calculate_order_pricing($conn, ['width' => 240, 'height' => 100, 'profile' => 'ПШ10', 'frame_count' => 1]);
check('Страна точно 240 см — без промяна', near($notLong['profile_lines'][0]['base_meters'], 2 * (240 + 100) / 100));
$narrow = calculate_order_pricing($conn, ['width' => 250, 'height' => 100, 'profile' => 'Д100', 'frame_count' => 1]);
check('Добавка под 80 см — без промяна', near($narrow['profile_lines'][0]['base_meters'], 2 * (250 + 100) / 100));

echo "Окачване\n";
$hang = calculate_order_pricing($conn, ['width' => 40, 'height' => 50, 'hanging' => 'Връзка до 1 м, до 19 кг, без закачалки', 'hanger' => 'Закачалка тип „крокодил“', 'frame_count' => 1]);
check('Връзка 19 кг + 2 закачалки „крокодил“ = 3.00 + 2 × 0.60', near($hang['hanging_cost'], 4.20), (string)$hang['hanging_cost']);
$hang3 = calculate_order_pricing($conn, ['width' => 40, 'height' => 50, 'hanger' => 'Закачалка малка', 'hanger_count' => 3, 'frame_count' => 2]);
check('Брой закачалки 3 × 0.30 × 2 рамки', near($hang3['hanging_cost'], 1.80));
$legacy = calculate_order_pricing($conn, ['width' => 40, 'height' => 50, 'hanging' => 'Връзка до 9 kg', 'frame_count' => 1]);
check('Старото име „Връзка до 9 kg“ се разпознава (алиас)', near($legacy['hanging_cost'], 2.00));

echo "Каталог и миграция\n";
$glassNames = $conn->query('SELECT name FROM glasses ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
check('Стъкло: „Нормално 2 мм“ е първо', ($glassNames[0] ?? '') === 'Нормално 2 мм', implode(', ', $glassNames));
check('Стъкло: „Антирефлексен плексиглас“ е след „Плексиглас 3 mm“', array_search('Антирефлексен плексиглас', $glassNames) === array_search('Плексиглас 3 mm', $glassNames) + 1);
$ar = get_catalog_item_by_name($conn, 'glasses', 'Антирефлексен плексиглас');
$p2 = get_catalog_item_by_name($conn, 'glasses', 'Плексиглас 2 mm');
check('Антирефлексният е със същата цена като „Плексиглас 2 mm“', $ar && $p2 && near((float)$ar['price'], (float)$p2['price']));
check('Старото „Нормално“ намира новия артикул', get_catalog_item_by_name($conn, 'glasses', 'Нормално') !== null);
$backNames = $conn->query('SELECT name FROM backs ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
check('Гръб: велпапе, сив картон, пенокартон, бирен картон, фазер', array_slice($backNames, 0, 5) === ['Велпапе', 'Сив картон', 'Пенокартон', 'Бирен картон', 'Фазер'], implode(', ', $backNames));
$inactive = $conn->query("SELECT is_active FROM services WHERE name = 'Опъване платно на подрамка (странично)'")->fetchColumn();
check('„Опъване платно на подрамка (странично)“ не е в програмата', $inactive === false || (int)$inactive === 0);
check('Отстъпки: няма автоматични настройки', get_pricing_setting($conn, 'volume_discount_5_threshold', -1) === -1.0);
init_db();
check('Миграцията е идемпотентна (повторно init_db)', (int)$conn->query('SELECT COUNT(*) FROM services WHERE name = \'Каширане на пенокартон\'')->fetchColumn() === 1);
$conn->exec("UPDATE services SET price = 99 WHERE name = 'Клипс'");
init_db();
check('Цена, редактирана в „Услуги“, не се презаписва при следваща заявка', near((float)$conn->query("SELECT price FROM services WHERE name = 'Клипс'")->fetchColumn(), 99));
$conn->exec("UPDATE services SET price = 0.5 WHERE name = 'Клипс'");

echo "Миграция от старите имена (както са в работната база)\n";
$conn->exec("DELETE FROM schema_migrations WHERE name = 'services_catalog_2026_11'");
$conn->exec("DELETE FROM services WHERE name = 'Изправяне на вакуум преса'");
$conn->exec("INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES ('Изправяне на преса', 8, 0, 'piece', 'general')");
$legacyPressId = service_id($conn, 'Изправяне на преса');
$conn->exec("INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES ('Каширане пенокартон', 26, 6, 'sqm', 'mounting')");
$legacyDupId = service_id($conn, 'Каширане пенокартон');
$conn->exec("INSERT INTO services (name, price, min_price, pricing_unit, category) VALUES ('Опъване платно на подрамка (странично)', 3.05, 0, 'piece', 'stretching')");
migrate_services_price_list_2026($conn);
check('Старо име се преименува, ID-то се запазва (старите поръчки)', service_id($conn, 'Изправяне на вакуум преса') === $legacyPressId);
check('Дубликат със старо име се скрива, не се трие', (int)$conn->query("SELECT is_active FROM services WHERE id = {$legacyDupId}")->fetchColumn() === 0);
check('„Опъване платно на подрамка (странично)“ се скрива', (int)$conn->query("SELECT is_active FROM services WHERE name = 'Опъване платно на подрамка (странично)'")->fetchColumn() === 0);
check('Скритите услуги не се предлагат във формата', !in_array('Каширане пенокартон', array_column(get_all_services($conn, true), 'name'), true));
check('Скритите услуги остават за етикетите на старите поръчки', in_array('Каширане пенокартон', array_column(get_all_services($conn), 'name'), true));
$conn->exec("DELETE FROM schema_migrations WHERE name = 'hanging_catalog_2026_11'");
$conn->exec("DELETE FROM hanging_options WHERE name = 'Връзка до 1 м, до 9 кг, с 2 закачалки'");
$conn->exec("INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES ('Връзка до 9 kg', 2, 0, 'piece', 7)");
migrate_hanging_options_2026($conn);
check('Окачване: старото име се преименува, наличността се пази', (int)$conn->query("SELECT stock FROM hanging_options WHERE name = 'Връзка до 1 м, до 9 кг, с 2 закачалки'")->fetchColumn() === 7);

echo "Допълнителни услуги\n";
$montage = service_id($conn, 'Монтаж на място');
$svc = calculate_order_pricing($conn, ['width' => 40, 'height' => 50, 'frame_count' => 2, 'extra_services' => json_encode([$montage])]);
check('Монтаж на място: 2 × 10 € → минимум 40 € на поръчка', near($svc['extra_services_cost'], 40.0));
$transport = service_id($conn, 'Транспорт');
$svc = calculate_order_pricing($conn, ['width' => 40, 'height' => 50, 'frame_count' => 1, 'extra_services' => json_encode([$transport]), 'extra_services_manual' => json_encode([(string)$transport => 35])]);
check('Транспорт — твърда цена от клетката', near($svc['extra_services_cost'], 35.0));
$kash = service_id($conn, 'Каширане на пенокартон');
$svc = calculate_order_pricing($conn, ['width' => 100, 'height' => 100, 'field_margin_all' => 8, 'frame_count' => 1, 'extra_services' => json_encode([$kash])]);
check('Кв.м услугите ползват размерите от ред 1 (1 кв.м × 26 €)', near($svc['extra_services_cost'], 26.0));
$svc = calculate_order_pricing($conn, ['width' => 10, 'height' => 10, 'frame_count' => 1, 'extra_services' => json_encode([$kash])]);
check('Начислява се по-голямото от цената и минимума (6 €)', near($svc['extra_services_cost'], 6.0));
$spacer = service_id($conn, 'Поставяне на дистанционер на рамка');
$svc = calculate_order_pricing($conn, ['width' => 50, 'height' => 70, 'frame_count' => 1, 'extra_services' => json_encode([$spacer]), 'spacer_profile' => 'ДИСТ1']);
check('Дистанционер: 3.00 € + 2.4 л.м. × 4.00 €', near($svc['extra_services_cost'], 3.00 + 2.4 * 4.00));

echo "Отстъпки и спешност\n";
$big = ['width' => 100, 'height' => 100, 'profile_ready_frame' => 1, 'profile_ready_frame_price' => 600, 'frame_count' => 1];
$p = calculate_order_pricing($conn, $big);
check('Няма автоматична отстъпка над 500 €', near($p['total'], 600.0));
$p = calculate_order_pricing($conn, $big + ['urgent' => 2]);
check('Спешна за следващия ден +30 %', near($p['total'], 780.0));
$p = calculate_order_pricing($conn, $big + ['urgent' => 1]);
check('Спешна за същия ден +50 %', near($p['total'], 900.0));
$p = calculate_order_pricing($conn, $big + ['manual_discount' => 10, 'complex_order_percent' => 50]);
check('Ръчна отстъпка 10 % и сложна поръчка +50 %', near($p['total'], 540.0 + 270.0));

echo "Склад: количество 0\n";
$shortOrder = enrich_order_pricing_fields($conn, ['width' => 50, 'height' => 70, 'profile' => 'ДИСТ1', 'frame_count' => 1]);
$shortages = find_order_stock_shortages($conn, $shortOrder);
check('Предупреждение за недостатъчна наличност (профил с наличност 0)', count($shortages) === 1 && $shortages[0]['name'] === 'ДИСТ1');
$okOrder = enrich_order_pricing_fields($conn, ['width' => 50, 'height' => 70, 'profile' => 'Д100', 'frame_count' => 1]);
check('Без предупреждение при наличност', find_order_stock_shortages($conn, $okOrder) === []);
$_POST = [];
$persist = ['stock_order' => $shortOrder];
check('Без потвърждение поръчката не се записва', apply_stock_shortage_confirmation($conn, $persist, []) === false);
check('С потвърждение „Материалът е наличен“ — маркировка и липсващо количество', apply_stock_shortage_confirmation($conn, $persist, ['stock_confirmed' => '1']) === true
    && (int)$persist['stock_override'] === 1 && str_contains((string)$persist['stock_shortage'], 'ДИСТ1'));

echo "Списък, натоварване, напомняния\n";
$today = date('Y-m-d');
$old = (new DateTime('today'))->modify('-8 days')->format('Y-m-d');
$recent = (new DateTime('today'))->modify('-3 days')->format('Y-m-d');
$insert = $conn->prepare('INSERT INTO orders (order_number, sub_order_number, date, width, height, profile, frame_count, paid, collected, description, extra_services, passepartout, passepartout_use) VALUES (?, 0, ?, 50, 70, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([1, $today, 'М300', 2, 0, 0, '', '[]', '501 (паспарту)', 'passepartout']);
$insert->execute([2, $today, 'П200', 1, 0, 0, 'бележка', json_encode([$montage]), '', '']);
$insert->execute([3, $today, 'Д100', 3, 1, 0, '', '[]', '', '']);
$insert->execute([4, $old, 'Д100', 1, 1, 0, '', '[]', '', '']);
$insert->execute([5, $recent, 'Д100', 1, 0, 0, '', '[]', '', '']);
$insert->execute([6, $old, 'Д100', 1, 0, 1, '', '[]', '', '']);
$orders = $conn->query('SELECT * FROM orders ORDER BY order_number')->fetchAll();
check('ДА/НЕ: допълнителни услуги', order_has_additional_services($orders[1]) && !order_has_additional_services($orders[0]));
check('ДА/НЕ: описание', order_has_description($orders[1]) && !order_has_description($orders[0]));
$chart = build_order_workload_chart($conn, $orders, 3);
$day = $chart['days'][0];
check('Натоварване днес: метални 2, пластмасови 1, дървени 3, паспартута 2', $day['metal'] === 2 && $day['plastic'] === 1 && $day['wood'] === 3 && $day['passepartout'] === 2, json_encode($day));
$uncollected = array_column(fetch_overdue_uncollected_orders($conn), 'order_number');
check('Неполучени, минала е седмица след датата (платени или не): само № 4', $uncollected == [4], json_encode($uncollected));
$unpaid = array_column(fetch_collected_unpaid_orders($conn), 'order_number');
check('Предадени, но неплатени: № 6', $unpaid == [6], json_encode($unpaid));


echo "Сигурност\n";
$_SESSION = ['csrf_token' => 'tok123'];
$_POST = ['_csrf' => 'tok123'];
check('CSRF: валиден токен се приема', csrf_request_is_valid());
$_POST = ['_csrf' => 'грешен'];
check('CSRF: грешен токен се отхвърля', !csrf_request_is_valid());
$_POST = [];
check('CSRF: липсващ токен се отхвърля', !csrf_request_is_valid());
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'tok123';
check('CSRF: токен в хедър (fetch) се приема', csrf_request_is_valid());
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$html = inject_csrf_fields('<form method="post" action="/x"></form><form method="get"></form>');
check('CSRF поле се добавя само в POST форми', substr_count($html, 'name="_csrf"') === 1);
$token = User::create('нов', 'new@example.test', 'парола123');
check('Регистрация: създава се таен токен', is_string($token) && strlen($token) === 64);
check('Потвърждение по имейл адрес вече не работи', !User::verifyByToken('new@example.test'));
check('Потвърждение с токена работи', User::verifyByToken($token));
check('Токенът е еднократен', !User::verifyByToken($token));

echo "Склад: без отрязване на 0\n";
$conn->exec("UPDATE profiles SET stock = 1 WHERE name = 'ДИСТ1'");
$stockOrder = enrich_order_pricing_fields($conn, ['width' => 50, 'height' => 70, 'profile' => 'ДИСТ1', 'frame_count' => 1]);
deduct_stock_for_order($conn, $stockOrder);
$after = (float)$conn->query("SELECT stock FROM profiles WHERE name = 'ДИСТ1'")->fetchColumn();
check('Отчисляване над наличността → отрицателна наличност (липсващо)', near($after, 1 - 2.48), (string)$after);
restore_stock_for_order($conn, $stockOrder);
$restored = (float)$conn->query("SELECT stock FROM profiles WHERE name = 'ДИСТ1'")->fetchColumn();
check('Връщането възстановява точно началната наличност', near($restored, 1.0), (string)$restored);

echo "Склад: допълнителни паспартута\n";
$pp2 = seed_passepartout($conn, '602', 'type2', ['80x100' => 10]);
$ppOrder = enrich_order_pricing_fields($conn, [
    'width' => 30, 'height' => 40, 'frame_count' => 1, 'passepartout' => '501', 'passepartout_use' => 'passepartout',
    'passepartout_layers' => json_encode([['name' => '501'], ['name' => '602', 'all' => 3]]),
]);
$extra = decode_json_array($ppOrder['passepartout_extra_stock']);
check('Вторият слой паспарту се записва за склада', count($extra) === 1 && (int)$extra[0]['passepartout_id'] === $pp2);
$sheet = (int)$extra[0]['sheet_type_id'];
$before = get_passepartout_sheet_stocks($conn, $pp2)[$sheet] ?? 0;
deduct_stock_for_order($conn, $ppOrder);
$mid = get_passepartout_sheet_stocks($conn, $pp2)[$sheet] ?? 0;
check('Вторият слой се отчислява', $mid < $before);
restore_stock_for_order($conn, $ppOrder);
check('…и се връща при изтриване', near(get_passepartout_sheet_stocks($conn, $pp2)[$sheet] ?? 0, $before));
$legacy = $ppOrder;
unset($legacy['passepartout_extra_stock']);
check('Стари поръчки (без запис) не връщат материал, който не е бил отчислен', count(resolve_passepartout_stock_actions($conn, $legacy)) === 1);

echo "Номер на поръчка\n";
$conn->beginTransaction();
$n1 = next_order_number($conn);
$n2 = next_order_number($conn);
$conn->commit();
$maxNow = (int)$conn->query('SELECT MAX(order_number) FROM orders')->fetchColumn();
check('Броячът дава последователни номера над най-големия', $n1 === $maxNow + 1 && $n2 === $n1 + 1, "$n1, $n2, max $maxNow");

echo "Статуси с един клик\n";
$conn->exec("INSERT INTO orders (order_number, sub_order_number, date, width, height, frame_count, paid, collected, customer_name) VALUES (900, 0, '2026-11-20', 30, 40, 1, 0, 0, 'Статус тест')");
$sid = (int)$conn->lastInsertId();
$o = update_order_status($conn, $sid, 'ready', true);
check('Маркиране „Готова“ + дата', (int)$o['ready'] === 1 && $o['ready_at'] !== null);
$o = update_order_status($conn, $sid, 'collected', true);
check('„Получена“ записва дата', (int)$o['collected'] === 1 && $o['collected_at'] !== null);
$o = update_order_status($conn, $sid, 'ready', false);
check('Махане на „Готова“ маха и „Получена“', (int)$o['ready'] === 0 && (int)$o['collected'] === 0);
$o = update_order_status($conn, $sid, 'collected', true);
check('„Получена“ прави поръчката и „Готова“', (int)$o['ready'] === 1);
check('Невалидно поле се отказва', update_order_status($conn, $sid, 'price', true) === null);
$chartOrders = [array_merge($o, ['profile' => 'Д100', 'date' => date('Y-m-d')])];
check('Готовите поръчки не натоварват цеха', build_order_workload_chart($conn, $chartOrders, 1)['days'][0]['wood'] === 0);

echo "Клиенти\n";
check('Телефон +359 88 123 4567 → 0881234567', normalize_phone('+359 88 123 4567') === '0881234567');
check('Телефон 00359881234567 → 0881234567', normalize_phone('00359881234567') === '0881234567');
$c1 = resolve_customer_for_order($conn, null, 'Мария Петрова', '088 123 4567', 'maria@example.test');
$c2 = resolve_customer_for_order($conn, null, 'Мария П.', '+359881234567', '');
check('Същият телефон в друг формат → същият клиент', $c1 === $c2);
$c3 = resolve_customer_for_order($conn, null, 'Без контакт', '', '');
check('Без телефон и имейл не се създава клиент', $c3 === null);
$c4 = resolve_customer_for_order($conn, null, 'Иван', '', 'MARIA@example.test');
check('Разпознаване по имейл (без значение главни/малки)', $c4 === $c1);
$found = search_customers($conn, '0881');
check('Търсене по част от телефона', count($found) === 1 && $found[0]['id'] === $c1);
$found = search_customers($conn, 'иван');
check('Търсене по име', count($found) >= 1);
$conn->exec("INSERT INTO orders (order_number, sub_order_number, date, customer_name, customer_phone, paid, collected) VALUES (901, 0, '2026-11-20', 'Стар клиент', '0899 111 222', 0, 0)");
$oldOrderId = (int)$conn->lastInsertId();
backfill_customers_from_orders($conn);
check('Старите поръчки се свързват с клиент по телефон', (int)$conn->query("SELECT customer_id FROM orders WHERE id = {$oldOrderId}")->fetchColumn() > 0);

echo "Уведомяване\n";
$notifyOrder = ['id' => $sid, 'order_number' => 900, 'sub_order_number' => 0, 'customer_name' => 'Мария', 'customer_phone' => '0881234567',
    'price' => 50, 'advance_payment' => 20, 'paid' => 0, 'collected' => 0, 'date' => date('Y-m-d'), 'customer_id' => $c1];
$msg = build_notification_message($notifyOrder, 'ready');
check('Текст „готова“ с дължима сума', str_contains($msg, 'Мария') && str_contains($msg, '№ 900') && str_contains($msg, '30.00 €'), $msg);
check('Повод по подразбиране: платено+получено → няма; получена неплатена → плащане',
    default_notification_kind(array_merge($notifyOrder, ['collected' => 1])) === 'payment');
check('SMS линк в международен формат', str_starts_with(sms_app_link('0881234567', 'текст'), 'sms:+359881234567?&body='));
$result = send_order_notification($conn, $notifyOrder, 'sms', 'ready');
check('SMS без доставчик: отваря приложението и се записва', $result['ok'] && $result['status'] === 'opened' && isset($result['sms_link']));
check('Историята пази уведомяването', count(fetch_order_notifications($conn, $sid)) === 1);
$noEmail = send_order_notification($conn, array_merge($notifyOrder, ['customer_id' => null]), 'email', 'ready');
check('Имейл без адрес → ясна грешка', !$noEmail['ok'] && str_contains($noEmail['message'], 'няма имейл'));
check('Имейлът на клиента се ползва, ако поръчката няма', order_contact($conn, $notifyOrder)['email'] === 'maria@example.test');

echo "Използвани материали\n";
$report = build_used_materials_report($conn, (int)date('Y'));
check('Справката сумира по месеци', count($report) >= 1 && $report[0]['total_value'] > 0);

echo "Сесия\n";
$_SESSION = ['user_id' => 1, 'last_activity' => time() - 3 * 3600];
enforce_session_idle_timeout();
check('3 часа бездействие — сесията е активна', isset($_SESSION['user_id']));
$_SESSION = ['user_id' => 1, 'last_activity' => time() - 4 * 3600 - 5];
enforce_session_idle_timeout();
check('Над 4 часа бездействие — изход', !isset($_SESSION['user_id']) && !empty($_SESSION['session_expired']));
$_SESSION = ['user_id' => 1, 'last_activity' => time() - 100];
$_SERVER['HTTP_X_SESSION_PASSIVE'] = '1';
enforce_session_idle_timeout();
check('Пасивните заявки (автоматично опресняване) не удължават сесията', $_SESSION['last_activity'] < time() - 50);
unset($_SERVER['HTTP_X_SESSION_PASSIVE']);
enforce_session_idle_timeout();
check('Реална активност удължава сесията', $_SESSION['last_activity'] >= time() - 1);

$conn = null;
@unlink($tmpDb);

echo "\n{$passes} успешни, {$failures} неуспешни\n";
exit($failures > 0 ? 1 : 0);
