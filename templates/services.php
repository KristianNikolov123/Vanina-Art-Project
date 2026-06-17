<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<h1 class="mb-4">Услуги и труд</h1>
<p class="text-muted mb-4">
    Тарифи от ценоразписа. Автоматичните се прилагат при поръчка; допълнителните се избират в секцията „Допълнителни опции".
</p>

<?php
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
?>

<?php foreach ($pricing_settings as $category => $settings): ?>
<div class="card mb-4">
    <div class="card-header"><strong><?= e($categoryLabels[$category] ?? $category) ?></strong> <span class="badge bg-secondary">автоматично</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($settings as $setting): ?>
                <tr>
                    <td><?= e($setting['label']) ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float)$setting['setting_value'], 2) ?> <?= str_contains($setting['setting_key'], 'percent') ? '%' : '€' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (!empty($services)): ?>
<div class="card mb-4">
    <div class="card-header"><strong>Допълнителни услуги</strong> <span class="badge bg-primary">по избор в поръчка</span></div>
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
                    <td><?= number_format((float)$service['price'], 2) ?> €</td>
                    <td><?= e($unitLabels[$service['pricing_unit'] ?? 'piece'] ?? $service['pricing_unit']) ?></td>
                    <td><?= (float)($service['min_price'] ?? 0) > 0 ? number_format((float)$service['min_price'], 2) . ' €' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card border-info">
    <div class="card-body small mb-0">
        <h6 class="card-title">Правила от ценоразписа</h6>
        <ul class="mb-0">
            <li><strong>Широк профил</strong> (≥10 см) — страна над 2.5 м се фактурира като 3 м</li>
            <li><strong>Метален профил</strong> — 5.07 €/бр. труд; <strong>материал на клиент</strong> — 15.27 €/бр.</li>
            <li><strong>Паспарту</strong> — всеки допълнителен отвор +1.22 €; сложно рязане +50% (мин. 6.10 €/бр.)</li>
            <li><strong>Отстъпки</strong> — автоматично −5% (над 255 €) / −10% (над 510 €); ученик −10%</li>
            <li><strong>Спешна</strong> — +50% върху сумата след отстъпките</li>
        </ul>
    </div>
</div>
