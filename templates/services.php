<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<?php
$edit_mode = !empty($edit_mode);
$categoryLabels = [
    'frame_labor' => 'Изработка на рамки',
    'passepartout_labor' => 'Изработка на паспарту',
    'discounts' => 'Отстъпки и доплащания',
    'general' => 'Общи',
    'glass_labor' => 'Стъкло',
    'assembly' => 'Монтаж',
    'stretching' => 'Опъване',
    'mounting' => 'Каширане',
    'hardware' => 'Дребно',
    'frame_labor_svc' => 'Изработка рамка',
];
$unitLabels = ['piece' => 'бр.', 'sqm' => 'кв.м.', 'lm' => 'л.м.', 'km' => 'км'];
$laborCategories = array_filter(
    $pricing_settings,
    static fn(string $category): bool => $category !== 'passepartout_material',
    ARRAY_FILTER_USE_KEY
);
$price_list_rules = $price_list_rules ?? [];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="mb-1">Услуги и труд</h1>
        <p class="text-muted mb-0">
            Тарифи от ценоразписа. Автоматичните се прилагат при поръчка; допълнителните се избират в секцията „Допълнителни опции".
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($edit_mode): ?>
        <a href="<?= url_for('services') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-times"></i> Отказ
        </a>
        <button type="submit" form="servicesPricingForm" class="btn btn-success">
            <i class="fas fa-save"></i> Запази
        </button>
        <?php else: ?>
        <a href="<?= url_for('services') ?>?edit=1" class="btn btn-primary">
            <i class="fas fa-edit"></i> Редактирай
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit_mode): ?>
<form id="servicesPricingForm" method="post" action="<?= url_for('save_services') ?>">
<?php endif; ?>

<?php foreach ($laborCategories as $category => $settings): ?>
<div class="card mb-4">
    <div class="card-header">
        <strong><?= e($categoryLabels[$category] ?? $category) ?></strong>
        <span class="badge bg-secondary">автоматично</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($settings as $setting): ?>
                <?php
                $settingKey = $setting['setting_key'];
                if (str_starts_with($settingKey, 'pp_kind_')) {
                    continue;
                }
                $unit = pricing_setting_unit_suffix($settingKey);
                ?>
                <tr>
                    <td><?= e($setting['label']) ?></td>
                    <td class="text-end fw-semibold" style="width: 11rem;">
                        <?php if ($edit_mode): ?>
                        <div class="input-group input-group-sm justify-content-end">
                            <input
                                type="number"
                                class="form-control text-end"
                                name="pricing[<?= e($settingKey) ?>]"
                                value="<?= e(number_format((float)$setting['setting_value'], 2, '.', '')) ?>"
                                step="0.01"
                                min="0"
                                required
                            >
                            <span class="input-group-text"><?= e($unit) ?></span>
                        </div>
                        <?php else: ?>
                        <?= number_format((float)$setting['setting_value'], 2) ?> <?= e($unit) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (!empty($passepartout_price_kinds)): ?>
