<?php
/**
 * Печат: разписка за клиента (всички подпоръчки, с цени).
 * @var array $order @var array $items @var PDO $conn
 */
$servicesMap = services_name_map();
$shopName = shop_display_name();
$shopAddress = trim((string)($_ENV['SHOP_ADDRESS'] ?? ''));
$shopPhone = trim((string)($_ENV['SHOP_PHONE'] ?? ''));
$total = array_sum(array_map(static fn ($o) => (float)($o['price'] ?? 0), $items));
$advance = array_sum(array_map(static fn ($o) => (float)($o['advance_payment'] ?? 0), $items));
$allPaid = count(array_filter($items, static fn ($o) => empty($o['paid']))) === 0;
$due = $allPaid ? 0.0 : max(0.0, $total - $advance);
$money = static fn ($v): string => number_format((float)$v, 2, '.', ' ') . ' €';

$itemLines = static function (array $item) use ($servicesMap, $conn): array {
    $lines = [];
    $geo = order_pricing_geometry($item);
    $size = format_cm_number($item['width']) . ' × ' . format_cm_number($item['height']) . ' см';
    if ($geo['frame_width'] > 0 && ($geo['frame_width'] != $geo['picture_width'] || $geo['frame_height'] != $geo['picture_height'])) {
        $size .= ' (рамка ' . format_cm_number($geo['frame_width']) . ' × ' . format_cm_number($geo['frame_height']) . ' см)';
    }
    $lines['Размер'] = $size;
    if ($item['artwork_type'] ?? '') {
        $lines['Произведение'] = artwork_type_label($item['artwork_type']);
    }
    $profile = trim((string)($item['profile'] ?? ''));
    if (!empty($item['profile_ready_frame'])) {
        $profile = 'Готова рамка' . ($profile !== '' ? ' (' . $profile . ')' : '');
    }
    if (trim((string)($item['additional_profiles'] ?? '')) !== '') {
        $profile .= ' + ' . $item['additional_profiles'];
    }
    if ($profile !== '') {
        $lines['Профил'] = $profile;
    }
    $measure = frame_measure_options()[$item['frame_measure'] ?? ''] ?? '';
    $shape = frame_shape_label($item['frame_shape'] ?? '');
    if ($measure !== '' || $shape !== '') {
        $lines['Рамка'] = implode('; ', array_filter([$measure, $shape !== '' ? 'фигура: ' . $shape : '']));
    }
    $fieldSides = order_field_margins($item);
    if ($fieldSides !== [0.0, 0.0, 0.0, 0.0]) {
        $lines['Поле'] = format_four_sides_label($fieldSides);
    }
    if (trim((string)($item['glass'] ?? '')) !== '') {
        $lines['Стъкло'] = $item['glass'];
    }
    if (trim((string)($item['back'] ?? '')) !== '') {
        $lines['Гръб'] = $item['back'];
    }
    $layers = [];
    foreach ($geo['layers'] as $layer) {
        $layers[] = ($layer['role'] === 'background' ? 'фон ' : 'паспарту ') . ($layer['name'] !== '' ? '№ ' . $layer['name'] : '(без №)')
            . ($layer['width'] > 0 ? ' — ' . format_cm_number($layer['width']) . ' × ' . format_cm_number($layer['height']) . ' см' : '')
            . ($layer['offsets'] !== [0.0, 0.0, 0.0, 0.0] ? ' (' . format_four_sides_label($layer['offsets']) . ')' : '');
    }
    if ($layers !== []) {
        $lines['Паспарту'] = implode('; ', $layers);
    }
    $hanging = trim((string)($item['hanging'] ?? ''));
    if (trim((string)($item['hanger'] ?? '')) !== '') {
        $hanging .= ($hanging !== '' ? ' + ' : '') . hanger_display_label($item['hanger']) . ' × ' . order_hanger_count($item) . '/рамка';
    }
    if ($hanging !== '') {
        $lines['Окачване'] = $hanging;
    }
    $extras = format_order_extras_labels($item, $servicesMap);
    if ($extras !== []) {
        $lines['Допълнително'] = implode('; ', $extras);
    }
    if (trim((string)($item['description'] ?? '')) !== '') {
        $lines['Описание'] = trim($item['description']);
    }

    return $lines;
};
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <title>Разписка — поръчка № <?= (int)$order['order_number'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #111; margin: 0; padding: 18px; font-size: 13px; }
        .sheet { max-width: 760px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        header img { height: 46px; margin-right: 10px; vertical-align: middle; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 15px; margin: 14px 0 6px; }
        .muted { color: #555; }
        .big { font-size: 26px; font-weight: 700; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 14px; margin-bottom: 10px; }
        .meta div span { display: block; color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 5px 7px; vertical-align: top; text-align: left; }
        th { background: #f2f2f2; font-size: 12px; }
        .num { text-align: right; white-space: nowrap; }
        .spec { margin: 0; padding: 0; list-style: none; }
        .spec li { margin: 1px 0; }
        .spec b { display: inline-block; min-width: 95px; font-weight: 600; }
        .totals { margin-left: auto; width: 280px; margin-top: 8px; }
        .totals td { border: none; padding: 2px 4px; }
        .totals tr.due td { font-size: 16px; font-weight: 700; border-top: 2px solid #111; }
        .sign { display: flex; justify-content: space-between; margin-top: 34px; }
        .sign div { width: 45%; border-top: 1px solid #111; padding-top: 4px; font-size: 11px; color: #555; }
        .toolbar { text-align: center; margin-bottom: 12px; }
        .toolbar button { font-size: 14px; padding: 6px 16px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar">
        <button type="button" onclick="window.print()">Печат</button>
    </div>
    <header>
        <div>
            <img src="<?= static_url('Logo.jpg') ?>" alt="">
            <strong style="font-size: 18px; vertical-align: middle;"><?= e($shopName) ?></strong>
            <?php if ($shopAddress !== '' || $shopPhone !== ''): ?>
            <div class="muted" style="margin-top: 4px;"><?= e(implode(' · ', array_filter([$shopAddress, $shopPhone]))) ?></div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h1>Разписка</h1>
            <div class="big">№ <?= (int)$order['order_number'] ?></div>
        </div>
    </header>

    <div class="meta">
        <div><span>Клиент</span><?= e($order['customer_name'] ?? '') ?></div>
        <div><span>Телефон</span><?= e($order['customer_phone'] ?? '') ?></div>
        <div><span>Приета на</span><?= e(format_date_ddmmyyyy(order_accepted_date_iso($order))) ?></div>
        <div><span>За получаване</span><strong><?= e(format_date_ddmmyyyy($order['date'] ?? '')) ?></strong></div>
        <?php if ((int)($order['urgent'] ?? 0) > 0): ?>
        <div><span>Спешност</span><strong><?= (int)$order['urgent'] === 1 ? 'за същия ден' : 'за следващия ден' ?></strong></div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr><th style="width: 46px;">№</th><th>Описание</th><th class="num" style="width: 50px;">Бр.</th><th class="num" style="width: 96px;">Цена</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e(order_number_label($item)) ?></td>
                <td>
                    <ul class="spec">
                        <?php foreach ($itemLines($item) as $label => $value): ?>
                        <li><b><?= e($label) ?>:</b> <?= e($value) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <td class="num"><?= (int)($item['frame_count'] ?? 1) ?></td>
                <td class="num"><?= $item['price'] !== null && $item['price'] !== '' ? $money($item['price']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Обща сума</td><td class="num"><?= $money($total) ?></td></tr>
        <?php if ($advance > 0): ?><tr><td>Аванс</td><td class="num">− <?= $money($advance) ?></td></tr><?php endif; ?>
        <tr class="due"><td><?= $allPaid ? 'Платено' : 'Остава за плащане' ?></td><td class="num"><?= $allPaid ? $money($total) : $money($due) ?></td></tr>
    </table>
    <div class="sign">
        <div>Приел поръчката</div>
        <div>Клиент</div>
    </div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
