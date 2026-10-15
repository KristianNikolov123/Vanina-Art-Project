// Function to add additional profile fields
function addProfileField(containerId = 'additionalProfiles') {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'input-group mt-2';
    div.innerHTML = `
        <input type="text" class="form-control order-price-trigger" name="additional_profiles[]" list="profileCatalog">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
            <i class="fas fa-minus"></i>
        </button>
    `;
    container.appendChild(div);
}

function appendAdditionalProfileField(container, value) {
    const div = document.createElement('div');
    div.className = 'input-group mt-2';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control order-price-trigger';
    input.name = 'additional_profiles[]';
    input.setAttribute('list', 'profileCatalog');
    input.value = value;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-danger';
    button.innerHTML = '<i class="fas fa-minus"></i>';
    button.addEventListener('click', () => div.remove());

    div.appendChild(input);
    div.appendChild(button);
    container.appendChild(div);
}

function clearAddOrderForm() {
    const form = document.getElementById('addOrderForm');
    if (!form) return;

    form.reset();

    const profilesContainer = document.getElementById('additionalProfiles');
    if (profilesContainer) profilesContainer.innerHTML = '';

    form.querySelectorAll('select').forEach((select) => {
        select.selectedIndex = 0;
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.checked = false;
    });

    const frameCount = form.querySelector('[name="frame_count"]');
    if (frameCount) frameCount.value = '1';

    const openings = form.querySelector('[name="passepartout_openings"]');
    if (openings) openings.value = '1';

    const priceInput = form.querySelector('[name="price"]');
    if (priceInput) priceInput.value = '';

    const preview = form.querySelector('.order-pricing-preview');
    if (preview) {
        preview.style.display = 'none';
        preview.innerHTML = '';
    }

    expandOrderExtrasIfNeeded(form, null);
    syncHangingWeightField(form);
    syncPassepartoutUseFields(form);
    syncProfileReadyFrameFields(form);
    syncAreaTierSuggestions(form);
    form.dataset.autoPrice = '1';
    setOrderFormAcceptedDateToday(form);
}

function formatDateDdMmYyyy(dateStr) {
    if (!dateStr) return '';
    const iso = String(dateStr).slice(0, 10);
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        const [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    }
    if (String(dateStr).includes('/')) {
        return String(dateStr);
    }
    return String(dateStr);
}

function todayDdMmYyyy() {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = now.getFullYear();
    return `${d}/${m}/${y}`;
}

function setOrderFormAcceptedDateToday(form) {
    const accepted = form.querySelector('[name="accepted_date"]');
    if (accepted) {
        accepted.value = todayDdMmYyyy();
    }
}

function resolvePassepartoutCatalogId(passepartoutLabel) {
    if (!passepartoutLabel || !window.PASSEPARTOUT_MAP) {
        return '';
    }
    if (window.PASSEPARTOUT_MAP[passepartoutLabel]) {
        return String(window.PASSEPARTOUT_MAP[passepartoutLabel]);
    }
    const base = String(passepartoutLabel).replace(/\s+\([^)]+\)\s*$/, '');
    const id = window.PASSEPARTOUT_MAP[base];
    return id ? String(id) : '';
}

function formatDiscountDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    const num = Number(value);
    if (!Number.isFinite(num) || num <= 0) {
        return '—';
    }
    const trimmed = num.toFixed(2).replace(/\.?0+$/, '');
    return `${trimmed}%`;
}

function isVrazkaHanging(value) {
    return String(value || '').trim() === 'Връзка';
}

function syncPassepartoutUseFields(form) {
    if (!form) return;

    const useSelect = form.querySelector('[name="passepartout_use"]');
    const primaryLabel = form.querySelector('.passepartout-primary-label');
    const backgroundField = form.querySelector('.passepartout-background-field');
    if (!useSelect) return;

    const use = useSelect.value || 'passepartout';
    const showBackground = use === 'both';

    if (backgroundField) {
        backgroundField.classList.toggle('d-none', !showBackground);
        if (!showBackground) {
            const bgSelect = backgroundField.querySelector('[name="passepartout_background_id"]');
            if (bgSelect) bgSelect.value = '';
        }
    }

    if (primaryLabel) {
        if (use === 'background') {
            primaryLabel.textContent = 'Фон (материал)';
        } else {
            primaryLabel.textContent = 'Паспарту';
        }
    }
}

function syncProfileReadyFrameFields(form) {
    if (!form) return;
    const checkbox = form.querySelector('[name="profile_ready_frame"]');
    const priceWrap = form.querySelector('.profile-ready-frame-price-wrap');
    const priceInput = form.querySelector('[name="profile_ready_frame_price"]');
    if (!checkbox || !priceWrap) return;

    const enabled = checkbox.checked;
    priceWrap.classList.toggle('d-none', !enabled);
    if (!enabled && priceInput) {
        priceInput.value = '';
    }
}

function framePieceSqmFromForm(form) {
    const w = parseFloat(form.querySelector('[name="width"]')?.value || '0');
    const h = parseFloat(form.querySelector('[name="height"]')?.value || '0');
    if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
        return 0;
    }
    return (w * h) / 10000;
}

function assemblyAreaTierFromSqm(sqm) {
    if (sqm <= 0) return 0;
    if (sqm <= 0.5) return 1;
    if (sqm <= 0.8) return 2;
    return 3;
}

function syncAreaTierSuggestions(form) {
    if (!form) return;
    const tier = assemblyAreaTierFromSqm(framePieceSqmFromForm(form));
    form.querySelectorAll('.area-tier-check').forEach((input) => {
        const item = input.closest('.extra-service-item');
        if (!item) return;
        const inputTier = parseInt(input.dataset.areaTier || '0', 10);
        item.classList.toggle('area-tier-suggested', tier > 0 && inputTier === tier);
    });
}

