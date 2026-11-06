<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Поръчки</h1>
        <div class="d-flex gap-2">
            <a href="<?= url_for('archives') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-archive"></i> Архив
            </a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addPaintingReservationModal">
                <i class="fas fa-bookmark"></i> Резервация
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                <i class="fas fa-plus"></i> Нова поръчка
            </button>
        </div>
    </div>

    <div class="orders-toolbar mb-3">
        <div class="orders-toolbar__left">
            <div class="orders-toolbar__search input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Няколко думи: име, №, телефон…">
            </div>
            <label class="orders-toolbar__sort" title="Подредба">
                <i class="fas fa-sort-amount-down"></i>
                <select id="orderSortSelect" class="form-select form-select-sm" aria-label="Подредба">
                    <option value="number-desc">№ ↓</option>
                    <option value="number-asc">№ ↑</option>
                    <option value="date-desc">Дата ↓</option>
                    <option value="date-asc">Дата ↑</option>
                </select>
            </label>
            <div class="orders-toolbar__day" title="Поръчки за избран ден">
                <div class="btn-group btn-group-sm" role="group" aria-label="Филтър по ден">
                    <button type="button" class="btn btn-outline-secondary" id="orderDayPrev" aria-label="Предишен ден">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary orders-toolbar__day-picker" id="orderDayPickerBtn">
                        <i class="fas fa-calendar-day me-1"></i>
                        <span id="orderDayPickerLabel">Всички дни</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="orderDayNext" aria-label="Следващ ден">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="orderDayClear" title="Премахни филтъра по ден" aria-label="Всички дни">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="date" class="visually-hidden" id="orderDayFilter" tabindex="-1" aria-hidden="true">
            </div>
        </div>
        <div class="orders-toolbar__filters">
            <button type="button" class="btn btn-outline-secondary orders-toolbar__filter-all active" data-filter="all">Всички</button>
            <div class="btn-group orders-toolbar__filter-group" role="group" aria-label="Плащане">
                <button type="button" class="btn btn-outline-success" data-filter="paid" data-filter-group="payment">Платени</button>
                <button type="button" class="btn btn-outline-danger" data-filter="unpaid" data-filter-group="payment">Неплатени</button>
            </div>
            <div class="btn-group orders-toolbar__filter-group" role="group" aria-label="Получаване">
                <button type="button" class="btn btn-outline-info" data-filter="collected" data-filter-group="collection">Получени</button>
                <button type="button" class="btn btn-outline-warning" data-filter="uncollected" data-filter-group="collection">Неполучени</button>
            </div>
        </div>
    </div>

    <script>
        window.PASSEPARTOUT_MAP = <?= json_encode(array_column($passepartouts, 'id', 'name'), JSON_UNESCAPED_UNICODE) ?>;
        window.SERVICES_MAP = <?= json_encode(array_column($services ?? [], 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;
        <?php
        $artworkTypeLabels = artwork_type_options();
        unset($artworkTypeLabels['']);
        ?>
        window.ARTWORK_TYPE_LABELS = <?= json_encode($artworkTypeLabels, JSON_UNESCAPED_UNICODE) ?>;
        window.ORDERS_SYNC_REVISION = <?= json_encode($orders_sync_revision ?? '', JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <?php
    $servicesById = array_column($services ?? [], 'name', 'id');
    $workloadChart = $workloadChart ?? ['days' => [], 'max' => 1];
    include __DIR__ . '/partials/orders_workload_chart.php';
    ?>

    <div class="orders-container">
        <div class="table-container">
            <div class="table-responsive" style="position: relative;">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>№</th>
                            <th>Дата</th>
                            <th>Размер</th>
                            <th>Профил</th>
                            <th>Доп. профил</th>
                            <th>Брой рамки</th>
                            <th>Описание</th>
                            <th>Стъкло</th>
                            <th>Гръб</th>
                            <th>Паспарту</th>
                            <th>Окачване</th>
                            <th>Цена</th>
                            <th>Аванс</th>
                            <th>Отстъпка</th>
                            <th>Клиент</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <?php include __DIR__ . '/partials/active_order_row.php'; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="row-float-actions"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/order_modals.php'; ?>
<?php include __DIR__ . '/partials/painting_reservation_modal.php'; ?>
