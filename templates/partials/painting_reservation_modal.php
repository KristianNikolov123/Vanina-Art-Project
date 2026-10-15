<div class="modal" data-bs-scroll="true" id="addPaintingReservationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Резервация — още без поръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Запазва брой картини за ден на получаване и име на клиент (напр. ще дойде по-късно с размери).
                </p>
                <form id="addPaintingReservationForm" action="<?= url_for('interface') ?>" method="post">
                    <input type="hidden" name="_action" value="add_painting_reservation">
                    <div class="mb-3">
                        <label class="form-label">Име на клиент</label>
                        <input type="text" class="form-control" name="customer_name" required autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Дата на получаване</label>
                        <input type="text" class="form-control order-date-dd" name="pickup_date"
                            inputmode="numeric" autocomplete="off" placeholder="дд/мм/гггг" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Брой картини</label>
                        <input type="text" inputmode="numeric" autocomplete="off" class="form-control" name="painting_count" value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Работилница (ако знаете)</label>
                        <select class="form-select" name="workshop_group">
                            <?php foreach (painting_reservation_workshop_groups() as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $value === 'unknown' ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Бележка <span class="text-muted fw-normal">(по избор)</span></label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Напр. идва сряда за данни"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="addPaintingReservationForm" class="btn btn-outline-primary">
                    <i class="fas fa-bookmark"></i> Запази резервация
                </button>
            </div>
        </div>
    </div>
</div>
