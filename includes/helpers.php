<?php

// Зареждат се и оттук, за да работи кодът и със стар/сървърен config.php
// (config.php е изключен от SFTP качването в .vscode/sftp.json).
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/notifications.php';

configure_error_display();
harden_session_cookie();
send_security_headers();

function url_for(string $route, array $params = []): string
{
    $routes = [
        'home' => '/',
        'login' => '/login',
        'signup' => '/signup',
        'logout' => '/logout',
        'archives' => '/archives',
        'login_logs' => '/login_logs',
        'interface' => '/interface',
        'warehouse' => '/warehouse',
        'profiles' => '/profiles',
        'glasses' => '/glasses',
        'passepartouts' => '/passepartouts',
        'backs' => '/backs',
        'hanging' => '/hanging',
        'services' => '/services',
        'used_materials' => '/used_materials',
        'reminders' => '/reminders',
        'customers' => '/customers',
        'add_back' => '/add_back',
        'add_hanging' => '/add_hanging',
        'submit_feedback' => '/submit_feedback',
        'add_order' => '/add_order',
        'add_painting_reservation' => '/add_painting_reservation',
        'delete_painting_reservation' => '/delete_painting_reservation',
        'add_profile' => '/add_profile',
        'add_glass' => '/add_glass',
        'add_passepartout' => '/add_passepartout',
        'bulk_edit_profiles' => '/bulk_edit_profiles',
        'profiles_raise_prices_10' => '/profiles_raise_prices_10',
        'bulk_edit_passepartouts' => '/bulk_edit_passepartouts',
        'save_services' => '/save_services',
        'save_hanging_weight_tiers' => '/save_hanging_weight_tiers',
        'verify_email' => '/verify',
    ];

    $path = $routes[$route] ?? '/' . $route;

    if ($route === 'verify_email' && isset($params['email'])) {
        $path = '/verify/' . urlencode($params['email']);
        unset($params['email']);
    }

    return BASE_PATH . $path;
}

function static_url(string $filename): string
{
    $path = ROOT_PATH . '/static/' . ltrim(str_replace('\\', '/', $filename), '/');
    $version = is_file($path) ? (string)filemtime($path) : (string)time();

    return BASE_PATH . '/static/' . $filename . '?v=' . $version;
}

function flash(string $message, string $category = 'info'): void
{
    $_SESSION['flash'][] = ['category' => $category, 'message' => $message];
}

function get_flashed_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function redirect(string $path): void
{
    header('Location: ' . BASE_PATH . $path);
    exit;
}

function current_user(): ?User
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return User::get((int)$_SESSION['user_id']);
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_authenticated()) {
        if (is_json_request()) {
            json_response(['authenticated' => false, 'error' => 'session_expired'], 401);
        }
        redirect('/login');
    }
}

function login_user(User $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $user->id;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['session_expired']);
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['last_activity']);
}

/**
 * Проверка за наличност преди запис. При недостиг поръчката продължава само с потвърждение
 * „Материалът е наличен“ — тогава се маркира и липсващото количество се записва за проверка на склада.
 *
 * @return bool false — поръчката не се записва (няма потвърждение)
 */
function apply_stock_shortage_confirmation(PDO $conn, array &$order, array $post, ?array $previousOrder = null): bool
{
    $shortages = find_order_stock_shortages($conn, $order['stock_order'], $previousOrder);
    if ($shortages === []) {
        $order['stock_override'] = 0;
        $order['stock_shortage'] = null;

        return true;
    }

    if (empty($post['stock_confirmed'])) {
        flash(
            'Недостатъчна наличност: ' . format_stock_shortage_summary($shortages)
            . '. Поръчката не е записана — потвърдете „Материалът е наличен“, ако колегите могат да я изпълнят.',
            'warning'
        );

        return false;
    }

    $order['stock_override'] = 1;
    $order['stock_shortage'] = json_encode($shortages, JSON_UNESCAPED_UNICODE);

    return true;
}

function render(string $template, array $data = []): void
{
    $current_user = current_user();
    $current_page = $data['current_page'] ?? '';
    $title = $data['title'] ?? '';
    $extra_css = $data['extra_css'] ?? null;
    $extra_js = $data['extra_js'] ?? null;
    $flashed_messages = get_flashed_messages();

    extract($data);

    ob_start();
    include ROOT_PATH . '/templates/' . $template;
    $content = inject_csrf_fields((string)ob_get_clean());

    include ROOT_PATH . '/templates/layout.php';
}

function format_date_ddmmyyyy(?string $dateStr): string
{
    if (!$dateStr) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt && str_contains($dateStr, '/')) {
        $dt = DateTime::createFromFormat('d/m/Y', $dateStr);
    }
    return $dt ? $dt->format('d/m/Y') : $dateStr;
}

function parse_user_date_to_iso(?string $input): ?string
{
    $input = trim($input ?? '');
    if ($input === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
        return $input;
    }
    $dt = DateTime::createFromFormat('d/m/Y', $input);
    if (!$dt) {
        $dt = DateTime::createFromFormat('d/m/y', $input);
    }
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', $input);
    }

    return $dt ? $dt->format('Y-m-d') : null;
}

function order_accepted_date_iso(array $order): string
{
    $accepted = trim($order['accepted_date'] ?? '');
    if ($accepted !== '') {
        return $accepted;
    }

    return trim($order['date'] ?? '');
}

function normalize_passepartout_use(?string $use): string
{
    $use = trim($use ?? '');
    return in_array($use, ['passepartout', 'background', 'both'], true) ? $use : '';
}

function passepartout_use_form_options(): array
{
    return [
        'passepartout' => 'Паспарту',
        'background' => 'Само фон',
        'both' => 'Паспарту + фон',
    ];
}

function artwork_type_options(): array
{
    return [
        '' => '—',
        'watercolor' => 'Акварел',
        'graphics' => 'Графика',
        'oil_acrylic' => 'Масло, акрил',
        'pastel' => 'Пастел',
        'collage' => 'Колаж',
        'tapestry' => 'Гоблен',
        'poster' => 'Постер',
        'papyrus' => 'Папирус',
        'other' => 'Други',
    ];
}

function artwork_type_label(?string $type): string
{
    $options = artwork_type_options();

    return $options[$type ?? ''] ?? ($type !== '' && $type !== null ? $type : '');
}

function format_passepartout_zero_label(array $order): string
{
    if (empty($order['passepartout_zero'])) {
        return '';
    }

    $parts = [];
    foreach (['l' => 'Л', 'r' => 'Д', 't' => 'Г', 'b' => 'Д'] as $key => $label) {
        $col = 'passepartout_margin_' . $key;
        if (isset($order[$col]) && $order[$col] !== '' && $order[$col] !== null) {
            $parts[] = $label . ' ' . $order[$col];
        }
    }

    return 'Нулево паспарту' . ($parts !== [] ? ' (' . implode(', ', $parts) . ' см)' : '');
}

function passepartout_layers_from_post(array $post): array
{
    $use = normalize_passepartout_use($post['passepartout_use'] ?? '');
    $items = $post['additional_passepartouts'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }
    $items = array_values(array_filter(array_map(static fn ($p) => trim((string)$p), $items)));

    if ($use === 'both') {
        return [
            'background' => trim($post['passepartout_background'] ?? ''),
            'additional' => count($items) > 0 ? implode(', ', $items) : '',
        ];
    }

    if ($use === 'background') {
        return [
            'background' => '',
            'additional' => '',
        ];
    }

    return [
        'background' => '',
        'additional' => count($items) > 0 ? implode(', ', $items) : '',
    ];
}