<div class="card mb-4">
    <div class="card-header">
        <strong>Видове паспарту (ценоразпис)</strong>
        <span class="badge bg-secondary"><?= $edit_mode ? 'редактируемо' : 'справочно' ?></span>
    </div>
    <div class="card-body">
        <?php if (!$edit_mode): ?>
        <p class="text-muted small mb-3">
            Всяко паспарту в <strong>Склад → Паспарту</strong> получава № (цвят/артикул) и се задава към един от тези
            <strong>видове цени</strong>. При поръчка цената се определя от фактурирания размер на изрязване, не от €/кв.м.
            Отделни артикули с нестандартни цени се въвеждат с опция <strong>Ръчно</strong>.
        </p>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Вид</th>
                        <th>Лист</th>
                        <th>Описание</th>
                        <th>Цени по размер (€/бр.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passepartout_price_kinds as $kind): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= e($kind['label']) ?></td>
                        <td class="text-nowrap"><?= e($kind['sheet_label']) ?></td>
                        <td><?= e($kind['summary']) ?></td>
                        <td>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($kind['tiers'] as $tierIndex => $tier): ?>
                                <?php $tierNumber = $tierIndex + 1; ?>
                                <li class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <span class="text-muted"><?= e($tier['size_label']) ?>:</span>
                                    <?php if ($edit_mode): ?>
                                    <div class="input-group input-group-sm" style="max-width: 8rem;">
                                        <input
                                            type="number"
                                            class="form-control text-end"
                                            name="pp_kind[<?= e($kind['key']) ?>][<?= (int)$tierNumber ?>]"
                                            value="<?= e(number_format((float)$tier['price'], 2, '.', '')) ?>"
                                            step="0.01"
                                            min="0"
                                            required
                                        >
                                        <span class="input-group-text">€</span>
                                    </div>
                                    <?php else: ?>
                                    <strong><?= number_format((float)$tier['price'], 2) ?> €</strong>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($services)): ?>
<div class="card mb-4">
    <div class="card-header">
        <strong>Допълнителни услуги</strong>
        <span class="badge bg-primary">по избор в поръчка</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Услуга</th>
                    <th>Цена</th>
                    <th>Единица</th>
                    <th>Мин.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                <tr>
                    <td><?= e($service['name']) ?></td>
                    <td style="width: 9rem;">
                        <?php if ($edit_mode): ?>
                        <div class="input-group input-group-sm">
                            <input
                                type="number"
                                class="form-control text-end"
                                name="services[<?= (int)$service['id'] ?>][price]"
                                value="<?= e(number_format((float)$service['price'], 2, '.', '')) ?>"
                                step="0.01"
                                min="0"
                                required
                            >
                            <span class="input-group-text">€</span>
                        </div>
                        <?php else: ?>
                        <?= number_format((float)$service['price'], 2) ?> €
                        <?php endif; ?>
                    </td>
                    <td><?= e($unitLabels[$service['pricing_unit'] ?? 'piece'] ?? $service['pricing_unit']) ?></td>
                    <td style="width: 9rem;">
                        <?php if ($edit_mode): ?>
                        <div class="input-group input-group-sm">
                            <input
                                type="number"
                                class="form-control text-end"
                                name="services[<?= (int)$service['id'] ?>][min_price]"
                                value="<?= e(number_format((float)($service['min_price'] ?? 0), 2, '.', '')) ?>"
                                step="0.01"
                                min="0"
                            >
                            <span class="input-group-text">€</span>
                        </div>
                        <?php else: ?>
                        <?= (float)($service['min_price'] ?? 0) > 0 ? number_format((float)$service['min_price'], 2) . ' €' : '—' ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card border-info">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Правила от ценоразписа</strong>
        <?php if ($edit_mode): ?>
        <span class="badge bg-secondary">редактируемо</span>
        <?php endif; ?>
    </div>
    <div class="card-body small mb-0">
        <?php if ($edit_mode): ?>
        <p class="text-muted mb-3">Справочен текст — не променя автоматичните изчисления. Редактирай го, ако ценоразписът е обновен.</p>
        <?php foreach ($price_list_rules as $index => $rule): ?>
        <div class="mb-3">
            <label class="form-label mb-1" for="price-list-rule-<?= e($rule['key']) ?>">Правило <?= (int)$index + 1 ?></label>
            <textarea
                class="form-control form-control-sm"
                id="price-list-rule-<?= e($rule['key']) ?>"
                name="price_list_rules[<?= e($rule['key']) ?>]"
                rows="2"
                required
            ><?= e($rule['content']) ?></textarea>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <ul class="mb-0">
            <?php foreach ($price_list_rules as $rule): ?>
            <li><?= e($rule['content']) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit_mode): ?>
</form>
<?php endif; ?>
