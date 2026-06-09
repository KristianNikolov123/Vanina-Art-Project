<div class="container-fluid">
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
                    <th>Цена(€/кв.м.)</th>
                    <th>Наличност (кв.м.)</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $profile): ?>
                <tr data-profile-id="<?= (int)$profile['id'] ?>">
                    <td class="name"><?= e($profile['name']) ?></td>
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
                        <label class="form-label">Цена(€/кв.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (кв.м.)</label>
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
                        <label class="form-label">Цена(€/кв.м.)</label>
                        <input type="number" class="form-control" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Наличност (кв.м.)</label>
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