function encode_passepartout_reveals_from_post(array $post): ?string
{
    $reveals = $post['passepartout_reveal'] ?? null;
    if (!is_array($reveals)) {
        $legacy = trim($post['passepartout_reveal_cm'] ?? '');

        return $legacy !== '' ? $legacy : null;
    }

    $parts = array_map(static fn ($v) => trim((string)$v), $reveals);
    while ($parts !== [] && end($parts) === '') {
        array_pop($parts);
    }

    return count($parts) > 0 ? implode(',', $parts) : null;
}

function decode_passepartout_reveals(?string $stored): array
{
    $stored = trim($stored ?? '');
    if ($stored === '') {
        return [];
    }

    if (!str_contains($stored, ',')) {
        return [$stored];
    }

    return array_map('trim', explode(',', $stored));
}

function infer_passepartout_use_from_post(array $post): string
{
    $explicit = normalize_passepartout_use($post['passepartout_use'] ?? '');
    if ($explicit !== '') {
        return $explicit;
    }

    $layers = passepartout_layers_from_post($post);
    $bg = trim($post['passepartout_background'] ?? '') ?: $layers['background'];

    if (!empty($post['passepartout_zero'])) {
        return $bg !== '' ? 'both' : 'passepartout';
    }

    $primary = trim($post['passepartout'] ?? '');

    if ($primary !== '' && $bg !== '') {
        return 'both';
    }
    if ($primary === '' && $bg !== '') {
        return 'both';
    }

    return 'passepartout';
}

function passepartout_material_layer_count(string $use): int
{
    return match ($use) {
        'both' => 2,
        'passepartout', 'background' => 1,
        default => 0,
    };
}

function format_passepartout_order_label(string $name, string $use): string
{
    if ($name === '') {
        return '';
    }

    return match (normalize_passepartout_use($use)) {
        'background' => $name . ' (фон)',
        'passepartout' => $name . ' (паспарту)',
        default => $name,
    };
}

function format_passepartout_both_order_label(string $passepartoutName, string $backgroundName): string
{
    $parts = [];
    if (trim($passepartoutName) !== '') {
        $parts[] = trim($passepartoutName) . ' (паспарту)';
    }
    if (trim($backgroundName) !== '') {
        $parts[] = trim($backgroundName) . ' (фон)';
    }

    return implode(' + ', $parts);
}

function format_order_discount_display($discount): string
{
    if ($discount === null || $discount === '') {
        return '';
    }
    $value = (float)$discount;
    if ($value <= 0) {
        return '';
    }

    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
}

function short_glass(?string $value): string
{
    $mapping = [
        'Антирефлексно' => 'Анти',
        'Антирефлексен плексиглас' => 'Анти плекс',
        'Нормално' => 'Норм',
        'Нормално 2 мм' => 'Норм',
        'Музейно' => 'Музейно',
        'Консервационно' => 'Конс',
        'Огледало' => 'Огледало',
        'Плексиглас 2 mm' => 'Плекси 2',
        'Плексиглас 3 mm' => 'Плекси 3',
        'Плексиглас' => 'Плекси',
    ];
    return $mapping[$value] ?? ($value ?? '');
}

function short_back(?string $value): string
{
    $mapping = [
        'Велпапе' => 'Велп',
        'Бирен картон' => 'Бирен',
        'Сив картон' => 'Сив',
        'Пенокартон' => 'Пено',
        'Фазер' => 'Фазер',
    ];
    return $mapping[$value] ?? ($value ?? '');
}

function short_hanging(?string $value): string
{
    $mapping = [
        'Закачалка' => 'Закач',
        'Две закачалки' => '2 закач',
        'Връзка' => 'Връзка',
    ];
    return $mapping[$value] ?? ($value ?? '');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function order_has_extras(array $order): bool
{
    $serviceIds = parse_extra_service_ids($order['extra_services'] ?? '[]');
    $openings = (int)($order['passepartout_openings'] ?? 1);
    $transportKm = (float)($order['transport_km'] ?? 0);
    $frameShape = trim($order['frame_shape'] ?? '');

    return !empty($order['urgent'])
        || !empty($order['student_discount'])
        || !empty($order['complex_passepartout'])
        || !empty($order['frame_box'])
        || !empty($order['frame_nonstandard'])
        || !empty($order['frame_high_complexity'])
        || !empty($order['client_passepartout_cutting'])
        || (float)($order['complex_order_percent'] ?? 0) > 0
        || $frameShape !== ''
        || $openings > 1
        || $transportKm > 0
        || !empty($order['profile_ready_frame'])
        || trim((string)($order['subframe_thickness'] ?? '')) !== ''
        || count($serviceIds) > 0;
}

/** „Допълнителни услуги“ (секцията от ценоразписа) — за индикацията ДА/НЕ в списъка. */
function order_has_additional_services(array $order): bool
{
    return count(parse_extra_service_ids($order['extra_services'] ?? '[]')) > 0
        || trim((string)($order['subframe_thickness'] ?? '')) !== ''
        || trim((string)($order['spacer_profile'] ?? '')) !== ''
        || (float)($order['box_subframe_gap_cm'] ?? 0) > 0
        || trim((string)($order['canvas_support_note'] ?? '')) !== '';
}

function order_has_description(array $order): bool
{
    return trim((string)($order['description'] ?? '')) !== '';
}

function format_cm_number($value): string
{
    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
}

function format_four_sides_label(array $sides): string
{
    [$l, $r, $t, $b] = $sides;
    if ($l == $r && $r == $t && $t == $b) {
        return 'по ' . format_cm_number($l) . ' см отвсякъде';
    }

    return sprintf(
        'Л %s, Д %s, Г %s, Дол %s см',
        format_cm_number($l),
        format_cm_number($r),
        format_cm_number($t),
        format_cm_number($b)
    );
}

function format_order_extras_labels(array $order, array $servicesById = []): array
{
    $labels = [];

    $urgent = (int)($order['urgent'] ?? 0);
    if ($urgent === 1) {
        $labels[] = 'Спешна за същия ден';
    } elseif ($urgent === 2) {
        $labels[] = 'Спешна за следващия ден';
    }
    $complexPercent = (float)($order['complex_order_percent'] ?? 0);
    if ($complexPercent > 0) {
        $labels[] = 'Сложна поръчка +' . format_cm_number($complexPercent) . '%';
    }
    if (!empty($order['student_discount'])) {
        $labels[] = 'Ученик';
    }
    if (!empty($order['complex_passepartout'])) {
        $labels[] = 'Сложно паспарту';
    }
    if (!empty($order['frame_box'])) {
        $labels[] = 'Рамка кутия';
    }
    if (!empty($order['frame_nonstandard'])) {
        $labels[] = 'Нестандартна изработка';
    }
    if (!empty($order['frame_high_complexity'])) {
        $labels[] = 'Висока сложност';
    }
    if (!empty($order['client_passepartout_cutting'])) {
        $labels[] = 'Рязане на паспарту на клиент';
    }

    $shapeLabel = frame_shape_label($order['frame_shape'] ?? '');
    if ($shapeLabel !== '') {
        $labels[] = 'Фигура: ' . $shapeLabel;
    }

    $openings = (int)($order['passepartout_openings'] ?? 1);
    if ($openings > 1) {
        $labels[] = "{$openings} отвора";
    }

    $transportKm = (float)($order['transport_km'] ?? 0);
    if ($transportKm > 0) {
        $labels[] = 'Транспорт ' . format_cm_number($transportKm) . ' км';
    }

    if (!empty($order['profile_ready_frame'])) {
        $price = (float)($order['profile_ready_frame_price'] ?? 0);
        $labels[] = $price > 0
            ? 'Готова рамка (' . format_cm_number($price) . ' €/бр.)'
            : 'Готова рамка';
    }

    $thickness = trim((string)($order['subframe_thickness'] ?? ''));
    if ($thickness !== '') {
        $label = 'Подрамка ' . $thickness . ' см';
        if ($order['subframe_manual_price'] ?? null) {
            $label .= ' (договорена ' . format_cm_number($order['subframe_manual_price']) . ' €)';
        }
        $labels[] = $label;
    }

    $manualPrices = parse_extra_services_manual_prices($order['extra_services_manual'] ?? null);
    foreach (parse_extra_service_ids($order['extra_services'] ?? '[]') as $serviceId) {
        if (!isset($servicesById[$serviceId])) {
            continue;
        }
        $label = $servicesById[$serviceId];
        if (isset($manualPrices[$serviceId])) {
            $label .= ' (' . format_cm_number($manualPrices[$serviceId]) . ' €)';
        }
        if (str_contains($label, 'дистанционер на рамка') && trim((string)($order['spacer_profile'] ?? '')) !== '') {
            $label .= ' — ' . trim($order['spacer_profile']);
        }
        $labels[] = $label;
    }

    if ((float)($order['box_subframe_gap_cm'] ?? 0) > 0) {
        $labels[] = 'Разстояние каса–подрамка ' . format_cm_number($order['box_subframe_gap_cm']) . ' см';
    }
    if (trim((string)($order['canvas_support_note'] ?? '')) !== '') {
        $labels[] = 'Подложка: ' . trim($order['canvas_support_note']);
    }

    return $labels;
}

function order_is_completed(array $order): bool
{
    return !empty($order['paid']) && !empty($order['collected']);
}

function profile_allowed_types(): array
{
    return ['wood', 'plastic', 'metal', 'client_material'];
}

function profile_type_labels(): array
{
    return [
        'wood' => 'Дърво',
        'plastic' => 'Пластмаса',
        'metal' => 'Метал',
        'client_material' => 'Материал на клиент',
    ];
}

/** @return 'metal'|'plastic'|'wood'|null */
function order_workload_workshop_bucket(array $order, array $profileTypeByName): ?string
{
    $profileName = trim($order['profile'] ?? '');
    if ($profileName === '') {
        // Готова рамка без профил от склада — броим я при пластмасовите (както досега в „пл.+м.“).
        return !empty($order['profile_ready_frame']) ? 'plastic' : null;
    }

    $profileType = $profileTypeByName[$profileName] ?? 'wood';

    return match ($profileType) {
        'metal' => 'metal',
        'plastic', 'client_material' => 'plastic',
        default => 'wood',
    };
}

/** Брой паспартута (слоеве паспарту без фона) × брой рамки. */
function order_workload_passepartout_count(array $order): int
{
    $layers = array_filter(
        order_material_layers($order),
        static fn (array $layer): bool => $layer['role'] === 'passepartout' && $layer['name'] !== ''
    );

    return count($layers) * max(1, (int)($order['frame_count'] ?? 1));
}

function build_profile_type_by_name(PDO $conn): array
{
    $map = [];
    foreach ($conn->query('SELECT name, profile_type FROM profiles')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['name']] = $row['profile_type'] ?? 'wood';
    }

    return $map;
}

