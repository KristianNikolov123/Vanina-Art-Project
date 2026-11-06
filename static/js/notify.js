/* Известия (toast) и уведомяване на клиента — поръчки и „Напомняния“. */
function showOrdersToast(html, variant = 'success', timeoutMs = 6000) {
    let host = document.getElementById('ordersToastHost');
    if (!host) {
        host = document.createElement('div');
        host.id = 'ordersToastHost';
        host.className = 'position-fixed bottom-0 end-0 p-3';
        host.style.zIndex = '1090';
        document.body.appendChild(host);
    }
    const toast = document.createElement('div');
    toast.className = `alert alert-${variant} shadow-sm py-2 px-3 mb-2 d-flex align-items-center gap-2`;
    toast.innerHTML = html;
    host.appendChild(toast);
    setTimeout(() => toast.remove(), timeoutMs);
    return toast;
}

// --- Уведомяване на клиента (имейл / SMS) ---
let notifyState = null;

async function openNotifyModal(orderId, preferredKind = '') {
    const modalEl = document.getElementById('notifyCustomerModal');
    if (!modalEl) return;
    const result = document.getElementById('notifyResult');
    result.classList.add('d-none');
    document.getElementById('notifySmsPanel')?.classList.add('d-none');
    try {
        const response = await fetch(`${window.BASE_PATH}/api/orders/${orderId}/notification`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            showOrdersToast(data.message || 'Грешка при зареждане.', 'danger');
            return;
        }
        notifyState = { orderId, data };
        const row = document.querySelector(`.order-row[data-order-id="${orderId}"]`);
        const orderData = row?.dataset.order ? JSON.parse(row.dataset.order) : (window.REMINDER_ORDERS?.[orderId] || {});
        document.getElementById('notifyOrderNumber').textContent = orderData.order_number
            ? `${orderData.order_number}${orderData.sub_order_number > 0 ? '.' + orderData.sub_order_number : ''}`
            : orderId;
        const kindSelect = document.getElementById('notifyKind');
        kindSelect.innerHTML = Object.entries(data.kinds).map(([k, label]) => `<option value="${k}">${label}</option>`).join('');
        kindSelect.value = preferredKind && data.kinds[preferredKind] ? preferredKind : data.kind;
        document.getElementById('notifyMessage').value = data.messages[kindSelect.value] || '';
        kindSelect.onchange = () => {
            document.getElementById('notifyMessage').value = data.messages[kindSelect.value] || '';
        };
        const contacts = [];
        contacts.push(data.phone ? `Телефон: ${data.phone}` : 'Няма телефон');
        contacts.push(data.email ? `Имейл: ${data.email}` : 'Няма имейл');
        if (!data.sms_provider) contacts.push('SMS се изпраща от телефона/компютъра (няма настроен SMS доставчик)');
        document.getElementById('notifyContacts').textContent = contacts.join(' · ');
        document.getElementById('notifySmsBtn').disabled = !data.phone;
        document.getElementById('notifyEmailBtn').disabled = !data.email;
        const history = document.getElementById('notifyHistory');
        history.innerHTML = '';
        if (data.history.length > 0) {
            const title = document.createElement('div');
            title.className = 'text-muted mb-1';
            title.textContent = 'Досега:';
            history.appendChild(title);
            data.history.forEach((h) => {
                const status = { sent: 'изпратено', opened: 'отворено за изпращане', failed: 'неуспешно' }[h.status] || h.status;
                const line = document.createElement('div');
                line.textContent = `${h.created_at} — ${h.channel === 'email' ? 'имейл' : 'SMS'} до ${h.recipient} (${status}${h.sent_by ? ', ' + h.sent_by : ''})`;
                history.appendChild(line);
            });
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (error) {
        showOrdersToast('Няма връзка със сървъра.', 'danger');
    }
}

async function sendNotification(channel) {
    if (!notifyState) return;
    const result = document.getElementById('notifyResult');
    const text = document.getElementById('notifyMessage').value;
    const body = new FormData();
    body.append('channel', channel);
    body.append('kind', document.getElementById('notifyKind').value);
    body.append('message', text);
    // Без SMS доставчик: SMS приложението се отваря веднага при клика (иначе браузърът го блокира).
    if (channel === 'sms' && !notifyState.data.sms_provider) {
        const digits = String(notifyState.data.phone || '').replace(/\D/g, '');
        const intl = digits.startsWith('0') ? '+359' + digits.slice(1) : (digits ? '+' + digits : '');
        const smsLink = `sms:${intl}?&body=${encodeURIComponent(text)}`;
        if (isMobileDevice()) {
            window.location.href = smsLink;
        }
        notifyState.pendingSms = { smsLink, intl, text };
    }
    try {
        const response = await fetch(`${window.BASE_PATH}/api/orders/${notifyState.orderId}/notification`, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        result.className = `alert py-1 px-2 small ${data.ok ? 'alert-success' : 'alert-danger'}`;
        result.textContent = data.message || (data.ok ? 'Готово.' : 'Грешка.');
        if (data.ok && data.status === 'opened' && !isMobileDevice() && notifyState.pendingSms) {
            // Компютърът не може да праща SMS: QR код с уеб адрес, който телефонът отваря и прехвърля към SMS.
            const { smsLink, intl } = notifyState.pendingSms;
            showSmsQrPanel(smsLink, intl, text, data.sms_qr_url || `SMSTO:${intl}:${text}`);
            result.textContent = 'Сканирайте QR кода с камерата на телефона, отворете линка и натиснете „Изпрати“. Уведомяването е записано.';
        }
        notifyState.pendingSms = null;
    } catch (error) {
        result.className = 'alert py-1 px-2 small alert-danger';
        result.textContent = 'Няма връзка със сървъра.';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('notifyEmailBtn')?.addEventListener('click', () => sendNotification('email'));
    document.getElementById('notifySmsBtn')?.addEventListener('click', () => sendNotification('sms'));
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-notify-order]');
        if (btn) openNotifyModal(btn.dataset.notifyOrder);
    });
});


function isMobileDevice() {
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}

let qrLibraryPromise = null;
function loadQrLibrary() {
    if (window.qrcode) return Promise.resolve();
    if (!qrLibraryPromise) {
        qrLibraryPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    return qrLibraryPromise;
}

async function showSmsQrPanel(smsLink, phone, text, qrContent) {
    const panel = document.getElementById('notifySmsPanel');
    const qrBox = document.getElementById('notifySmsQr');
    if (!panel || !qrBox) return;
    panel.classList.remove('d-none');
    document.getElementById('notifySmsLink').href = smsLink;
    const copyBtn = document.getElementById('notifyCopyBtn');
    copyBtn.onclick = async () => {
        try {
            await navigator.clipboard.writeText(text);
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Копирано';
        } catch (e) {
            copyBtn.textContent = 'Маркирайте текста ръчно';
        }
    };
    qrBox.innerHTML = '';
    try {
        await loadQrLibrary();
        window.qrcode.stringToBytes = window.qrcode.stringToBytesFuncs['UTF-8'];
        const qr = window.qrcode(0, 'L');
        qr.addData(qrContent, 'Byte');
        qr.make();
        qrBox.innerHTML = qr.createImgTag(4, 8);
    } catch (e) {
        qrBox.textContent = `Няма връзка за QR кода. Номер: ${phone}`;
    }
}
