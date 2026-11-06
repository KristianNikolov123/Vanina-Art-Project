<?php
/** @var array $order */
/** @var array $servicesById */
$desc = trim($order['description'] ?? '');
$descAttr = htmlspecialchars(str_replace(["\r", "\n"], ['\\r', '\\n'], $desc), ENT_QUOTES, 'UTF-8');
$orderData = encode_order_dataset($order);
$hasExtras = order_has_extras($order);
$extrasLabels = format_order_extras_labels($order, $servicesById);
$extrasTitle = $hasExtras ? 'Допълнителни опции: ' . implode(', ', $extrasLabels) : '';
$rowRevision = order_row_content_revision($order);
$hasAdditionalServices = order_has_additional_services($order);
$hasDescription = $desc !== '';
$stockShortage = !empty($order['stock_override']) ? format_stock_shortage_summary(decode_json_array($order['stock_shortage'] ?? null)) : '';
?>
<tr class="order-row<?= $hasExtras ? ' order-row--extras' : '' ?><?= !empty($order['ready']) ? ' order-row--ready' : '' ?>"
    data-order-id="<?= (int)$order['id'] ?>"
    data-row-revision="<?= e($rowRevision) ?>"
    data-order-date="<?= e($order['date'] ?? '') ?>"
    data-order-accepted-date="<?= e(order_accepted_date_iso($order)) ?>"
    data-order="<?= $orderData ?>"
    <?= $extrasTitle !== '' ? 'title="' . e($extrasTitle) . '"' : '' ?>>
    <td>
        <?= (int)$order['order_number'] ?><?= $order['sub_order_number'] > 0 ? '.' . (int)$order['sub_order_number'] : '' ?>
        <?php if ($stockShortage !== ''): ?>
        <span class="badge text-bg-warning d-block mt-1 stock-flag" title="Недостатъчна наличност (потвърдено): <?= e($stockShortage) ?>"><i class="fas fa-exclamation-triangle"></i> склад</span>
        <?php endif; ?>
    </td>
    <td class="order-dates">
        <span class="date-accepted"><?= format_date_ddmmyyyy(order_accepted_date_iso($order)) ?></span>
        <?php
        $pickupFormatted = format_date_ddmmyyyy($order['date'] ?? '');
        if ($pickupFormatted !== ''):
        ?>
        <small class="text-muted d-block date-pickup">получ. <?= e($pickupFormatted) ?></small>
        <?php endif; ?>
    </td>
    <td><span class="width"><?= e($order['width']) ?></span>x<span class="height"><?= e($order['height']) ?></span> см</td>
    <td class="profile"><?= e($order['profile']) ?></td>
    <td class="additional-profiles"><?= e($order['additional_profiles']) ?></td>
    <td class="frame-count"><?= e($order['frame_count']) ?></td>
    <td class="has-services text-center">
        <span class="yes-no <?= $hasAdditionalServices ? 'yes-no--yes' : 'yes-no--no' ?>"
            <?= $hasAdditionalServices ? 'title="' . e(implode(', ', $extrasLabels)) . '"' : '' ?>><?= $hasAdditionalServices ? 'ДА' : 'НЕ' ?></span>
    </td>
    <td class="description">
        <span class="yes-no <?= $hasDescription ? 'yes-no--yes' : 'yes-no--no' ?>"><?= $hasDescription ? 'ДА' : 'НЕ' ?></span>
        <div class="description-preview" style="cursor: pointer; font-size: 0.9em;"
        data-description="<?= $descAttr ?>"
        onclick="event.stopPropagation(); showFullDescription(this.dataset.description)">
        <?php if ($desc): ?>
            <?= e(mb_substr($desc, 0, 5)) ?><?= mb_strlen($desc) > 5 ? '...' : '' ?>
        <?php endif; ?>
        </div>
    </td>
    <td class="glass"><?= short_glass($order['glass']) ?></td>
    <td class="back"><?= short_back($order['back']) ?></td>
    <td class="passepartout">
        <?php
        $ppUse = normalize_passepartout_use($order['passepartout_use'] ?? '');
        $ppBgName = trim($order['passepartout_background'] ?? '');
        if ($ppUse === 'both' && $ppBgName !== ''):
            $ppCombined = trim($order['passepartout'] ?? '');
            $ppPrimaryLine = $ppCombined;
            if (str_contains($ppCombined, ' + ')) {
                $ppPrimaryLine = explode(' + ', $ppCombined, 2)[0];
            }
        ?>
        <?= e($ppPrimaryLine) ?>
        <small class="text-muted d-block"><?= e($ppBgName) ?> (фон)</small>
        <?php else: ?>
        <?= e($order['passepartout']) ?>
        <?php endif; ?>
        <?php if (!empty($order['passepartout_bill_width']) && !empty($order['passepartout_bill_height'])): ?>
            <small class="text-muted d-block">фактурирано: <?= (int)$order['passepartout_bill_width'] ?>×<?= (int)$order['passepartout_bill_height'] ?> см</small>
        <?php endif; ?>
    </td>
    <td class="hanging"><?= short_hanging($order['hanging']) ?></td>
    <td class="price"><?= e($order['price']) ?></td>
    <td class="advance-payment"><?= e($order['advance_payment']) ?></td>
    <td class="discount"><?= e(format_order_discount_display($order['discount'])) ?></td>
    <td class="customer-name">
        <?= e($order['customer_name']) ?>
        <?php if (!empty(trim($order['customer_phone'] ?? ''))): ?>
            <small class="text-muted d-block customer-phone"><?= e($order['customer_phone']) ?></small>
        <?php endif; ?>
    </td>
    <td class="order-status-cell">
        <?php
        $isReady = !empty($order['ready']) || !empty($order['collected']);
        $statusButtons = [
            'ready' => [$isReady, 'Готова', 'В работа', 'status-btn--ready'],
            'paid' => [!empty($order['paid']), 'Платена', 'Неплатена', 'status-btn--paid'],
            'collected' => [!empty($order['collected']), 'Получена', 'Неполучена', 'status-btn--collected'],
        ];
        ?>
        <div class="status-btns">
            <?php foreach ($statusButtons as $field => [$on, $onLabel, $offLabel, $cls]): ?>
            <button type="button" class="status-btn <?= e($cls) ?> <?= $on ? 'is-on' : 'is-off' ?>"
                data-status-field="<?= e($field) ?>" data-status-value="<?= $on ? '1' : '0' ?>"
                title="Клик: <?= e($on ? 'махни „' . $onLabel . '“' : 'маркирай като „' . $onLabel . '“') ?>">
                <i class="fas <?= $on ? 'fa-check' : 'fa-circle' ?>"></i> <?= e($on ? $onLabel : $offLabel) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </td>
</tr>