function initAreaTierExclusiveChecks() {
    document.addEventListener('change', (event) => {
        const input = event.target;
        if (!input.classList || !input.classList.contains('area-tier-check') || !input.checked) {
            return;
        }
        const group = input.dataset.tierGroup;
        if (!group) return;
        const form = input.closest('.order-form');
        if (!form) return;
        form.querySelectorAll(`.area-tier-check[data-tier-group="${group}"]`).forEach((other) => {
            if (other !== input) {
                other.checked = false;
            }
        });
        if (form.dataset.autoPrice !== '0') {
            updateOrderPricing(form);
        }
    });
}

const ORDER_WORKLOAD_OPEN_KEY = 'vaninaOrdersWorkloadOpen';

function syncWorkloadChartActiveDay(isoDate) {
    document.querySelectorAll('.orders-workload-row').forEach((btn) => {
        btn.classList.toggle('is-active', Boolean(isoDate) && btn.dataset.workloadDate === isoDate);
    });
}

function initOrderWorkloadCollapse() {
    const collapseEl = document.getElementById('ordersWorkloadCollapse');
    if (!collapseEl) return;

    const stored = sessionStorage.getItem(ORDER_WORKLOAD_OPEN_KEY);
    const instance = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
    if (stored === '0') {
        instance.hide();
    } else if (stored === '1') {
        instance.show();
    }

    collapseEl.addEventListener('hidden.bs.collapse', () => {
        sessionStorage.setItem(ORDER_WORKLOAD_OPEN_KEY, '0');
    });
    collapseEl.addEventListener('shown.bs.collapse', () => {
        sessionStorage.setItem(ORDER_WORKLOAD_OPEN_KEY, '1');
    });
}

function initOrderWorkloadChart() {
    document.querySelectorAll('.orders-workload-row').forEach((btn) => {
        btn.addEventListener('click', () => {
            const iso = btn.dataset.workloadDate || '';
            if (!iso) return;
            const current = getOrderDayFilterValue();
            setOrderDayFilter(current === iso ? '' : iso);
        });
    });
    syncWorkloadChartActiveDay(getOrderDayFilterValue());
    initOrderWorkloadCollapse();
}

function parsePrimaryPassepartoutLabel(label) {
    if (!label) return '';
    const text = String(label);
    if (text.includes(' + ')) {
        return text.split(' + ')[0].trim();
    }
    return text;
}

function syncHangingWeightField(form) {
    if (!form) return;

    const hanging = form.querySelector('[name="hanging"]');
    const weightWrap = form.querySelector('.hanging-weight-field');
    const weightInput = form.querySelector('[name="weight_kg"]');
    if (!hanging || !weightWrap) {
        return;
    }

    const showWeight = isVrazkaHanging(hanging.value);
    weightWrap.classList.toggle('d-none', !showWeight);
    if (!showWeight && weightInput) {
        weightInput.value = '';
    }
}

function getOrderRowSortKeys(row) {
    const data = row.dataset.order ? JSON.parse(row.dataset.order) : {};
    const number = (Number(data.order_number) || 0) + (Number(data.sub_order_number) || 0) / 100;
    const date = data.date || '';
    return { number, date };
}

