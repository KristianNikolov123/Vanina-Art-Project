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
