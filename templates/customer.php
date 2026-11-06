<?php
/** @var array $customer */
/** @var array $orders */
$total = array_sum(array_map(static fn ($o) => (float)($o['price'] ?? 0), $orders));
$due = array_sum(array_map(static fn ($o) => order_amount_due($o), $orders));
?>
<div class="mb-3">
    <a href="<?= url_for('customers') ?>" class="small text-decoration-none"><i class="fas fa-arrow-left"></i> Всички клиенти</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Данни за клиента</strong></div>
            <div class="card-body">
                <form method="post" action="<?= BASE_PATH ?>/customers/<?= (int)$customer['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small mb-0">Име</label>
                        <input type="text" class="form-control" name="name" value="<?= e($customer['name']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-0">Телефон</label>
                        <input type="tel" class="form-control" name="phone" value="<?= e($customer['phone'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-0">Имейл</label>
                        <input type="email" class="form-control" name="email" value="<?= e($customer['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-0">Бележки</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="напр. художник, отстъпка 10%"><?= e($customer['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Запази</button>
                </form>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body small">
                <div>Поръчки: <strong><?= count($orders) ?></strong></div>
                <div>Оборот: <strong><?= number_format($total, 2, '.', ' ') ?> €</strong></div>
                <div>Дължи: <strong class="<?= $due > 0.009 ? 'text-danger' : '' ?>"><?= number_format($due, 2, '.', ' ') ?> €</strong></div>
                <div class="text-muted mt-1">Клиент от <?= e(date('d.m.Y', strtotime((string)$customer['created_at']))) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <h1 class="h3 mb-3"><?= e($customer['name']) ?></h1>
        <?php if ($orders === []): ?>
        <div class="alert alert-light border">Няма поръчки.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>№</th>
                        <th>Приета</th>
                        <th>Получаване</th>
                        <th>Размер</th>
                        <th>Профил</th>
                        <th class="text-end">Цена</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e(order_number_label($order)) ?></td>
                        <td class="text-nowrap"><?= e(format_date_ddmmyyyy(order_accepted_date_iso($order))) ?></td>
                        <td class="text-nowrap"><?= e(format_date_ddmmyyyy($order['date'] ?? '')) ?></td>
                        <td class="text-nowrap"><?= e((string)$order['width']) ?>×<?= e((string)$order['height']) ?></td>
                        <td><?= e($order['profile'] ?? '') ?></td>
                        <td class="text-end text-nowrap"><?= $order['price'] !== null ? number_format((float)$order['price'], 2, '.', ' ') . ' €' : '—' ?></td>
                        <td class="text-nowrap small">
                            <?php if (!empty($order['ready'])): ?><span class="badge text-bg-warning">Готова</span><?php endif; ?>
                            <span class="badge <?= $order['paid'] ? 'bg-success' : 'bg-danger' ?>"><?= $order['paid'] ? 'Платена' : 'Неплатена' ?></span>
                            <span class="badge <?= $order['collected'] ? 'bg-info' : 'bg-secondary' ?>"><?= $order['collected'] ? 'Получена' : 'Неполучена' ?></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary py-0" target="_blank" rel="noopener" href="<?= BASE_PATH ?>/order_print/<?= (int)$order['id'] ?>" title="Разписка"><i class="fas fa-receipt"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0" data-notify-order="<?= (int)$order['id'] ?>" title="Уведоми"><i class="fas fa-paper-plane"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/notify_modal.php'; ?>
<script>
window.REMINDER_ORDERS = <?= json_encode(array_column(array_map(static fn ($o) => [
    'id' => (int)$o['id'],
    'order_number' => (int)$o['order_number'],
    'sub_order_number' => (int)$o['sub_order_number'],
], $orders), null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
</script>
