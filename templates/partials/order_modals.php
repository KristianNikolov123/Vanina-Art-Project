<div class="modal fade" id="addOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нова поръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addOrderForm" class="order-form" action="<?= url_for('add_order') ?>" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата на получаване</label>
                            <input type="date" class="form-control" name="date">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ширина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Височина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="height" step="0.1">
                        </div>
                    </div>
                    <?php $additionalProfilesId = 'additionalProfiles'; include __DIR__ . '/order_form_materials.php'; ?>
                    <?php $extrasId = 'add'; include __DIR__ . '/order_form_extras.php'; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Име на клиент</label>
                            <input type="text" class="form-control" name="customer_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Цена</label>
                            <input type="number" class="form-control" name="price" step="0.01">
                            <div class="form-text">Оставете празно за автоматично изчисление</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="number" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ръчна отстъпка (€)</label>
                            <input type="number" class="form-control order-price-trigger" name="discount" step="0.01">
                            <div class="form-text">Обемна (−5%/−10%) и ученическа се смятат автоматично</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="paid" id="paid">
                                <label class="form-check-label" for="paid">Платено</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="collected" id="collected">
                                <label class="form-check-label" for="collected">Получено</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="addOrderForm" class="btn btn-primary">Запази</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактиране на поръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editOrderForm" class="order-form" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата на получаване</label>
                            <input type="date" class="form-control" name="date" id="editDate">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ширина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Височина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="height" step="0.1">
                        </div>
                    </div>
                    <?php $additionalProfilesId = 'additionalProfilesEdit'; include __DIR__ . '/order_form_materials.php'; ?>
                    <?php $extrasId = 'edit'; include __DIR__ . '/order_form_extras.php'; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Име на клиент</label>
                            <input type="text" class="form-control" name="customer_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2" id="editDescription"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Цена</label>
                            <input type="number" class="form-control" name="price" step="0.01">
                            <div class="form-text">Обновява се автоматично при промяна на материалите</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="number" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ръчна отстъпка (€)</label>
                            <input type="number" class="form-control order-price-trigger" name="discount" step="0.01">
                            <div class="form-text">Обемна (−5%/−10%) и ученическа се смятат автоматично</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="paid" id="editPaid">
                                <label class="form-check-label" for="editPaid">Платено</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
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

<div class="modal fade" id="addSubOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавяне на подпоръчка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSubOrderForm" class="order-form" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата на получаване</label>
                            <input type="date" class="form-control" name="date">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ширина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="width" step="0.1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Височина (cm)</label>
                            <input type="number" class="form-control order-price-trigger" name="height" step="0.1">
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
                            <input type="number" class="form-control" name="price" step="0.01">
                            <div class="form-text">Оставете празно за автоматично изчисление</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="number" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ръчна отстъпка (€)</label>
                            <input type="number" class="form-control order-price-trigger" name="discount" step="0.01">
                            <div class="form-text">Обемна (−5%/−10%) и ученическа се смятат автоматично</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
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

<div class="modal fade" id="viewOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Поръчка <span id="viewOrderNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Дата</div>
                        <div id="viewOrderDate"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Размер</div>
                        <div id="viewOrderSize"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Брой рамки</div>
                        <div id="viewOrderFrameCount"></div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Профил</div>
                        <div id="viewOrderProfile"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Доп. профили</div>
                        <div id="viewOrderAdditionalProfiles"></div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Стъкло</div>
                        <div id="viewOrderGlass"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Паспарту</div>
                        <div id="viewOrderPassepartout"></div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Гръб</div>
                        <div id="viewOrderBack"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Окачване</div>
                        <div id="viewOrderHanging"></div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Клиент</div>
                        <div id="viewOrderCustomer"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Статус</div>
                        <div id="viewOrderStatus"></div>
                    </div>
                </div>
                <div class="mb-3" id="viewOrderDescriptionBlock" style="display: none;">
                    <div class="text-muted small">Описание</div>
                    <div id="viewOrderDescription" style="white-space: pre-wrap;"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Цена</div>
                        <div id="viewOrderPrice" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Аванс</div>
                        <div id="viewOrderAdvance"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Ръчна отстъпка</div>
                        <div id="viewOrderDiscount"></div>
                    </div>
                </div>
                <div class="card border-secondary-subtle mb-0" id="viewOrderExtrasCard" style="display: none;">
                    <div class="card-header py-2 bg-transparent">
                        <small class="fw-semibold"><i class="fas fa-sliders-h me-1"></i> Допълнителни опции</small>
                    </div>
                    <div class="card-body pt-2 pb-3">
                        <ul class="mb-0 ps-3" id="viewOrderExtrasList"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                <button type="button" class="btn btn-primary" id="viewOrderEditBtn">
                    <i class="fas fa-edit"></i> Редактирай
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="descriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Описание</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="fullDescription" style="white-space: pre-wrap;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
            </div>
        </div>
    </div>
</div>
