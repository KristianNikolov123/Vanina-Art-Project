<?php

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
        'add_back' => '/add_back',
        'add_hanging' => '/add_hanging',
        'submit_feedback' => '/submit_feedback',
        'add_order' => '/add_order',
        'add_profile' => '/add_profile',
        'add_glass' => '/add_glass',
        'add_passepartout' => '/add_passepartout',
        'bulk_edit_profiles' => '/bulk_edit_profiles',
        'bulk_edit_passepartouts' => '/bulk_edit_passepartouts',
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
    return BASE_PATH . '/static/' . $filename;
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
        redirect('/login');
    }
}

function login_user(User $user): void
{
    $_SESSION['user_id'] = $user->id;
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
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
    $content = ob_get_clean();

    include ROOT_PATH . '/templates/layout.php';
}

function format_date_ddmmyyyy(?string $dateStr): string
{
    if (!$dateStr) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    return $dt ? $dt->format('d/m/Y') : $dateStr;
}

function short_glass(?string $value): string
{
    $mapping = [
        'Антирефлексно' => 'Анти',
        'Нормално' => 'Норм',
        'Музейно' => 'Музейно',
        'Консервационно' => 'Конс',
        'Огледало' => 'Огледало',
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
        || $frameShape !== ''
        || $openings > 1
        || $transportKm > 0
        || count($serviceIds) > 0;
}

function format_order_extras_labels(array $order, array $servicesById = []): array
{
    $labels = [];

    if (!empty($order['urgent'])) {
        $labels[] = 'Спешна';
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
        $labels[] = 'Нестандартна рамка';
    }
    if (!empty($order['frame_high_complexity'])) {
        $labels[] = 'Висока сложност';
    }
    if (!empty($order['client_passepartout_cutting'])) {
        $labels[] = 'Рязане паспарту на клиент';
    }

    $frameShape = $order['frame_shape'] ?? '';
    if ($frameShape === 'ellipse_12') {
        $labels[] = 'Елипса / кръг 12 страни';
    } elseif ($frameShape === 'circle_24') {
        $labels[] = 'Кръг 24 страни';
    }

    $openings = (int)($order['passepartout_openings'] ?? 1);
    if ($openings > 1) {
        $labels[] = "{$openings} отвора";
    }

    $transportKm = (float)($order['transport_km'] ?? 0);
    if ($transportKm > 0) {
        $labels[] = 'Транспорт ' . rtrim(rtrim(number_format($transportKm, 2, '.', ''), '0'), '.') . ' км';
    }

    foreach (parse_extra_service_ids($order['extra_services'] ?? '[]') as $serviceId) {
        if (isset($servicesById[$serviceId])) {
            $labels[] = $servicesById[$serviceId];
        }
    }

    return $labels;
}

function order_is_completed(array $order): bool
{
    return !empty($order['paid']) && !empty($order['collected']);
}

function order_view_data(array $order): array
{
    return [
        'order_number' => (int)$order['order_number'],
        'sub_order_number' => (int)$order['sub_order_number'],
        'date' => $order['date'] ?? '',
        'width' => $order['width'],
        'height' => $order['height'],
        'profile' => $order['profile'] ?? '',
        'additional_profiles' => $order['additional_profiles'] ?? '',
        'frame_count' => $order['frame_count'] ?? 1,
        'glass' => $order['glass'] ?? '',
        'back' => $order['back'] ?? '',
        'passepartout' => $order['passepartout'] ?? '',
        'passepartout_bill_width' => $order['passepartout_bill_width'] ?? null,
        'passepartout_bill_height' => $order['passepartout_bill_height'] ?? null,
        'hanging' => $order['hanging'] ?? '',
        'customer_name' => $order['customer_name'] ?? '',
        'price' => $order['price'],
        'advance_payment' => $order['advance_payment'],
        'discount' => $order['discount'],
        'description' => $order['description'] ?? '',
        'paid' => (bool)$order['paid'],
        'collected' => (bool)$order['collected'],
        'passepartout_openings' => $order['passepartout_openings'] ?? 1,
        'urgent' => (bool)($order['urgent'] ?? false),
        'student_discount' => (bool)($order['student_discount'] ?? false),
        'complex_passepartout' => (bool)($order['complex_passepartout'] ?? false),
        'extra_services' => $order['extra_services'] ?? '[]',
        'transport_km' => $order['transport_km'] ?? '',
        'frame_box' => (bool)($order['frame_box'] ?? false),
        'frame_nonstandard' => (bool)($order['frame_nonstandard'] ?? false),
        'frame_shape' => $order['frame_shape'] ?? '',
        'frame_high_complexity' => (bool)($order['frame_high_complexity'] ?? false),
        'client_passepartout_cutting' => (bool)($order['client_passepartout_cutting'] ?? false),
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
    $allowedTypes = ['wood', 'metal', 'client_material'];
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
            $profileType = $post['profile_type'];
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

        if (!empty($post['apply_price']) && ($post['price'] ?? '') !== '') {
            $priceStmt = $conn->prepare('UPDATE passepartouts SET price = ? WHERE id = ?');
            $priceStmt->execute([(float)$post['price'], $id]);
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

function build_order_from_post(?string $customerName = null): array
{
    $passepartoutId = (int)($_POST['passepartout_id'] ?? 0);
    $passepartoutName = trim($_POST['passepartout'] ?? '');

    $extraServices = $_POST['extra_services'] ?? [];
    if (!is_array($extraServices)) {
        $extraServices = [];
    }

    return [
        'width' => $_POST['width'] ?? null,
        'height' => $_POST['height'] ?? null,
        'profile' => $_POST['profile'] ?? '',
        'glass' => $_POST['glass'] ?? '',
        'passepartout' => $passepartoutName,
        'passepartout_id' => $passepartoutId > 0 ? $passepartoutId : null,
        'back' => trim($_POST['back'] ?? ''),
        'hanging' => trim($_POST['hanging'] ?? ''),
        'passepartout_openings' => max(1, (int)($_POST['passepartout_openings'] ?? 1)),
        'urgent' => isset($_POST['urgent']) ? 1 : 0,
        'student_discount' => isset($_POST['student_discount']) ? 1 : 0,
        'complex_passepartout' => isset($_POST['complex_passepartout']) ? 1 : 0,
        'extra_services' => json_encode(array_values(array_map('intval', $extraServices))),
        'transport_km' => ($_POST['transport_km'] ?? '') !== '' ? (float)$_POST['transport_km'] : null,
        'frame_box' => isset($_POST['frame_box']) ? 1 : 0,
        'frame_nonstandard' => isset($_POST['frame_nonstandard']) ? 1 : 0,
        'frame_shape' => in_array($_POST['frame_shape'] ?? '', ['ellipse_12', 'circle_24'], true) ? $_POST['frame_shape'] : '',
        'frame_high_complexity' => isset($_POST['frame_high_complexity']) ? 1 : 0,
        'client_passepartout_cutting' => isset($_POST['client_passepartout_cutting']) ? 1 : 0,
        'manual_discount' => ($_POST['discount'] ?? '') !== '' ? (float)$_POST['discount'] : 0,
        'additional_profiles' => get_additional_profiles(),
        'frame_count' => max(1, (int)($_POST['frame_count'] ?? 1)),
        'customer_name' => $customerName ?? ($_POST['customer_name'] ?? ''),
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

function order_bind_values(array $order): array
{
    return [
        $order['date'],
        $order['width'],
        $order['height'],
        $order['profile'],
        $order['glass'],
        $order['passepartout'],
        $order['passepartout_bill_width'],
        $order['passepartout_bill_height'],
        $order['passepartout_sheet_type_id'],
        $order['passepartout_sheet_usage'],
        $order['back'],
        $order['hanging'],
        $order['customer_name'],
        $order['price'],
        $order['paid'],
        $order['collected'],
        $order['additional_profiles'],
        $order['frame_count'],
        $order['advance_payment'],
        $order['discount'],
        $order['description'],
        $order['passepartout_openings'],
        $order['urgent'],
        $order['student_discount'],
        $order['complex_passepartout'],
        $order['extra_services'],
        $order['transport_km'],
        $order['frame_box'],
        $order['frame_nonstandard'],
        $order['frame_shape'],
        $order['frame_high_complexity'],
        $order['client_passepartout_cutting'],
    ];
}

function insert_order_row(PDO $conn, array $order, int $orderNumber, int $subOrderNumber): void
{
    $stmt = $conn->prepare('
        INSERT INTO orders (
            order_number, sub_order_number, date, width, height, profile, glass, passepartout,
            passepartout_bill_width, passepartout_bill_height,
            passepartout_sheet_type_id, passepartout_sheet_usage,
            back, hanging, customer_name, price, paid, collected,
            additional_profiles, frame_count, advance_payment, discount, description,
            passepartout_openings, urgent, student_discount, complex_passepartout, extra_services, transport_km,
            frame_box, frame_nonstandard, frame_shape, frame_high_complexity, client_passepartout_cutting
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ');
    $stmt->execute(array_merge([$orderNumber, $subOrderNumber], order_bind_values($order)));
}

function update_order_row(PDO $conn, array $order, int $orderId): void
{
    $stmt = $conn->prepare('
        UPDATE orders SET
            date = ?, width = ?, height = ?, profile = ?, glass = ?,
            passepartout = ?, passepartout_bill_width = ?, passepartout_bill_height = ?,
            passepartout_sheet_type_id = ?, passepartout_sheet_usage = ?,
            back = ?, hanging = ?, customer_name = ?,
            price = ?, paid = ?, collected = ?, additional_profiles = ?,
            frame_count = ?, advance_payment = ?, discount = ?, description = ?,
            passepartout_openings = ?, urgent = ?, student_discount = ?,
            complex_passepartout = ?, extra_services = ?, transport_km = ?,
            frame_box = ?, frame_nonstandard = ?, frame_shape = ?, frame_high_complexity = ?,
            client_passepartout_cutting = ?
        WHERE id = ?
    ');
    $stmt->execute(array_merge(order_bind_values($order), [$orderId]));
}

function prepare_order_persistence(PDO $conn, array $post, ?string $customerName = null): array
{
    $orderData = build_order_from_post($customerName);
    $stockOrder = enrich_order_pricing_fields($conn, $orderData);

    return [
        'stock_order' => $stockOrder,
        'date' => $post['date'] ?? '',
        'width' => $post['width'] ?? null,
        'height' => $post['height'] ?? null,
        'profile' => $post['profile'] ?? '',
        'glass' => $post['glass'] ?? '',
        'passepartout' => $stockOrder['passepartout'] ?? ($post['passepartout'] ?? ''),
        'passepartout_bill_width' => $stockOrder['passepartout_bill_width'] ?? null,
        'passepartout_bill_height' => $stockOrder['passepartout_bill_height'] ?? null,
        'passepartout_sheet_type_id' => $stockOrder['passepartout_sheet_type_id'] ?? null,
        'passepartout_sheet_usage' => $stockOrder['passepartout_sheet_usage'] ?? null,
        'back' => $post['back'] ?? '',
        'hanging' => $post['hanging'] ?? '',
        'customer_name' => $customerName ?? ($post['customer_name'] ?? ''),
        'price' => resolve_order_price($conn, $orderData, $post['price'] ?? null),
        'paid' => isset($post['paid']) ? 1 : 0,
        'collected' => isset($post['collected']) ? 1 : 0,
        'additional_profiles' => get_additional_profiles(),
        'frame_count' => max(1, (int)($post['frame_count'] ?? 1)),
        'advance_payment' => $post['advance_payment'] ?? null,
        'discount' => $post['discount'] ?? null,
        'description' => $post['description'] ?? '',
        'passepartout_openings' => max(1, (int)($post['passepartout_openings'] ?? 1)),
        'urgent' => isset($post['urgent']) ? 1 : 0,
        'student_discount' => isset($post['student_discount']) ? 1 : 0,
        'complex_passepartout' => isset($post['complex_passepartout']) ? 1 : 0,
        'extra_services' => json_encode(array_values(array_map('intval', $post['extra_services'] ?? []))),
        'transport_km' => ($post['transport_km'] ?? '') !== '' ? (float)$post['transport_km'] : null,
        'frame_box' => isset($post['frame_box']) ? 1 : 0,
        'frame_nonstandard' => isset($post['frame_nonstandard']) ? 1 : 0,
        'frame_shape' => in_array($post['frame_shape'] ?? '', ['ellipse_12', 'circle_24'], true) ? $post['frame_shape'] : '',
        'frame_high_complexity' => isset($post['frame_high_complexity']) ? 1 : 0,
        'client_passepartout_cutting' => isset($post['client_passepartout_cutting']) ? 1 : 0,
    ];
}
