<?php

require_once __DIR__ . '/includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

if (BASE_PATH && str_starts_with($uri, BASE_PATH)) {
    $uri = substr($uri, strlen(BASE_PATH)) ?: '/';
}

// Всяка POST заявка трябва да носи CSRF токена на сесията.
enforce_csrf($uri, $method);

// --- Auth routes ---

if ($uri === '/login') {
    if (is_authenticated()) {
        redirect('/');
    }
    if ($method === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $throttle = login_throttle_minutes(get_db_connection(), (string)$email);
        if ($throttle > 0) {
            flash("Твърде много грешни опити. Опитайте отново след {$throttle} мин.", 'danger');
            redirect('/login');
        }
        $user = User::getByEmail($email);
        if ($user && $user->checkPassword($password)) {
            clear_failed_logins(get_db_connection(), (string)$email);
            if (!$user->is_verified) {
                flash('Моля, потвърдете вашия имейл преди да влезете.', 'warning');
                redirect('/login');
            }
            login_user($user);
            User::logLogin($user->id, $user->username);
            flash('Успешен вход!', 'success');
            redirect('/');
        }
        record_failed_login(get_db_connection(), (string)$email);
        flash('Грешен имейл или парола!', 'danger');
        redirect('/login');
    }
    if (!empty($_SESSION['session_expired'])) {
        unset($_SESSION['session_expired']);
        flash('Сесията изтече поради неактивност. Моля, влезте отново.', 'warning');
    }
    render('login.php', ['title' => 'Вход', 'current_page' => 'login']);
    exit;
}

// Линк от QR кода за SMS: отваря се на телефона без вход и прехвърля към SMS приложението.
if (preg_match('#^/sms/(\d+)/([a-f0-9]{32})$#', $uri, $m)) {
    $handoff = find_sms_handoff(get_db_connection(), (int)$m[1], $m[2]);
    include ROOT_PATH . '/templates/sms_handoff.php';
    exit;
}

// Вход без презареждане (прозорец върху страницата при изтекла сесия).
if ($uri === '/api/login' && $method === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $throttle = login_throttle_minutes(get_db_connection(), $email);
    if ($throttle > 0) {
        json_response(['ok' => false, 'error' => "Твърде много грешни опити. Опитайте след {$throttle} мин."], 429);
    }
    $user = $email !== '' ? User::getByEmail($email) : null;
    if (!$user || !$user->checkPassword($password)) {
        record_failed_login(get_db_connection(), $email);
        json_response(['ok' => false, 'error' => 'Грешен имейл или парола!'], 401);
    }
    clear_failed_logins(get_db_connection(), $email);
    if (!$user->is_verified) {
        json_response(['ok' => false, 'error' => 'Моля, потвърдете вашия имейл преди да влезете.'], 403);
    }
    login_user($user);
    User::logLogin($user->id, $user->username);
    json_response([
        'ok' => true,
        'user_id' => $user->id,
        'username' => $user->username,
        'csrf_token' => csrf_token(),
        'expires_in' => session_seconds_remaining(),
    ]);
}

// Реална активност на служителя (клавиатура/мишка) удължава сесията.
if ($uri === '/api/ping' && $method === 'POST') {
    require_login();
    json_response(['ok' => true, 'expires_in' => session_seconds_remaining()]);
}

if ($uri === '/signup') {
    if (is_authenticated()) {
        redirect('/');
    }
    if ($method === 'POST') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            flash('Паролите не съвпадат!', 'danger');
            redirect('/signup');
        }
        $verifyToken = User::create($username, $email, $password);
        if ($verifyToken !== null) {
            if (send_verification_email_to_admin($email, $username, $verifyToken)) {
                flash('Регистрацията беше успешна! Моля, изчакайте администратор да потвърди акаунта ви.', 'success');
            } else {
                flash('Регистрацията беше успешна, но има проблем с уведомяването на администратора.', 'warning');
            }
            redirect('/login');
        }
        flash('Потребителското име или имейлът вече съществуват!', 'danger');
        redirect('/signup');
    }
    render('signup.php', ['title' => 'Регистрация', 'current_page' => 'signup']);
    exit;
}

// Потвърждение на нов потребител само с таен токен (линкът в имейла до администратора).
if (preg_match('#^/verify/([a-f0-9]{64})$#', $uri, $m)) {
    if (User::verifyByToken($m[1])) {
        flash('Акаунтът е потвърден успешно! Потребителят може да влезе в системата.', 'success');
    } else {
        flash('Линкът за потвърждение е невалиден или вече е използван.', 'danger');
    }
    redirect('/login');
}

if (preg_match('#^/verify/#', $uri)) {
    flash('Линкът за потвърждение е невалиден. Одобрете потребителя от „Влизания“ → „Чакащи регистрации“.', 'danger');
    redirect('/login');
}

