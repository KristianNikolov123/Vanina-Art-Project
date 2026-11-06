<?php
/** @var array $unpaid_orders */
/** @var array $uncollected_orders */
$allReminderOrders = array_merge($unpaid_orders, $uncollected_orders);
$lastNotified = fetch_last_notifications(get_db_connection(), array_column($allReminderOrders, 'id'));
$renderTable = static function (array $orders, string $emptyText, string $daysLabel) use ($lastNotified): void {
    if ($orders === []) {
        echo '<div class="p-3 text-muted small">' . e($emptyText) . '</div>';

        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>№</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>Дата за получаване</th>
                    <th><?= e($daysLabel) ?></th>
                    <th class="text-end">Цена</th>
                    <th class="text-end">Аванс</th>
                    <th>Статус</th>
                    <th>Уведомяване</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <?php $days = days_since_iso((string)($order['date'] ?? '')); ?>
                <tr>
                    <td><?= (int)$order['order_number'] ?><?= (int)$order['sub_order_number'] > 0 ? '.' . (int)$order['sub_order_number'] : '' ?></td>
                    <td><?= e($order['customer_name'] ?? '') ?></td>
                    <td><?= e($order['customer_phone'] ?? '') ?></td>
                    <td><?= e(format_date_ddmmyyyy($order['date'] ?? '')) ?></td>
                    <td><?= $days > 0 ? (int)$days . ' дни' : '—' ?></td>
                    <td class="text-end text-nowrap"><?= $order['price'] !== null && $order['price'] !== '' ? number_format((float)$order['price'], 2, '.', ' ') . ' €' : '—' ?></td>
                    <td class="text-end text-nowrap"><?= $order['advance_payment'] !== null && $order['advance_payment'] !== '' ? number_format((float)$order['advance_payment'], 2, '.', ' ') . ' €' : '—' ?></td>
                    <td class="text-nowrap">
                        <span class="badge <?= $order['paid'] ? 'bg-success' : 'bg-danger' ?>"><?= $order['paid'] ? 'Платена' : 'Неплатена' ?></span>
                        <span class="badge <?= $order['collected'] ? 'bg-info' : 'bg-warning' ?>"><?= $order['collected'] ? 'Получена' : 'Неполучена' ?></span>
                    </td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary py-0" data-notify-order="<?= (int)$order['id'] ?>">
                            <i class="fas fa-paper-plane"></i> Уведоми
                        </button>
                        <?php if (!empty($lastNotified[$order['id']])): ?>
                        <div class="small text-muted">последно: <?= e(date('d.m.Y H:i', strtotime($lastNotified[$order['id']]))) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="mb-0">Напомняния</h1>
    <a href="<?= url_for('interface') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-list"></i> Към поръчките
    </a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Предадени, но неплатени</strong>
        <span class="badge text-bg-secondary"><?= count($unpaid_orders) ?></span>
    </div>
    <div class="card-body p-0">
        <?php $renderTable($unpaid_orders, 'Няма предадени неплатени поръчки.', 'От датата'); ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Неполучени — минала е една седмица след определената дата</strong>
        <span class="badge text-bg-secondary"><?= count($uncollected_orders) ?></span>
    </div>
    <div class="card-body p-0">
        <?php $renderTable($uncollected_orders, 'Няма неполучени поръчки с изтекла седмица.', 'Закъснение'); ?>
    </div>
</div>

<?php include __DIR__ . '/partials/notify_modal.php'; ?>
<script>
window.REMINDER_ORDERS = <?= json_encode(array_column(array_map(static fn ($o) => [
    'id' => (int)$o['id'],
    'order_number' => (int)$o['order_number'],
    'sub_order_number' => (int)$o['sub_order_number'],
], $allReminderOrders), null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
</script>
