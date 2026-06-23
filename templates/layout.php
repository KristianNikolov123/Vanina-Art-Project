<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? '') ?> - Ванина Арт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= static_url('css/glasses.css') ?>" rel="stylesheet">
    <?php
    $extraCssFiles = is_array($extra_css ?? null) ? $extra_css : (($extra_css ?? '') !== '' ? [$extra_css] : []);
    foreach ($extraCssFiles as $cssFile):
    ?>
    <link rel="stylesheet" href="<?= static_url('css/' . $cssFile) ?>">
    <?php endforeach; ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= url_for('home') ?>">
                <img src="<?= static_url('Logo.jpg') ?>" alt="Vanina Art Logo">
                <span>Ванина Арт</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'home' ? 'active' : '' ?>" href="<?= url_for('home') ?>">
                            <i class="fas fa-home"></i> Начало
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'interface' ? 'active' : '' ?>" href="<?= url_for('interface') ?>">
                            <i class="fas fa-list"></i> Поръчки
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= is_inventory_page($current_page ?? '') ? 'active' : '' ?>"
                           href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Склад
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= url_for('warehouse') ?>"><i class="fas fa-th-large me-2"></i>Преглед</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url_for('profiles') ?>"><i class="fas fa-border-all me-2"></i>Профили</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('glasses') ?>"><i class="fas fa-window-maximize me-2"></i>Стъкла</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('passepartouts') ?>"><i class="fas fa-image me-2"></i>Паспарту</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('backs') ?>"><i class="fas fa-layer-group me-2"></i>Гръбове</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('hanging') ?>"><i class="fas fa-link me-2"></i>Окачване</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('services') ?>"><i class="fas fa-tools me-2"></i>Услуги</a></li>
                        </ul>
                    </li>
                    <?php if ($current_user): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'archives' ? 'active' : '' ?>" href="<?= url_for('archives') ?>">
                            <i class="fas fa-archive"></i> Архив
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($current_user && User::isAdminEmail($current_user->email)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'login_logs' ? 'active' : '' ?>" href="<?= url_for('login_logs') ?>">
                            <i class="fas fa-history"></i> Влизания
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($current_user): ?>
                        <li class="nav-item">
                            <span class="nav-link">Здравей, <?= e($current_user->username) ?>!</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url_for('logout') ?>">
                                <i class="fas fa-sign-out-alt"></i> Изход
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url_for('login') ?>">
                                <i class="fas fa-sign-in-alt"></i> Вход
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url_for('signup') ?>">
                                <i class="fas fa-user-plus"></i> Регистрация
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php foreach ($flashed_messages as $flash): ?>
            <div class="alert alert-<?= e($flash['category']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>

    <?php if (!$current_user && !in_array($current_page, ['login', 'signup'])): ?>
    <div class="login-overlay" id="loginOverlay">
        <div class="login-modal">
            <i class="fas fa-lock"></i>
            <h3>Влез в профил</h3>
            <p>За да видиш съдържанието, трябва да влезеш в профила си.</p>
            <a href="<?= url_for('login') ?>" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Вход
            </a>
        </div>
    </div>
    <?php endif; ?>

    <script>window.BASE_PATH = '<?= BASE_PATH ?>';</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    $extraJsFiles = is_array($extra_js ?? null) ? $extra_js : (($extra_js ?? '') !== '' ? [$extra_js] : []);
    foreach ($extraJsFiles as $jsFile):
    ?>
    <script src="<?= static_url('js/' . $jsFile) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
