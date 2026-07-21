<?php
$pricingNameBase = $pricingNameBase ?? '';
$pricingIdSuffix = $pricingIdSuffix ?? '';
$pricingField = static function (string $field) use ($pricingNameBase): string {
    return $pricingNameBase !== '' ? "{$pricingNameBase}[{$field}]" : $field;
};
$pricingId = static function (string $field) use ($pricingIdSuffix): string {
    return 'pp_' . $field . $pricingIdSuffix;
};
?>
<div class="pp-pricing-block" data-pp-pricing-root>
    <div class="row g-2 mb-2">
        <div class="col-md-6">
            <label class="form-label" for="<?= $pricingId('price_kind') ?>">Вид цени</label>
            <select class="form-select" id="<?= $pricingId('price_kind') ?>"
                    name="<?= e($pricingField('price_kind')) ?>"
                    data-field="price_kind" data-pp-price-kind>
                <option value="manual">Ръчно</option>
                <?php foreach ($passepartout_price_kinds as $kindKey => $kindDef): ?>
                <option value="<?= e($kindKey) ?>" title="<?= e(describe_passepartout_price_kind($kindKey)) ?>">
                    <?= e($kindDef['label']) ?> — <?= e($kindDef['sheet_label'] ?? $kindDef['scheme']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 pp-manual-scheme" data-pp-manual-scheme>
            <label class="form-label" for="<?= $pricingId('tier_scheme') ?>">Размери на нивата</label>
            <select class="form-select" id="<?= $pricingId('tier_scheme') ?>"
                    name="<?= e($pricingField('tier_scheme')) ?>"
                    data-field="tier_scheme" data-pp-tier-scheme>
                <option value="80x120">80×120 (30×40 … 81×120)</option>
                <option value="80x100">80×100 (25×40 … 81×102)</option>
            </select>
        </div>
    </div>
    <div class="small text-muted mb-2">Цена (€/бр.) по размер на изрязване</div>
    <div class="row g-2 pp-tier-fields">
        <?php for ($tier = 1; $tier <= 4; $tier++): ?>
        <div class="col-md-6 col-lg-3">
            <label class="form-label small pp-tier-label" data-pp-tier-label="<?= $tier ?>">
                Ниво <?= $tier ?>
            </label>
            <input type="number" class="form-control form-control-sm"
                   id="<?= $pricingId('price_tier_' . $tier) ?>"
                   name="<?= e($pricingField('price_tier_' . $tier)) ?>"
                   data-field="price_tier_<?= $tier ?>"
                   data-pp-tier-input="<?= $tier ?>"
                   step="0.01" min="0" value="0">
        </div>
        <?php endfor; ?>
    </div>
</div>