function sortOrderRows(mode) {
    const tbody = document.querySelector('.orders-container tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('.order-row'));
    rows.sort((a, b) => {
        const ka = getOrderRowSortKeys(a);
        const kb = getOrderRowSortKeys(b);

        switch (mode) {
            case 'number-asc':
                return ka.number - kb.number || ka.date.localeCompare(kb.date);
            case 'number-desc':
                return kb.number - ka.number || kb.date.localeCompare(ka.date);
            case 'date-asc':
                return ka.date.localeCompare(kb.date) || ka.number - kb.number;
            case 'date-desc':
                return kb.date.localeCompare(ka.date) || kb.number - ka.number;
            default:
                return kb.number - ka.number;
        }
    });

    rows.forEach((row) => tbody.appendChild(row));
}

const ORDER_DAY_FILTER_KEY = 'vaninaOrdersDayFilter';

function getOrderRowIsoDate(row) {
    if (row.dataset.orderDate) {
        return row.dataset.orderDate.slice(0, 10);
    }
    if (row.dataset.order) {
        try {
            const data = JSON.parse(row.dataset.order);
            return (data.date || '').slice(0, 10);
        } catch (e) {
            return '';
        }
    }
    return '';
}

function getOrderDayFilterValue() {
    return document.getElementById('orderDayFilter')?.value || '';
}

function formatOrderDayLabel(isoDate) {
    if (!isoDate) {
        return 'Всички дни';
    }
    const parts = isoDate.split('-');
    if (parts.length !== 3) {
        return isoDate;
    }
    const dt = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    if (Number.isNaN(dt.getTime())) {
        return isoDate;
    }
    return dt.toLocaleDateString('bg-BG', { day: 'numeric', month: 'short', year: 'numeric' });
}

function todayIsoLocal() {
    const dt = new Date();
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function shiftIsoDate(isoDate, dayDelta) {
    const parts = isoDate.split('-').map(Number);
    if (parts.length !== 3) {
        return isoDate;
    }
    const dt = new Date(parts[0], parts[1] - 1, parts[2]);
    dt.setDate(dt.getDate() + dayDelta);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function setOrderDayFilter(isoDate, persist) {
    const input = document.getElementById('orderDayFilter');
    const label = document.getElementById('orderDayPickerLabel');
    const pickerBtn = document.getElementById('orderDayPickerBtn');
    const clearBtn = document.getElementById('orderDayClear');
    if (!input || !label) {
        return;
    }

    input.value = isoDate || '';
    label.textContent = formatOrderDayLabel(isoDate);
    pickerBtn?.classList.toggle('active', Boolean(isoDate));
    clearBtn?.classList.toggle('d-none', !isoDate);

    if (persist !== false) {
        if (isoDate) {
            sessionStorage.setItem(ORDER_DAY_FILTER_KEY, isoDate);
        } else {
            sessionStorage.removeItem(ORDER_DAY_FILTER_KEY);
        }
    }

    applyOrderRowVisibility();
    syncWorkloadChartActiveDay(isoDate || '');
}

function initOrderDayFilter() {
    const input = document.getElementById('orderDayFilter');
    const pickerBtn = document.getElementById('orderDayPickerBtn');
    const prevBtn = document.getElementById('orderDayPrev');
    const nextBtn = document.getElementById('orderDayNext');
    const clearBtn = document.getElementById('orderDayClear');
    if (!input || !pickerBtn) {
        return;
    }

    const saved = sessionStorage.getItem(ORDER_DAY_FILTER_KEY) || '';
    if (saved) {
        setOrderDayFilter(saved, false);
    } else {
        setOrderDayFilter('', false);
    }

    pickerBtn.addEventListener('click', () => {
        if (typeof input.showPicker === 'function') {
            input.showPicker();
        } else {
            input.click();
        }
    });

    input.addEventListener('change', () => {
        setOrderDayFilter(input.value || '');
    });

    prevBtn?.addEventListener('click', () => {
        const current = getOrderDayFilterValue() || todayIsoLocal();
        setOrderDayFilter(shiftIsoDate(current, -1));
    });

    nextBtn?.addEventListener('click', () => {
        const current = getOrderDayFilterValue() || todayIsoLocal();
        setOrderDayFilter(shiftIsoDate(current, 1));
    });

    clearBtn?.addEventListener('click', () => {
        setOrderDayFilter('');
    });
}

function applyOrderRowVisibility() {
    const searchRaw = document.getElementById('searchInput')?.value || '';
    const dayFilter = getOrderDayFilterValue();
    const paymentFilter = document.querySelector('[data-filter-group="payment"].active')?.dataset.filter;
    const collectionFilter = document.querySelector('[data-filter-group="collection"].active')?.dataset.filter;

    document.querySelectorAll('.order-row').forEach((row) => {
        const haystack = typeof buildOrderRowSearchHaystack === 'function'
            ? buildOrderRowSearchHaystack(row)
            : (row.textContent || '');
        const matchesSearch = typeof rowMatchesTokenSearch === 'function'
            ? rowMatchesTokenSearch(haystack, searchRaw)
            : haystack.toLowerCase().includes(searchRaw.trim().toLowerCase());

        const order = row.dataset.order ? JSON.parse(row.dataset.order) : null;
        const paid = order ? Boolean(order.paid) : row.querySelector('.badge.bg-success') !== null;
        const collected = order ? Boolean(order.collected) : row.querySelector('.badge.bg-info') !== null;

        let matchesPayment = true;
        if (paymentFilter === 'paid') matchesPayment = paid;
        if (paymentFilter === 'unpaid') matchesPayment = !paid;

        let matchesCollection = true;
        if (collectionFilter === 'collected') matchesCollection = collected;
        if (collectionFilter === 'uncollected') matchesCollection = !collected;

        const matchesDay = !dayFilter || getOrderRowIsoDate(row) === dayFilter;

        row.style.display = matchesSearch && matchesPayment && matchesCollection && matchesDay ? '' : 'none';
    });
}

function syncStatusFilterAllButton() {
    const allButton = document.querySelector('.orders-toolbar__filter-all');
    if (!allButton) return;

    const hasPayment = document.querySelector('[data-filter-group="payment"].active');
    const hasCollection = document.querySelector('[data-filter-group="collection"].active');
    allButton.classList.toggle('active', !hasPayment && !hasCollection);
}

// Function to add a sub-order
function addSubOrder(orderId) {
    const form = document.getElementById('addSubOrderForm');
    form.action = `${window.BASE_PATH}/add_sub_order/${orderId}`;

    const modal = new bootstrap.Modal(document.getElementById('addSubOrderModal'));
    modal.show();
}

// Function to view an order (read-only)
let currentViewOrderId = null;

function buildOrderExtrasLabels(data) {
    const labels = [];
    const servicesMap = window.SERVICES_MAP || {};

    if (data.urgent) labels.push('Спешна (+50%)');
    if (data.student_discount) labels.push('Ученик (−10%)');
    if (data.complex_passepartout) labels.push('Сложно паспарту (+50%)');
    if (data.frame_box) labels.push('Рамка тип кутия');
    if (data.frame_nonstandard) labels.push('Нестандартна форма');
    if (data.frame_high_complexity) labels.push('Висока сложност (+50%)');
    if (data.client_passepartout_cutting) labels.push('Рязане паспарту на клиент');

    if (data.frame_shape === 'ellipse_12') {
        labels.push('Елипса / кръг 12 страни');
    } else if (data.frame_shape === 'circle_24') {
        labels.push('Кръг 24 страни');
    }

    const openings = Number(data.passepartout_openings ?? 1);
    if (openings > 1) {
        labels.push(`${openings} отвора паспарту`);
    }

    const transportKm = Number(data.transport_km ?? 0);
    if (transportKm > 0) {
        const kmText = Number.isInteger(transportKm) ? transportKm : transportKm.toFixed(2).replace(/\.?0+$/, '');
        labels.push(`Транспорт ${kmText} км`);
    }

    let serviceIds = [];
    try {
        serviceIds = typeof data.extra_services === 'string'
            ? JSON.parse(data.extra_services || '[]')
            : (data.extra_services || []);
    } catch (e) {
        serviceIds = [];
    }

    serviceIds.forEach((id) => {
        const name = servicesMap[id] || servicesMap[String(id)];
        if (name) labels.push(name);
    });

    return labels;
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return String(value);
}

function viewOrder(orderId) {
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!row || !row.dataset.order) return;

    const data = JSON.parse(row.dataset.order);
    currentViewOrderId = orderId;

    const orderLabel = data.sub_order_number > 0
        ? `${data.order_number}.${data.sub_order_number}`
        : String(data.order_number ?? orderId);

    document.getElementById('viewOrderNumber').textContent = `№ ${orderLabel}`;
    const datesCell = row.querySelector('.order-dates');
    const acceptedCell = datesCell?.querySelector('.date-accepted') || row.querySelector('.date-accepted');
    const pickupCell = datesCell?.querySelector('.date-pickup') || row.querySelector('.date-pickup');
    const pickupFromCell = pickupCell?.textContent.replace(/^получ\.\s*/i, '').trim();
    document.getElementById('viewOrderAcceptedDate').textContent = acceptedCell?.textContent.trim()
        || formatDateDdMmYyyy(data.accepted_date)
        || '—';
    document.getElementById('viewOrderPickupDate').textContent = pickupFromCell
        || formatDateDdMmYyyy(data.date)
        || '—';
    document.getElementById('viewOrderSize').textContent = data.width && data.height
        ? `${data.width} × ${data.height} cm`
        : '—';
    document.getElementById('viewOrderFrameCount').textContent = displayValue(data.frame_count ?? 1);
    document.getElementById('viewOrderProfile').textContent = displayValue(data.profile);
    document.getElementById('viewOrderAdditionalProfiles').textContent = displayValue(data.additional_profiles);
    document.getElementById('viewOrderGlass').textContent = displayValue(data.glass);
    document.getElementById('viewOrderBack').textContent = displayValue(data.back);
    document.getElementById('viewOrderHanging').textContent = displayValue(data.hanging);
    document.getElementById('viewOrderCustomer').textContent = displayValue(data.customer_name);
    const viewPhone = document.getElementById('viewOrderPhone');
    if (viewPhone) {
        viewPhone.textContent = displayValue(data.customer_phone);
    }

    let passepartoutText = displayValue(data.passepartout);
    if (data.passepartout_bill_width && data.passepartout_bill_height) {
        passepartoutText += ` (фактурирано: ${data.passepartout_bill_width}×${data.passepartout_bill_height} cm)`;
    }
    document.getElementById('viewOrderPassepartout').textContent = passepartoutText;

    const statusEl = document.getElementById('viewOrderStatus');
    statusEl.innerHTML = `
        <span class="badge ${data.paid ? 'bg-success' : 'bg-danger'}">${data.paid ? 'Платена' : 'Неплатена'}</span>
        <span class="badge ${data.collected ? 'bg-info' : 'bg-warning'}">${data.collected ? 'Получена' : 'Неполучена'}</span>
    `;

    const descBlock = document.getElementById('viewOrderDescriptionBlock');
    const desc = (data.description ?? '').trim();
    if (desc) {
        document.getElementById('viewOrderDescription').textContent = desc;
        descBlock.style.display = '';
    } else {
        descBlock.style.display = 'none';
    }

    document.getElementById('viewOrderPrice').textContent = data.price != null && data.price !== ''
        ? `${data.price} €`
        : '—';
    document.getElementById('viewOrderAdvance').textContent = data.advance_payment != null && data.advance_payment !== ''
        ? `${data.advance_payment} €`
        : '—';
    document.getElementById('viewOrderDiscount').textContent = formatDiscountDisplay(data.discount);

    const extrasLabels = buildOrderExtrasLabels(data);
    const extrasCard = document.getElementById('viewOrderExtrasCard');
    const extrasList = document.getElementById('viewOrderExtrasList');

    if (extrasLabels.length > 0) {
        extrasList.innerHTML = '';
        extrasLabels.forEach((label) => {
            const li = document.createElement('li');
            li.textContent = label;
            extrasList.appendChild(li);
        });
        extrasCard.style.display = '';
    } else {
        extrasList.innerHTML = '';
        extrasCard.style.display = 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('viewOrderModal'));
    modal.show();
}

function editOrderFromView() {
    if (!currentViewOrderId) return;
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewOrderModal'));
    if (viewModal) viewModal.hide();
    editOrder(currentViewOrderId);
}

// Function to edit an order
function editOrder(orderId) {
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!row || !row.dataset.order) return;

    const data = JSON.parse(row.dataset.order);
    const form = document.getElementById('editOrderForm');
    form.dataset.autoPrice = '1';

    const acceptedInput = form.querySelector('[name="accepted_date"]');
    if (acceptedInput) {
        acceptedInput.value = formatDateDdMmYyyy(data.accepted_date || data.date);
    }
    form.querySelector('[name="date"]').value = formatDateDdMmYyyy(data.date);
    form.querySelector('[name="width"]').value = data.width ?? '';
    form.querySelector('[name="height"]').value = data.height ?? '';
    form.querySelector('[name="profile"]').value = data.profile ?? '';
    form.querySelector('[name="glass"]').value = data.glass ?? '';
    const passepartoutSelect = form.querySelector('[name="passepartout_id"]');
    if (passepartoutSelect && data.passepartout) {
        passepartoutSelect.value = resolvePassepartoutCatalogId(parsePrimaryPassepartoutLabel(data.passepartout));
    }
    const passepartoutUse = form.querySelector('[name="passepartout_use"]');
    if (passepartoutUse) {
        let use = data.passepartout_use || '';
        if (!use && data.passepartout) {
            const label = String(data.passepartout);
            if (label.includes(' + ') || (data.passepartout_background && String(data.passepartout_background).trim() !== '')) {
                use = 'both';
            } else if (label.includes('(фон)')) {
                use = 'background';
            } else {
                use = 'passepartout';
            }
        }
        passepartoutUse.value = ['passepartout', 'background', 'both'].includes(use) ? use : 'passepartout';
    }
    const passepartoutBgSelect = form.querySelector('[name="passepartout_background_id"]');
    if (passepartoutBgSelect && data.passepartout_background) {
        passepartoutBgSelect.value = resolvePassepartoutCatalogId(data.passepartout_background);
    }
    syncPassepartoutUseFields(form);
    const readyFrame = form.querySelector('[name="profile_ready_frame"]');
    if (readyFrame) {
        readyFrame.checked = Boolean(data.profile_ready_frame);
    }
    const readyFramePrice = form.querySelector('[name="profile_ready_frame_price"]');
    if (readyFramePrice) {
        readyFramePrice.value = data.profile_ready_frame_price ?? '';
    }
    syncProfileReadyFrameFields(form);
    form.querySelector('[name="back"]').value = data.back ?? '';
    form.querySelector('[name="hanging"]').value = data.hanging ?? '';
    const weightKg = form.querySelector('[name="weight_kg"]');
    if (weightKg) {
        weightKg.value = data.weight_kg ?? '';
    }
    syncHangingWeightField(form);
    form.querySelector('[name="customer_name"]').value = data.customer_name ?? '';
    const customerPhone = form.querySelector('[name="customer_phone"]');
    if (customerPhone) {
        customerPhone.value = data.customer_phone ?? '';
    }
    form.querySelector('[name="price"]').value = data.price ?? '';
    form.querySelector('[name="advance_payment"]').value = data.advance_payment ?? '';
    form.querySelector('[name="discount"]').value = data.discount ?? '';
    form.querySelector('[name="frame_count"]').value = data.frame_count ?? 1;
    form.querySelector('[name="paid"]').checked = Boolean(data.paid);
    form.querySelector('[name="collected"]').checked = Boolean(data.collected);
    form.querySelector('[name="description"]').value = data.description ?? '';
    loadOrderExtras(form, data);

    const additionalProfilesContainer = document.getElementById('additionalProfilesEdit');
    additionalProfilesContainer.innerHTML = '';
    if (data.additional_profiles) {
        data.additional_profiles.split(', ').forEach((profile) => {
            if (profile.trim() !== '') {
                appendAdditionalProfileField(additionalProfilesContainer, profile);
            }
        });
    }

    form.action = `${window.BASE_PATH}/edit_order/${orderId}`;

    updateOrderPricing(form);

    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

function loadOrderExtras(form, data) {
    const openings = form.querySelector('[name="passepartout_openings"]');
    if (openings) openings.value = data.passepartout_openings ?? 1;

    const transportKm = form.querySelector('[name="transport_km"]');
    if (transportKm) transportKm.value = data.transport_km ?? '';

    const urgent = form.querySelector('[name="urgent"]');
    if (urgent) urgent.checked = Boolean(data.urgent);

    const student = form.querySelector('[name="student_discount"]');
    if (student) student.checked = Boolean(data.student_discount);

    const complex = form.querySelector('[name="complex_passepartout"]');
    if (complex) complex.checked = Boolean(data.complex_passepartout);

    let serviceIds = [];
    try {
        serviceIds = typeof data.extra_services === 'string'
            ? JSON.parse(data.extra_services || '[]')
            : (data.extra_services || []);
    } catch (e) {
        serviceIds = [];
    }

    form.querySelectorAll('.extra-service-check').forEach((checkbox) => {
        checkbox.checked = serviceIds.includes(parseInt(checkbox.value, 10));
    });

    const frameBox = form.querySelector('[name="frame_box"]');
    if (frameBox) frameBox.checked = Boolean(data.frame_box);

    const frameNonstandard = form.querySelector('[name="frame_nonstandard"]');
    if (frameNonstandard) frameNonstandard.checked = Boolean(data.frame_nonstandard);

    const frameHighComplexity = form.querySelector('[name="frame_high_complexity"]');
    if (frameHighComplexity) frameHighComplexity.checked = Boolean(data.frame_high_complexity);

    const clientPassepartoutCutting = form.querySelector('[name="client_passepartout_cutting"]');
    if (clientPassepartoutCutting) clientPassepartoutCutting.checked = Boolean(data.client_passepartout_cutting);

    const frameShape = form.querySelector('[name="frame_shape"]');
    if (frameShape) frameShape.value = data.frame_shape ?? '';

    const weightKg = form.querySelector('[name="weight_kg"]');
    if (weightKg) weightKg.value = data.weight_kg ?? '';

    syncHangingWeightField(form);
    expandOrderExtrasIfNeeded(form, data);
}

function orderHasExtras(data) {
    let serviceIds = [];
    try {
        serviceIds = typeof data.extra_services === 'string'
            ? JSON.parse(data.extra_services || '[]')
            : (data.extra_services || []);
    } catch (e) {
        serviceIds = [];
    }

    return Boolean(
        data.urgent
        || data.student_discount
        || data.complex_passepartout
        || data.frame_box
        || data.frame_nonstandard
        || data.frame_high_complexity
        || data.client_passepartout_cutting
        || (data.frame_shape && data.frame_shape !== '')
        || (data.passepartout_openings && Number(data.passepartout_openings) > 1)
        || (data.transport_km && Number(data.transport_km) > 0)
        || data.profile_ready_frame
        || (Array.isArray(serviceIds) && serviceIds.length > 0)
    );
}

function expandOrderExtrasIfNeeded(form, data) {
    const collapseEl = form.querySelector('.order-extras-collapse');
    if (!collapseEl) return;

    if (data && orderHasExtras(data)) {
        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
    } else {
        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).hide();
    }
}

function collectOrderFormData(form) {
    const formData = new FormData(form);
    return formData;
}

async function updateOrderPricing(form) {
    const preview = form.querySelector('.order-pricing-preview');
    if (!preview) return;

    const formData = new FormData(form);

    try {
        const response = await fetch(`${window.BASE_PATH}/calculate_price`, {
            method: 'POST',
            body: formData
        });
        if (!response.ok) {
            preview.style.display = 'none';
            return;
        }

        const pricing = await response.json();
        const lines = [];

        const profileName = formData.get('profile');
        if (pricing.profile_lines && pricing.profile_lines.length > 0) {
            pricing.profile_lines.forEach((line, index) => {
                const label = `Профил ${index + 1}`;
                if (!line.found) {
                    lines.push(`<span class="text-warning">${label} („${line.name}"): не е в Склад → Профили</span>`);
                    return;
                }
                const dims = line.bill_width && line.bill_height
                    ? ` при ${line.bill_width}×${line.bill_height} cm`
                    : '';
                const costPart = line.cost > 0 ? ` → ${line.cost.toFixed(2)} €` : '';
                const laborPart = line.labor_cost > 0 ? `, труд ${line.labor_cost.toFixed(2)} €` : '';
                const meterPart = line.fir_meters > 0 && line.base_meters != null
                    ? `${line.base_meters.toFixed(2)} + ${line.fir_meters.toFixed(2)} фира = ${line.meters.toFixed(2)} л.м.`
                    : `${line.meters} л.м.`;
                let text = `${label} (${line.name}): ${meterPart}${dims}${costPart}${laborPart}`;
                if (index === 0 && pricing.profile_wide_billing) {
                    text += ' (широк профил → 3 м/страна)';
                }
                if (index === 0 && pricing.frame_shape) {
                    text += ` (${pricing.frame_shape})`;
                }
                lines.push(text);
            });
            if (pricing.frame_labor_cost > 0 && !(pricing.profile_lines && pricing.profile_lines.some((line) => line.labor_cost > 0))) {
                lines.push(`Труд по рамка → ${pricing.frame_labor_cost.toFixed(2)} €`);
            } else if (pricing.frame_labor_cost > 0 && pricing.profile_lines && pricing.profile_lines.length > 1) {
                lines.push(`Общ труд по профили → ${pricing.frame_labor_cost.toFixed(2)} €`);
            }
        } else if (pricing.profile_material_cost > 0 || pricing.frame_labor_cost > 0) {
            const parts = [];
            if (pricing.profile_material_cost > 0) {
                parts.push(`материал ${pricing.profile_material_cost.toFixed(2)} €`);
            }
            if (pricing.frame_labor_cost > 0) {
                parts.push(`труд ${pricing.frame_labor_cost.toFixed(2)} €`);
            }
            if (pricing.profile_wide_billing) {
                parts.push('широк профил → 3 м/страна');
            }
            if (pricing.frame_shape) {
                parts.push(pricing.frame_shape);
            }
            lines.push(`Профил (${pricing.profile_meters} л.м.): ${parts.join(' + ')}`);
        } else if (profileName && pricing.profile_meters > 0 && pricing.profile_found === false) {
            lines.push(`<span class="text-warning">Профил „${profileName}" не е в Склад → Профили (липсва цена на материал)</span>`);
        }
        if (pricing.profile_ready_frame_cost > 0) {
            lines.push(`Готова рамка → ${pricing.profile_ready_frame_cost.toFixed(2)} €`);
        }
        if (pricing.frame_extras && pricing.frame_extras.length > 0) {
            pricing.frame_extras.forEach((extra) => {
                lines.push(`${extra.name} → ${extra.cost.toFixed(2)} €`);
            });
        }
        if (pricing.glass_cost > 0) {
            lines.push(`Стъкло: ${pricing.glass_sqm} кв.м. → ${pricing.glass_cost.toFixed(2)} €`);
        }
        if (pricing.back_cost > 0) {
            lines.push(`Гръб: ${pricing.back_sqm} кв.м. → ${pricing.back_cost.toFixed(2)} €`);
        }
        if (pricing.hanging_cost > 0) {
            const hanging = pricing.hanging;
            let hangingText = 'Окачване';
            if (hanging && hanging.uses_weight_tiers) {
                const parts = [];
                if (hanging.tier_label) parts.push(hanging.tier_label);
                if (hanging.unit_price) parts.push(`${hanging.unit_price.toFixed(2)} €/л.м.`);
                if (hanging.weight_kg) parts.push(`${hanging.weight_kg} kg`);
                if (hanging.linear_meters > 0) parts.push(`${hanging.linear_meters.toFixed(2)} л.м.`);
                if (parts.length > 0) {
                    hangingText += `: ${parts.join(', ')}`;
                }
            }
            lines.push(`${hangingText} → ${pricing.hanging_cost.toFixed(2)} €`);
        }
        if (pricing.passepartout) {
            const pp = pricing.passepartout;
            const usageText = pp.sheet_usage
                ? `, ${pp.sheet_usage} листа ${pp.sheet_name}`
                : '';
            const multiSheetText = pp.sheets_charged > 1
                ? ` (${pp.sheets_charged}× ${pp.sheet_name})`
                : '';
            const laborParts = [];
            if (pricing.passepartout_material_cost > 0) {
                laborParts.push(`материал ${pricing.passepartout_material_cost.toFixed(2)}`);
            }
            if (pricing.passepartout_labor_cost > 0) {
                laborParts.push(`рязане ${pricing.passepartout_labor_cost.toFixed(2)}`);
            }
            const labor = laborParts.length > 0 ? ` (${laborParts.join(' + ')} €)` : '';
            const openingsText = pricing.passepartout_openings > 1
                ? `, ${pricing.passepartout_openings} отвора`
                : '';
            const priceDetail = pp.pricing_mode === 'tier'
                ? `, ${pp.tier_label} (${pp.unit_price.toFixed(2)} €/бр.)`
                : '';
            const formatPpLayerLine = (layer, roleLabel, shareCost) => {
                if (!layer || !layer.passepartout_name) return '';
                const layerUsage = layer.sheet_usage
                    ? `, ${layer.sheet_usage} листа ${layer.sheet_name || pp.sheet_name}`
                    : '';
                const layerPrice = layer.pricing_mode === 'tier' && layer.tier_label
                    ? `, ${layer.tier_label} (${layer.unit_price.toFixed(2)} €/бр.)`
                    : '';
                const costPart = shareCost != null ? ` → ${shareCost.toFixed(2)} €` : '';
                return `${roleLabel}: ${layer.passepartout_name}, ${layer.bill_width}×${layer.bill_height} cm${layerPrice}${layerUsage}${costPart}`;
            };

            if (pp.passepartout_use === 'both' && pp.background_layer) {
                const bg = pp.background_layer;
                const ppMaterial = Math.max(0, pricing.passepartout_material_cost - (bg.cost || 0));
                const primaryLine = formatPpLayerLine(pp, 'Паспарту', ppMaterial);
                const bgLine = formatPpLayerLine(bg, 'Фон', bg.cost);
                if (primaryLine) lines.push(primaryLine);
                if (bgLine) lines.push(bgLine);
                if (pricing.passepartout_labor_cost > 0) {
                    lines.push(`Рязане паспарту → ${pricing.passepartout_labor_cost.toFixed(2)} €`);
                }
                lines.push(`Паспарту общо → ${pricing.passepartout_cost.toFixed(2)} €`);
            } else {
                const useLabel = pp.use_label ? ` (${pp.use_label})` : '';
                lines.push(
                    `Паспарту${useLabel}: ${pp.bill_width}×${pp.bill_height} cm${multiSheetText}${openingsText}${priceDetail}${usageText}${labor} → ${pricing.passepartout_cost.toFixed(2)} €`
                );
            }
        } else if (pricing.passepartout_labor_cost > 0) {
            lines.push(`Рязане паспарту на клиент → ${pricing.passepartout_labor_cost.toFixed(2)} €`);
        }
        if (pricing.extra_services && pricing.extra_services.length > 0) {
            pricing.extra_services.forEach((service) => {
                lines.push(`${service.name} → ${service.cost.toFixed(2)} €`);
            });
        }
        if (pricing.complex_passepartout_surcharge > 0) {
            lines.push(`Сложно паспарту (+50%) → ${pricing.complex_passepartout_surcharge.toFixed(2)} €`);
        }
        if (pricing.volume_discount > 0) {
            lines.push(`Обемна отстъпка → −${pricing.volume_discount.toFixed(2)} €`);
        }
        if (pricing.student_discount_amount > 0) {
            lines.push(`Ученик/студент → −${pricing.student_discount_amount.toFixed(2)} €`);
        }
        if (pricing.manual_discount_amount > 0) {
            const pct = pricing.manual_discount_percent > 0
                ? ` (${pricing.manual_discount_percent}%)`
                : '';
            lines.push(`Ръчна отстъпка${pct} → −${pricing.manual_discount_amount.toFixed(2)} €`);
        }
        if (pricing.urgent_surcharge > 0) {
            lines.push(`Спешна поръчка (+50%) → +${pricing.urgent_surcharge.toFixed(2)} €`);
        }
        if (pricing.total > 0) {
            lines.push(`<strong>Общо: ${pricing.total.toFixed(2)} €</strong>`);
            const priceInput = form.querySelector('[name="price"]');
            if (priceInput && form.dataset.autoPrice !== '0') {
                priceInput.value = pricing.total.toFixed(2);
            }
        }

        if (lines.length === 0) {
            preview.style.display = 'none';
            return;
        }

        preview.innerHTML = lines.join('<br>');
        preview.style.display = 'block';
    } catch (error) {
        preview.style.display = 'none';
    }
}

// Function to confirm order deletion
function confirmDelete(orderId) {
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    const collected = row && row.dataset.order
        ? JSON.parse(row.dataset.order).collected
        : row && row.querySelector('.badge.bg-info') !== null;

    if (!collected) {
        if (confirm('Поръчката не е маркирана като получена.\n\nМатериалите ще бъдат върнати в наличност. Поръчката ще отиде в архива на изтритите.\n\nСигурни ли сте?')) {
            window.location.href = `${window.BASE_PATH}/delete_order/${orderId}?restore_stock=1`;
        }
        return;
    }

    if (confirm('Поръчката е маркирана като получена. Материалите няма да бъдат върнати. Поръчката ще отиде в архива на изтритите.\n\nСигурни ли сте?')) {
        window.location.href = `${window.BASE_PATH}/delete_order/${orderId}`;
    }
}

// Function to show full description in modal
function showFullDescription(description) {
    const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
    document.getElementById('fullDescription').textContent = description
        .replace(/\\r\\n/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\\r/g, '\n');
    modal.show();
}

function filterExtraServices(searchInput) {
    const panel = searchInput.closest('.extra-services-panel');
    if (!panel) return;

    const query = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    panel.querySelectorAll('.extra-service-item').forEach((item) => {
        const name = item.dataset.serviceName || '';
        const category = item.closest('.extra-services-category');
        const categoryLabel = category?.dataset.categoryLabel || '';
        const match = !query || name.includes(query) || categoryLabel.includes(query);
        item.classList.toggle('d-none', !match);
        if (match) visibleCount += 1;
    });

    panel.querySelectorAll('.extra-services-category').forEach((category) => {
        const hasVisible = category.querySelector('.extra-service-item:not(.d-none)') !== null;
        category.classList.toggle('d-none', !hasVisible);
    });

    const emptyMsg = panel.querySelector('.extra-services-no-match');
    if (emptyMsg) {
        emptyMsg.classList.toggle('d-none', visibleCount > 0 || !query);
    }
}

// Initialize tooltips and filters
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    initOrderDayFilter();
    initOrderWorkloadChart();
    initAreaTierExclusiveChecks();

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', applyOrderRowVisibility);
    }

    document.querySelectorAll('.extra-services-search').forEach((input) => {
        input.addEventListener('input', () => filterExtraServices(input));
    });

    const sortSelect = document.getElementById('orderSortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            sortOrderRows(this.value);
            applyOrderRowVisibility();
        });
        sortOrderRows(sortSelect.value);
    }

    const allFilterButton = document.querySelector('.orders-toolbar__filter-all');
    if (allFilterButton) {
        allFilterButton.addEventListener('click', function () {
            document.querySelectorAll('[data-filter-group]').forEach((btn) => btn.classList.remove('active'));
            this.classList.add('active');
            applyOrderRowVisibility();
        });
    }

    document.querySelectorAll('[data-filter-group]').forEach((button) => {
        button.addEventListener('click', function () {
            const group = this.dataset.filterGroup;
            const wasActive = this.classList.contains('active');

            document.querySelectorAll(`[data-filter-group="${group}"]`).forEach((btn) => {
                btn.classList.remove('active');
            });

            if (!wasActive) {
                this.classList.add('active');
            }

            syncStatusFilterAllButton();
            applyOrderRowVisibility();
        });
    });

    const floatActions = document.querySelector('.row-float-actions');
    const orderRows = document.querySelectorAll('.order-row');
    const tableResponsive = document.querySelector('.table-responsive');

    orderRows.forEach(row => {
        row.addEventListener('click', function (e) {
            if (e.target.closest('.description-preview')) {
                return;
            }
            viewOrder(row.dataset.orderId);
        });

        row.addEventListener('mouseenter', function () {
            const orderId = row.dataset.orderId;
            const rowRect = row.getBoundingClientRect();
            const containerRect = tableResponsive.getBoundingClientRect();
            floatActions.style.top = (rowRect.top - containerRect.top + tableResponsive.scrollTop) + 'px';
            floatActions.style.display = 'flex';
            floatActions.innerHTML = `
                <button class="btn btn-outline-primary btn-sm" onclick="editOrder('${orderId}')"><i class="fas fa-edit"></i></button>
                <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete('${orderId}')"><i class="fas fa-trash"></i></button>
                <button class="btn btn-outline-success btn-sm" onclick="addSubOrder('${orderId}')"><i class="fas fa-plus"></i></button>
            `;
            floatActions.style.height = row.offsetHeight + 'px';
        });
        row.addEventListener('mouseleave', function (e) {
            const toElement = e.relatedTarget;
            if (!toElement || (!toElement.classList.contains('order-row') && !floatActions.contains(toElement))) {
                floatActions.style.display = 'none';
            }
        });
    });

    floatActions.addEventListener('mouseleave', function () {
        floatActions.style.display = 'none';
    });

    function bindAutoPrice(form) {
        if (form.dataset.autoPriceBound) return;
        form.dataset.autoPriceBound = '1';
        const priceInput = form.querySelector('[name="price"]');
        if (priceInput) {
            priceInput.addEventListener('input', () => {
                form.dataset.autoPrice = '0';
            });
        }
    }

    function shouldRecalculatePrice(event) {
        const el = event.target;
        return el.classList.contains('order-price-trigger')
            || el.name === 'additional_profiles[]'
            || el.name === 'extra_services[]'
            || el.name === 'passepartout_id'
            || el.name === 'passepartout_background_id'
            || el.name === 'passepartout_use'
            || el.name === 'back'
            || el.name === 'hanging'
            || el.name === 'weight_kg'
            || el.name === 'profile'
            || el.name === 'frame_count'
            || el.name === 'passepartout_openings'
            || el.name === 'transport_km'
            || el.name === 'discount'
            || el.name === 'urgent'
            || el.name === 'student_discount'
            || el.name === 'complex_passepartout'
            || el.name === 'frame_box'
            || el.name === 'frame_nonstandard'
            || el.name === 'frame_high_complexity'
            || el.name === 'frame_shape'
            || el.name === 'client_passepartout_cutting'
            || el.name === 'profile_ready_frame'
            || el.name === 'profile_ready_frame_price';
    }

    document.querySelectorAll('.order-form').forEach((form) => {
        bindAutoPrice(form);
        syncHangingWeightField(form);
        if (!form.dataset.autoPrice) {
            form.dataset.autoPrice = '1';
        }

        form.addEventListener('input', function (event) {
            if (event.target && event.target.name === 'hanging') {
                syncHangingWeightField(form);
            }
            if (event.target && event.target.name === 'passepartout_use') {
                syncPassepartoutUseFields(form);
            }
            if (event.target && event.target.name === 'profile_ready_frame') {
                syncProfileReadyFrameFields(form);
            }
            if (event.target && (event.target.name === 'width' || event.target.name === 'height')) {
                syncAreaTierSuggestions(form);
            }
            if (shouldRecalculatePrice(event)) {
                updateOrderPricing(form);
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target && event.target.name === 'hanging') {
                syncHangingWeightField(form);
            }
            if (event.target && event.target.name === 'passepartout_use') {
                syncPassepartoutUseFields(form);
            }
            if (event.target && event.target.name === 'profile_ready_frame') {
                syncProfileReadyFrameFields(form);
            }
            if (event.target && (event.target.name === 'width' || event.target.name === 'height')) {
                syncAreaTierSuggestions(form);
            }
            if (shouldRecalculatePrice(event)) {
                updateOrderPricing(form);
            }
        });

        syncPassepartoutUseFields(form);
        syncProfileReadyFrameFields(form);
        syncAreaTierSuggestions(form);
    });

    const addOrderModal = document.getElementById('addOrderModal');
    if (addOrderModal) {
        addOrderModal.addEventListener('show.bs.modal', () => {
            const form = document.getElementById('addOrderForm');
            if (!form) return;
            form.dataset.autoPrice = '1';
            const priceInput = form.querySelector('[name="price"]');
            if (priceInput) priceInput.value = '';
            setOrderFormAcceptedDateToday(form);
            expandOrderExtrasIfNeeded(form, null);
            syncHangingWeightField(form);
        });
    }

    const editOrderModal = document.getElementById('editOrderModal');
    if (editOrderModal) {
        editOrderModal.addEventListener('shown.bs.modal', () => {
            syncHangingWeightField(document.getElementById('editOrderForm'));
        });
    }

    const subOrderModal = document.getElementById('addSubOrderModal');
    if (subOrderModal) {
        subOrderModal.addEventListener('show.bs.modal', () => {
            const form = document.getElementById('addSubOrderForm');
            if (!form) return;
            expandOrderExtrasIfNeeded(form, null);
            syncHangingWeightField(form);
        });
    }

    const viewOrderEditBtn = document.getElementById('viewOrderEditBtn');
    if (viewOrderEditBtn) {
        viewOrderEditBtn.addEventListener('click', editOrderFromView);
    }

    document.addEventListener('click', (event) => {
        const preset = event.target.closest('.order-discount-preset');
        if (!preset) return;
        const form = preset.closest('.order-form');
        if (!form) return;
        const input = form.querySelector('.order-discount-input');
        if (!input) return;
        input.value = preset.dataset.percent || '';
        form.dataset.autoPrice = '1';
        updateOrderPricing(form);
    });
});
