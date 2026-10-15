<?php
$profiles = $profiles ?? [];
$glasses = $glasses ?? [];
$passepartouts = $passepartouts ?? [];
$backs = $backs ?? [];
$hanging_options = $hanging_options ?? [];
$additionalProfilesId = $additionalProfilesId ?? 'additionalProfiles';
?>
<div class="row">
    <div class="col-md-6 mb-3">
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
    <div class="col-md-3 mb-3">
        <label class="form-label">Брой рамки</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="frame_count" value="1" min="1">
    </div>
</div>
<datalist id="profileCatalog">
    <?php foreach ($profiles as $profile): ?>
    <option value="<?= e($profile['name']) ?>"></option>
    <?php endforeach; ?>
</datalist>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Стъкло</label>
        <select class="form-select order-price-trigger" name="glass">
            <option value=""></option>
            <?php foreach ($glasses as $glass): ?>
            <option value="<?= e($glass['name']) ?>"><?= e($glass['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Употреба</label>
        <select class="form-select order-price-trigger" name="passepartout_use">
            <option value="passepartout">Паспарту</option>
            <option value="background">Фон</option>
            <option value="both">Паспарту + фон</option>
        </select>
    </div>
</div>
<div class="row passepartout-materials-row">
    <div class="col-md-6 mb-3 passepartout-primary-field">
        <label class="form-label passepartout-primary-label">Паспарту</label>
        <select class="form-select order-price-trigger" name="passepartout_id">
            <option value=""></option>
            <?php foreach ($passepartouts as $passepartout): ?>
            <option value="<?= (int)$passepartout['id'] ?>"><?= e($passepartout['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3 passepartout-background-field d-none">
        <label class="form-label">Фон (втори материал)</label>
        <select class="form-select order-price-trigger" name="passepartout_background_id">
            <option value=""></option>
            <?php foreach ($passepartouts as $passepartout): ?>
            <option value="<?= (int)$passepartout['id'] ?>"><?= e($passepartout['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Гръб</label>
        <select class="form-select order-price-trigger" name="back">
            <option value=""></option>
            <?php foreach ($backs as $back): ?>
            <option value="<?= e($back['name']) ?>"><?= e($back['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Окачване</label>
        <select class="form-select order-price-trigger" name="hanging" id="<?= e($additionalProfilesId) ?>_hanging">
            <option value=""></option>
            <?php foreach ($hanging_options as $option): ?>
            <option value="<?= e($option['name']) ?>"><?= e($option['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="hanging-weight-field mt-2 d-none">
            <label class="form-label mb-1">Тегло за връзка (kg)</label>
            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="weight_kg" step="0.1" min="0" placeholder="напр. 12">
            <div class="form-text">Задължително при „Връзка“ — определя €/л.м. (Склад → Окачване).</div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12 mb-3">
        <div class="alert alert-light border order-pricing-preview small mb-0" style="display: none;"></div>
    </div>
</div>