function painting_reservation_workshop_groups(): array
{
    return [
        'unknown' => 'Още не е ясно',
        'metal' => 'Метал',
        'plastic' => 'Пластмаса',
        'wood' => 'Дърво',
    ];
}

function painting_reservation_allowed_workshop(?string $group): string
{
    $groups = painting_reservation_workshop_groups();
    if ($group === 'plastic_metal') {
        return 'plastic_metal'; // стари резервации
    }

    return isset($groups[$group ?? '']) ? ($group ?? 'unknown') : 'unknown';
}

function fetch_painting_reservations_in_range(PDO $conn, string $fromIso, string $toIso): array
{
    $stmt = $conn->prepare('
        SELECT * FROM painting_reservations
        WHERE pickup_date >= ? AND pickup_date <= ?
        ORDER BY pickup_date ASC, id ASC
    ');
    $stmt->execute([$fromIso, $toIso]);

    return $stmt->fetchAll();
}

function create_painting_reservation_from_post(PDO $conn, array $post): void
{
    $name = trim($post['customer_name'] ?? '');
    if ($name === '') {
        throw new InvalidArgumentException('Въведете име на клиент.');
    }

    $pickup = parse_user_date_to_iso($post['pickup_date'] ?? $post['date'] ?? '');
    if ($pickup === null) {
        throw new InvalidArgumentException('Въведете валидна дата на получаване (дд/мм/гггг).');
    }

    $count = max(1, (int)($post['painting_count'] ?? 1));
    $workshop = painting_reservation_allowed_workshop($post['workshop_group'] ?? 'unknown');
    $notes = trim($post['notes'] ?? '');

    $stmt = $conn->prepare('
        INSERT INTO painting_reservations (pickup_date, customer_name, painting_count, workshop_group, notes)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $pickup,
        $name,
        $count,
        $workshop,
        $notes !== '' ? $notes : null,
    ]);
}

function delete_painting_reservation(PDO $conn, int $id): bool
{
    $stmt = $conn->prepare('DELETE FROM painting_reservations WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->rowCount() > 0;
}

function process_add_painting_reservation_submission(PDO $conn, array $post): void
{
    try {
        create_painting_reservation_from_post($conn, $post);
        flash('Резервацията е записана.', 'success');
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'warning');
    } catch (Exception $e) {
        flash('Грешка при запис: ' . $e->getMessage(), 'danger');
    }
}

function process_delete_painting_reservation_submission(PDO $conn, int $id): void
{
    if ($id < 1) {
        flash('Невалидна резервация.', 'warning');

        return;
    }

    if (delete_painting_reservation($conn, $id)) {
        flash('Резервацията е премахната.', 'success');
    } else {
        flash('Резервацията не е намерена.', 'warning');
    }
}

/**
 * Брой приети рамки по ден на получаване: метални, пластмасови, дървени, паспартута + резервации.
 */
function build_order_workload_chart(PDO $conn, array $orders, int $futureDays = 14): array
{
    $profileTypeByName = build_profile_type_by_name($conn);
    $counts = ['metal' => [], 'plastic' => [], 'wood' => [], 'passepartout' => []];

    foreach ($orders as $order) {
        $pickupDate = trim($order['date'] ?? '');
        if ($pickupDate === '' || !empty($order['ready'])) {
            // Готовите поръчки вече не натоварват цеха.
            continue;
        }

        $pieces = max(1, (int)($order['frame_count'] ?? 1));
        $bucket = order_workload_workshop_bucket($order, $profileTypeByName);
        if ($bucket !== null) {
            $counts[$bucket][$pickupDate] = ($counts[$bucket][$pickupDate] ?? 0) + $pieces;
        }

        $ppCount = order_workload_passepartout_count($order);
        if ($ppCount > 0) {
            $counts['passepartout'][$pickupDate] = ($counts['passepartout'][$pickupDate] ?? 0) + $ppCount;
        }
    }

    $weekdayLabels = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $start = new DateTime('today');
    $days = [];
    for ($i = 0; $i <= $futureDays; $i++) {
        $dt = clone $start;
        if ($i > 0) {
            $dt->modify("+{$i} days");
        }
        $iso = $dt->format('Y-m-d');
        $days[] = [
            'date' => $iso,
            'label' => $dt->format('d/m'),
            'weekday' => $weekdayLabels[(int)$dt->format('w')],
            'is_today' => $i === 0,
            'metal' => (int)($counts['metal'][$iso] ?? 0),
            'plastic' => (int)($counts['plastic'][$iso] ?? 0),
            'wood' => (int)($counts['wood'][$iso] ?? 0),
            'passepartout' => (int)($counts['passepartout'][$iso] ?? 0),
            'reserved_unknown' => 0,
            'reservations' => [],
        ];
    }

    if ($days !== []) {
        $fromIso = $days[0]['date'];
        $toIso = $days[count($days) - 1]['date'];
        $reservations = fetch_painting_reservations_in_range($conn, $fromIso, $toIso);
        $dayIndexByDate = [];
        foreach ($days as $idx => $day) {
            $dayIndexByDate[$day['date']] = $idx;
        }

        $workshopLabels = painting_reservation_workshop_groups() + ['plastic_metal' => 'Пластмаса + метал'];
        foreach ($reservations as $reservation) {
            $pickupDate = trim($reservation['pickup_date'] ?? '');
            if (!isset($dayIndexByDate[$pickupDate])) {
                continue;
            }

            $idx = $dayIndexByDate[$pickupDate];
            $count = max(1, (int)($reservation['painting_count'] ?? 1));
            $workshop = painting_reservation_allowed_workshop($reservation['workshop_group'] ?? 'unknown');

            if (in_array($workshop, ['metal', 'plastic', 'wood'], true)) {
                $days[$idx][$workshop] += $count;
            } else {
                // „Още не е ясно“ и старите „Пластмаса + метал“ остават на реда за запазени.
                $days[$idx]['reserved_unknown'] += $count;
            }

            $days[$idx]['reservations'][] = [
                'id' => (int)$reservation['id'],
                'customer_name' => $reservation['customer_name'] ?? '',
                'painting_count' => $count,
                'workshop_group' => $workshop,
                'workshop_label' => $workshopLabels[$workshop] ?? $workshop,
                'notes' => trim($reservation['notes'] ?? ''),
            ];
        }
    }

    $maxCount = 0;
    foreach ($days as $day) {
        $maxCount = max($maxCount, $day['metal'], $day['plastic'], $day['wood'], $day['passepartout'], $day['reserved_unknown']);
    }

    return [
        'days' => $days,
        'max' => max(1, $maxCount),
        'total_metal' => array_sum(array_column($days, 'metal')),
        'total_plastic' => array_sum(array_column($days, 'plastic')),
        'total_wood' => array_sum(array_column($days, 'wood')),
        'total_passepartout' => array_sum(array_column($days, 'passepartout')),
    ];
}

