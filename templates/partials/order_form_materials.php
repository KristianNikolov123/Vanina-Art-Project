<?php
$profiles = $profiles ?? [];
$glasses = $glasses ?? [];
$passepartouts = $passepartouts ?? [];
$backs = $backs ?? [];
$hanging_options = $hanging_options ?? [];
$additionalProfilesId = $additionalProfilesId ?? 'additionalProfiles';
$additionalPassepartoutsId = $additionalProfilesId . '_extraPp';

$hangingPackages = [];
$hangerAddons = [];
foreach ($hanging_options as $option) {
    $name = $option['name'] ?? '';
    if (str_starts_with($name, 'Закачалка')) {
        $hangerAddons[] = $option;
    } elseif ($name !== '' && !in_array($name, ['Връзка', 'Две закачалки', 'Закачалка'], true)) {
        $hangingPackages[] = $option;
    }
}
if ($hangingPackages === []) {
    $hangingPackages = array_filter($hanging_options, static fn ($o) => ($o['name'] ?? '') !== '');
}
?>
<div class="row g-2 order-form-row-2">
    <div class="col-md-8 mb-3">
        <label class="form-label">Профил</label>
        <div class="input-group">
            <input type="text" class="form-control order-price-trigger" name="profile" list="profileCatalog">
            <button type="button" class="btn btn-outline-secondary" onclick="addProfileField('<?= e($additionalProfilesId) ?>')">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div id="<?= e($additionalProfilesId) ?>"></div>
        <div class="profile-ready-frame-fields mt-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input order-price-trigger" name="profile_ready_frame" id="<?= e($additionalProfilesId) ?>_ready_frame">
                <label class="form-check-label" for="<?= e($additionalProfilesId) ?>_ready_frame">Готова рамка (своя цена)</label>
            </div>
            <div class="input-group input-group-sm mt-1 profile-ready-frame-price-wrap d-none">
                <input type="text" inputmode="decimal" autocomplete="off"
                    class="form-control order-price-trigger" name="profile_ready_frame_price"
                    placeholder="€ / бр. рамка" step="0.01">
                <span class="input-group-text">€/бр.</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Брой рамки</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="frame_count" value="1" min="1">
    </div>
</div>
<datalist id="profileCatalog">
    <?php foreach ($profiles as $profile): ?>
    <option value="<?= e($profile['name']) ?>"></option>
    <?php endforeach; ?>
