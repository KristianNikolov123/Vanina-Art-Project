<?php
/** @var array $order */
/** @var array $servicesById */
$desc = trim($order['description'] ?? '');
$descAttr = htmlspecialchars(str_replace(["\r", "\n"], ['\\r', '\\n'], $desc), ENT_QUOTES, 'UTF-8');
$orderData = encode_order_dataset($order);
$hasExtras = order_has_extras($order);
$extrasLabels = $hasExtras ? format_order_extras_labels($order, $servicesById) : [];
$extrasTitle = $hasExtras ? 'Допълнителни опции: ' . implode(', ', $extrasLabels) : '';
$rowRevision = order_row_content_revision($order);
?>
<tr class="order-row<?= $hasExtras ? ' order-row--extras' : '' ?>"
    data-order-id="<?= (int)$order['id'] ?>"
    data-row-revision="<?= e($rowRevision) ?>"
    data-order-date="<?= e($order['date'] ?? '') ?>"
    data-order-accepted-date="<?= e(order_accepted_date_iso($order)) ?>"
    data-order="<?= $orderData ?>"
    <?= $extrasTitle !== '' ? 'title="' . e($extrasTitle) . '"' : '' ?>>
    <td><?= (int)$order['order_number'] ?><?= $order['sub_order_number'] > 0 ? '.' . (int)$order['sub_order_number'] : '' ?></td>
    <td class="order-dates">
        <span class="date-accepted"><?= format_date_ddmmyyyy(order_accepted_date_iso($order)) ?></span>
        <?php
        $pickupFormatted = format_date_ddmmyyyy($order['date'] ?? '');
        if ($pickupFormatted !== ''):
        ?>
        <small class="text-muted d-block date-pickup">получ. <?= e($pickupFormatted) ?></small>
        <?php endif; ?>
    </td>
    <td><span class="width"><?= e($order['width']) ?></span>x<span class="height"><?= e($order['height']) ?></span> cm</td>
    <td class="profile"><?= e($order['profile']) ?></td>
    <td class="additional-profiles"><?= e($order['additional_profiles']) ?></td>
    <td class="frame-count"><?= e($order['frame_count']) ?></td>
    <td class="description">
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
            <small class="text-muted d-block">фактурирано: <?= (int)$order['passepartout_bill_width'] ?>×<?= (int)$order['passepartout_bill_height'] ?> cm</small>
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
    <td>
        <span class="badge <?= $order['paid'] ? 'bg-success' : 'bg-danger' ?>"><?= $order['paid'] ? 'Платена' : 'Неплатена' ?></span>
        <span class="badge <?= $order['collected'] ? 'bg-info' : 'bg-warning' ?>"><?= $order['collected'] ? 'Получена' : 'Неполучена' ?></span>
    </td>
</tr>
