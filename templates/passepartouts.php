<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="container px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Паспарту</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="bulkEditPassepartoutsBtn" disabled>
                <i class="fas fa-edit"></i> Групово<span id="bulkPassepartoutsCount"></span>
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPassepartoutModal">
                <i class="fas fa-plus"></i> Добави паспарту
            </button>
        </div>
    </div>

    <div class="bulk-toolbar">
        <div class="bulk-toolbar__search search-container">
            <input type="text" id="searchInput" class="form-control" placeholder="Търси по име...">
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="bulk-col">
                            <input type="checkbox" class="form-check-input" id="selectAllPassepartouts" title="Избери всички">
                        </th>
                        <th>№</th>
                        <th>Цени</th>
                        <?php foreach ($sheet_types as $sheetType): ?>
                        <th>Наличност <?= e($sheetType['name']) ?> (бр.)</th>
                        <?php endforeach; ?>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passepartouts as $passepartout): ?>
                    <?php
                    $tierValues = [];
                    for ($tier = 1; $tier <= 4; $tier++) {
                        $tierValues[] = (float)($passepartout['price_tier_' . $tier] ?? 0);
                    }
                    ?>
                    <tr data-passepartout-id="<?= (int)$passepartout['id'] ?>"
                        data-sheet-stocks="<?= e(json_encode($passepartout['sheet_stocks'] ?? [])) ?>"
                        data-price-kind="<?= e($passepartout['price_kind'] ?? 'manual') ?>"
                        data-tier-scheme="<?= e($passepartout['tier_scheme'] ?? '80x100') ?>"
                        data-price-tiers="<?= e(json_encode($tierValues)) ?>">
                        <td class="bulk-col">
                            <input type="checkbox" class="form-check-input bulk-row-checkbox" value="<?= (int)$passepartout['id'] ?>">
                        </td>
                        <td class="name"><?= e($passepartout['name']) ?></td>
                        <td class="price-summary"><?= e(format_passepartout_price_summary($passepartout)) ?></td>
                        <?php foreach ($sheet_types as $sheetType): ?>
                        <?php
                            $stock = $passepartout['sheet_stocks'][(int)$sheetType['id']] ?? 0;
                            $stockClass = $stock < 10 ? 'bg-danger' : 'bg-success';
                        ?>
                        <td>
                            <span class="badge <?= $stockClass ?> stock-sheet-<?= (int)$sheetType['id'] ?>">
                                <?= rtrim(rtrim(number_format((float)$stock, 2, '.', ''), '0'), '.') ?>
                            </span>
                        </td>
                        <?php endforeach; ?>
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

<div class="modal fade" id="bulkEditPassepartoutsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Групово редактиране на паспарту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkEditPassepartoutsForm" action="<?= url_for('bulk_edit_passepartouts') ?>" method="post">
                    <div class="bulk-ids-container"></div>
                    <p class="text-muted small mb-3">Отметнете полетата, които искате да промените за избраните редове.</p>

                    <div class="bulk-field">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="apply_price" id="bulk_pp_apply_price" data-bulk-toggle="price">
                            <label class="form-check-label" for="bulk_pp_apply_price">Цена</label>
                        </div>
                        <label class="bulk-field__label text-muted" for="bulk_pp_price">€/кв.м.</label>
                        <input type="number" class="form-control bulk-field__value" name="price" id="bulk_pp_price" step="0.01" min="0" disabled>
                    </div>

                    <div class="mb-2">
                        <div class="small fw-semibold">Наличност по листове</div>
                        <div class="bulk-sheet-fields">
                            <?php foreach ($sheet_types as $sheetType): ?>
                            <?php $sheetId = (int)$sheetType['id']; ?>
                            <div class="bulk-field">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="apply_sheet_stock[<?= $sheetId ?>]" id="bulk_pp_sheet_<?= $sheetId ?>" data-bulk-toggle="sheet_stock[<?= $sheetId ?>]" data-bulk-toggle-extras="sheet_stock_mode[<?= $sheetId ?>]">
                                    <label class="form-check-label" for="bulk_pp_sheet_<?= $sheetId ?>"><?= e($sheetType['name']) ?></label>
                                </div>
                                <select class="form-select bulk-field__mode" name="sheet_stock_mode[<?= $sheetId ?>]" disabled>
                                    <option value="set">Задай</option>
                                    <option value="add">Добави/извади</option>
                                </select>
                                <input type="number" class="form-control bulk-field__value" name="sheet_stock[<?= $sheetId ?>]" min="0" step="0.01" disabled>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="bulkEditPassepartoutsForm" class="btn btn-primary">Приложи</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addPassepartoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ново паспарту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPassepartoutForm" action="<?= url_for('add_passepartout') ?>" method="POST">
                <div class="modal-body">
                    <div id="passepartoutRowsContainer" class="multi-add-rows">
                        <div class="multi-add-row">
                            <div class="multi-add-row__head">
                                <span class="multi-add-row__label">Паспарту 1</span>
                                <button type="button" class="btn btn-sm btn-outline-danger multi-add-remove" title="Премахни">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">№</label>
                                    <input type="text" class="form-control" name="passepartout_rows[0][name]" data-field="name" required>
                                </div>
                            </div>
                            <?php
                            $pricingNameBase = 'passepartout_rows[0]';
                            $pricingIdSuffix = '_0';
                            include __DIR__ . '/partials/passepartout_pricing_fields.php';
                            ?>
                            <div class="mt-2">
                                <div class="small text-muted mb-1">Наличност по листове (бр.)</div>
                                <div class="row g-2">
                                    <?php foreach ($sheet_types as $sheetType): ?>
                                    <?php $sheetId = (int)$sheetType['id']; ?>
                                    <div class="col-md-4">
                                        <label class="form-label small"><?= e($sheetType['name']) ?></label>
                                        <input type="number" class="form-control form-control-sm"
                                               name="passepartout_rows[0][sheet_stock][<?= $sheetId ?>]"
                                               data-field="sheet_stock_<?= $sheetId ?>"
                                               min="0" step="0.01" value="0">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm multi-add-add-btn mt-2" id="addPassepartoutRowBtn">
                        <i class="fas fa-plus"></i> Още паспарту
                    </button>
                    <div class="form-text mt-2">Новият ред копира вид цени и наличности от предишния — сменете само номера.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="submit" class="btn btn-primary">Добави всички</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editPassepartoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирай паспарту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPassepartoutForm" method="POST">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="edit_name" class="form-label">№</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <?php
                    $pricingNameBase = '';
                    $pricingIdSuffix = '_edit';
                    include __DIR__ . '/partials/passepartout_pricing_fields.php';
                    ?>
                    <div class="form-group mb-3">
                        <label class="form-label">Наличност по листове</label>
                        <?php foreach ($sheet_types as $sheetType): ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted"><?= e($sheetType['name']) ?> (бр.)</label>
                            <input type="number" class="form-control edit-sheet-stock" data-sheet-id="<?= (int)$sheetType['id'] ?>" name="sheet_stock[<?= (int)$sheetType['id'] ?>]" min="0" step="0.01" value="0">
                        </div>
                        <?php endforeach; ?>
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

<script>
window.PASSEPARTOUT_PRICE_KINDS = <?= json_encode($passepartout_price_kinds, JSON_UNESCAPED_UNICODE) ?>;
window.PASSEPARTOUT_TIER_LABELS = <?= json_encode($passepartout_tier_labels, JSON_UNESCAPED_UNICODE) ?>;
</script>
