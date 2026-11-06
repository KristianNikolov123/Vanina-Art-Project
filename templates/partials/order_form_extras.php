<?php
$services = $services ?? [];
$serviceCategories = service_category_definitions();
$groupedServices = array_fill_keys(array_keys($serviceCategories), []);
foreach ($services as $service) {
    $category = $service['category'] ?? 'other';
    if (!array_key_exists($category, $groupedServices)) {
        $category = 'other';
    }
    $groupedServices[$category][] = $service;
}
$extrasId = $extrasId ?? 'extras';
$collapseId = 'orderExtras_' . $extrasId;
$hints = $order_pricing_hints ?? [];
$fmtHint = static function (string $key, float $default) use ($hints): string {
    return number_format((float)($hints[$key] ?? $default), 2, '.', '');
};
$ppExtraOpening = $fmtHint('passepartout_multi_opening', 1.50);
$ppCuttingLabor = $fmtHint('passepartout_cutting_labor', 3.00);
$frameBoxLabor = $fmtHint('frame_labor_box', 3.05);
$side150 = $fmtHint('frame_surcharge_side_150', 3.00);
$side200 = $fmtHint('frame_surcharge_side_200', 6.00);
$urgentOptions = $urgent_options ?? [];
$subframeTiers = [
    ['До 30/40 см', $fmtHint('subframe_lm_tier_1', 6.90), 'без труд'],
    ['От 30/40 до 50/70 см', $fmtHint('subframe_lm_tier_2', 6.90), '+' . $fmtHint('subframe_labor', 3.00) . ' €/бр. труд'],
    ['От 50/70 до 70/100 см', $fmtHint('subframe_lm_tier_3', 7.80), '+' . $fmtHint('subframe_labor', 3.00) . ' €/бр.; 3 × късата + 2 × дългата'],
    ['От 100/70 до 100/150 см', $fmtHint('subframe_lm_tier_4', 9.00), '+' . $fmtHint('subframe_labor', 3.00) . ' €/бр.; 4 × късата + 2 × дългата'],
];
$profiles = $profiles ?? [];

