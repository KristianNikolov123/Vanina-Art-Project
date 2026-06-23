<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Поръчки</h1>
        <div class="d-flex gap-2">
            <a href="<?= url_for('archives') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-archive"></i> Архив
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                <i class="fas fa-plus"></i> Нова поръчка
            </button>
        </div>
    </div>

    <div class="orders-toolbar mb-3">
        <div class="orders-toolbar__left">
            <div class="orders-toolbar__search input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Търсене...">
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
    </script>
    <?php $servicesById = array_column($services ?? [], 'name', 'id'); ?>

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
                        <?php
                            $desc = trim($order['description'] ?? '');
                            $descAttr = htmlspecialchars(str_replace(["\r", "\n"], ['\\r', '\\n'], $desc), ENT_QUOTES, 'UTF-8');
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
                                <?= e($order['passepartout']) ?>
                                <?php if (!empty($order['passepartout_bill_width']) && !empty($order['passepartout_bill_height'])): ?>
                                    <small class="text-muted d-block">фактурирано: <?= (int)$order['passepartout_bill_width'] ?>×<?= (int)$order['passepartout_bill_height'] ?> cm</small>
                                <?php endif; ?>
                            </td>
                            <td class="hanging"><?= short_hanging($order['hanging']) ?></td>
                            <td class="price"><?= e($order['price']) ?></td>
                            <td class="advance-payment"><?= e($order['advance_payment']) ?></td>
                            <td class="discount"><?= e($order['discount']) ?></td>
                            <td class="customer-name"><?= e($order['customer_name']) ?></td>
                            <td>
                                <span class="badge <?= $order['paid'] ? 'bg-success' : 'bg-danger' ?>"><?= $order['paid'] ? 'Платена' : 'Неплатена' ?></span>
                                <span class="badge <?= $order['collected'] ? 'bg-info' : 'bg-warning' ?>"><?= $order['collected'] ? 'Получена' : 'Неполучена' ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="row-float-actions"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/order_modals.php'; ?>
