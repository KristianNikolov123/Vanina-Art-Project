<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Паспарту</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPassepartoutModal">
            <i class="fas fa-plus"></i> Добави паспарту
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="search-container">
                <input type="text" id="searchInput" class="form-control" placeholder="Търси по име...">
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Цена(€/кв.м.)</th>
                        <th>Наличност (кв.м.)</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passepartouts as $passepartout): ?>
                    <tr data-passepartout-id="<?= (int)$passepartout['id'] ?>">
                        <td class="name"><?= e($passepartout['name']) ?></td>
                        <td class="price"><?= e($passepartout['price']) ?></td>
                        <td>
                            <span class="badge <?= $passepartout['stock'] < 10 ? 'bg-danger' : 'bg-success' ?>">
                                <?= (int)$passepartout['stock'] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editPassepartout('<?= (int)$passepartout['id'] ?>')" data-bs-toggle="tooltip" title="Редактирай">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('<?= (int)$passepartout['id'] ?>')" data-bs-toggle="tooltip" title="Изтрий">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addPassepartoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добави паспарту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url_for('add_passepartout') ?>" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name" class="form-label">№</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="price" class="form-label">Цена(€/кв.м.)</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="stock" class="form-label">Наличност (кв.м.)</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" required>
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

<div class="modal fade" id="editPassepartoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирай паспарту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPassepartoutForm" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name" class="form-label">№</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_price" class="form-label">Цена(€/кв.м.)</label>
                        <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_stock" class="form-label">Наличност (кв.м.)</label>
                        <input type="number" class="form-control" id="edit_stock" name="stock" min="0" required>
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
