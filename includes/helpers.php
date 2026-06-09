<?php

function url_for(string $route, array $params = []): string
{
    $routes = [
        'home' => '/',
        'login' => '/login',
        'signup' => '/signup',
        'logout' => '/logout',
        'archives' => '/archives',
        'interface' => '/interface',
        'profiles' => '/profiles',
        'glasses' => '/glasses',
        'passepartouts' => '/passepartouts',
        'submit_feedback' => '/submit_feedback',
        'add_order' => '/add_order',
        'add_profile' => '/add_profile',
        'add_glass' => '/add_glass',
        'add_passepartout' => '/add_passepartout',
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

function full_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_PATH . $path;
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

    return [
        'width' => $_POST['width'] ?? null,
        'height' => $_POST['height'] ?? null,
        'profile' => $_POST['profile'] ?? '',
        'glass' => $_POST['glass'] ?? '',
        'passepartout' => $passepartoutName,
        'passepartout_id' => $passepartoutId > 0 ? $passepartoutId : null,
        'additional_profiles' => get_additional_profiles(),
        'frame_count' => $_POST['frame_count'] ?? 1,
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
        'back' => $post['back'] ?? '',
        'hanging' => $post['hanging'] ?? '',
        'customer_name' => $customerName ?? ($post['customer_name'] ?? ''),
        'price' => resolve_order_price($conn, $orderData, $post['price'] ?? null),
        'paid' => isset($post['paid']) ? 1 : 0,
        'collected' => isset($post['collected']) ? 1 : 0,
        'additional_profiles' => get_additional_profiles(),
        'frame_count' => $post['frame_count'] ?? 1,
        'advance_payment' => $post['advance_payment'] ?? null,
        'discount' => $post['discount'] ?? null,
        'description' => $post['description'] ?? '',
    ];
}
