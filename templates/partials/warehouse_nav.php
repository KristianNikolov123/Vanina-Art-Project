<?php
$current_page = $current_page ?? '';
$tabs = [
    'warehouse' => ['label' => 'Преглед', 'url' => url_for('warehouse'), 'icon' => 'fa-warehouse'],
    'profiles' => ['label' => 'Профили', 'url' => url_for('profiles'), 'icon' => 'fa-border-all'],
    'glasses' => ['label' => 'Стъкла', 'url' => url_for('glasses'), 'icon' => 'fa-window-maximize'],
    'passepartouts' => ['label' => 'Паспарту', 'url' => url_for('passepartouts'), 'icon' => 'fa-image'],
    'backs' => ['label' => 'Гръбове', 'url' => url_for('backs'), 'icon' => 'fa-layer-group'],
    'hanging' => ['label' => 'Окачване', 'url' => url_for('hanging'), 'icon' => 'fa-link'],
    'services' => ['label' => 'Услуги', 'url' => url_for('services'), 'icon' => 'fa-tools'],
];
?>
<nav class="nav nav-pills flex-wrap gap-1 mb-4 warehouse-nav">
    <?php foreach ($tabs as $key => $tab): ?>
    <a class="nav-link <?= $current_page === $key ? 'active' : '' ?>" href="<?= $tab['url'] ?>">
        <i class="fas <?= e($tab['icon']) ?> me-1"></i><?= e($tab['label']) ?>
    </a>
    <?php endforeach; ?>
</nav>
