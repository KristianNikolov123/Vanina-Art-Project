<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Профили</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="bulkEditProfilesBtn" disabled>
                <i class="fas fa-edit"></i> Групово<span id="bulkProfilesCount"></span>
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProfileModal">
                <i class="fas fa-plus"></i> Нов профил
            </button>
        </div>
    </div>

    <div class="bulk-toolbar">
        <div class="bulk-toolbar__search input-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Търси по име...">
            <button class="btn btn-outline-secondary" type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th class="bulk-col">
                        <input type="checkbox" class="form-check-input" id="selectAllProfiles" title="Избери всички">
                    </th>
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
                    <td class="bulk-col">
                        <input type="checkbox" class="form-check-input bulk-row-checkbox" value="<?= (int)$profile['id'] ?>">
                    </td>
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

<div class="modal fade" id="bulkEditProfilesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Групово редактиране на профили</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkEditProfilesForm" action="<?= url_for('bulk_edit_profiles') ?>" method="post">
                    <div class="bulk-ids-container"></div>
                    <p class="text-muted small mb-3">Отметнете полетата, които искате да промените за избраните редове.</p>

                    <div class="bulk-field">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="apply_price" id="bulk_apply_price" data-bulk-toggle="price">
                            <label class="form-check-label" for="bulk_apply_price">Цена</label>
                        </div>
                        <label class="bulk-field__label text-muted" for="bulk_price">€/л.м.</label>
                        <input type="number" class="form-control bulk-field__value" name="price" id="bulk_price" step="0.01" disabled>
                    </div>

                    <div class="bulk-field">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="apply_stock" id="bulk_apply_stock" data-bulk-toggle="stock" data-bulk-toggle-extras="stock_mode">
                            <label class="form-check-label" for="bulk_apply_stock">Наличност</label>
                        </div>
                        <select class="form-select bulk-field__mode" name="stock_mode" disabled>
                            <option value="set">Задай</option>
                            <option value="add">Добави/извади</option>
                        </select>
                        <input type="number" class="form-control bulk-field__value" name="stock" step="1" disabled>
                    </div>

                    <div class="bulk-field">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="apply_width_cm" id="bulk_apply_width" data-bulk-toggle="width_cm">
                            <label class="form-check-label" for="bulk_apply_width">Ширина</label>
                        </div>
                        <label class="bulk-field__label text-muted" for="bulk_width_cm">см</label>
                        <input type="number" class="form-control bulk-field__value" name="width_cm" id="bulk_width_cm" step="0.1" min="0" disabled>
                    </div>

                    <div class="bulk-field">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="apply_profile_type" id="bulk_apply_type" data-bulk-toggle="profile_type">
                            <label class="form-check-label" for="bulk_apply_type">Тип</label>
                        </div>
                        <select class="form-select bulk-field__value" name="profile_type" disabled>
                            <option value="wood">Дърво/пластмаса</option>
                            <option value="metal">Метален</option>
                            <option value="client_material">Материал на клиент</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="bulkEditProfilesForm" class="btn btn-primary">Приложи</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нови профили</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProfileForm" action="<?= url_for('add_profile') ?>" method="post">
                    <div id="profileRowsContainer" class="multi-add-rows">
                        <div class="multi-add-row">
                            <div class="multi-add-row__head">
                                <span class="multi-add-row__label">Профил 1</span>
                                <button type="button" class="btn btn-sm btn-outline-danger multi-add-remove" title="Премахни">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Име</label>
                                    <input type="text" class="form-control" name="profile_rows[0][name]" data-field="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Тип профил</label>
                                    <select class="form-select" name="profile_rows[0][profile_type]" data-field="profile_type">
                                        <option value="wood">Дърво/пластмаса</option>
                                        <option value="metal">Метален</option>
                                        <option value="client_material">Материал на клиент</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ширина (см)</label>
                                    <input type="number" class="form-control" name="profile_rows[0][width_cm]" data-field="width_cm" step="0.1" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Цена (€/л.м.)</label>
                                    <input type="number" class="form-control" name="profile_rows[0][price]" data-field="price" step="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Наличност (л.м.)</label>
                                    <input type="number" class="form-control" name="profile_rows[0][stock]" data-field="stock" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm multi-add-add-btn mt-2" id="addProfileRowBtn">
                        <i class="fas fa-plus"></i> Още профил
                    </button>
                    <div class="form-text mt-2">Новият ред копира тип, ширина, цена и наличност от предишния — сменете само името.</div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="addProfileForm" class="btn btn-primary">Запази всички</button>
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
