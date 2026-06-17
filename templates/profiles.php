<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Профили</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProfileModal">
            <i class="fas fa-plus"></i> Нов профил
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" id="searchInput" class="form-control" placeholder="Търси по име...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Име</th>
                    <th>Ширина (см)</th>
                    <th>Тип</th>
                    <th>Цена (€/л.м.)</th>
                    <th>Наличност (л.м.)</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $profile): ?>
                <tr data-profile-id="<?= (int)$profile['id'] ?>" data-profile-type="<?= e($profile['profile_type'] ?? 'wood') ?>">
                    <td class="name"><?= e($profile['name']) ?></td>
                    <td class="width-cm"><?= e($profile['width_cm'] ?? '—') ?></td>
                    <td class="profile-type"><?= e(['wood' => 'Дърво', 'metal' => 'Метал', 'client_material' => 'Клиент'][$profile['profile_type'] ?? 'wood'] ?? 'Дърво') ?></td>
                    <td class="price"><?= e($profile['price']) ?></td>
                    <td>
                        <span class="badge <?= $profile['stock'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                            <?= (int)$profile['stock'] ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editProfile('<?= (int)$profile['id'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('<?= (int)$profile['id'] ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нов профил</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProfileForm" action="<?= url_for('add_profile') ?>" method="post">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип профил</label>
                        <select class="form-select" name="profile_type">
                            <option value="wood">Дърво/пластмаса</option>
                            <option value="metal">Метален</option>
                            <option value="client_material">Материал на клиент</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ширина на профила (см)</label>
                        <input type="number" class="form-control" name="width_cm" step="0.1" min="0">
                        <div class="form-text">За труд и широк профил (≥10 см, страна &gt;2.5 м → 3 м)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€/л.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" required>
                        <div class="form-text">При „материал на клиент" може да е 0</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (л.м.)</label>
                        <input type="number" class="form-control" name="stock" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="addProfileForm" class="btn btn-primary">Запази</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактиране на профил</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm" method="post">
                    <div class="mb-3">
                        <label class="form-label">Име</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип профил</label>
                        <select class="form-select" name="profile_type">
                            <option value="wood">Дърво/пластмаса</option>
                            <option value="metal">Метален</option>
                            <option value="client_material">Материал на клиент</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ширина на профила (см)</label>
                        <input type="number" class="form-control" name="width_cm" step="0.1" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цена (€/л.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (л.м.)</label>
                        <input type="number" class="form-control" name="stock" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="editProfileForm" class="btn btn-primary">Запази</button>
            </div>
        </div>
    </div>
</div>
