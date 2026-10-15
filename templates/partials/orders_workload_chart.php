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
                <span>Натоварване (бр.) по <strong>получаване</strong> — метал ×2; резервации включени</span>
                <span class="d-flex flex-wrap gap-3">
                    <span><span class="workload-legend workload-legend--plastic-metal"></span> Пластмаса + метал</span>
                    <span><span class="workload-legend workload-legend--wood"></span> Дърво</span>
                    <span><span class="workload-legend workload-legend--reserved"></span> Резерв. (неясно)</span>
                    <span>Клик по ред → филтър по получаване</span>
                </span>
            </div>
            <div class="orders-workload-list" role="list" aria-label="Брой картини по дни">
                <?php foreach ($days as $day): ?>
                <?php
                    $plasticMetal = (int)$day['plastic_metal'];
                    $wood = (int)$day['wood'];
                    $reservedUnknown = (int)($day['reserved_unknown'] ?? 0);
                    $plasticMetalPct = $plasticMetal > 0 ? max(6, round($plasticMetal / $max * 100)) : 0;
                    $woodPct = $wood > 0 ? max(6, round($wood / $max * 100)) : 0;
                    $reservedPct = $reservedUnknown > 0 ? max(6, round($reservedUnknown / $max * 100)) : 0;
                    $iso = $day['date'];
                    $resList = $day['reservations'] ?? [];
                    $resNames = array_map(static fn ($r) => ($r['customer_name'] ?? '') . ' (' . (int)($r['painting_count'] ?? 1) . ')', $resList);
                    $title = format_date_ddmmyyyy($iso)
                        . ' — пл.+м.: ' . $plasticMetal . ', дърво: ' . $wood . ', резерв.: ' . $reservedUnknown;
                    if ($resNames !== []) {
                        $title .= ' — ' . implode('; ', $resNames);
                    }
                    $dayTotal = $plasticMetal + $wood + $reservedUnknown;
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
                        <div class="orders-workload-metric">
                            <span class="orders-workload-metric-label">пл.+м.</span>
                            <div class="orders-workload-track" aria-hidden="true">
                                <?php if ($plasticMetal > 0): ?>
                                <div class="orders-workload-fill orders-workload-fill--plastic-metal" style="width: <?= (int)$plasticMetalPct ?>%;"></div>
                                <?php endif; ?>
                            </div>
                            <span class="orders-workload-num orders-workload-num--plastic-metal"><?= $plasticMetal ?></span>
                        </div>
                        <div class="orders-workload-metric">
                            <span class="orders-workload-metric-label">дърво</span>
                            <div class="orders-workload-track" aria-hidden="true">
                                <?php if ($wood > 0): ?>
                                <div class="orders-workload-fill orders-workload-fill--wood" style="width: <?= (int)$woodPct ?>%;"></div>
                                <?php endif; ?>
                            </div>
                            <span class="orders-workload-num orders-workload-num--wood"><?= $wood ?></span>
                        </div>
                        <?php if ($reservedUnknown > 0): ?>
                        <div class="orders-workload-metric orders-workload-metric--reserved">
                            <span class="orders-workload-metric-label">рез.</span>
                            <div class="orders-workload-track" aria-hidden="true">
                                <?php if ($reservedUnknown > 0): ?>
                                <div class="orders-workload-fill orders-workload-fill--reserved" style="width: <?= (int)$reservedPct ?>%;"></div>
                                <?php endif; ?>
                            </div>
                            <span class="orders-workload-num orders-workload-num--reserved"><?= $reservedUnknown ?></span>
                        </div>
                        <?php endif; ?>
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
