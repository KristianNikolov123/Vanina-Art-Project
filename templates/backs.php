<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Гръбове</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBackModal">
        <i class="fas fa-plus"></i> Добави гръб
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
                <th>Цена (€/кв.м.)</th>
                <th>Мин. цена (€/бр.)</th>
                <th>Наличност (кв.м.)</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($backs as $back): ?>
            <tr data-back-id="<?= (int)$back['id'] ?>">
                <td class="name"><?= e($back['name']) ?></td>
                <td class="price"><?= e($back['price']) ?></td>
                <td class="min-price"><?= e($back['min_price'] ?? 0) ?></td>
                <td>
                    <span class="badge <?= ($back['stock'] ?? 0) < 10 ? 'bg-danger' : 'bg-success' ?>">
                        <?= e($back['stock']) ?>
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editBack('<?= (int)$back['id'] ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('<?= (int)$back['id'] ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="addBackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добави гръб</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url_for('add_back') ?>" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€/кв.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Мин. цена (€/бр.)</label>
                        <input type="number" class="form-control" name="min_price" step="0.01" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (кв.м.)</label>
                        <input type="number" class="form-control" name="stock" step="0.01" min="0" value="0">
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

<div class="modal fade" id="editBackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирай гръб</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBackForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€/кв.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Мин. цена (€/бр.)</label>
                        <input type="number" class="form-control" name="min_price" step="0.01" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (кв.м.)</label>
                        <input type="number" class="form-control" name="stock" step="0.01" min="0">
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