$renderServiceRow = static function (array $service, string $category) use ($extrasId): void {
    $id = (int)$service['id'];
    $unit = $service['pricing_unit'] ?? 'piece';
    $isManual = $unit === 'manual';
    $tierRank = in_array($category, ['assembly'], true) ? service_area_tier_rank($service['name']) : 0;
    $tierRank = $tierRank === 99 ? 0 : $tierRank;
    $minPrice = (float)($service['min_price'] ?? 0);
    $note = trim((string)($service['note'] ?? ''));
    if ($isManual) {
        $priceHint = $note !== '' ? $note : 'договорена цена';
    } else {
        $priceHint = number_format((float)$service['price'], 2) . ' ' . service_pricing_unit_label($unit);
        if ($minPrice > 0) {
            $priceHint .= $unit === 'sqm'
                ? ', мин. ' . number_format($minPrice, 2) . ' €/бр.'
                : ', мин. ' . number_format($minPrice, 2) . ' €';
        }
        if ($note !== '' && !str_starts_with($note, 'Минимум')) {
            $priceHint .= '; ' . $note;
        }
    }
    $checkId = 'svc_' . $extrasId . '_' . $id;
    ?>
    <div class="extra-service-item<?= $isManual ? ' extra-service-item--manual' : '' ?>"
         data-service-name="<?= e(mb_strtolower($service['name'], 'UTF-8')) ?>">
        <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input order-price-trigger extra-service-check<?= $tierRank > 0 ? ' area-tier-check' : '' ?>"
                   name="extra_services[]" value="<?= $id ?>" id="<?= e($checkId) ?>"
                   data-manual="<?= $isManual ? '1' : '0' ?>"
                   data-spacer="<?= service_is_spacer($service) ? '1' : '0' ?>"
                   <?= $tierRank > 0 ? 'data-tier-group="' . e($category) . '" data-area-tier="' . (int)$tierRank . '"' : '' ?>>
            <label class="form-check-label" for="<?= e($checkId) ?>">
                <?= e($service['name']) ?>
                <span class="text-muted">(<?= e($priceHint) ?>)</span>
            </label>
        </div>
        <?php if ($isManual): ?>
        <div class="input-group input-group-sm extra-service-manual-price d-none">
            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger"
                   name="extra_services_manual[<?= $id ?>]" placeholder="Цена">
            <span class="input-group-text">€</span>
        </div>
        <?php endif; ?>
        <?php if (service_is_spacer($service)): ?>
        <div class="input-group input-group-sm extra-service-spacer d-none">
            <span class="input-group-text">№ дистанционер</span>
            <input type="text" class="form-control order-price-trigger" name="spacer_profile"
                   list="profileCatalog" autocomplete="off" placeholder="№ профил от склада">
        </div>
        <div class="small spacer-stock-hint d-none"></div>
        <?php endif; ?>
    </div>
    <?php
};
?>
<div class="card mb-3 border-secondary-subtle order-extras-card">
    <button class="card-header py-2 w-100 text-start border-0 bg-transparent d-flex align-items-center justify-content-between"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?= e($collapseId) ?>"
            aria-expanded="false"
            aria-controls="<?= e($collapseId) ?>">
        <small class="fw-semibold">
            <i class="fas fa-sliders-h me-1"></i> Допълнителни опции (ценоразпис)
        </small>
        <span class="text-muted small">
            <span class="order-extras-hint"></span>
            <i class="fas fa-chevron-down ms-1 order-extras-chevron"></i>
        </span>
    </button>
    <div class="collapse order-extras-collapse" id="<?= e($collapseId) ?>">
        <div class="card-body border-top pt-3 order-extras-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Отвори паспарту</label>
                    <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="passepartout_openings" value="1" min="1">
                    <div class="form-text">Първият е включен; всеки следващ +<?= e($ppExtraOpening) ?> €/бр.</div>
                </div>
                <div class="col-md-9 mb-3">
                    <label class="form-label d-block">Паспарту</label>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="client_passepartout_cutting" id="<?= e($extrasId) ?>_client_pp">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_client_pp">Рязане на паспарту, донесено от клиента (+<?= e($ppCuttingLabor) ?> €/бр.)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="complex_passepartout" id="<?= e($extrasId) ?>_complex">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_complex">Сложно паспарту (+50%)</label>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-7 mb-2">
                    <label class="form-label">Спешна поръчка</label>
                    <select class="form-select form-select-sm order-price-trigger" name="urgent">
                        <?php foreach ($urgentOptions as $value => $option): ?>
                        <option value="<?= (int)$value ?>">
                            <?= e($option['label']) ?><?= $option['percent'] > 0 ? ' +' . e(format_cm_number($option['percent'])) . '%' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label">Сложна поръчка (+%)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger"
                               name="complex_order_percent" placeholder="50 до 100">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Договаря се в магазина.</div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <p class="small text-muted mb-2">
                        Големи рамки: поне една страна от 150 см +<?= e($side150) ?> € (и четирите — ×2); от 200 см +<?= e($side200) ?> € (и четирите — ×2). Прилага се само най-високата.
                    </p>
                    <label class="form-label">Специална изработка на рамка</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input order-price-trigger" name="frame_box" id="<?= e($extrasId) ?>_frame_box">
                            <label class="form-check-label" for="<?= e($extrasId) ?>_frame_box">Тип кутия (+<?= e($frameBoxLabor) ?> €/бр.)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input order-price-trigger" name="frame_nonstandard" id="<?= e($extrasId) ?>_frame_nonstandard">
                            <label class="form-check-label" for="<?= e($extrasId) ?>_frame_nonstandard">Нестандартна изработка (+30% или мин.)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input order-price-trigger" name="frame_high_complexity" id="<?= e($extrasId) ?>_frame_high_complexity">
                            <label class="form-check-label" for="<?= e($extrasId) ?>_frame_high_complexity">Висока сложност (+50%)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="extra-services-panel">
                <label class="form-label" for="extraServicesSearch_<?= e($extrasId) ?>">Допълнителни услуги</label>
                <input type="search"
                       class="form-control form-control-sm mb-2 extra-services-search"
                       id="extraServicesSearch_<?= e($extrasId) ?>"
                       placeholder="Търсене по име…"
                       autocomplete="off">
                <p class="small text-muted mb-2">Където цената е за кв.м, площта се смята от размерите на ред 1; начислява се по-голямото от цената и минимума.</p>
                <div class="extra-services-list">
                    <?php foreach ($serviceCategories as $category => $categoryLabel): ?>
                    <?php if ($category !== 'subframes' && empty($groupedServices[$category])) {
                        continue;
                    } ?>
                    <section class="extra-services-category" data-category="<?= e($category) ?>"
                             data-category-label="<?= e(mb_strtolower($categoryLabel, 'UTF-8')) ?>">
                        <h6 class="extra-services-category-title"><?= e($categoryLabel) ?></h6>

                        <?php if ($category === 'subframes'): ?>
                        <div class="extra-service-item" data-service-name="подрамка подрамки">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small mb-0">Дебелина</label>
                                    <select class="form-select form-select-sm order-price-trigger" name="subframe_thickness">
                                        <?php foreach (subframe_thickness_options() as $value => $label): ?>
                                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 subframe-manual-wrap d-none">
                                    <label class="form-label small mb-0">Договорена цена (над 100/150)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger"
                                               name="subframe_manual_price" placeholder="Цена">
                                        <span class="input-group-text">€</span>
                                    </div>
                                </div>
                            </div>
                            <ul class="small text-muted mb-0 mt-1 ps-3">
                                <?php foreach ($subframeTiers as [$label, $price, $note]): ?>
                                <li><?= e($label) ?> — <?= e($price) ?> €/л.м., <?= e($note) ?></li>
                                <?php endforeach; ?>
                                <li>Над посочените размери — договорена цена. Размерът е от ред 1.</li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($groupedServices[$category] ?? [] as $service): ?>
                        <?php $renderServiceRow($service, $category); ?>
                        <?php endforeach; ?>

                        <?php if ($category === 'frame_making'): ?>
                        <div class="row g-2 mt-1 extra-service-item" data-service-name="разстояние каса подрамка подложка платно">
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Разстояние между касата и подрамката</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" inputmode="decimal" autocomplete="off" class="form-control"
                                           name="box_subframe_gap_cm" placeholder="0.0">
                                    <span class="input-group-text">см</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Подложка за височината на платното</label>
                                <input type="text" class="form-control form-control-sm" name="canvas_support_note"
                                       maxlength="40" placeholder="до две думи">
                            </div>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mb-0 d-none extra-services-no-match">Няма услуги за това търсене.</p>
            </div>
        </div>
    </div>
</div>
<style>
.order-extras-card .card-header:hover { background-color: rgba(0,0,0,.03) !important; }
.order-extras-card .order-extras-chevron { transition: transform .2s; }
.order-extras-card [aria-expanded="true"] .order-extras-chevron { transform: rotate(180deg); }
.order-extras-card [aria-expanded="true"] .order-extras-hint::after { content: 'Скрий'; }
.order-extras-card [aria-expanded="false"] .order-extras-hint::after { content: 'Покажи'; }
</style>
