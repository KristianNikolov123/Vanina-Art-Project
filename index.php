<?php

require_once __DIR__ . '/includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

if (BASE_PATH && str_starts_with($uri, BASE_PATH)) {
    $uri = substr($uri, strlen(BASE_PATH)) ?: '/';
}

// --- Auth routes ---

if ($uri === '/login') {
    if (is_authenticated()) {
        redirect('/');
    }
    if ($method === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $user = User::getByEmail($email);
        if ($user && $user->checkPassword($password)) {
            if (!$user->is_verified) {
                flash('Моля, потвърдете вашия имейл преди да влезете.', 'warning');
                redirect('/login');
            }
            login_user($user);
            User::logLogin($user->id, $user->username);
            flash('Успешен вход!', 'success');
            redirect('/');
        }
        flash('Грешен имейл или парола!', 'danger');
        redirect('/login');
    }
    render('login.php', ['title' => 'Вход', 'current_page' => 'login']);
    exit;
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
        if (User::create($username, $email, $password)) {
            if (send_verification_email_to_admin($email, $username)) {
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

if (preg_match('#^/verify/(.+)$#', $uri, $m)) {
    $email = urldecode($m[1]);
    if (User::verifyUser($email)) {
        flash('Имейлът е потвърден успешно! Можете да влезете в системата.', 'success');
    } else {
        flash('Грешка при потвърждаване на имейла.', 'danger');
    }
    redirect('/login');
}

if ($uri === '/logout') {
    require_login();
    logout_user();
    flash('Успешно излязохте от системата!', 'success');
    redirect('/login');
}

if ($uri === '/check_auth') {
    header('Content-Type: application/json');
    echo json_encode(['authenticated' => is_authenticated()]);
    exit;
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
    $user = current_user();
    if (!User::isAdminEmail($user->email)) {
        flash('Нямате достъп до тази страница!', 'danger');
        redirect('/');
    }
    $logs = User::getLoginLogs();
    render('archives.php', ['title' => 'Архив', 'current_page' => 'archives', 'logs' => $logs]);
    exit;
}

if ($uri === '/interface') {
    require_login();
    $conn = get_db_connection();
    $orders = $conn->query('SELECT * FROM orders ORDER BY order_number, sub_order_number')->fetchAll();
    $profiles = $conn->query('SELECT id, name, price FROM profiles ORDER BY name')->fetchAll();
    $glasses = $conn->query('SELECT id, name, price FROM glasses ORDER BY name')->fetchAll();
    $passepartouts = $conn->query('SELECT id, name, price FROM passepartouts ORDER BY name')->fetchAll();
    render('interface.php', [
        'title' => 'Поръчки',
        'current_page' => 'interface',
        'orders' => $orders,
        'profiles' => $profiles,
        'glasses' => $glasses,
        'passepartouts' => $passepartouts,
        'extra_css' => 'interface.css',
        'extra_js' => 'interface.js',
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

if ($uri === '/profiles') {
    require_login();
    $conn = get_db_connection();
    $profiles = $conn->query('SELECT * FROM profiles')->fetchAll();
    render('profiles.php', ['title' => 'Профили', 'current_page' => 'profiles', 'profiles' => $profiles, 'extra_js' => 'profiles.js']);
    exit;
}

if ($uri === '/glasses') {
    require_login();
    $conn = get_db_connection();
    $glasses = $conn->query('SELECT * FROM glasses')->fetchAll();
    render('glasses.php', ['title' => 'Стъкла', 'current_page' => 'glasses', 'glasses' => $glasses, 'extra_js' => 'glasses.js']);
    exit;
}

if ($uri === '/passepartouts') {
    require_login();
    $conn = get_db_connection();
    $passepartouts = $conn->query('SELECT * FROM passepartouts ORDER BY name')->fetchAll();
    foreach ($passepartouts as &$passepartoutRow) {
        $passepartoutRow['sheet_type_ids'] = get_passepartout_sheet_type_ids($conn, (int)$passepartoutRow['id']);
        $names = [];
        foreach ($passepartoutRow['sheet_type_ids'] as $sheetTypeId) {
            $stmt = $conn->prepare('SELECT name FROM passepartout_sheet_types WHERE id = ?');
            $stmt->execute([$sheetTypeId]);
            $name = $stmt->fetchColumn();
            if ($name) {
                $names[] = $name;
            }
        }
        $passepartoutRow['sheet_types'] = implode(', ', $names);
    }
    unset($passepartoutRow);
    $sheetTypes = get_all_sheet_types($conn);
    render('passepartouts.php', [
        'title' => 'Паспарту',
        'current_page' => 'passepartouts',
        'passepartouts' => $passepartouts,
        'sheet_types' => $sheetTypes,
        'extra_js' => 'passepartouts.js',
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

        $lastOrder = $conn->query('SELECT MAX(order_number) FROM orders')->fetchColumn();
        $orderNumber = ($lastOrder ?: 0) + 1;
        $order = prepare_order_persistence($conn, $_POST);

        $stmt = $conn->prepare('
            INSERT INTO orders (
                order_number, sub_order_number, date, width, height, profile, glass, passepartout,
                passepartout_bill_width, passepartout_bill_height,
                back, hanging, customer_name, price, paid, collected,
                additional_profiles, frame_count, advance_payment, discount, description
            ) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $orderNumber,
            $order['date'],
            $order['width'],
            $order['height'],
            $order['profile'],
            $order['glass'],
            $order['passepartout'],
            $order['passepartout_bill_width'],
            $order['passepartout_bill_height'],
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
        ]);

        deduct_stock_for_order($conn, $order['stock_order']);

        $conn->commit();
        flash('Поръчката е добавена успешно!', 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash('Грешка при добавяне на поръчката: ' . $e->getMessage(), 'error');
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

        $order = prepare_order_persistence($conn, $_POST);

        $conn->beginTransaction();

        if (!(bool)$existingOrder['collected']) {
            adjust_stock_for_order_edit($conn, $existingOrder, $order['stock_order']);
        }

        $stmt = $conn->prepare('
            UPDATE orders SET
                date = ?, width = ?, height = ?, profile = ?, glass = ?,
                passepartout = ?, passepartout_bill_width = ?, passepartout_bill_height = ?,
                back = ?, hanging = ?, customer_name = ?,
                price = ?, paid = ?, collected = ?, additional_profiles = ?,
                frame_count = ?, advance_payment = ?, discount = ?, description = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $order['date'],
            $order['width'],
            $order['height'],
            $order['profile'],
            $order['glass'],
            $order['passepartout'],
            $order['passepartout_bill_width'],
            $order['passepartout_bill_height'],
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
            $orderId,
        ]);

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

if (preg_match('#^/delete_order/(\d+)$#', $uri, $m)) {
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

        $restoreStock = isset($_GET['restore_stock']) && $_GET['restore_stock'] === '1';

        if (!(bool)$order['collected'] && $restoreStock) {
            $conn->beginTransaction();
            restore_stock_for_order($conn, $order);
            $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $conn->commit();
            flash('Поръчката е изтрита и материалите са върнати в наличност.', 'success');
        } elseif ((bool)$order['collected']) {
            $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            flash('Поръчката е изтрита успешно!', 'success');
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

if (preg_match('#^/add_sub_order/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $orderId = (int)$m[1];
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $mainOrder = $stmt->fetch();
        if (!$mainOrder) {
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

        $conn->beginTransaction();

        $order = prepare_order_persistence($conn, $_POST, $mainOrder['customer_name']);
        $stmt = $conn->prepare('
            INSERT INTO orders (
                order_number, sub_order_number, date, width, height, profile, glass, passepartout,
                passepartout_bill_width, passepartout_bill_height,
                back, hanging, customer_name, price, paid, collected,
                additional_profiles, frame_count, advance_payment, discount, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $mainOrder['order_number'],
            $subOrderNumber,
            $order['date'],
            $order['width'],
            $order['height'],
            $order['profile'],
            $order['glass'],
            $order['passepartout'],
            $order['passepartout_bill_width'],
            $order['passepartout_bill_height'],
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
        ]);

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
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('INSERT INTO profiles (name, price, stock) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock']]);
        flash('Профилът е добавен успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при добавяне на профил: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if (preg_match('#^/edit_profile/(\d+)$#', $uri, $m) && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('UPDATE profiles SET name = ?, price = ?, stock = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], (int)$m[1]]);
        flash('Профилът е редактиран успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране на профил: ' . $e->getMessage(), 'danger');
    }
    redirect('/profiles');
}

if (preg_match('#^/delete_profile/(\d+)$#', $uri, $m)) {
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

// --- Glass actions ---

if ($uri === '/add_glass' && $method === 'POST') {
    require_login();
    $conn = get_db_connection();
    try {
        $stmt = $conn->prepare('INSERT INTO glasses (name, price, stock) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock']]);
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
        $stmt = $conn->prepare('UPDATE glasses SET name = ?, price = ?, stock = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], (int)$m[1]]);
        flash('Стъклото е редактирано успешно!', 'success');
    } catch (Exception $e) {
        flash('Грешка при редактиране на стъкло: ' . $e->getMessage(), 'danger');
    }
    redirect('/glasses');
}

if (preg_match('#^/delete_glass/(\d+)$#', $uri, $m)) {
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
    $conn = get_db_connection();
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO passepartouts (name, price, stock) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock']]);
        $passepartoutId = (int)$conn->lastInsertId();
        save_passepartout_sheet_types($conn, $passepartoutId, $_POST['sheet_types'] ?? []);
        $conn->commit();
        flash('Паспартуто е добавено успешно!', 'success');
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
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
        $stmt = $conn->prepare('UPDATE passepartouts SET name = ?, price = ?, stock = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], $passepartoutId]);
        save_passepartout_sheet_types($conn, $passepartoutId, $_POST['sheet_types'] ?? []);
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

if (preg_match('#^/delete_passepartout/(\d+)$#', $uri, $m)) {
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

http_response_code(404);
echo '404 Not Found';
