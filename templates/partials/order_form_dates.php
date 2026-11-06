<?php
$showAcceptedDate = $showAcceptedDate ?? true;
$acceptedDateId = $acceptedDateId ?? '';
$pickupDateId = $pickupDateId ?? '';
$pickupDatePickerId = $pickupDateId !== '' ? $pickupDateId . '_picker' : 'pickupDatePicker';
?>
<?php if ($showAcceptedDate): ?>
<div class="col order-form-col-accepted">
    <label class="form-label">Дата на поръчка</label>
    <input type="text" class="form-control order-date-dd" name="accepted_date"
        inputmode="numeric" autocomplete="off" placeholder="дд/мм/гггг"
        <?= $acceptedDateId !== '' ? 'id="' . e($acceptedDateId) . '"' : '' ?>>
</div>
<?php endif; ?>
<div class="col order-form-col-pickup">
    <label class="form-label">Дата на получаване</label>
    <div class="input-group order-form-pickup-group">
        <input type="text" class="form-control order-date-dd" name="date"
            inputmode="numeric" autocomplete="off" placeholder="дд/мм/гггг"
            <?= $pickupDateId !== '' ? 'id="' . e($pickupDateId) . '"' : '' ?>>
        <input type="date" class="order-date-picker order-date-picker-native" id="<?= e($pickupDatePickerId) ?>"
            tabindex="-1" aria-hidden="true">
        <button type="button" class="btn btn-outline-secondary order-date-picker-btn"
            aria-label="Календар — дата на получаване" title="Избор от календар">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
        </button>
    </div>
</div>
