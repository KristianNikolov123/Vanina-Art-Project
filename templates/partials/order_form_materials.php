<?php
$profiles = $profiles ?? [];
$glasses = $glasses ?? [];
$passepartouts = $passepartouts ?? [];
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
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Брой рамки</label>
        <input type="number" class="form-control order-price-trigger" name="frame_count" value="1" min="1">
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
    <div class="col-md-6 mb-3">
        <label class="form-label">Паспарту</label>
        <select class="form-select order-price-trigger" name="passepartout_id">
            <option value=""></option>
            <?php foreach ($passepartouts as $passepartout): ?>
            <option value="<?= (int)$passepartout['id'] ?>"><?= e($passepartout['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="row">
    <div class="col-12 mb-3">
        <div class="alert alert-light border order-pricing-preview small mb-0" style="display: none;"></div>
    </div>
</div>
