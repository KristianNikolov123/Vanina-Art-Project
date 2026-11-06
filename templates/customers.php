<?php
/** @var array $customers */
/** @var string $query */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="mb-1">Клиенти</h1>
        <p class="text-muted small mb-0">Създават се автоматично от поръчките по телефон или имейл. Във формата за поръчка започнете да пишете име или телефон — програмата предлага съществуващ клиент.</p>
    </div>
    <form method="get" action="<?= url_for('customers') ?>" class="d-flex gap-2">
        <input type="search" class="form-control form-control-sm" name="q" value="<?= e($query) ?>" placeholder="Име, телефон или имейл" autofocus>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
</div>

<?php if ($customers === []): ?>
<div class="alert alert-light border"><?= $query !== '' ? 'Няма клиенти за това търсене.' : 'Все още няма клиенти.' ?></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Клиент</th>
                <th>Телефон</th>
                <th>Имейл</th>
                <th class="text-end">Поръчки</th>
                <th class="text-end">Оборот</th>
                <th class="text-end">Дължи</th>
                <th>Последна поръчка</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $c): ?>
            <tr style="cursor: pointer;" onclick="window.location='<?= BASE_PATH ?>/customers/<?= (int)$c['id'] ?>'">
                <td><a href="<?= BASE_PATH ?>/customers/<?= (int)$c['id'] ?>" class="text-decoration-none fw-semibold"><?= e($c['name']) ?></a></td>
                <td class="text-nowrap"><?= e($c['phone'] ?? '') ?></td>
                <td><?= e($c['email'] ?? '') ?></td>
                <td class="text-end"><?= (int)$c['orders_count'] ?></td>
                <td class="text-end text-nowrap"><?= number_format((float)$c['total_price'], 2, '.', ' ') ?> €</td>
                <td class="text-end text-nowrap <?= (float)$c['due'] > 0.009 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= (float)$c['due'] > 0.009 ? number_format((float)$c['due'], 2, '.', ' ') . ' €' : '—' ?>
                </td>
                <td class="text-nowrap"><?= e(format_date_ddmmyyyy($c['last_order'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
