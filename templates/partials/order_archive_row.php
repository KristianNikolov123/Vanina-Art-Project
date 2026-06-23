<?php
/** @var array $order */
/** @var array $servicesById */
/** @var bool $showRestore */
$showRestore = $showRestore ?? false;
$orderData = encode_order_dataset($order);
$hasExtras = order_has_extras($order);
$extrasLabels = $hasExtras ? format_order_extras_labels($order, $servicesById) : [];
$extrasTitle = $hasExtras ? 'Допълнителни опции: ' . implode(', ', $extrasLabels) : '';
?>
<tr class="order-row<?= $hasExtras ? ' order-row--extras' : '' ?>"
    data-order-id="<?= (int)$order['id'] ?>"
    data-order="<?= $orderData ?>"
    <?= $extrasTitle !== '' ? 'title="' . e($extrasTitle) . '"' : '' ?>>
    <td><?= (int)$order['order_number'] ?><?= $order['sub_order_number'] > 0 ? '.' . (int)$order['sub_order_number'] : '' ?></td>
    <td class="date"><?= format_date_ddmmyyyy($order['date']) ?></td>
    <td><span class="width"><?= e($order['width']) ?></span>x<span class="height"><?= e($order['height']) ?></span> cm</td>
    <td class="profile"><?= e($order['profile']) ?></td>
    <td class="additional-profiles"><?= e($order['additional_profiles']) ?></td>
    <td class="customer-name"><?= e($order['customer_name']) ?></td>
    <td class="price"><?= e($order['price']) ?></td>
    <td>
        <span class="badge <?= $order['paid'] ? 'bg-success' : 'bg-danger' ?>"><?= $order['paid'] ? 'Платена' : 'Неплатена' ?></span>
        <span class="badge <?= $order['collected'] ? 'bg-info' : 'bg-warning' ?>"><?= $order['collected'] ? 'Получена' : 'Неполучена' ?></span>
    </td>
    <?php if ($showRestore): ?>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-success" onclick="event.stopPropagation(); restoreArchivedOrder(<?= (int)$order['id'] ?>)" title="Възстанови">
            <i class="fas fa-undo"></i> Възстанови
        </button>
    </td>
    <?php endif; ?>
</tr>
