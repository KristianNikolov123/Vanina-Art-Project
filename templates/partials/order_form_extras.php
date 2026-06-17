<?php
$services = $services ?? [];
$serviceCategories = [
    'frame_labor_svc' => 'Изработка рамка',
    'glass_labor' => 'Стъкло',
    'assembly' => 'Монтаж',
    'stretching' => 'Опъване',
    'mounting' => 'Каширане',
    'general' => 'Други',
    'hardware' => 'Дребно',
];
$groupedServices = [];
foreach ($services as $service) {
    $groupedServices[$service['category'] ?? 'general'][] = $service;
}
$extrasId = $extrasId ?? 'extras';
$collapseId = 'orderExtras_' . $extrasId;
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
        <div class="card-body border-top pt-3" style="max-height: 420px; overflow-y: auto;">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Отвори паспарту</label>
                    <input type="number" class="form-control order-price-trigger" name="passepartout_openings" value="1" min="1">
                    <div class="form-text">Първият включен, +1.22 €/отвор</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Км транспорт</label>
                    <input type="number" class="form-control order-price-trigger transport-km-input" name="transport_km" step="0.1" min="0" placeholder="При транспорт">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block">Паспарту и отстъпки</label>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="client_passepartout_cutting" id="<?= e($extrasId) ?>_client_pp">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_client_pp">Рязане на паспарту на клиент (+1.84 €/бр.)</label>
                    </div>
                    <div class="form-text mb-2">Само при собствено паспарту. При паспарту от фирмата рязането не се начислява.</div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="student_discount" id="<?= e($extrasId) ?>_student">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_student">Ученик (−10%)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="urgent" id="<?= e($extrasId) ?>_urgent">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_urgent">Спешна (+50%)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input order-price-trigger" name="complex_passepartout" id="<?= e($extrasId) ?>_complex">
                        <label class="form-check-label" for="<?= e($extrasId) ?>_complex">Сложно паспарту (+50%)</label>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label">Специална изработка на рамка</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input order-price-trigger" name="frame_box" id="<?= e($extrasId) ?>_frame_box">
                                <label class="form-check-label" for="<?= e($extrasId) ?>_frame_box">Тип кутия (+3.05 €/бр.)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input order-price-trigger" name="frame_nonstandard" id="<?= e($extrasId) ?>_frame_nonstandard">
                                <label class="form-check-label" for="<?= e($extrasId) ?>_frame_nonstandard">Нестандартна (+30% или мин.)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input order-price-trigger" name="frame_high_complexity" id="<?= e($extrasId) ?>_frame_high_complexity">
                                <label class="form-check-label" for="<?= e($extrasId) ?>_frame_high_complexity">Висока сложност (+50%)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Форма на профила</label>
                            <select class="form-select form-select-sm order-price-trigger" name="frame_shape">
                                <option value="">Правоъгълна</option>
                                <option value="ellipse_12">Елипса / кръг 12 страни (×3 труд)</option>
                                <option value="circle_24">Кръг 24 страни (×4 труд)</option>
                            </select>
                            <div class="form-text">Материалът се фактурира ×1.25</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($groupedServices)): ?>
            <div class="row">
                <div class="col-12">
                    <label class="form-label">Допълнителни услуги</label>
                    <div class="row">
                        <?php foreach ($groupedServices as $category => $categoryServices): ?>
                        <div class="col-md-6 mb-2">
                            <div class="small text-muted mb-1"><?= e($serviceCategories[$category] ?? $category) ?></div>
                            <?php foreach ($categoryServices as $service): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input order-price-trigger extra-service-check"
                                       name="extra_services[]" value="<?= (int)$service['id'] ?>"
                                       id="svc_<?= e($extrasId) ?>_<?= (int)$service['id'] ?>"
                                       data-needs-km="<?= ($service['pricing_unit'] ?? '') === 'km' ? '1' : '0' ?>">
                                <label class="form-check-label" for="svc_<?= e($extrasId) ?>_<?= (int)$service['id'] ?>">
                                    <?= e($service['name']) ?>
                                    <span class="text-muted">(<?= number_format((float)$service['price'], 2) ?> €)</span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
