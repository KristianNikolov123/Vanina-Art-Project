<?php
$showAcceptedDate = $showAcceptedDate ?? true;
$acceptedDateId = $acceptedDateId ?? '';
$pickupDateId = $pickupDateId ?? '';
?>
<?php if ($showAcceptedDate): ?>
<div class="col-md-4 mb-3">
    <label class="form-label">Дата на приемане</label>
    <input type="text" class="form-control order-date-dd" name="accepted_date"
        inputmode="numeric" autocomplete="off" placeholder="дд/мм/гггг"
        <?= $acceptedDateId !== '' ? 'id="' . e($acceptedDateId) . '"' : '' ?>>
</div>
<?php endif; ?>
<div class="col-md-4 mb-3">
    <label class="form-label">Дата на получаване</label>
    <input type="text" class="form-control order-date-dd" name="date"
        inputmode="numeric" autocomplete="off" placeholder="дд/мм/гггг"
        <?= $pickupDateId !== '' ? 'id="' . e($pickupDateId) . '"' : '' ?>>
</div>
