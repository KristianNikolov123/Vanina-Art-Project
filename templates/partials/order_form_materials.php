<?php
$profiles = $profiles ?? [];
$glasses = $glasses ?? [];
$passepartouts = $passepartouts ?? [];
$backs = $backs ?? [];
$hanging_options = $hanging_options ?? [];
$additionalProfilesId = $additionalProfilesId ?? 'additionalProfiles';
$additionalPassepartoutsId = $additionalProfilesId . '_extraPp';

$hangingSplit = split_hanging_options_for_form($hanging_options);
$hangingPackages = $hangingSplit['packages'];
$hangerAddons = $hangingSplit['hangers'];

/** Четири малки клетки Л/Д/Г/Д + „отвсякъде“. */
$renderSideInputs = static function (string $prefix, bool $asArray, string $allLabel, string $allTitle): void {
    $suffix = $asArray ? '[]' : '';
    $cells = [
        'all' => [$allLabel, $allTitle],
        'l' => ['Л', 'Ляво (см)'],
        'r' => ['Д', 'Дясно (см)'],
        't' => ['Г', 'Горе (см)'],
        'b' => ['Дол', 'Долу (см)'],
    ];
    foreach ($cells as $key => [$label, $title]): ?>
        <div class="side-cell<?= $key === 'all' ? ' side-cell--all' : '' ?>">
            <label class="form-label small text-muted mb-0"><?= e($label) ?></label>
            <input type="text" inputmode="decimal" autocomplete="off" maxlength="5"
                class="form-control form-control-sm order-price-trigger side-input"
                name="<?= e($prefix . $key . $suffix) ?>" title="<?= e($title) ?>" placeholder="см">
        </div>
    <?php endforeach;
};
?>
<div class="row g-2 order-form-row-2">
    <div class="col-md-4 mb-2">
        <label class="form-label">Профил</label>
        <div class="input-group">
            <input type="text" class="form-control order-price-trigger" name="profile" list="profileCatalog">
            <button type="button" class="btn btn-outline-secondary" onclick="addProfileField('<?= e($additionalProfilesId) ?>')" title="Добави профил">
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
    <div class="col-md-2 mb-2">
        <label class="form-label">Брой рамки</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="frame_count" value="1" min="1">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label text-nowrap" title="Как е измерен размерът">Изисквания към рамката</label>
        <select class="form-select order-price-trigger" name="frame_measure">
            <?php foreach (frame_measure_options() as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label">Нестандартна фигура</label>
        <select class="form-select order-price-trigger" name="frame_shape">
            <?php foreach (frame_shape_options() as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
            <option value="circle_24" hidden>Кръг 24 страни (стара поръчка)</option>
        </select>
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
    <div class="col-md-6 mb-2">
        <label class="form-label">Стъкло</label>
        <select class="form-select order-price-trigger" name="glass">
            <option value=""></option>
            <?php foreach ($glasses as $glass): ?>
            <option value="<?= e($glass['name']) ?>"><?= e($glass['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-2">
        <label class="form-label">Гръб</label>
        <select class="form-select order-price-trigger" name="back">
            <option value=""></option>
            <?php foreach ($backs as $back): ?>
            <option value="<?= e($back['name']) ?>"><?= e($back['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="order-passepartout-block mb-2">
    <div class="row g-2 align-items-end mb-2">
        <div class="col-md-5">
            <label class="form-label">Паспарту / фон</label>
            <select class="form-select order-price-trigger passepartout-use-select" name="passepartout_use">
                <?php foreach (passepartout_use_form_options() as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
                <option value="field">Поле (+ паспарту по желание)</option>
            </select>
        </div>
    </div>

    <div class="passepartout-mode-field d-none border rounded p-2 mb-2">
        <label class="form-label mb-0">Поле</label>
        <div class="form-text small mt-0 mb-1">Откъде започва профилът/паспартуто, ако не е от края на картината. Общата стойност се разделя на 4 страни.</div>
        <div class="side-cells field-margin-cells">
            <?php $renderSideInputs('field_margin_', false, 'Общо', 'Общо (разделя се поравно на 4 страни)'); ?>
        </div>
    </div>

    <div class="passepartout-mode-background d-none mb-2 border rounded p-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-0">№ фон</label>
                <input type="text" class="form-control form-control-sm order-price-trigger passepartout-fond-only-input" name="passepartout"
                    list="passepartoutCatalog" autocomplete="off" placeholder="№ от склада">
            </div>
            <div class="col-md-9">
                <div class="side-cells">
                    <?php $renderSideInputs('bg_', false, 'Отвсякъде', 'Отстояние от всяка страна (см)'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="passepartout-mode-stack">
        <div class="passepartout-layers border rounded">
            <div class="passepartout-layer passepartout-layer-primary p-2">
                <div class="row g-2 align-items-end passepartout-layer-fields">
                    <div class="col-md-3 col-12">
                        <label class="form-label small text-muted mb-0">№ паспарту</label>
                        <input type="text" class="form-control form-control-sm order-price-trigger passepartout-name-input" name="passepartout"
                            list="passepartoutCatalog" autocomplete="off" placeholder="№ от склада">
                    </div>
                    <div class="col-md-8 col-10">
                        <div class="side-cells">
                            <?php $renderSideInputs('pp_', true, 'Отвсякъде', 'Размер на паспартуто от всяка страна (см)'); ?>
                        </div>
                    </div>
                    <div class="col-md-1 col-2 d-grid">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPassepartoutField('<?= e($additionalPassepartoutsId) ?>')"
                            title="Добави следващо паспарту">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div id="<?= e($additionalPassepartoutsId) ?>" class="passepartout-extra-layers"></div>
        </div>
        <div class="form-text small">Отстоянията (Л, Д, Г, Дол) увеличават размера на паспартуто спрямо картината; попълнените отделни клетки имат предимство пред „Отвсякъде“. Първият отвор е включен в цената.</div>

        <div class="passepartout-mode-both-fond border rounded p-2 mt-2 d-none">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-0">№ фон</label>
                    <input type="text" class="form-control form-control-sm order-price-trigger" name="passepartout_background"
                        list="passepartoutCatalog" autocomplete="off" placeholder="№ от склада">
                </div>
                <div class="col-md-9">
                    <div class="side-cells">
                        <?php $renderSideInputs('bg_', false, 'Отвсякъде', 'Отстояние от всяка страна (см)'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="small text-muted mt-1 order-geometry-hint"></div>
</div>

<div class="row g-2 order-form-row-hanging">
    <div class="col-md-6 mb-2">
        <label class="form-label">Окачване</label>
        <select class="form-select order-price-trigger" name="hanging" id="<?= e($additionalProfilesId) ?>_hanging">
            <option value=""></option>
            <?php foreach ($hangingPackages as $option): ?>
            <option value="<?= e($option['name']) ?>"
                data-needs-hangers="<?= hanging_package_requires_hangers($option['name']) ? '1' : '0' ?>">
                <?= e($option['name']) ?><?= isset($option['price']) ? ' — ' . number_format((float)$option['price'], 2) . ' €' . (hanging_package_requires_hangers($option['name']) ? ' + закачалки' : '') : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 mb-2">
        <label class="form-label">Закачалки</label>
        <select class="form-select order-price-trigger" name="hanger">
            <option value=""></option>
            <?php foreach ($hangerAddons as $option): ?>
            <option value="<?= e($option['name']) ?>"><?= e(hanger_display_label($option['name'])) ?><?= isset($option['price']) ? ' — ' . number_format((float)$option['price'], 2) . ' €/бр.' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 mb-2">
        <label class="form-label">Бр. на рамка</label>
        <input type="text" inputmode="numeric" autocomplete="off" class="form-control order-price-trigger" name="hanger_count" placeholder="2">
    </div>
</div>

<div class="row">
    <div class="col-12 mb-3">
        <div class="alert alert-light border order-pricing-preview small mb-0" style="display: none;"></div>
    </div>
</div>
