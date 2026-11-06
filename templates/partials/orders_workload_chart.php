<?php
/** @var array $workloadChart */
$workloadChart = $workloadChart ?? ['days' => [], 'max' => 1];
$days = $workloadChart['days'] ?? [];
$max = max(1, (int)($workloadChart['max'] ?? 1));
$hasAnyReservations = false;
foreach ($days as $day) {
    if (!empty($day['reservations'])) {
        $hasAnyReservations = true;
        break;
    }
}
?>
<div class="card border-secondary-subtle mb-3 orders-workload-card">
    <button class="card-header py-2 w-100 text-start border-0 bg-transparent d-flex align-items-center justify-content-between orders-workload-toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#ordersWorkloadCollapse"
            aria-expanded="true"
            aria-controls="ordersWorkloadCollapse">
        <span class="small fw-semibold">
            <i class="fas fa-chart-bar me-1"></i> Натоварване — следващите 2 седмици
        </span>
        <span class="text-muted small">
            <span class="orders-workload-toggle-hint"></span>
            <i class="fas fa-chevron-down ms-1 orders-workload-chevron"></i>
        </span>
    </button>
    <div class="collapse show" id="ordersWorkloadCollapse">
        <div class="card-body py-2 px-3 border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 small text-muted">
                <span>Брой приети рамки по <strong>дата на получаване</strong>; резервациите са включени</span>
                <span class="d-flex flex-wrap gap-3">
                    <span><span class="workload-legend workload-legend--metal"></span> Метални</span>
                    <span><span class="workload-legend workload-legend--plastic"></span> Пластмасови</span>
                    <span><span class="workload-legend workload-legend--wood"></span> Дървени</span>
                    <span><span class="workload-legend workload-legend--passepartout"></span> Паспартута</span>
                    <span><span class="workload-legend workload-legend--reserved"></span> Запазени (неясно)</span>
                    <span>Клик по ред → филтър по получаване</span>
                </span>
            </div>
            <div class="orders-workload-list" role="list" aria-label="Брой рамки по дни">
                <?php
                $workloadMetrics = [
                    'metal' => 'метални',
                    'plastic' => 'пластм.',
                    'wood' => 'дървени',
                    'passepartout' => 'паспарту',
                    'reserved_unknown' => 'запазени',
                ];
                ?>
                <?php foreach ($days as $day): ?>
                <?php
                    $iso = $day['date'];
                    $resList = $day['reservations'] ?? [];
                    $resNames = array_map(static fn ($r) => ($r['customer_name'] ?? '') . ' (' . (int)($r['painting_count'] ?? 1) . ', ' . ($r['workshop_label'] ?? '') . ')', $resList);
                    $titleParts = [];
                    foreach ($workloadMetrics as $key => $label) {
                        $titleParts[] = $label . ': ' . (int)($day[$key] ?? 0);
                    }
                    $title = format_date_ddmmyyyy($iso) . ' — ' . implode(', ', $titleParts);
                    if ($resNames !== []) {
                        $title .= ' — резервации: ' . implode('; ', $resNames);
                    }
                    $dayTotal = (int)$day['metal'] + (int)$day['plastic'] + (int)$day['wood'] + (int)($day['reserved_unknown'] ?? 0);
                    $busy = $dayTotal >= max(5, (int)ceil($max * 0.75));
                ?>
                <button type="button"
                    class="orders-workload-row<?= !empty($day['is_today']) ? ' orders-workload-row--today' : '' ?><?= $busy ? ' orders-workload-row--busy' : '' ?>"
                    data-workload-date="<?= e($iso) ?>"
                    title="<?= e($title) ?>"
                    aria-label="<?= e($title) ?>">
                    <div class="orders-workload-date">
                        <span class="orders-workload-date-main"><?= e($day['label']) ?></span>
                        <span class="orders-workload-date-sub"><?= e($day['weekday'] ?? '') ?></span>
                    </div>
                    <div class="orders-workload-metrics">
                        <?php foreach ($workloadMetrics as $key => $label): ?>
                        <?php
                            $value = (int)($day[$key] ?? 0);
                            $pct = $value > 0 ? max(6, round($value / $max * 100)) : 0;
                            $cssKey = $key === 'reserved_unknown' ? 'reserved' : $key;
                        ?>
                        <div class="orders-workload-metric orders-workload-metric--<?= e($cssKey) ?>">
                            <span class="orders-workload-metric-label"><?= e($label) ?></span>
                            <div class="orders-workload-track" aria-hidden="true">
                                <?php if ($value > 0): ?>
                                <div class="orders-workload-fill orders-workload-fill--<?= e($cssKey) ?>" style="width: <?= (int)$pct ?>%;"></div>
                                <?php endif; ?>
                            </div>
                            <span class="orders-workload-num orders-workload-num--<?= e($cssKey) ?>"><?= $value ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>

            <?php if ($hasAnyReservations): ?>
            <div class="orders-reservations-panel mt-3 pt-2 border-top">
                <div class="small fw-semibold mb-2"><i class="fas fa-bookmark text-primary me-1"></i> Активни резервации (2 седмици)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 orders-reservations-table">
                        <thead>
                            <tr class="text-muted">
                                <th>Получаване</th>
                                <th>Клиент</th>
                                <th>Бр.</th>
                                <th>Работилница</th>
                                <th>Бележка</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                            <?php foreach ($day['reservations'] ?? [] as $res): ?>
                            <tr data-reservation-date="<?= e($day['date']) ?>">
                                <td><?= format_date_ddmmyyyy($day['date']) ?></td>
                                <td><?= e($res['customer_name']) ?></td>
                                <td><?= (int)$res['painting_count'] ?></td>
                                <td><?= e($res['workshop_label'] ?? '') ?></td>
                                <td class="text-muted small"><?= e($res['notes'] ?? '') ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url_for('interface') ?>"
                                        class="d-inline" onsubmit="return confirm('Премахване на резервацията?');">
                                        <input type="hidden" name="_action" value="delete_painting_reservation">
                                        <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Премахни">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.orders-workload-card .orders-workload-chevron { transition: transform .2s; }
.orders-workload-card [aria-expanded="true"] .orders-workload-chevron { transform: rotate(180deg); }
.orders-workload-card [aria-expanded="true"] .orders-workload-toggle-hint::after { content: 'Скрий'; }
.orders-workload-card [aria-expanded="false"] .orders-workload-toggle-hint::after { content: 'Покажи'; }
</style>
