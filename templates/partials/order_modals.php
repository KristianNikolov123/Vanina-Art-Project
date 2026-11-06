<div class="modal" data-bs-scroll="true" id="addOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg order-form-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нова поръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addOrderForm" class="order-form" action="<?= url_for('add_order') ?>" method="post" data-draft-key="add">
                    <input type="hidden" name="stock_confirmed" value="">
                    <div class="row g-2 mb-2 order-form-row-1">
                        <?php
                        $showAcceptedDate = true;
                        $acceptedDateId = 'addAcceptedDate';
                        $pickupDateId = 'addPickupDate';
                        include __DIR__ . '/order_form_dates.php';
                        ?>
                        <div class="col order-form-col-size">
                            <label class="form-label">Ширина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col order-form-col-size">
                            <label class="form-label">Височина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="height" step="0.1">
                        </div>
                        <div class="col order-form-col-artwork">
                            <label class="form-label">Вид произведение</label>
                            <select class="form-select" name="artwork_type">
                                <?php foreach (artwork_type_options() as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php $additionalProfilesId = 'additionalProfiles'; include __DIR__ . '/order_form_materials.php'; ?>
                    <?php $extrasId = 'add'; include __DIR__ . '/order_form_extras.php'; ?>
                    <div class="row">
                        <div class="col-md-4 mb-3 position-relative customer-lookup">
                            <label class="form-label">Име на клиент</label>
                            <input type="text" class="form-control customer-lookup-input" name="customer_name" autocomplete="off" placeholder="Търсене по име или телефон">
                            <input type="hidden" name="customer_id" value="">
                        </div>
                        <div class="col-md-4 mb-3 position-relative customer-lookup">
                            <label class="form-label">Телефон</label>
                            <input type="tel" class="form-control customer-lookup-input" name="customer_phone" inputmode="tel" autocomplete="off" placeholder="08…">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Имейл <span class="text-muted small">(за уведомяване)</span></label>
                            <input type="email" class="form-control" name="customer_email" autocomplete="off" placeholder="по желание">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="При вид „Други“ — опишете произведението"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Цена</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="price" step="0.01">
                            <div class="form-text">Оставете празно за автоматично изчисление</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <?php include __DIR__ . '/order_form_discount.php'; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="ready" id="ready">
                                <label class="form-check-label" for="ready">Готова</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="paid" id="paid">
                                <label class="form-check-label" for="paid">Платено</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="collected" id="collected">
                                <label class="form-check-label" for="collected">Получено</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAddOrderForm()" title="Изчисти всички полета">
                    <i class="fas fa-eraser"></i> Изчисти
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="submit" form="addOrderForm" class="btn btn-primary">Запази</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal" data-bs-scroll="true" id="editOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg order-form-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактиране на поръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editOrderForm" class="order-form" method="post" data-draft-key="edit">
                    <input type="hidden" name="stock_confirmed" value="">
                    <input type="hidden" name="_order_id" value="">
                    <div class="row g-2 mb-2 order-form-row-1">
                        <?php
                        $showAcceptedDate = true;
                        $acceptedDateId = 'editAcceptedDate';
                        $pickupDateId = 'editDate';
                        include __DIR__ . '/order_form_dates.php';
                        ?>
                        <div class="col order-form-col-size">
                            <label class="form-label">Ширина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col order-form-col-size">
                            <label class="form-label">Височина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="height" step="0.1">
                        </div>
                        <div class="col order-form-col-artwork">
                            <label class="form-label">Вид произведение</label>
                            <select class="form-select" name="artwork_type" id="editArtworkType">
                                <?php foreach (artwork_type_options() as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php $additionalProfilesId = 'additionalProfilesEdit'; include __DIR__ . '/order_form_materials.php'; ?>
                    <?php $extrasId = 'edit'; include __DIR__ . '/order_form_extras.php'; ?>
                    <div class="row">
                        <div class="col-md-4 mb-3 position-relative customer-lookup">
                            <label class="form-label">Име на клиент</label>
                            <input type="text" class="form-control customer-lookup-input" name="customer_name" autocomplete="off" placeholder="Търсене по име или телефон">
                            <input type="hidden" name="customer_id" value="">
                        </div>
                        <div class="col-md-4 mb-3 position-relative customer-lookup">
                            <label class="form-label">Телефон</label>
                            <input type="tel" class="form-control customer-lookup-input" name="customer_phone" inputmode="tel" autocomplete="off" placeholder="08…">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Имейл <span class="text-muted small">(за уведомяване)</span></label>
                            <input type="email" class="form-control" name="customer_email" autocomplete="off" placeholder="по желание">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2" id="editDescription" placeholder="При вид „Други“ — опишете произведението"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Цена</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="price" step="0.01">
                            <div class="form-text">Обновява се автоматично при промяна на материалите</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <?php include __DIR__ . '/order_form_discount.php'; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="ready" id="editReady">
                                <label class="form-check-label" for="editReady">Готова</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="paid" id="editPaid">
                                <label class="form-check-label" for="editPaid">Платено</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="collected" id="editCollected">
                                <label class="form-check-label" for="editCollected">Получено</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="editOrderForm" class="btn btn-primary">Запази</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" data-bs-scroll="true" id="addSubOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg order-form-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавяне на подпоръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSubOrderForm" class="order-form" method="post" data-draft-key="sub">
                    <input type="hidden" name="stock_confirmed" value="">
                    <div class="row">
                        <?php
                        $showAcceptedDate = false;
                        $pickupDateId = 'subPickupDate';
                        include __DIR__ . '/order_form_dates.php';
                        ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ширина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Височина (см)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control order-price-trigger" name="height" step="0.1">
                        </div>
                    </div>
                    <?php $additionalProfilesId = 'additionalProfilesSub'; include __DIR__ . '/order_form_materials.php'; ?>
                    <?php $extrasId = 'sub'; include __DIR__ . '/order_form_extras.php'; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Цена</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="price" step="0.01">
                            <div class="form-text">Оставете празно за автоматично изчисление</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="text" inputmode="decimal" autocomplete="off" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <?php include __DIR__ . '/order_form_discount.php'; ?>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="ready" id="subReady">
                                <label class="form-check-label" for="subReady">Готова</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="paid" id="subPaid">
                                <label class="form-check-label" for="subPaid">Платено</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="collected" id="subCollected">
                                <label class="form-check-label" for="subCollected">Получено</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="addSubOrderForm" class="btn btn-primary">Запази</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="stockShortageModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-1"></i> Недостатъчна наличност</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Според склада няма достатъчно материал:</p>
                <ul class="mb-3" id="stockShortageList"></ul>
                <p class="small text-muted mb-0">Ако колегите потвърдят, че могат да изпълнят поръчката, продължете. Поръчката ще получи маркировка, а липсващото количество ще се запише за проверка на склада.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Назад към формата</button>
                <button type="button" class="btn btn-warning" id="stockShortageConfirmBtn">Материалът е наличен</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/notify_modal.php'; ?>

<?php include __DIR__ . '/order_view_modals.php'; ?>