if ($uri === '/logout') {
    require_login();
    logout_user();
    flash('Успешно излязохте от системата!', 'success');
    redirect('/login');
}

if ($uri === '/check_auth') {
    // Пасивна проверка — не удължава сесията.
    $user = current_user();
    json_response([
        'authenticated' => $user !== null,
        'user_id' => $user?->id,
        'expires_in' => session_seconds_remaining(),
        'idle_timeout' => session_idle_timeout_seconds(),
    ]);
}

// --- Protected page routes ---

if ($uri === '/') {
    require_login();
    $conn = get_db_connection();
    $feedbacks = $conn->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();
    $tz = new DateTimeZone('Europe/Sofia');
    foreach ($feedbacks as &$fb) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $fb['created_at'], new DateTimeZone('UTC'));
        if ($dt) {
            $dt->setTimezone($tz);
            $fb['formatted_time'] = $dt->format('d.m.Y H:i');
        } else {
            $fb['formatted_time'] = $fb['created_at'];
        }
    }
    render('home.php', ['title' => 'Обратна връзка', 'current_page' => 'home', 'feedbacks' => $feedbacks, 'extra_js' => 'home.js']);
    exit;
}

if ($uri === '/archives') {
    require_login();
    $conn = get_db_connection();
    $completed_orders = fetch_completed_orders($conn);
    $deleted_orders = fetch_deleted_orders($conn);
    $services = get_all_services($conn);
    render('archives.php', [
        'title' => 'Архив',
        'current_page' => 'archives',
        'completed_orders' => $completed_orders,
        'deleted_orders' => $deleted_orders,
        'services' => $services,
        'extra_js' => ['search-utils.js', 'order-view.js', 'archives.js'],
    ]);
    exit;
}

if ($uri === '/login_logs') {
    require_login();
    $user = current_user();
    if (!User::isAdminEmail($user->email)) {
        flash('Нямате достъп до тази страница!', 'danger');
        redirect('/');
    }
    $logs = User::getLoginLogs();
    render('login_logs.php', [
        'title' => 'Влизания',
        'current_page' => 'login_logs',
        'logs' => $logs,
        'pending_users' => User::getPendingUsers(),
    ]);
    exit;
}

if (preg_match('#^/users/(\d+)/(approve|reject)$#', $uri, $m) && $method === 'POST') {
    require_admin();
    $ok = $m[2] === 'approve' ? User::approve((int)$m[1]) : User::reject((int)$m[1]);
    flash(
        $ok ? ($m[2] === 'approve' ? 'Потребителят е одобрен.' : 'Заявката е отхвърлена.') : 'Потребителят не е намерен.',
        $ok ? 'success' : 'warning'
    );
    redirect('/login_logs');
}

// --- Клиенти ---

if ($uri === '/customers') {
    require_login();
    $conn = get_db_connection();
    $query = trim((string)($_GET['q'] ?? ''));
    render('customers.php', [
        'title' => 'Клиенти',
        'current_page' => 'customers',
        'query' => $query,
        'customers' => fetch_customers_overview($conn, $query),
    ]);
    exit;
}

if (preg_match('#^/customers/(\d+)$#', $uri, $m)) {
    require_login();
    $conn = get_db_connection();
    $customerId = (int)$m[1];
    $customer = get_customer($conn, $customerId);
    if (!$customer) {
        flash('Клиентът не е намерен.', 'warning');
        redirect('/customers');
    }
    if ($method === 'POST') {
        try {
            update_customer_from_post($conn, $customerId, $_POST);
            flash('Данните на клиента са запазени.', 'success');
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage(), 'warning');
        }
        redirect('/customers/' . $customerId);
    }
    render('customer.php', [
        'title' => $customer['name'],
        'current_page' => 'customers',
        'customer' => $customer,
        'orders' => fetch_customer_orders($conn, $customerId),
        'extra_js' => ['notify.js'],
    ]);
    exit;
}

if ($uri === '/api/customers' && $method === 'GET') {
    require_login();
    json_response(['customers' => search_customers(get_db_connection(), (string)($_GET['q'] ?? ''))]);
}

// --- Статус с един клик (Готова / Платена / Получена) ---

if ($uri === '/api/order_status' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    $field = (string)($_POST['field'] ?? '');
    $order = update_order_status($conn, (int)($_POST['order_id'] ?? 0), $field, ($_POST['value'] ?? '') === '1');
    if ($order === null) {
        json_response(['ok' => false, 'message' => 'Поръчката не е намерена.'], 404);
    }
    $archived = !empty($order['paid']) && !empty($order['collected']);
    json_response([
        'ok' => true,
        'archived' => $archived,
        'order' => order_view_data($order),
        'html' => $archived ? '' : render_active_order_row_html($order, services_name_map()),
        'revision' => order_row_content_revision($order),
    ]);
}

