<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Окачване</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHangingModal">
        <i class="fas fa-plus"></i> Добави опция
    </button>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <input type="text" id="searchInput" class="form-control" placeholder="Търси по име...">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Име</th>
                <th>Цена</th>
                <th>Единица</th>
                <th>Мин. (€/бр.)</th>
                <th>Наличност</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $unitLabels = ['piece' => 'бр.', 'lm' => 'л.м.', 'sqm' => 'кв.м.'];
            foreach ($hanging_options as $option):
            ?>
            <?php $displayStock = (float)($option['display_stock'] ?? $option['stock'] ?? 0); ?>
            <tr data-hanging-id="<?= (int)$option['id'] ?>"
                data-unit="<?= e($option['pricing_unit'] ?? 'piece') ?>"
                data-stock="<?= e($displayStock) ?>"
                data-shared-pool="<?= hanging_shares_hanger_pool($option['name'] ?? '') ? '1' : '0' ?>">
                <td class="name"><?= e($option['name']) ?></td>
                <td class="price"><?= e($option['price']) ?></td>
                <td class="unit"><?= e($unitLabels[$option['pricing_unit'] ?? 'piece'] ?? $option['pricing_unit']) ?></td>
                <td class="min-price"><?= e($option['min_price'] ?? 0) ?></td>
                <td>
                    <span class="badge stock-badge <?= $displayStock < 5 ? 'bg-danger' : 'bg-success' ?>">
                        <?= e((int)$displayStock) ?>
                    </span>
                    <?php if (($option['name'] ?? '') === 'Две закачалки'): ?>
                    <span class="text-muted small d-block">−2 бр. при поръчка</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editHanging('<?= (int)$option['id'] ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('<?= (int)$option['id'] ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="addHangingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добави окачване</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url_for('add_hanging') ?>" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€)</label>
                        <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Единица</label>
                        <select class="form-select" name="pricing_unit">
                            <option value="piece">на брой</option>
                            <option value="lm">на л.м.</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Мин. цена (€/бр.)</label>
                        <input type="number" class="form-control" name="min_price" step="0.01" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност</label>
                        <input type="number" class="form-control" name="stock" min="0" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="submit" class="btn btn-primary">Добави</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editHangingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирай окачване</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editHangingForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€)</label>
                        <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Единица</label>
                        <select class="form-select" name="pricing_unit">
                            <option value="piece">на брой</option>
                            <option value="lm">на л.м.</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Мин. цена (€/бр.)</label>
                        <input type="number" class="form-control" name="min_price" step="0.01" min="0">
                    </div>
                    <div class="mb-3" id="editHangingStockGroup">
                        <label class="form-label">Наличност</label>
                        <input type="number" class="form-control" name="stock" id="editHangingStock" min="0">
                        <div class="form-text" id="editHangingStockHint" style="display: none;">
                            Обща наличност закачалки (споделена с „Закачалка" и „Две закачалки").
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="submit" class="btn btn-primary">Запази</button>
                </div>
            </form>
        </div>
    </div>
</div>
