<div class="modal" id="notifyCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-1"></i> Уведоми клиента — № <span id="notifyOrderNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small mb-0">Повод</label>
                    <select class="form-select form-select-sm" id="notifyKind"></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Текст</label>
                    <textarea class="form-control" id="notifyMessage" rows="4"></textarea>
                </div>
                <div class="small text-muted mb-2" id="notifyContacts"></div>
                <div class="alert py-1 px-2 small d-none" id="notifyResult"></div>
                <div class="border rounded p-2 mb-2 text-center d-none" id="notifySmsPanel">
                    <div class="small mb-2">Сканирайте с камерата на телефона и отворете линка — появява се SMS с готовия номер и текст. Натиснете „Изпрати“.</div>
                    <div id="notifySmsQr" class="d-inline-block mb-2"></div>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="notifyCopyBtn"><i class="fas fa-copy"></i> Копирай текста</button>
                        <a class="btn btn-sm btn-outline-secondary" id="notifySmsLink" href="#"><i class="fas fa-mobile-alt"></i> Отвори през „Връзка с телефона“</a>
                    </div>
                </div>
                <div class="small notify-history" id="notifyHistory"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Затвори</button>
                <button type="button" class="btn btn-outline-primary" id="notifySmsBtn"><i class="fas fa-sms"></i> SMS</button>
                <button type="button" class="btn btn-primary" id="notifyEmailBtn"><i class="fas fa-envelope"></i> Имейл</button>
            </div>
        </div>
    </div>
</div>