function services_name_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = array_column(get_all_services(get_db_connection()), 'name', 'id');
    }

    return $map;
}

/** Описание на слоевете паспарту/фон с отстоянията — за прегледа на поръчката. */
function format_order_material_layers_label(array $order): string
{
    $parts = [];
    foreach (order_material_layers($order) as $layer) {
        $name = $layer['name'] !== '' ? $layer['name'] : '(без №)';
        $role = $layer['role'] === 'background' ? 'фон' : 'паспарту';
        $offsets = $layer['offsets'] !== [0.0, 0.0, 0.0, 0.0] ? ' — ' . format_four_sides_label($layer['offsets']) : '';
        $parts[] = "{$role} {$name}{$offsets}";
    }

    return implode('; ', $parts);
}

function order_view_data(array $order): array
{
    $fieldSides = order_field_margins($order);

    return [
        'order_number' => (int)$order['order_number'],
        'sub_order_number' => (int)$order['sub_order_number'],
        'date' => $order['date'] ?? '',
        'accepted_date' => order_accepted_date_iso($order),
        'passepartout_use' => normalize_passepartout_use($order['passepartout_use'] ?? ''),
        'width' => $order['width'],
        'height' => $order['height'],
        'profile' => $order['profile'] ?? '',
        'profile_ready_frame' => (bool)($order['profile_ready_frame'] ?? false),
        'profile_ready_frame_price' => $order['profile_ready_frame_price'] ?? null,
        'additional_profiles' => $order['additional_profiles'] ?? '',
        'frame_count' => $order['frame_count'] ?? 1,
        'glass' => $order['glass'] ?? '',
        'back' => $order['back'] ?? '',
        'passepartout' => $order['passepartout'] ?? '',
        'passepartout_background' => $order['passepartout_background'] ?? '',
        'passepartout_bill_width' => $order['passepartout_bill_width'] ?? null,
        'passepartout_bill_height' => $order['passepartout_bill_height'] ?? null,
        'hanging' => $order['hanging'] ?? '',
        'weight_kg' => isset($order['weight_kg']) && $order['weight_kg'] !== '' ? (float)$order['weight_kg'] : null,
        'customer_name' => $order['customer_name'] ?? '',
        'customer_phone' => $order['customer_phone'] ?? '',
        'price' => $order['price'],
        'advance_payment' => $order['advance_payment'],
        'discount' => $order['discount'],
        'description' => $order['description'] ?? '',
        'paid' => (bool)$order['paid'],
        'collected' => (bool)$order['collected'],
        'ready' => (bool)($order['ready'] ?? false) || (bool)$order['collected'],
        'ready_at' => $order['ready_at'] ?? null,
        'customer_id' => isset($order['customer_id']) ? (int)$order['customer_id'] : null,
        'customer_email' => $order['customer_email'] ?? '',
        'amount_due' => order_amount_due($order),
        'passepartout_openings' => $order['passepartout_openings'] ?? 1,
        'urgent' => (int)($order['urgent'] ?? 0),
        'complex_order_percent' => $order['complex_order_percent'] ?? null,
        'student_discount' => (bool)($order['student_discount'] ?? false),
        'complex_passepartout' => (bool)($order['complex_passepartout'] ?? false),
        'extra_services' => $order['extra_services'] ?? '[]',
        'extra_services_manual' => $order['extra_services_manual'] ?? null,
        'transport_km' => $order['transport_km'] ?? '',
        'frame_box' => (bool)($order['frame_box'] ?? false),
        'frame_nonstandard' => (bool)($order['frame_nonstandard'] ?? false),
        'frame_shape' => $order['frame_shape'] ?? '',
        'frame_shape_label' => frame_shape_label($order['frame_shape'] ?? ''),
        'frame_measure' => $order['frame_measure'] ?? '',
        'frame_measure_label' => frame_measure_options()[$order['frame_measure'] ?? ''] ?? '',
        'frame_high_complexity' => (bool)($order['frame_high_complexity'] ?? false),
        'client_passepartout_cutting' => (bool)($order['client_passepartout_cutting'] ?? false),
        'artwork_type' => $order['artwork_type'] ?? '',
        'passepartout_zero' => (bool)($order['passepartout_zero'] ?? false),
        'passepartout_margin_l' => $order['passepartout_margin_l'] ?? null,
        'passepartout_margin_r' => $order['passepartout_margin_r'] ?? null,
        'passepartout_margin_t' => $order['passepartout_margin_t'] ?? null,
        'passepartout_margin_b' => $order['passepartout_margin_b'] ?? null,
        'field_margin_all' => $order['field_margin_all'] ?? null,
        'field_margin_l' => $order['field_margin_l'] ?? null,
        'field_margin_r' => $order['field_margin_r'] ?? null,
        'field_margin_t' => $order['field_margin_t'] ?? null,
        'field_margin_b' => $order['field_margin_b'] ?? null,
        'field_label' => $fieldSides !== [0.0, 0.0, 0.0, 0.0] ? format_four_sides_label($fieldSides) : '',
        'passepartout_layers' => decode_json_array($order['passepartout_layers'] ?? null),
        'passepartout_bg_offsets' => decode_json_array($order['passepartout_bg_offsets'] ?? null),
        'material_layers_label' => format_order_material_layers_label($order),
        'hanger' => $order['hanger'] ?? '',
        'hanger_count' => $order['hanger_count'] ?? null,
        'carry_floors' => $order['carry_floors'] ?? null,
        'additional_passepartouts' => $order['additional_passepartouts'] ?? null,
        'passepartout_reveal_cm' => $order['passepartout_reveal_cm'] ?? null,
        'subframe_thickness' => $order['subframe_thickness'] ?? '',
        'subframe_manual_price' => $order['subframe_manual_price'] ?? null,
        'spacer_profile' => $order['spacer_profile'] ?? '',
        'box_subframe_gap_cm' => $order['box_subframe_gap_cm'] ?? null,
        'canvas_support_note' => $order['canvas_support_note'] ?? '',
        'stock_override' => (bool)($order['stock_override'] ?? false),
        'stock_shortage' => decode_json_array($order['stock_shortage'] ?? null),
        'has_additional_services' => order_has_additional_services($order),
        'extras_labels' => format_order_extras_labels($order, services_name_map()),
    ];
}

