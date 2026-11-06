<?php
/** @var int $year */
/** @var array $months */
$yearTotal = array_sum(array_column($months, 'total_value'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="mb-1">Използвани материали</h1>
        <p class="text-muted mb-0 small">
            Отчислените от склада материали по месеци (по дата на приемане на поръчката) — за счетоводството.
            Количествата се изчисляват както при отчисляването; стойността е по текущите цени от склада.
        </p>
    </div>
    <form method="get" action="<?= url_for('used_materials') ?>" class="d-flex gap-2 align-items-center">
        <label class="small text-muted" for="usedMaterialsYear">Година</label>
        <select class="form-select form-select-sm" id="usedMaterialsYear" name="year" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<?php if ($months === []): ?>
<div class="alert alert-light border">Няма отчислени материали за <?= (int)$year ?> г.</div>
<?php else: ?>
<div class="alert alert-light border small mb-3">
    Общо за <?= (int)$year ?> г.: <strong><?= number_format($yearTotal, 2, '.', ' ') ?> €</strong>
</div>
<?php foreach ($months as $month): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= e($month['label']) ?></strong>
        <span class="small text-muted"><?= (int)$month['orders'] ?> поръчки · <strong class="text-body"><?= number_format($month['total_value'], 2, '.', ' ') ?> €</strong></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Вид</th>
                        <th>Материал</th>
                        <th class="text-end">Количество</th>
                        <th class="text-end">Стойност</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($month['rows'] as $row): ?>
                    <tr>
                        <td><?= e($row['category']) ?></td>
                        <td><?= e($row['name']) ?></td>
                        <td class="text-end text-nowrap"><?= number_format($row['quantity'], 2, '.', ' ') ?> <?= e($row['unit']) ?></td>
                        <td class="text-end text-nowrap"><?= number_format($row['value'], 2, '.', ' ') ?> €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
