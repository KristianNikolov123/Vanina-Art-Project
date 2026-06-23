<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Архив на поръчки</h1>
        <a href="<?= url_for('interface') ?>" class="btn btn-outline-primary">
            <i class="fas fa-list"></i> Към поръчки
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <input type="text" id="archiveSearchInput" class="form-control" placeholder="Търсене в архива...">
        </div>
    </div>

    <script>
        window.ARCHIVE_PAGE = true;
        window.SERVICES_MAP = <?= json_encode(array_column($services ?? [], 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <?php $servicesById = array_column($services ?? [], 'name', 'id'); ?>

    <section class="mb-5">
        <h2 class="h4 mb-3">
            <i class="fas fa-check-circle text-success me-2"></i>
            Завършени поръчки
            <span class="badge bg-secondary"><?= count($completed_orders) ?></span>
        </h2>
        <p class="text-muted small">Поръчки, маркирани като платени и получени — автоматично излизат от списъка „Поръчки“.</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover archive-table">
                <thead class="table-dark">
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Размер</th>
                        <th>Профил</th>
                        <th>Доп. профил</th>
                        <th>Клиент</th>
                        <th>Цена</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completed_orders)): ?>
                    <tr><td colspan="8" class="text-muted text-center py-4">Няма завършени поръчки</td></tr>
                    <?php else: ?>
                    <?php foreach ($completed_orders as $order): ?>
                    <?php $showRestore = false; include __DIR__ . '/partials/order_archive_row.php'; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="h4 mb-3">
            <i class="fas fa-trash-alt text-danger me-2"></i>
            Изтрити поръчки
            <span class="badge bg-secondary"><?= count($deleted_orders) ?></span>
        </h2>
        <p class="text-muted small">Изтрити поръчки могат да бъдат възстановени обратно в „Поръчки“ (ако не са завършени).</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover archive-table">
                <thead class="table-dark">
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Размер</th>
                        <th>Профил</th>
                        <th>Доп. профил</th>
                        <th>Клиент</th>
                        <th>Цена</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_orders)): ?>
                    <tr><td colspan="9" class="text-muted text-center py-4">Няма изтрити поръчки</td></tr>
                    <?php else: ?>
                    <?php foreach ($deleted_orders as $order): ?>
                    <?php $showRestore = true; include __DIR__ . '/partials/order_archive_row.php'; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include __DIR__ . '/partials/order_view_modals.php'; ?>