// --- Уведомяване на клиента ---

if (preg_match('#^/api/orders/(\d+)/notification$#', $uri, $m)) {
    require_login();
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([(int)$m[1]]);
    $order = $stmt->fetch();
    if (!$order) {
        json_response(['ok' => false, 'message' => 'Поръчката не е намерена.'], 404);
    }
    if ($method === 'POST') {
        $result = send_order_notification(
            $conn,
            $order,
            (string)($_POST['channel'] ?? ''),
            (string)($_POST['kind'] ?? ''),
            (string)($_POST['message'] ?? '')
        );
        json_response($result, $result['ok'] ? 200 : 422);
    }
    $contact = order_contact($conn, $order);
    $kind = default_notification_kind($order);
    $messages = [];
    foreach (array_keys(notification_kind_labels()) as $k) {
        $messages[$k] = build_notification_message($order, $k);
    }
    json_response([
        'ok' => true,
        'kind' => $kind,
        'kinds' => notification_kind_labels(),
        'messages' => $messages,
        'email' => $contact['email'],
        'phone' => $contact['phone'],
        'sms_provider' => sms_provider_configured(),
        'history' => array_map(static fn (array $n): array => [
            'channel' => $n['channel'],
            'status' => $n['status'],
            'recipient' => $n['recipient'],
            'sent_by' => $n['sent_by'],
            'created_at' => date('d.m.Y H:i', strtotime($n['created_at'])),
        ], fetch_order_notifications($conn, (int)$order['id'])),
    ]);
}

// --- Печат: разписка за клиента ---

if (preg_match('#^/order_print/(\d+)$#', $uri, $m)) {
    require_login();
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([(int)$m[1]]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        echo 'Поръчката не е намерена.';
        exit;
    }
    $stmt = $conn->prepare('SELECT * FROM orders WHERE order_number = ? AND deleted_at IS NULL ORDER BY sub_order_number');
    $stmt->execute([(int)$order['order_number']]);
    $items = $stmt->fetchAll() ?: [$order];
    include ROOT_PATH . '/templates/order_print.php';
    exit;
}