</datalist>
<datalist id="passepartoutCatalog">
    <?php foreach ($passepartouts as $passepartout): ?>
    <option value="<?= e($passepartout['name']) ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="row g-2 order-form-row-3">
    <div class="col-md-6 mb-3">
        <label class="form-label">Стъкло</label>
        <select class="form-select order-price-trigger" name="glass">
            <option value=""></option>
            <?php foreach ($glasses as $glass): ?>
            <option value="<?= e($glass['name']) ?>"><?= e($glass['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Гръб</label>
        <select class="form-select order-price-trigger" name="back">
            <option value=""></option>
            <?php foreach ($backs as $back): ?>
            <option value="<?= e($back['name']) ?>"><?= e($back['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="order-passepartout-block mb-3">
    <div class="row g-2 align-items-end mb-2">
        <div class="col-md-6">
            <label class="form-label">Паспарту / фон</label>
            <select class="form-select order-price-trigger passepartout-use-select" name="passepartout_use">
                <?php foreach (passepartout_use_form_options() as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="passepartout-mode-background d-none mb-2">
        <label class="form-label">№ фон от склад</label>
        <input type="text" class="form-control order-price-trigger passepartout-fond-only-input" name="passepartout"
            list="passepartoutCatalog" autocomplete="off" placeholder="№ от склада">
        <div class="form-text small">Само фон — без слоеве паспарту.</div>
    </div>

    <div class="passepartout-mode-stack">
    <p class="form-text small mt-0 mb-2">Слой <strong>1</strong> е най-горе (към стъкло), с «+» добавяте <strong>2, 3…</strong> надолу към произведението.</p>

    <div class="passepartout-layers border rounded">
        <div class="passepartout-layer passepartout-layer-primary p-2 p-md-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge rounded-pill text-bg-primary passepartout-layer-badge">1</span>
                <span class="small fw-semibold passepartout-layer-title">Слой 1 — най-горе (към стъкло)</span>
            </div>
            <div class="row g-2 align-items-end passepartout-layer-fields">
                <div class="col-6">
                    <label class="form-label small text-muted mb-0">№ от склад</label>
                    <input type="text" class="form-control order-price-trigger passepartout-name-input" name="passepartout"
                        list="passepartoutCatalog" autocomplete="off" inputmode="text" placeholder="№ от склада">
                </div>
                <div class="col-4">
                    <label class="form-label small text-muted mb-0">Отстъп</label>
                    <input type="text" inputmode="decimal" autocomplete="off" class="form-control passepartout-reveal-input"
                        name="passepartout_reveal[]" placeholder="см">
                </div>
                <div class="col-2 d-grid">
                    <label class="form-label small text-muted mb-0 d-none d-md-block">&nbsp;</label>
                    <button type="button" class="btn btn-outline-secondary" onclick="addPassepartoutField('<?= e($additionalPassepartoutsId) ?>')"
                        title="Добави слой надолу (2, 3…)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="passepartout-zero-panel mt-2 pt-2 border-top border-light">
                <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input order-price-trigger" name="passepartout_zero"
                        id="<?= e($additionalProfilesId) ?>_pp_zero">
                    <label class="form-check-label" for="<?= e($additionalProfilesId) ?>_pp_zero">
                        Нулево — само <strong>слой 1</strong> (най-горе, без материал)
                    </label>
                </div>
                <div class="passepartout-zero-margins row g-2 mt-2 d-none">
                    <div class="col-3 col-sm-3">
                        <label class="form-label small mb-0">Л (см)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm"
                            name="passepartout_margin_l">
                    </div>
                    <div class="col-3 col-sm-3">
                        <label class="form-label small mb-0">Д (см)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm"
                            name="passepartout_margin_r">
                    </div>
                    <div class="col-3 col-sm-3">
                        <label class="form-label small mb-0">Г (см)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm"
                            name="passepartout_margin_t">
                    </div>
                    <div class="col-3 col-sm-3">
                        <label class="form-label small mb-0">Д (см)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm"
                            name="passepartout_margin_b">
                    </div>
                </div>
            </div>
        </div>
        <div id="<?= e($additionalPassepartoutsId) ?>" class="passepartout-extra-layers"></div>
    </div>

    <div class="passepartout-mode-both-fond border rounded p-2 p-md-3 mt-2 d-none">
        <label class="form-label mb-1">№ фон (долен лист под паспартутата)</label>
        <input type="text" class="form-control order-price-trigger" name="passepartout_background"
            list="passepartoutCatalog" autocomplete="off" placeholder="№ от склада">
    </div>
    </div>
</div>

<div class="row g-2 order-form-row-hanging">
    <div class="col-md-6 mb-3">
        <label class="form-label">Окачване</label>
        <select class="form-select order-price-trigger" name="hanging" id="<?= e($additionalProfilesId) ?>_hanging">
            <option value=""></option>
            <?php foreach ($hangingPackages as $option): ?>
            <option value="<?= e($option['name']) ?>"><?= e($option['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Закачалки</label>
        <select class="form-select order-price-trigger" name="hanger">
            <option value=""></option>
            <?php foreach ($hangerAddons as $option): ?>
            <option value="<?= e($option['name']) ?>"><?= e($option['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-3">
        <div class="alert alert-light border order-pricing-preview small mb-0" style="display: none;"></div>
    </div>
</div>