function encode_order_dataset(array $order): string
{
    return htmlspecialchars(
        json_encode(order_view_data($order), JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
}

function fetch_active_orders(PDO $conn): array
{
    return $conn->query('
        SELECT * FROM orders
        WHERE deleted_at IS NULL
          AND (paid = 0 OR collected = 0)
        ORDER BY order_number DESC, sub_order_number
    ')->fetchAll();
}

function order_row_content_revision(array $order): string
{
    $payload = json_encode(order_view_data($order), JSON_UNESCAPED_UNICODE);

    return hash('sha256', (int)$order['id'] . '|' . $payload);
}

function compute_active_orders_revision(array $orders): string
{
    $chunks = [];
    foreach ($orders as $order) {
        $chunks[] = order_row_content_revision($order);
    }
    sort($chunks);

    return hash('sha256', implode("\n", $chunks));
}

function render_active_order_row_html(array $order, array $servicesById): string
{
    ob_start();
    include ROOT_PATH . '/templates/partials/active_order_row.php';

    return trim(ob_get_clean());
}

function build_active_orders_sync_payload(PDO $conn): array
{
    $orders = fetch_active_orders($conn);
    $revision = compute_active_orders_revision($orders);
    $services = get_all_services($conn);
    $servicesById = array_column($services, 'name', 'id');
    $rows = [];
    foreach ($orders as $order) {
        $rows[] = [
            'id' => (int)$order['id'],
            'revision' => order_row_content_revision($order),
            'html' => render_active_order_row_html($order, $servicesById),
        ];
    }

    return [
        'revision' => $revision,
        'orders' => $rows,
    ];
}

function fetch_completed_orders(PDO $conn): array
{
    return $conn->query('
        SELECT * FROM orders
        WHERE deleted_at IS NULL
          AND paid = 1
          AND collected = 1
        ORDER BY order_number DESC, sub_order_number
    ')->fetchAll();
}

function fetch_deleted_orders(PDO $conn): array
{
    return $conn->query('
        SELECT * FROM orders
        WHERE deleted_at IS NOT NULL
        ORDER BY deleted_at DESC, order_number DESC, sub_order_number
    ')->fetchAll();
}

function soft_delete_order(PDO $conn, int $orderId): void
{
    $stmt = $conn->prepare('UPDATE orders SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([date('Y-m-d H:i:s'), $orderId]);
}

function restore_archived_order(PDO $conn, int $orderId): bool
{
    $stmt = $conn->prepare('UPDATE orders SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
    $stmt->execute([$orderId]);

    return $stmt->rowCount() > 0;
}

function full_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_PATH . $path;
}

function parse_passepartout_sheet_stocks_from_post(): array
{
    $stocks = [];
    $posted = $_POST['sheet_stock'] ?? [];
    if (!is_array($posted)) {
        return $stocks;
    }

    foreach ($posted as $sheetTypeId => $stock) {
        $sheetTypeId = (int)$sheetTypeId;
        if ($sheetTypeId > 0 && $stock !== '') {
            $stocks[$sheetTypeId] = (float)$stock;
        }
    }

    return $stocks;
}

function parse_profile_rows_from_post(): array
{
    $rows = $_POST['profile_rows'] ?? [];
    if (!is_array($rows)) {
        return [];
    }

    $parsed = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = trim($row['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $parsed[] = [
            'name' => $name,
            'price' => $row['price'] ?? 0,
            'stock' => $row['stock'] ?? 0,
            'width_cm' => isset($row['width_cm']) && $row['width_cm'] !== '' ? $row['width_cm'] : null,
            'profile_type' => $row['profile_type'] ?? 'wood',
        ];
    }

    return $parsed;
}

function parse_passepartout_rows_from_post(): array
{
    $rows = $_POST['passepartout_rows'] ?? [];
    if (!is_array($rows)) {
        return [];
    }

    $parsed = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = trim($row['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $sheetStock = [];
        if (isset($row['sheet_stock']) && is_array($row['sheet_stock'])) {
            foreach ($row['sheet_stock'] as $sheetTypeId => $stock) {
                $sheetTypeId = (int)$sheetTypeId;
                if ($sheetTypeId > 0 && $stock !== '') {
                    $sheetStock[$sheetTypeId] = (float)$stock;
                }
            }
        }

        $parsed[] = [
            'name' => $name,
            'price' => $row['price'] ?? 0,
            'sheet_stock' => $sheetStock,
            'price_kind' => $row['price_kind'] ?? 'manual',
            'tier_scheme' => $row['tier_scheme'] ?? '80x100',
            'price_tier_1' => $row['price_tier_1'] ?? 0,
            'price_tier_2' => $row['price_tier_2'] ?? 0,
            'price_tier_3' => $row['price_tier_3'] ?? 0,
            'price_tier_4' => $row['price_tier_4'] ?? 0,
        ];
    }

    return $parsed;
}

function parse_passepartout_pricing_from_post(): array
{
    return resolve_passepartout_pricing_from_input($_POST);
}

function save_passepartout_pricing_fields(PDO $conn, int $passepartoutId, array $pricing): void
{
    $stmt = $conn->prepare('
        UPDATE passepartouts
        SET price = ?, price_kind = ?, tier_scheme = ?,
            price_tier_1 = ?, price_tier_2 = ?, price_tier_3 = ?, price_tier_4 = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $pricing['price'],
        $pricing['price_kind'],
        $pricing['tier_scheme'],
        $pricing['price_tier_1'],
        $pricing['price_tier_2'],
        $pricing['price_tier_3'],
        $pricing['price_tier_4'],
        $passepartoutId,
    ]);
}

function is_duplicate_key_exception(Throwable $e): bool
{
    if (!$e instanceof PDOException) {
        return false;
    }

    $sqlState = $e->errorInfo[0] ?? '';
    $message = $e->getMessage();

    if ($sqlState === '23000') {
        return true;
    }

    return stripos($message, 'UNIQUE constraint failed') !== false
        || stripos($message, 'Duplicate entry') !== false;
}

function create_profiles_bulk(PDO $conn, array $rows): array
{
    $allowedTypes = profile_allowed_types();
    $stmt = $conn->prepare('INSERT INTO profiles (name, price, stock, width_cm, profile_type) VALUES (?, ?, ?, ?, ?)');
    $added = [];
    $skipped = [];

    foreach ($rows as $row) {
        $profileType = in_array($row['profile_type'], $allowedTypes, true) ? $row['profile_type'] : 'wood';

        try {
            $stmt->execute([
                $row['name'],
                $row['price'],
                $row['stock'],
                $row['width_cm'],
                $profileType,
            ]);
            $added[] = $row['name'];
        } catch (PDOException $e) {
            if (is_duplicate_key_exception($e)) {
                $skipped[] = $row['name'];
                continue;
            }
            throw $e;
        }
    }

    return ['added' => $added, 'skipped' => $skipped];
}

function create_passepartouts_bulk(PDO $conn, array $rows): array
{
    $stmt = $conn->prepare('
        INSERT INTO passepartouts (
            name, price, stock, price_kind, tier_scheme,
            price_tier_1, price_tier_2, price_tier_3, price_tier_4
        ) VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?)
    ');
    $added = [];
    $skipped = [];

    foreach ($rows as $row) {
        try {
            $pricing = resolve_passepartout_pricing_from_input($row);
            $stmt->execute([
                $row['name'],
                $pricing['price'],
                $pricing['price_kind'],
                $pricing['tier_scheme'],
                $pricing['price_tier_1'],
                $pricing['price_tier_2'],
                $pricing['price_tier_3'],
                $pricing['price_tier_4'],
            ]);
            $passepartoutId = (int)$conn->lastInsertId();
            save_passepartout_sheet_stocks($conn, $passepartoutId, $row['sheet_stock']);
            $added[] = $row['name'];
        } catch (PDOException $e) {
            if (is_duplicate_key_exception($e)) {
                $skipped[] = $row['name'];
                continue;
            }
            throw $e;
        }
    }

    return ['added' => $added, 'skipped' => $skipped];
}

function flash_bulk_create_profiles_result(array $result): void
{
    $added = $result['added'];
    $skipped = $result['skipped'];
    $addedCount = count($added);
    $skippedCount = count($skipped);

    if ($addedCount > 0 && $skippedCount === 0) {
        flash(
            $addedCount === 1 ? 'Профилът е добавен успешно!' : "Добавени са {$addedCount} профила.",
            'success'
        );
        return;
    }

    if ($addedCount > 0 && $skippedCount > 0) {
        flash(
            "Добавени са {$addedCount} профила. Пропуснати (вече съществуват): " . implode(', ', $skipped),
            'warning'
        );
        return;
    }

    flash(
        'Нищо не беше добавено. Профили с вече съществуващо име: ' . implode(', ', $skipped),
        'danger'
    );
}

function flash_bulk_create_passepartouts_result(array $result): void
{
    $added = $result['added'];
    $skipped = $result['skipped'];
    $addedCount = count($added);
    $skippedCount = count($skipped);

    if ($addedCount > 0 && $skippedCount === 0) {
        flash(
            $addedCount === 1 ? 'Паспартуто е добавено успешно!' : "Добавени са {$addedCount} паспартута.",
            'success'
        );
        return;
    }

    if ($addedCount > 0 && $skippedCount > 0) {
        flash(
            "Добавени са {$addedCount} паспартута. Пропуснати (вече съществуват): " . implode(', ', $skipped),
            'warning'
        );
        return;
    }

    flash(
        'Нищо не беше добавено. Паспарту с вече съществуващ номер: ' . implode(', ', $skipped),
        'danger'
    );
}

function parse_bulk_ids_from_post(): array
{
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
}

/** +10% then round up to the next 0.10 € (10.61 → 10.70, 10.60 unchanged). */
function profile_name_series_letter(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

function profile_name_sort_number(string $name): int
{
    if (preg_match('/(\d+)/u', $name, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

/** Unique first letters from profile names (Д, И, В, …), sorted. */
function collect_profile_series_letters(array $profiles): array
{
    $letters = [];
    foreach ($profiles as $profile) {
        $letter = profile_name_series_letter($profile['name'] ?? '');
        if ($letter !== '') {
            $letters[$letter] = true;
        }
    }

    $list = array_keys($letters);
    usort($list, static function (string $a, string $b): int {
        return strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
    });

    return $list;
}

function profile_price_raise_ten_percent(float $price): float
{
    $raised = $price * 1.10;
    $rounded = ceil(($raised - 1e-9) * 10) / 10;

    return round($rounded, 2);
}

function raise_profile_prices_by_ten_percent(PDO $conn, array $ids = []): int
{
    if ($ids === []) {
        $rows = $conn->query('SELECT id, price FROM profiles ORDER BY id')->fetchAll();
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT id, price FROM profiles WHERE id IN ({$placeholders}) ORDER BY id");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    }

    $update = $conn->prepare('UPDATE profiles SET price = ? WHERE id = ?');
    $updated = 0;

    foreach ($rows as $row) {
        $newPrice = profile_price_raise_ten_percent((float)$row['price']);
        $update->execute([$newPrice, (int)$row['id']]);
        $updated++;
    }

    return $updated;
}

function apply_bulk_numeric_change(float $current, string $mode, float $value): float
{
    if ($mode === 'add') {
        return max(0, $current + $value);
    }

    return max(0, $value);
}

function bulk_update_profiles(PDO $conn, array $ids, array $post): int
{
    $updated = 0;

    foreach ($ids as $id) {
        $stmt = $conn->prepare('SELECT * FROM profiles WHERE id = ?');
        $stmt->execute([$id]);
        $profile = $stmt->fetch();
        if (!$profile) {
            continue;
        }

        $price = (float)$profile['price'];
        $stock = (float)$profile['stock'];
        $widthCm = $profile['width_cm'];
        $profileType = $profile['profile_type'] ?? 'wood';

        if (!empty($post['apply_price']) && ($post['price'] ?? '') !== '') {
            $price = (float)$post['price'];
        }

        if (!empty($post['apply_stock']) && ($post['stock'] ?? '') !== '') {
            $stock = apply_bulk_numeric_change($stock, $post['stock_mode'] ?? 'set', (float)$post['stock']);
        }

        if (!empty($post['apply_width_cm'])) {
            $widthCm = ($post['width_cm'] ?? '') !== '' ? (float)$post['width_cm'] : null;
        }

        if (!empty($post['apply_profile_type']) && ($post['profile_type'] ?? '') !== '') {
            $requestedType = $post['profile_type'];
            $profileType = in_array($requestedType, profile_allowed_types(), true) ? $requestedType : $profileType;
        }

        $update = $conn->prepare('UPDATE profiles SET price = ?, stock = ?, width_cm = ?, profile_type = ? WHERE id = ?');
        $update->execute([$price, $stock, $widthCm, $profileType, $id]);
        $updated++;
    }

    return $updated;
}

function bulk_update_passepartouts(PDO $conn, array $ids, array $post): int
{
    $updated = 0;
    $applySheetStock = $post['apply_sheet_stock'] ?? [];
    $sheetStockValues = $post['sheet_stock'] ?? [];
    $sheetStockModes = $post['sheet_stock_mode'] ?? [];

    foreach ($ids as $id) {
        $stmt = $conn->prepare('SELECT * FROM passepartouts WHERE id = ?');
        $stmt->execute([$id]);
        $passepartout = $stmt->fetch();
        if (!$passepartout) {
            continue;
        }

        if (!empty($post['apply_passepartout_pricing'])) {
            save_passepartout_pricing_fields($conn, $id, resolve_passepartout_pricing_from_input($post));
        }

        if (is_array($applySheetStock) && !empty($applySheetStock)) {
            $stocks = get_passepartout_sheet_stocks($conn, $id);
            $changed = false;

            foreach ($applySheetStock as $sheetTypeId => $enabled) {
                if (!$enabled || ($sheetStockValues[$sheetTypeId] ?? '') === '') {
                    continue;
                }

                $sheetTypeId = (int)$sheetTypeId;
                if ($sheetTypeId <= 0) {
                    continue;
                }

                $current = (float)($stocks[$sheetTypeId] ?? 0);
                $mode = $sheetStockModes[$sheetTypeId] ?? 'set';
                $stocks[$sheetTypeId] = apply_bulk_numeric_change($current, $mode, (float)$sheetStockValues[$sheetTypeId]);
                $changed = true;
            }

            if ($changed) {
                save_passepartout_sheet_stocks($conn, $id, $stocks);
            }
        }

        $updated++;
    }

    return $updated;
}

function get_additional_profiles(): ?string
{
    $profiles = $_POST['additional_profiles'] ?? [];
    if (is_array($profiles) && count($profiles) > 0) {
        $filtered = array_filter($profiles, fn($p) => trim($p) !== '');
        return count($filtered) > 0 ? implode(', ', $filtered) : null;
    }
    return null;
}

function get_additional_passepartouts(): ?string
{
    $layers = passepartout_layers_from_post($_POST);

    return $layers['additional'] !== '' ? $layers['additional'] : null;
}

function parse_optional_margin_cm(?string $value): ?float
{
    $value = trim($value ?? '');
    if ($value === '') {
        return null;
    }

    return max(0, (float)str_replace(',', '.', $value));
}

function post_four_sides(array $post, string $prefix, ?int $index = null): array
{
    $pick = static function (string $key) use ($post, $index) {
        $value = $post[$key] ?? null;
        if ($index !== null) {
            $value = is_array($value) ? ($value[$index] ?? null) : null;
        }
        $parsed = parse_cm_value(is_array($value) ? null : $value);

        return $parsed;
    };

    return [
        'all' => $pick($prefix . 'all'),
        'l' => $pick($prefix . 'l'),
        'r' => $pick($prefix . 'r'),
        't' => $pick($prefix . 't'),
        'b' => $pick($prefix . 'b'),
    ];
}

function four_sides_has_value(array $sides): bool
{
    foreach (['all', 'l', 'r', 't', 'b'] as $key) {
        if (($sides[$key] ?? null) !== null) {
            return true;
        }
    }

    return false;
}

/** Слоеве паспарту от формата: № + отстояния (отвсякъде / Л / Д / Г / Д). */
function passepartout_layers_entries_from_post(array $post): array
{
    $use = normalize_passepartout_use($post['passepartout_use'] ?? '') ?: 'passepartout';
    if ($use === 'background') {
        return [];
    }

    $names = [trim((string)($post['passepartout'] ?? ''))];
    $additional = $post['additional_passepartouts'] ?? [];
    if (is_array($additional)) {
        foreach ($additional as $name) {
            $names[] = trim((string)$name);
        }
    }

    $entries = [];
    foreach ($names as $index => $name) {
        $sides = post_four_sides($post, 'pp_', $index);
        if ($name === '' && !four_sides_has_value($sides)) {
            continue;
        }
        $entries[] = array_merge(['name' => $name], $sides);
    }

    return $entries;
}

function background_offsets_from_post(array $post): ?array
{
    $use = normalize_passepartout_use($post['passepartout_use'] ?? '') ?: 'passepartout';
    if ($use === 'passepartout') {
        return null;
    }
    $sides = post_four_sides($post, 'bg_');

    return four_sides_has_value($sides) ? $sides : null;
}

function extra_services_manual_from_post(array $post, array $serviceIds): ?string
{
    $raw = $post['extra_services_manual'] ?? [];
    if (!is_array($raw)) {
        return null;
    }
    $prices = [];
    foreach ($raw as $id => $value) {
        $id = (int)$id;
        $price = parse_cm_value($value);
        if ($id > 0 && $price !== null && in_array($id, $serviceIds, true)) {
            $prices[(string)$id] = round($price, 2);
        }
    }

    return $prices !== [] ? json_encode($prices) : null;
}

function allowed_frame_shape(?string $shape): string
{
    $shape = trim((string)$shape);
    if ($shape === 'circle_24') {
        return $shape; // стари поръчки
    }

    return array_key_exists($shape, frame_shape_options()) ? $shape : '';
}

function build_order_from_post(?string $customerName = null, ?string $customerPhone = null): array
{
    $post = $_POST;
    $conn = get_db_connection();
    $ppUse = infer_passepartout_use_from_post($post);
    $passepartoutName = trim($post['passepartout'] ?? '');
    $ppLayers = passepartout_layers_from_post($post);
    $passepartoutBackgroundName = $ppUse === 'both' ? $ppLayers['background'] : '';

    $passepartoutId = null;
    if ($passepartoutName !== '') {
        $resolved = get_catalog_item_by_name($conn, 'passepartouts', $passepartoutName);
        $passepartoutId = $resolved ? (int)$resolved['id'] : null;
    }

    $passepartoutBackgroundId = null;
    if ($passepartoutBackgroundName !== '') {
        $resolvedBg = get_catalog_item_by_name($conn, 'passepartouts', $passepartoutBackgroundName);
        $passepartoutBackgroundId = $resolvedBg ? (int)$resolvedBg['id'] : null;
    }

    $extraServices = $post['extra_services'] ?? [];
    if (!is_array($extraServices)) {
        $extraServices = [];
    }
    $extraServiceIds = array_values(array_filter(array_map('intval', $extraServices)));

    $artworkType = trim($post['artwork_type'] ?? '');
    if (!array_key_exists($artworkType, artwork_type_options())) {
        $artworkType = $artworkType === '' ? '' : 'other';
    }

    $layerEntries = passepartout_layers_entries_from_post($post);
    $bgOffsets = background_offsets_from_post($post);
    $urgent = (int)($post['urgent'] ?? 0);
    $hanger = trim($post['hanger'] ?? '');
    $subframeThickness = trim((string)($post['subframe_thickness'] ?? ''));
    if (!array_key_exists($subframeThickness, subframe_thickness_options())) {
        $subframeThickness = '';
    }
    $supportNote = trim((string)($post['canvas_support_note'] ?? ''));

    return [
        'width' => $post['width'] ?? null,
        'height' => $post['height'] ?? null,
        'profile' => $post['profile'] ?? '',
        'glass' => $post['glass'] ?? '',
        'passepartout' => $passepartoutName,
        'passepartout_id' => $passepartoutId,
        'passepartout_background' => $passepartoutBackgroundName,
        'passepartout_background_id' => $passepartoutBackgroundId,
        'passepartout_layers' => $layerEntries !== [] ? json_encode($layerEntries, JSON_UNESCAPED_UNICODE) : null,
        'passepartout_bg_offsets' => $bgOffsets !== null ? json_encode($bgOffsets) : null,
        'back' => trim($post['back'] ?? ''),
        'hanging' => trim($post['hanging'] ?? ''),
        'hanger' => $hanger,
        'hanger_count' => $hanger !== '' && ($post['hanger_count'] ?? '') !== '' ? max(0, (int)$post['hanger_count']) : null,
        'weight_kg' => ($post['weight_kg'] ?? '') !== '' ? max(0, (float)$post['weight_kg']) : null,
        'passepartout_openings' => max(1, (int)($post['passepartout_openings'] ?? 1)),
        'urgent' => in_array($urgent, [0, 1, 2], true) ? $urgent : 0,
        'complex_order_percent' => ($post['complex_order_percent'] ?? '') !== ''
            ? max(0, min(100, (float)str_replace(',', '.', (string)$post['complex_order_percent'])))
            : null,
        'student_discount' => 0,
        'complex_passepartout' => isset($post['complex_passepartout']) ? 1 : 0,
        'extra_services' => json_encode($extraServiceIds),
        'extra_services_manual' => extra_services_manual_from_post($post, $extraServiceIds),
        'transport_km' => null,
        'carry_floors' => null,
        'frame_box' => isset($post['frame_box']) ? 1 : 0,
        'frame_nonstandard' => isset($post['frame_nonstandard']) ? 1 : 0,
        'frame_shape' => allowed_frame_shape($post['frame_shape'] ?? ''),
        'frame_measure' => in_array($post['frame_measure'] ?? '', ['clear', 'outer'], true) ? $post['frame_measure'] : '',
        'frame_high_complexity' => isset($post['frame_high_complexity']) ? 1 : 0,
        'client_passepartout_cutting' => isset($post['client_passepartout_cutting']) ? 1 : 0,
        'profile_ready_frame' => isset($post['profile_ready_frame']) ? 1 : 0,
        'profile_ready_frame_price' => ($post['profile_ready_frame_price'] ?? '') !== ''
            ? max(0, (float)$post['profile_ready_frame_price'])
            : null,
        'manual_discount' => ($post['discount'] ?? '') !== '' ? max(0, min(100, (float)$post['discount'])) : 0,
        'passepartout_use' => $ppUse,
        'passepartout_zero' => 0,
        'passepartout_margin_l' => null,
        'passepartout_margin_r' => null,
        'passepartout_margin_t' => null,
        'passepartout_margin_b' => null,
        'field_margin_all' => parse_cm_value($post['field_margin_all'] ?? null),
        'field_margin_l' => parse_cm_value($post['field_margin_l'] ?? null),
        'field_margin_r' => parse_cm_value($post['field_margin_r'] ?? null),
        'field_margin_t' => parse_cm_value($post['field_margin_t'] ?? null),
        'field_margin_b' => parse_cm_value($post['field_margin_b'] ?? null),
        'subframe_thickness' => $subframeThickness,
        'subframe_manual_price' => $subframeThickness !== '' ? parse_cm_value($post['subframe_manual_price'] ?? null) : null,
        'spacer_profile' => trim((string)($post['spacer_profile'] ?? '')) ?: null,
        'box_subframe_gap_cm' => parse_cm_value($post['box_subframe_gap_cm'] ?? null),
        'canvas_support_note' => $supportNote !== '' ? mb_substr($supportNote, 0, 80, 'UTF-8') : null,
        'artwork_type' => $artworkType,
        'additional_profiles' => get_additional_profiles(),
        'additional_passepartouts' => get_additional_passepartouts(),
        'passepartout_reveal_cm' => null,
        'frame_count' => max(1, (int)($post['frame_count'] ?? 1)),
        'customer_name' => $customerName ?? ($post['customer_name'] ?? ''),
        'customer_phone' => $customerPhone ?? trim($post['customer_phone'] ?? ''),
        'description' => $post['description'] ?? '',
    ];
}

function resolve_order_price(PDO $conn, array $order, ?string $postedPrice): ?float
{
    if ($postedPrice !== null && $postedPrice !== '') {
        return (float)$postedPrice;
    }

    $pricing = calculate_order_pricing($conn, $order);
    return $pricing['total'] > 0 ? $pricing['total'] : null;
}

/** Колони на orders, които се записват при добавяне/редакция (без order_number/sub_order_number). */
function order_persisted_columns(): array
{
    return [
        'date', 'accepted_date', 'width', 'height', 'profile', 'glass', 'passepartout',
        'passepartout_bill_width', 'passepartout_bill_height',
        'passepartout_sheet_type_id', 'passepartout_sheet_usage', 'passepartout_use',
        'passepartout_background', 'passepartout_bg_sheet_type_id', 'passepartout_bg_sheet_usage',
        'back', 'hanging', 'weight_kg', 'customer_name', 'customer_phone', 'price', 'paid', 'collected',
        'additional_profiles', 'frame_count', 'advance_payment', 'discount', 'description',
        'passepartout_openings', 'urgent', 'student_discount', 'complex_passepartout', 'extra_services', 'transport_km',
        'frame_box', 'frame_nonstandard', 'frame_shape', 'frame_high_complexity', 'client_passepartout_cutting',
        'profile_ready_frame', 'profile_ready_frame_price',
        'artwork_type', 'passepartout_zero', 'passepartout_margin_l', 'passepartout_margin_r',
        'passepartout_margin_t', 'passepartout_margin_b', 'hanger', 'carry_floors',
        'additional_passepartouts', 'passepartout_reveal_cm',
        'frame_measure', 'field_margin_all', 'field_margin_l', 'field_margin_r', 'field_margin_t', 'field_margin_b',
        'passepartout_layers', 'passepartout_bg_offsets', 'hanger_count', 'extra_services_manual',
        'subframe_thickness', 'subframe_manual_price', 'spacer_profile', 'box_subframe_gap_cm',
        'canvas_support_note', 'complex_order_percent', 'stock_override', 'stock_shortage',
        'ready', 'ready_at', 'paid_at', 'collected_at', 'customer_id', 'customer_email', 'passepartout_extra_stock',
    ];
}

function order_column_default(string $column)
{
    return match ($column) {
        'paid', 'collected', 'urgent', 'student_discount', 'complex_passepartout', 'frame_box',
        'frame_nonstandard', 'frame_high_complexity', 'client_passepartout_cutting', 'profile_ready_frame',
        'passepartout_zero', 'stock_override', 'ready' => 0,
        'passepartout_use', 'passepartout_background', 'frame_shape', 'frame_measure', 'subframe_thickness',
        'hanger', 'artwork_type' => '',
        'passepartout_openings', 'frame_count' => 1,
        default => null,
    };
}

function order_bind_values(array $order): array
{
    $values = [];
    foreach (order_persisted_columns() as $column) {
        $values[] = array_key_exists($column, $order) ? $order[$column] : order_column_default($column);
    }

    return $values;
}

function insert_order_row(PDO $conn, array $order, int $orderNumber, int $subOrderNumber): void
{
    $columns = order_persisted_columns();
    $sql = sprintf(
        'INSERT INTO orders (order_number, sub_order_number, %s) VALUES (?, ?, %s)',
        implode(', ', $columns),
        implode(', ', array_fill(0, count($columns), '?'))
    );
    $conn->prepare($sql)->execute(array_merge([$orderNumber, $subOrderNumber], order_bind_values($order)));
}

function update_order_row(PDO $conn, array $order, int $orderId): void
{
    $assignments = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", order_persisted_columns()));
    $conn->prepare("UPDATE orders SET {$assignments} WHERE id = ?")
        ->execute(array_merge(order_bind_values($order), [$orderId]));
}

function prepare_order_persistence(PDO $conn, array $post, ?string $customerName = null, ?string $customerPhone = null): array
{
    $orderData = build_order_from_post($customerName, $customerPhone);
    $stockOrder = enrich_order_pricing_fields($conn, $orderData);

    $pickupDate = parse_user_date_to_iso($post['date'] ?? '');
    $acceptedDate = parse_user_date_to_iso($post['accepted_date'] ?? '') ?? date('Y-m-d');

    return array_merge($orderData, [
        'stock_order' => $stockOrder,
        'date' => $pickupDate,
        'accepted_date' => $acceptedDate,
        'passepartout' => $stockOrder['passepartout'] ?? ($orderData['passepartout'] ?? ''),
        'passepartout_bill_width' => $stockOrder['passepartout_bill_width'] ?? null,
        'passepartout_bill_height' => $stockOrder['passepartout_bill_height'] ?? null,
        'passepartout_sheet_type_id' => $stockOrder['passepartout_sheet_type_id'] ?? null,
        'passepartout_sheet_usage' => $stockOrder['passepartout_sheet_usage'] ?? null,
        'passepartout_background' => $stockOrder['passepartout_background'] ?? ($orderData['passepartout_background'] ?? ''),
        'passepartout_bg_sheet_type_id' => $stockOrder['passepartout_bg_sheet_type_id'] ?? null,
        'passepartout_bg_sheet_usage' => $stockOrder['passepartout_bg_sheet_usage'] ?? null,
        'back' => $post['back'] ?? '',
        'hanging' => $post['hanging'] ?? '',
        'price' => resolve_order_price($conn, $orderData, $post['price'] ?? null),
        'paid' => isset($post['paid']) ? 1 : 0,
        'collected' => isset($post['collected']) ? 1 : 0,
        'ready' => isset($post['ready']) || isset($post['collected']) ? 1 : 0,
        'customer_email' => normalize_email($post['customer_email'] ?? '') ?: null,
        'customer_id' => resolve_customer_for_order(
            $conn,
            (int)($post['customer_id'] ?? 0) ?: null,
            (string)($customerName ?? ($post['customer_name'] ?? '')),
            (string)($customerPhone ?? ($post['customer_phone'] ?? '')),
            (string)($post['customer_email'] ?? '')
        ),
        'passepartout_extra_stock' => $stockOrder['passepartout_extra_stock'] ?? null,
        'advance_payment' => ($post['advance_payment'] ?? '') !== '' ? $post['advance_payment'] : null,
        'discount' => ($post['discount'] ?? '') !== '' ? max(0, min(100, (float)$post['discount'])) : null,
        'stock_override' => 0,
        'stock_shortage' => null,
    ]);
}


// Със стар config.php (без извикването) срокът на сесията пак се прилага.
if (session_status() === PHP_SESSION_ACTIVE) {
    enforce_session_idle_timeout();
}