if ($uri === '/used_materials') {
    // Същата сървърна проверка като „Влизания“ — само администратор.
    require_admin();
    $conn = get_db_connection();
    $year = (int)($_GET['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }
    render('used_materials.php', [
        'title' => 'Използвани материали',
        'current_page' => 'used_materials',
        'year' => $year,
        'months' => build_used_materials_report($conn, $year),
    ]);
    exit;
}

if ($uri === '/reminders') {
    require_login();
    $conn = get_db_connection();
    render('reminders.php', [
        'title' => 'Напомняния',
        'current_page' => 'reminders',
        'unpaid_orders' => fetch_collected_unpaid_orders($conn),
        'uncollected_orders' => fetch_overdue_uncollected_orders($conn),
        'extra_js' => ['notify.js'],
    ]);
    exit;
}

if ($uri === '/interface') {
    require_login();
    $conn = get_db_connection();
    if ($method === 'POST') {
        $action = $_POST['_action'] ?? '';
        if ($action === 'add_painting_reservation') {
            process_add_painting_reservation_submission($conn, $_POST);
            redirect('/interface');
        }
        if ($action === 'delete_painting_reservation') {
            process_delete_painting_reservation_submission($conn, (int)($_POST['reservation_id'] ?? 0));
            redirect('/interface');
        }
    }
    $orders = fetch_active_orders($conn);
    $orders_sync_revision = compute_active_orders_revision($orders);
    $profiles = $conn->query('SELECT id, name, price, stock FROM profiles ORDER BY name')->fetchAll();
    $glasses = $conn->query('SELECT id, name, price FROM glasses ORDER BY sort_order, name')->fetchAll();
    $passepartouts = $conn->query('SELECT id, name, price FROM passepartouts ORDER BY name')->fetchAll();
    $backs = $conn->query('SELECT id, name FROM backs ORDER BY sort_order, name')->fetchAll();
    $hanging_options = $conn->query('SELECT id, name, price, sort_order FROM hanging_options ORDER BY sort_order, name')->fetchAll();
    $services = get_all_services($conn, true);
    $allServices = get_all_services($conn);
    render('interface.php', [
        'title' => 'Поръчки',
        'current_page' => 'interface',
        'body_class' => 'page-orders',
        'layout_container_class' => 'container-fluid px-2 px-lg-3',
        'orders' => $orders,
        'orders_sync_revision' => $orders_sync_revision,
        'workloadChart' => build_order_workload_chart($conn, $orders, 14),
        'profiles' => $profiles,
        'glasses' => $glasses,
        'passepartouts' => $passepartouts,
        'backs' => $backs,
        'hanging_options' => $hanging_options,
        'services' => $services,
        'services_name_map' => array_column($allServices, 'name', 'id'),
        'order_pricing_hints' => get_order_automatic_pricing_hints($conn),
        'urgent_options' => urgent_level_options($conn),
        'reminders_count' => count_reminders($conn),
        'sms_provider' => sms_provider_configured(),
        'extra_css' => 'interface.css',
        'extra_js' => ['search-utils.js', 'notify.js', 'interface.js'],
    ]);
    exit;
}

if ($uri === '/calculate_price' && $method === 'POST') {
    require_login();
    header('Content-Type: application/json');
    $order = build_order_from_post();
    echo json_encode(calculate_order_pricing(get_db_connection(), $order));
    exit;
}

// Проверка за недостатъчна наличност преди запис (без запис в базата).
if ($uri === '/check_stock' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    $previous = null;
    $orderId = (int)($_POST['_order_id'] ?? 0);
    if ($orderId > 0) {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $previous = $stmt->fetch() ?: null;
        if ($previous && !empty($previous['collected'])) {
            // При получена поръчка складът не се коригира при редакция.
            json_response(['shortages' => []]);
        }
    }
    $order = enrich_order_pricing_fields($conn, build_order_from_post());
    json_response(['shortages' => find_order_stock_shortages($conn, $order, $previous)]);
}

if ($uri === '/api/active_orders' && $method === 'GET') {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $conn = get_db_connection();
    $clientRevision = trim((string)($_GET['revision'] ?? ''));
    $payload = build_active_orders_sync_payload($conn);
    if ($clientRevision !== '' && hash_equals($payload['revision'], $clientRevision)) {
        echo json_encode(['unchanged' => true, 'revision' => $payload['revision']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'unchanged' => false,
        'revision' => $payload['revision'],
        'orders' => $payload['orders'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($uri === '/warehouse') {
    require_login();
    render('warehouse.php', ['title' => 'Склад', 'current_page' => 'warehouse']);
    exit;
}

if ($uri === '/profiles') {
    require_login();
    $conn = get_db_connection();
    $profiles = $conn->query('SELECT * FROM profiles')->fetchAll();
    render('profiles.php', [
        'title' => 'Профили',
        'current_page' => 'profiles',
        'profiles' => $profiles,
        'extra_css' => ['profiles.css', 'bulk-edit.css'],
        'extra_js' => ['bulk-select.js', 'multi-add-rows.js', 'profiles.js'],
    ]);
    exit;
}

if ($uri === '/glasses') {
    require_login();
    $conn = get_db_connection();
    $glasses = $conn->query('SELECT * FROM glasses')->fetchAll();
    render('glasses.php', ['title' => 'Стъкла', 'current_page' => 'glasses', 'glasses' => $glasses, 'extra_js' => 'glasses.js']);
    exit;
}

if ($uri === '/backs') {
    require_login();
    $conn = get_db_connection();
    $backs = $conn->query('SELECT * FROM backs ORDER BY name')->fetchAll();
    render('backs.php', ['title' => 'Гръбове', 'current_page' => 'backs', 'backs' => $backs, 'extra_js' => 'backs.js']);
    exit;
}

if ($uri === '/hanging') {
    require_login();
    $conn = get_db_connection();
    $hanging_options = $conn->query('SELECT * FROM hanging_options ORDER BY name')->fetchAll();
    $hangerPoolStock = get_hanger_pool_stock($conn);
    foreach ($hanging_options as &$hangingRow) {
        $hangingRow['display_stock'] = hanging_shares_hanger_pool($hangingRow['name'] ?? '')
            ? $hangerPoolStock
            : (float)($hangingRow['stock'] ?? 0);
    }
    unset($hangingRow);
    seed_hanging_weight_tiers($conn);
    render('hanging.php', [
        'title' => 'Окачване',
        'current_page' => 'hanging',
        'hanging_options' => $hanging_options,
        'weight_tiers' => get_hanging_weight_tiers($conn),
        'extra_js' => 'hanging.js',
    ]);
    exit;
}

if ($uri === '/save_hanging_weight_tiers' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $updated = save_hanging_weight_tiers_from_post($conn, $_POST);
        flash("Запазени са {$updated} стъпки за връзка по тегло.", 'success');
    } catch (Exception $e) {
        flash('Грешка при запазване: ' . $e->getMessage(), 'danger');
    }
    redirect('/hanging');
}

if ($uri === '/services') {
    require_login();
    $conn = get_db_connection();
    seed_passepartout_kind_tier_settings($conn);
    seed_price_list_rules($conn);
    $pricing_settings = get_all_pricing_settings($conn);
    $services = get_all_services($conn);
    render('services.php', [
        'title' => 'Услуги',
        'current_page' => 'services',
        'pricing_settings' => $pricing_settings,
        'services' => $services,
        'passepartout_price_kinds' => get_passepartout_price_kinds_for_display($conn),
        'price_list_rules' => get_price_list_rules($conn),
        'edit_mode' => isset($_GET['edit']),
        'extra_js' => 'services.js',
    ]);
    exit;
}

if ($uri === '/save_services' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    seed_passepartout_kind_tier_settings($conn);
    seed_price_list_rules($conn);
    try {
        $conn->beginTransaction();
        $counts = save_services_pricing_from_post($conn, $_POST);
        $conn->commit();
        $synced = (int)($counts['pp_synced'] ?? 0);
        $syncMsg = $synced > 0 ? " Синхронизирани са {$synced} артикула паспарту към новите видове." : '';
        flash(
            sprintf(
                'Запазени са %d тарифи за труд, %d услуги, %d цени по вид паспарту и %d правила.%s',
                $counts['settings'],
                $counts['services'],
                $counts['pp_tiers'],
                $counts['rules'],
                $syncMsg
            ),
            'success'
        );
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при запазване: ' . $e->getMessage(), 'danger');
    }
    redirect('/services');
}

if ($uri === '/passepartouts') {
    require_login();
    $conn = get_db_connection();
    $passepartouts = $conn->query('SELECT * FROM passepartouts ORDER BY name')->fetchAll();
    foreach ($passepartouts as &$passepartoutRow) {
        $passepartoutId = (int)$passepartoutRow['id'];
        $passepartoutRow['sheet_stocks'] = get_passepartout_sheet_stocks($conn, $passepartoutId);
    }
    unset($passepartoutRow);
    $sheetTypes = get_all_sheet_types($conn);
    render('passepartouts.php', [
        'title' => 'Паспарту',
        'current_page' => 'passepartouts',
        'passepartouts' => $passepartouts,
        'sheet_types' => $sheetTypes,
        'passepartout_price_kinds' => get_passepartout_price_kind_definitions($conn),
        'passepartout_tier_labels' => get_passepartout_tier_scheme_labels(),
        'extra_css' => ['passepartouts.css', 'bulk-edit.css'],
        'extra_js' => ['bulk-select.js', 'multi-add-rows.js', 'passepartouts.js'],
    ]);
    exit;
}

// --- Feedback actions ---

if ($uri === '/submit_feedback' && $method === 'POST') {
    require_login();
    $message = trim($_POST['message'] ?? '');
    if ($message) {
        $conn = get_db_connection();
        $stmt = $conn->prepare('INSERT INTO feedback (message) VALUES (?)');
        $stmt->execute([$message]);
        flash('Обратната връзка беше изпратена успешно!', 'success');
    }
    redirect('/');
}

if (preg_match('#^/delete_feedback/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    $stmt = $conn->prepare('DELETE FROM feedback WHERE id = ?');
    $stmt->execute([(int)$m[1]]);
    flash('Обратната връзка е изтрита.', 'success');
    redirect('/');
}

if (preg_match('#^/edit_feedback/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $newMessage = trim($_POST['edit_message'] ?? '');
    if ($newMessage) {
        $conn = get_db_connection();
        $stmt = $conn->prepare('UPDATE feedback SET message = ? WHERE id = ?');
        $stmt->execute([$newMessage, (int)$m[1]]);
        flash('Обратната връзка е редактирана.', 'success');
    }
    redirect('/');
}

// --- Order actions ---

if ($uri === '/add_order' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $conn->beginTransaction();

        $orderNumber = next_order_number($conn);
        $order = apply_status_timestamps(prepare_order_persistence($conn, $_POST), null);
        if (!apply_stock_shortage_confirmation($conn, $order, $_POST)) {
            $conn->rollBack();
            redirect('/interface');
        }
        insert_order_row($conn, $order, $orderNumber, 0);

        deduct_stock_for_order($conn, $order['stock_order']);

        $conn->commit();
        flash(
            !empty($order['stock_override'])
                ? 'Поръчката е добавена с маркировка за недостатъчна наличност (за проверка на склада).'
                : 'Поръчката е добавена успешно!',
            !empty($order['stock_override']) ? 'warning' : 'success'
        );
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при добавяне на поръчката: ' . $e->getMessage(), 'error');
    }
    redirect('/interface');
}

if ($uri === '/add_painting_reservation') {
    require_login();
    if ($method === 'POST') {
        process_add_painting_reservation_submission(get_db_connection(), $_POST);
    }
    redirect('/interface');
}

if (preg_match('#^/delete_painting_reservation/(\d+)$#', $uri, $m)) {
    require_login();
    if ($method === 'POST') {
        process_delete_painting_reservation_submission(get_db_connection(), (int)$m[1]);
    }
    redirect('/interface');
}

if (preg_match('#^/edit_order/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $orderId = (int)$m[1];
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $existingOrder = $stmt->fetch();
        if (!$existingOrder) {
            flash('Поръчката не е намерена!', 'danger');
            redirect('/interface');
        }
        if (!empty($existingOrder['deleted_at'])) {
            flash('Изтритите поръчки не могат да бъдат редактирани оттук. Възстановете ги от архива.', 'warning');
            redirect('/archives');
        }

        $order = apply_status_timestamps(prepare_order_persistence($conn, $_POST), $existingOrder);

        if (!(bool)$existingOrder['collected']) {
            if (!apply_stock_shortage_confirmation($conn, $order, $_POST, $existingOrder)) {
                redirect('/interface');
            }
        } else {
            $order['stock_override'] = (int)($existingOrder['stock_override'] ?? 0);
            $order['stock_shortage'] = $existingOrder['stock_shortage'] ?? null;
        }

        $conn->beginTransaction();

        if (!(bool)$existingOrder['collected']) {
            adjust_stock_for_order_edit($conn, $existingOrder, $order['stock_order']);
        }

        update_order_row($conn, $order, $orderId);

        $conn->commit();
        flash('Поръчката е редактирана успешно!', 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при редактиране на поръчката: ' . $e->getMessage(), 'error');
    }
    redirect('/interface');
}

if (preg_match('#^/delete_order/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $orderId = (int)$m[1];
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('Поръчката не е намерена!', 'danger');
            redirect('/interface');
        }

        if (!empty($order['deleted_at'])) {
            flash('Поръчката вече е в архива.', 'warning');
            redirect('/archives');
        }

        $restoreStock = ($_POST['restore_stock'] ?? '') === '1';

        if (!(bool)$order['collected'] && $restoreStock) {
            $conn->beginTransaction();
            restore_stock_for_order($conn, $order);
            soft_delete_order($conn, $orderId);
            $conn->commit();
            flash('Поръчката е преместена в архива и материалите са върнати в наличност.', 'success');
        } elseif ((bool)$order['collected'] || $restoreStock) {
            soft_delete_order($conn, $orderId);
            flash('Поръчката е преместена в архива.', 'success');
        } else {
            flash('Изтриването е отменено. Материалите не са върнати в наличност.', 'warning');
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при изтриване на поръчка: ' . $e->getMessage(), 'danger');
    }
    redirect('/interface');
}

if (preg_match('#^/restore_order/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $orderId = (int)$m[1];
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || empty($order['deleted_at'])) {
        flash('Поръчката не е намерена в архива на изтритите.', 'danger');
        redirect('/archives');
    }
    if (restore_archived_order($conn, $orderId)) {
        $destination = order_is_completed($order) ? 'архива на завършените' : 'списъка „Поръчки“';
        flash("Поръчката е възстановена в {$destination}.", 'success');
    } else {
        flash('Грешка при възстановяване на поръчката.', 'danger');
    }
    redirect('/archives');
}

if (preg_match('#^/add_sub_order/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $orderId = (int)$m[1];
    $conn = get_db_connection();
    try {
        $conn->beginTransaction();
        // Заключва основната поръчка, за да не получат две подпоръчки един и същ номер.
        $mainOrder = lock_order_row($conn, $orderId);
        if (!$mainOrder) {
            $conn->rollBack();
            flash('Основната поръчка не е намерена!', 'error');
            redirect('/interface');
        }

        $stmt = $conn->prepare('SELECT MAX(sub_order_number) FROM orders WHERE order_number = ?');
        $stmt->execute([$mainOrder['order_number']]);
        $lastSubOrder = $stmt->fetchColumn();

        if ($lastSubOrder == 0) {
            $subOrderNumber = 2;
            $stmt = $conn->prepare('UPDATE orders SET sub_order_number = 1 WHERE id = ?');
            $stmt->execute([$orderId]);
        } else {
            $subOrderNumber = $lastSubOrder + 1;
        }

        $order = prepare_order_persistence(
            $conn,
            $_POST,
            $mainOrder['customer_name'],
            $mainOrder['customer_phone'] ?? null
        );
        $order['accepted_date'] = $mainOrder['accepted_date'] ?? $order['accepted_date'];
        $order['customer_id'] = $mainOrder['customer_id'] ?? $order['customer_id'];
        $order['customer_email'] = $order['customer_email'] ?: ($mainOrder['customer_email'] ?? null);
        $order = apply_status_timestamps($order, null);
        if (!apply_stock_shortage_confirmation($conn, $order, $_POST)) {
            $conn->rollBack();
            redirect('/interface');
        }
        insert_order_row($conn, $order, (int)$mainOrder['order_number'], $subOrderNumber);

        deduct_stock_for_order($conn, $order['stock_order']);

        $conn->commit();
        flash('Подпоръчката е добавена успешно!', 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при добавяне на подпоръчката: ' . $e->getMessage(), 'error');
    }
    redirect('/interface');
}

// --- Profile actions ---

if ($uri === '/add_profile' && $method === 'POST') {
    require_login();
    $rows = parse_profile_rows_from_post();
    if (empty($rows)) {
        flash('Добавете поне един профил с име.', 'warning');
        redirect('/profiles');
    }

    $conn = get_db_connection();
    try {
        $result = create_profiles_bulk($conn, $rows);
        flash_bulk_create_profiles_result($result);
    } catch (Exception $e) {
        flash('Грешка при добавяне на профил: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if (preg_match('#^/edit_profile/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $widthCm = isset($_POST['width_cm']) && $_POST['width_cm'] !== '' ? $_POST['width_cm'] : null;
        $requestedType = $_POST['profile_type'] ?? 'wood';
        $profileType = in_array($requestedType, profile_allowed_types(), true) ? $requestedType : 'wood';
        $stmt = $conn->prepare('UPDATE profiles SET name = ?, price = ?, stock = ?, width_cm = ?, profile_type = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], $widthCm, $profileType, (int)$m[1]]);
        flash('Профилът е редактиран успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране на профил: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if (preg_match('#^/delete_profile/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('DELETE FROM profiles WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        flash('Профилът е изтрит успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при изтриване на профил: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if ($uri === '/bulk_edit_profiles' && $method === 'POST') {
    require_login();
    $ids = parse_bulk_ids_from_post();
    if (empty($ids)) {
        flash('Не са избрани профили за групово редактиране.', 'warning');
        redirect('/profiles');
    }

    $hasChanges = !empty($_POST['apply_price'])
        || !empty($_POST['apply_stock'])
        || !empty($_POST['apply_width_cm'])
        || !empty($_POST['apply_profile_type']);

    if (!$hasChanges) {
        flash('Маркирайте поне едно поле за промяна.', 'warning');
        redirect('/profiles');
    }

    $conn = get_db_connection();
    try {
        $conn->beginTransaction();
        $updated = bulk_update_profiles($conn, $ids, $_POST);
        $conn->commit();
        flash("Обновени са {$updated} профила.", 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при групово редактиране: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if ($uri === '/profiles_raise_prices_10' && $method === 'POST') {
    require_login();
    $ids = parse_bulk_ids_from_post();
    $conn = get_db_connection();
    try {
        $conn->beginTransaction();
        $updated = raise_profile_prices_by_ten_percent($conn, $ids);
        $conn->commit();
        flash(
            $ids === []
                ? "Повишени са цените (+10%, закръгляне нагоре до 0.10 €) на {$updated} профила."
                : "Повишени са цените (+10%, закръгляне нагоре до 0.10 €) на {$updated} избрани профила.",
            'success'
        );
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при повишаване на цените: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

// --- Glass actions ---

if ($uri === '/add_glass' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('INSERT INTO glasses (name, price, min_price, stock) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['min_price'] ?? 0, $_POST['stock']]);
        flash('Стъклото е добавено успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при добавяне на стъкло: ' . $e->getMessage(), 'danger');
    }
    redirect('/glasses');
}

if (preg_match('#^/edit_glass/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('UPDATE glasses SET name = ?, price = ?, min_price = ?, stock = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['min_price'] ?? 0, $_POST['stock'], (int)$m[1]]);
        flash('Стъклото е редактирано успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране на стъкло: ' . $e->getMessage(), 'danger');
    }
    redirect('/glasses');
}

if (preg_match('#^/delete_glass/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('DELETE FROM glasses WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        flash('Стъклото е изтрито успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при изтриване на стъкло: ' . $e->getMessage(), 'danger');
    }
    redirect('/glasses');
}

// --- Passepartout actions ---

if ($uri === '/add_passepartout' && $method === 'POST') {
    require_login();
    $rows = parse_passepartout_rows_from_post();
    if (empty($rows)) {
        flash('Добавете поне едно паспарту с номер.', 'warning');
        redirect('/passepartouts');
    }

    $conn = get_db_connection();
    try {
        $result = create_passepartouts_bulk($conn, $rows);
        flash_bulk_create_passepartouts_result($result);
    } catch (Exception $e) {
        flash('Грешка при добавяне на паспарту: ' . $e->getMessage(), 'danger');
    }
    redirect('/passepartouts');
}

if (preg_match('#^/edit_passepartout/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $passepartoutId = (int)$m[1];
        $conn->beginTransaction();
        $stmt = $conn->prepare('UPDATE passepartouts SET name = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $passepartoutId]);
        save_passepartout_pricing_fields($conn, $passepartoutId, parse_passepartout_pricing_from_post());
        save_passepartout_sheet_stocks($conn, $passepartoutId, parse_passepartout_sheet_stocks_from_post());
        $conn->commit();
        flash('Паспартуто е редактирано успешно!', 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при редактиране на паспарту: ' . $e->getMessage(), 'danger');
    }
    redirect('/passepartouts');
}

if (preg_match('#^/delete_passepartout/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('DELETE FROM passepartouts WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        flash('Паспартуто е изтрито успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при изтриване на паспарту: ' . $e->getMessage(), 'danger');
    }
    redirect('/passepartouts');
}

if ($uri === '/bulk_edit_passepartouts' && $method === 'POST') {
    require_login();
    $ids = parse_bulk_ids_from_post();
    if (empty($ids)) {
        flash('Не са избрани паспартута за групово редактиране.', 'warning');
        redirect('/passepartouts');
    }

    $applySheetStock = $_POST['apply_sheet_stock'] ?? [];
    $hasSheetChanges = is_array($applySheetStock) && count(array_filter($applySheetStock)) > 0;
    $hasChanges = !empty($_POST['apply_passepartout_pricing']) || $hasSheetChanges;

    if (!$hasChanges) {
        flash('Маркирайте поне едно поле за промяна.', 'warning');
        redirect('/passepartouts');
    }

    $conn = get_db_connection();
    try {
        $conn->beginTransaction();
        $updated = bulk_update_passepartouts($conn, $ids, $_POST);
        $conn->commit();
        flash("Обновени са {$updated} паспартута.", 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при групово редактиране: ' . $e->getMessage(), 'danger');
    }
    redirect('/passepartouts');
}

// --- Back actions ---

if ($uri === '/add_back' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('INSERT INTO backs (name, price, min_price, stock) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['min_price'] ?? 0, $_POST['stock'] ?? 0]);
        flash('Гръбът е добавен успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при добавяне: ' . $e->getMessage(), 'danger');
    }
    redirect('/backs');
}

if (preg_match('#^/edit_back/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('UPDATE backs SET name = ?, price = ?, min_price = ?, stock = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['min_price'] ?? 0, $_POST['stock'] ?? 0, (int)$m[1]]);
        flash('Гръбът е редактиран успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране: ' . $e->getMessage(), 'danger');
    }
    redirect('/backs');
}

if (preg_match('#^/delete_back/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('DELETE FROM backs WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        flash('Гръбът е изтрит успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при изтриване: ' . $e->getMessage(), 'danger');
    }
    redirect('/backs');
}

// --- Hanging actions ---

if ($uri === '/add_hanging' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('INSERT INTO hanging_options (name, price, min_price, pricing_unit, stock) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['name'],
            $_POST['price'],
            $_POST['min_price'] ?? 0,
            $_POST['pricing_unit'] ?? 'piece',
            $_POST['stock'] ?? 0,
        ]);
        flash('Опцията е добавена успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при добавяне: ' . $e->getMessage(), 'danger');
    }
    redirect('/hanging');
}

if (preg_match('#^/edit_hanging/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $name = trim($_POST['name'] ?? '');
        $stock = (int)($_POST['stock'] ?? 0);

        $stmt = $conn->prepare('UPDATE hanging_options SET name = ?, price = ?, min_price = ?, pricing_unit = ? WHERE id = ?');
        $stmt->execute([
            $name,
            $_POST['price'],
            $_POST['min_price'] ?? 0,
            $_POST['pricing_unit'] ?? 'piece',
            (int)$m[1],
        ]);

        if (hanging_shares_hanger_pool($name)) {
            $conn->prepare('UPDATE hanging_options SET stock = ? WHERE TRIM(name) = ?')
                ->execute([$stock, 'Закачалка']);
        } else {
            $conn->prepare('UPDATE hanging_options SET stock = ? WHERE id = ?')
                ->execute([$stock, (int)$m[1]]);
        }

        flash('Опцията е редактирана успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране: ' . $e->getMessage(), 'danger');
    }
    redirect('/hanging');
}

if (preg_match('#^/delete_hanging/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('DELETE FROM hanging_options WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        flash('Опцията е изтрита успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при изтриване: ' . $e->getMessage(), 'danger');
    }
    redirect('/hanging');
}

http_response_code(404);
echo '404 Not Found';
