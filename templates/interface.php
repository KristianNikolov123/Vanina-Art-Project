<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Поръчки</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
            <i class="fas fa-plus"></i> Нова поръчка
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" id="searchInput" class="form-control" placeholder="Търсене...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="col-md-6">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" data-filter="all">Всички</button>
                <button type="button" class="btn btn-outline-success" data-filter="paid">Платени</button>
                <button type="button" class="btn btn-outline-danger" data-filter="unpaid">Неплатени</button>
                <button type="button" class="btn btn-outline-info" data-filter="collected">Получени</button>
                <button type="button" class="btn btn-outline-warning" data-filter="uncollected">Неполучени</button>
            </div>
        </div>
    </div>

    <script>
        window.PASSEPARTOUT_MAP = <?= json_encode(array_column($passepartouts, 'id', 'name'), JSON_UNESCAPED_UNICODE) ?>;
    </script>

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
                            $orderData = htmlspecialchars(json_encode([
                                'date' => $order['date'] ?? '',
                                'width' => $order['width'],
                                'height' => $order['height'],
                                'profile' => $order['profile'] ?? '',
                                'additional_profiles' => $order['additional_profiles'] ?? '',
                                'frame_count' => $order['frame_count'] ?? 1,
                                'glass' => $order['glass'] ?? '',
                                'back' => $order['back'] ?? '',
                                'passepartout' => $order['passepartout'] ?? '',
                                'passepartout_bill_width' => $order['passepartout_bill_width'] ?? null,
                                'passepartout_bill_height' => $order['passepartout_bill_height'] ?? null,
                                'hanging' => $order['hanging'] ?? '',
                                'customer_name' => $order['customer_name'] ?? '',
                                'price' => $order['price'],
                                'advance_payment' => $order['advance_payment'],
                                'discount' => $order['discount'],
                                'description' => $order['description'] ?? '',
                                'paid' => (bool)$order['paid'],
                                'collected' => (bool)$order['collected'],
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="order-row" data-order-id="<?= (int)$order['id'] ?>" data-order="<?= $orderData ?>">
                            <td><?= (int)$order['order_number'] ?><?= $order['sub_order_number'] > 0 ? '.' . (int)$order['sub_order_number'] : '' ?></td>
                            <td class="date"><?= format_date_ddmmyyyy($order['date']) ?></td>
                            <td><span class="width"><?= e($order['width']) ?></span>x<span class="height"><?= e($order['height']) ?></span> cm</td>
                            <td class="profile"><?= e($order['profile']) ?></td>
                            <td class="additional-profiles"><?= e($order['additional_profiles']) ?></td>
                            <td class="frame-count"><?= e($order['frame_count']) ?></td>
                            <td class="description">
                                <div class="description-preview" style="cursor: pointer; font-size: 0.9em;"
                                data-description="<?= $descAttr ?>"
                                onclick="showFullDescription(this.dataset.description)">
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
