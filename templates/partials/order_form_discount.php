<?php
$discountFieldId = $discountFieldId ?? '';
?>
<div class="col-md-4 mb-3">
    <label class="form-label">Ръчна отстъпка (%)</label>
    <div class="input-group">
        <input type="text" inputmode="decimal" autocomplete="off"
            class="form-control order-price-trigger order-discount-input"
            name="discount" step="0.01"
            <?= $discountFieldId !== '' ? 'id="' . e($discountFieldId) . '"' : '' ?>>
        <span class="input-group-text">%</span>
    </div>
    <div class="btn-group btn-group-sm mt-1" role="group" aria-label="Бърза отстъпка">
        <button type="button" class="btn btn-outline-secondary order-discount-preset" data-percent="5">5%</button>
        <button type="button" class="btn btn-outline-secondary order-discount-preset" data-percent="10">10%</button>
    </div>
    <div class="form-text">Обемна (−5%/−10%) и ученическа се смятат автоматично</div>
</div>
