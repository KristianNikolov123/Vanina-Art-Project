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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Гръб</label>
                            <select class="form-select" name="back">
                                <option value=""></option>
                                <option value="Велпапе">Велп</option>
                                <option value="Бирен картон">Бирен</option>
                                <option value="Сив картон">Сив</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Окачване</label>
                            <select class="form-select" name="hanging">
                                <option value=""></option>
                                <option value="Закачалка">Закач</option>
                                <option value="Две закачалки">2 закач</option>
                                <option value="Връзка">Връзка</option>
                            </select>
                        </div>
                    </div>
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
                            <label class="form-label">Отстъпка</label>
                            <input type="number" class="form-control" name="discount" step="0.01">
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Гръб</label>
                            <select class="form-select" name="back">
                                <option value=""></option>
                                <option value="Велпапе">Велп</option>
                                <option value="Бирен картон">Бирен</option>
                                <option value="Сив картон">Сив</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Окачване</label>
                            <select class="form-select" name="hanging">
                                <option value=""></option>
                                <option value="Закачалка">Закач</option>
                                <option value="Две закачалки">2 закач</option>
                                <option value="Връзка">Връзка</option>
                            </select>
                        </div>
                    </div>
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
                            <div class="form-text">Оставете празно за автоматично изчисление</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Аванс</label>
                            <input type="number" class="form-control" name="advance_payment" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Отстъпка</label>
                            <input type="number" class="form-control" name="discount" step="0.01">
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Гръб</label>
                            <select class="form-select" name="back">
                                <option value=""></option>
                                <option value="Велпапе">Велп</option>
                                <option value="Бирен картон">Бирен</option>
                                <option value="Сив картон">Сив</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Окачване</label>
                            <select class="form-select" name="hanging">
                                <option value=""></option>
                                <option value="Закачалка">Закач</option>
                                <option value="Две закачалки">2 закач</option>
                                <option value="Връзка">Връзка</option>
                            </select>
                        </div>
                    </div>
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
                            <label class="form-label">Отстъпка</label>
                            <input type="number" class="form-control" name="discount" step="0.01">
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
