// Паспарту: всеки ред има № + отстояния (отвсякъде / Л / Д / Г / Дол).
const SIDE_KEYS = ['all', 'l', 'r', 't', 'b'];
const SIDE_LABELS = { all: 'Отвсякъде', l: 'Л', r: 'Д', t: 'Г', b: 'Дол' };
const SIDE_TITLES = {
    all: 'Размер на паспартуто от всяка страна (см)',
    l: 'Ляво (см)',
    r: 'Дясно (см)',
    t: 'Горе (см)',
    b: 'Долу (см)',
};

function renumberPassepartoutLayers(container) {
    // Редовете вече нямат номер „слой“ — функцията остава за съвместимост.
    return container;
}

function parsePassepartoutReveals(stored) {
    const text = String(stored ?? '').trim();
    if (text === '') {
        return [];
    }
    if (!text.includes(',')) {
        return [text];
    }
    return text.split(',').map((part) => part.trim());
}

function buildSideCells(prefix, asArray, values = {}) {
    const wrap = document.createElement('div');
    wrap.className = 'side-cells';
    SIDE_KEYS.forEach((key) => {
        const cell = document.createElement('div');
        cell.className = `side-cell${key === 'all' ? ' side-cell--all' : ''}`;
        const label = document.createElement('label');
        label.className = 'form-label small text-muted mb-0';
        label.textContent = SIDE_LABELS[key];
        const input = document.createElement('input');
        input.type = 'text';
        input.inputMode = 'decimal';
        input.autocomplete = 'off';
        input.maxLength = 5;
        input.className = 'form-control form-control-sm order-price-trigger side-input';
        input.name = `${prefix}${key}${asArray ? '[]' : ''}`;
        input.title = SIDE_TITLES[key];
        input.placeholder = 'см';
        const value = values[key];
        input.value = value === null || value === undefined ? '' : String(value);
        cell.appendChild(label);
        cell.appendChild(input);
        wrap.appendChild(cell);
    });
    return wrap;
}

function buildPassepartoutLayerRow(value = '', sides = {}) {
    const layer = document.createElement('div');
    layer.className = 'passepartout-layer passepartout-layer-additional p-2 border-top';

    const fields = document.createElement('div');
    fields.className = 'row g-2 align-items-end passepartout-layer-fields';

    const stockCol = document.createElement('div');
    stockCol.className = 'col-md-3 col-12';
    stockCol.innerHTML = '<label class="form-label small text-muted mb-0">№ паспарту</label>';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm order-price-trigger';
    input.name = 'additional_passepartouts[]';
    input.setAttribute('list', 'passepartoutCatalog');
    input.setAttribute('autocomplete', 'off');
    input.placeholder = '№ от склада';
    input.value = value;
    stockCol.appendChild(input);

    const sidesCol = document.createElement('div');
    sidesCol.className = 'col-md-8 col-10';
    sidesCol.appendChild(buildSideCells('pp_', true, sides));

    const actionCol = document.createElement('div');
    actionCol.className = 'col-md-1 col-2 d-grid';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-danger';
    button.title = 'Премахни паспартуто';
    button.innerHTML = '<i class="fas fa-minus"></i>';
    button.addEventListener('click', () => {
        const form = layer.closest('form');
        layer.remove();
        if (form) {
            form.dispatchEvent(new Event('input', { bubbles: true }));
            if (form.dataset.autoPrice !== '0') {
                updateOrderPricing(form);
            }
        }
    });
    actionCol.appendChild(button);

    fields.appendChild(stockCol);
    fields.appendChild(sidesCol);
    fields.appendChild(actionCol);
    layer.appendChild(fields);

    return layer;
}

function addPassepartoutField(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.appendChild(buildPassepartoutLayerRow());
}

function appendAdditionalPassepartoutField(container, value, sides = {}) {
    if (!container) return;
    container.appendChild(buildPassepartoutLayerRow(value, sides));
}

function setSideInputs(scope, prefix, values = {}, arrayIndex = null) {
    if (!scope) return;
    SIDE_KEYS.forEach((key) => {
        const selector = arrayIndex === null ? `[name="${prefix}${key}"]` : `[name="${prefix}${key}[]"]`;
        const input = scope.querySelector(selector);
        if (!input) return;
        const value = values ? values[key] : null;
        input.value = value === null || value === undefined ? '' : String(value);
    });
}

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

    const extraPpContainer = document.getElementById('additionalProfiles_extraPp');
    if (extraPpContainer) extraPpContainer.innerHTML = '';

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
    syncExtrasDynamicFields(form);
    form.dataset.autoPrice = '1';
    setOrderFormAcceptedDateToday(form);
    syncPickupDatePickerFromForm(form);
    if (typeof clearFormDraft === 'function') {
        clearFormDraft(form);
    }
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

function setPassepartoutBlockDisabled(block, disabled) {
    if (!block) return;
    block.querySelectorAll('input, select, textarea, button').forEach((el) => {
        if (el.classList.contains('passepartout-use-select')) {
            return;
        }
        el.disabled = disabled;
    });
}

function syncPassepartoutUseFields(form) {
    if (!form) return;

    const useSelect = form.querySelector('[name="passepartout_use"]');
    const use = useSelect && useSelect.value ? useSelect.value : 'passepartout';

    const stack = form.querySelector('.passepartout-mode-stack');
    const bgOnly = form.querySelector('.passepartout-mode-background');
    const fondWrap = form.querySelector('.passepartout-mode-both-fond');
    const fieldWrap = form.querySelector('.passepartout-mode-field');

    // „Поле“ е рядко ползвана опция: показва клетките за поле + редовете за паспарту (по желание).
    const showStack = use === 'passepartout' || use === 'both' || use === 'field';
    const showBgOnly = use === 'background';
    const showFond = use === 'both';
    const showField = use === 'field';

    stack?.classList.toggle('d-none', !showStack);
    bgOnly?.classList.toggle('d-none', !showBgOnly);
    fondWrap?.classList.toggle('d-none', !showFond);
    fieldWrap?.classList.toggle('d-none', !showField);

    setPassepartoutBlockDisabled(stack, !showStack);
    setPassepartoutBlockDisabled(bgOnly, !showBgOnly);
    setPassepartoutBlockDisabled(fondWrap, !showFond);
    setPassepartoutBlockDisabled(fieldWrap, !showField);
}

function syncPassepartoutZeroFields() {
    // Нулевото паспарту е премахнато (2026-11).
}

/** Показва/скрива клетките за договорена цена, дистанционера, подрамката и броя закачалки. */
function syncExtrasDynamicFields(form) {
    if (!form) return;
    form.querySelectorAll('.extra-service-check').forEach((check) => {
        const item = check.closest('.extra-service-item');
        if (!item) return;
        const manual = item.querySelector('.extra-service-manual-price');
        if (manual) {
            manual.classList.toggle('d-none', !check.checked);
            manual.querySelectorAll('input').forEach((input) => { input.disabled = !check.checked; });
        }
        const spacer = item.querySelector('.extra-service-spacer');
        if (spacer) {
            spacer.classList.toggle('d-none', !check.checked);
            spacer.querySelectorAll('input').forEach((input) => { input.disabled = !check.checked; });
            syncSpacerStockHint(form, item, check.checked);
        }
    });

    const thickness = form.querySelector('[name="subframe_thickness"]');
    const manualWrap = form.querySelector('.subframe-manual-wrap');
    if (thickness && manualWrap) {
        const w = parseDecimal(form.querySelector('[name="width"]')?.value);
        const h = parseDecimal(form.querySelector('[name="height"]')?.value);
        const shortSide = Math.min(w, h);
        const longSide = Math.max(w, h);
        const oversize = shortSide > 100.001 || longSide > 150.001;
        const show = thickness.value !== '' && (oversize || shortSide <= 0);
        manualWrap.classList.toggle('d-none', !show);
    }

    const hanger = form.querySelector('[name="hanger"]');
    const hangerCount = form.querySelector('[name="hanger_count"]');
    if (hanger && hangerCount) {
        hangerCount.disabled = hanger.value === '';
        if (hanger.value === '') {
            hangerCount.value = '';
        }
    }
}

function syncSpacerStockHint(form, item, enabled) {
    const hint = item.querySelector('.spacer-stock-hint');
    const input = item.querySelector('[name="spacer_profile"]');
    if (!hint || !input) return;
    const name = input.value.trim();
    const info = name !== '' && window.PROFILE_STOCK ? window.PROFILE_STOCK[name] : null;
    if (!enabled || name === '') {
        hint.classList.add('d-none');
        hint.textContent = '';
        return;
    }
    hint.classList.remove('d-none');
    if (!info) {
        hint.className = 'small spacer-stock-hint text-warning';
        hint.textContent = 'Няма такъв профил в склада.';
        return;
    }
    hint.className = `small spacer-stock-hint ${info.stock > 0 ? 'text-muted' : 'text-danger'}`;
    hint.textContent = `Цена ${info.price.toFixed(2)} €/л.м., наличност ${info.stock} л.м.`;
}

function parseDecimal(value) {
    const num = parseFloat(String(value ?? '').replace(',', '.'));
    return Number.isFinite(num) ? num : 0;
}

function isoDateToDdMmYyyy(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function ddMmYyyyToIso(value) {
    const text = String(value || '').trim();
    if (!text) return '';
    const m = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!m) return '';
    const dd = m[1].padStart(2, '0');
    const mm = m[2].padStart(2, '0');
    return `${m[3]}-${mm}-${dd}`;
}

function bindOrderDatePicker(form) {
    if (!form || form.dataset.datePickerBound === '1') return;
    form.dataset.datePickerBound = '1';

    form.querySelectorAll('.order-form-pickup-group').forEach((group) => {
        const picker = group.querySelector('.order-date-picker-native');
        const textInput = group.querySelector('.order-date-dd[name="date"]');
        const btn = group.querySelector('.order-date-picker-btn');
        if (!picker || !textInput) return;

        if (btn) {
            btn.addEventListener('click', () => {
                if (typeof picker.showPicker === 'function') {
                    picker.showPicker();
                } else {
                    picker.focus();
                }
            });
        }

        picker.addEventListener('change', () => {
            if (picker.value) {
                textInput.value = isoDateToDdMmYyyy(picker.value);
                textInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        textInput.addEventListener('change', () => {
            const iso = ddMmYyyyToIso(textInput.value);
            picker.value = iso || '';
        });
    });
}

function syncPickupDatePickerFromForm(form) {
    if (!form) return;
    form.querySelectorAll('.order-form-pickup-group').forEach((group) => {
        const picker = group.querySelector('.order-date-picker-native');
        const textInput = group.querySelector('.order-date-dd[name="date"]');
        if (!picker || !textInput) return;
        const iso = ddMmYyyyToIso(textInput.value);
        picker.value = iso || '';
    });
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
    const weightWrap = form.querySelector('.hanging-weight-field');
    const weightInput = form.querySelector('[name="weight_kg"]');
    if (weightWrap) {
        weightWrap.classList.add('d-none');
    }
    if (weightInput) {
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
    const workFilter = document.querySelector('[data-filter-group="work"].active')?.dataset.filter;

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
        const ready = order ? Boolean(order.ready) : false;
        let matchesWork = true;
        if (workFilter === 'ready') matchesWork = ready;
        if (workFilter === 'in_progress') matchesWork = !ready;

        let matchesPayment = true;
        if (paymentFilter === 'paid') matchesPayment = paid;
        if (paymentFilter === 'unpaid') matchesPayment = !paid;

        let matchesCollection = true;
        if (collectionFilter === 'collected') matchesCollection = collected;
        if (collectionFilter === 'uncollected') matchesCollection = !collected;

        const matchesDay = !dayFilter || getOrderRowIsoDate(row) === dayFilter;

        row.style.display = matchesSearch && matchesPayment && matchesCollection && matchesWork && matchesDay ? '' : 'none';
    });
}

function syncStatusFilterAllButton() {
    const allButton = document.querySelector('.orders-toolbar__filter-all');
    if (!allButton) return;

    const hasPayment = document.querySelector('[data-filter-group="payment"].active');
    const hasCollection = document.querySelector('[data-filter-group="collection"].active');
    const hasWork = document.querySelector('[data-filter-group="work"].active');
    allButton.classList.toggle('active', !hasPayment && !hasCollection && !hasWork);
}

const ORDERS_SYNC_INTERVAL_MS = 3500;
let ordersSyncRevision = window.ORDERS_SYNC_REVISION || '';
let ordersSyncTimer = null;
let ordersTableResponsive = null;
let ordersFloatActions = null;

function shouldPauseOrdersLiveSync() {
    if (document.hidden) {
        return true;
    }
    return document.querySelector('.modal.show') !== null;
}

function rowFromSyncHtml(html) {
    const temp = document.createElement('tbody');
    temp.innerHTML = html.trim();
    return temp.querySelector('tr.order-row');
}

function bindSingleOrderRow(row) {
    if (!row || !ordersTableResponsive || !ordersFloatActions) {
        return;
    }

    row.addEventListener('click', function (e) {
        if (e.target.closest('.description-preview') || e.target.closest('.status-btn')) {
            return;
        }
        viewOrder(row.dataset.orderId);
    });

    row.addEventListener('mouseenter', function () {
        const orderId = row.dataset.orderId;
        const rowRect = row.getBoundingClientRect();
        const containerRect = ordersTableResponsive.getBoundingClientRect();
        ordersFloatActions.style.top = (rowRect.top - containerRect.top + ordersTableResponsive.scrollTop) + 'px';
        ordersFloatActions.style.display = 'flex';
        ordersFloatActions.innerHTML = `
                <button class="btn btn-outline-primary btn-sm" onclick="editOrder('${orderId}')"><i class="fas fa-edit"></i></button>
                <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete('${orderId}')"><i class="fas fa-trash"></i></button>
                <button class="btn btn-outline-success btn-sm" onclick="addSubOrder('${orderId}')"><i class="fas fa-plus"></i></button>
            `;
        ordersFloatActions.style.height = row.offsetHeight + 'px';
    });

    row.addEventListener('mouseleave', function (e) {
        const toElement = e.relatedTarget;
        if (!toElement || (!toElement.classList.contains('order-row') && !ordersFloatActions.contains(toElement))) {
            ordersFloatActions.style.display = 'none';
        }
    });
}

function applyActiveOrdersSync(remoteOrders) {
    const tbody = document.querySelector('.orders-container tbody');
    if (!tbody || !Array.isArray(remoteOrders)) {
        return;
    }

    const remoteById = new Map(remoteOrders.map((entry) => [String(entry.id), entry]));

    tbody.querySelectorAll('.order-row').forEach((row) => {
        const id = row.dataset.orderId;
        if (!remoteById.has(id)) {
            row.remove();
        }
    });

    remoteOrders.forEach((entry) => {
        const id = String(entry.id);
        const existing = tbody.querySelector(`.order-row[data-order-id="${id}"]`);
        if (existing && existing.dataset.rowRevision === entry.revision) {
            return;
        }

        const newRow = rowFromSyncHtml(entry.html);
        if (!newRow) {
            return;
        }

        if (existing) {
            existing.replaceWith(newRow);
        } else {
            tbody.appendChild(newRow);
        }
        bindSingleOrderRow(newRow);
    });

    const sortSelect = document.getElementById('orderSortSelect');
    if (sortSelect) {
        sortOrderRows(sortSelect.value);
    }
    applyOrderRowVisibility();
}

async function pollActiveOrdersSync() {
    if (shouldPauseOrdersLiveSync()) {
        return;
    }

    const base = window.BASE_PATH || '';
    const query = ordersSyncRevision ? `?revision=${encodeURIComponent(ordersSyncRevision)}` : '';
    try {
        const response = await fetch(`${base}/api/active_orders${query}`, {
            credentials: 'same-origin',
            // Автоматичното опресняване не удължава сесията.
            headers: { Accept: 'application/json', 'X-Session-Passive': '1' },
        });
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        if (!data || data.unchanged) {
            if (data?.revision) {
                ordersSyncRevision = data.revision;
            }
            return;
        }
        ordersSyncRevision = data.revision || ordersSyncRevision;
        applyActiveOrdersSync(data.orders || []);
    } catch (err) {
        // ignore transient network errors
    }
}

function initOrdersLiveSync() {
    if (!document.querySelector('.orders-container tbody')) {
        return;
    }

    ordersSyncRevision = window.ORDERS_SYNC_REVISION || ordersSyncRevision;
    pollActiveOrdersSync();
    if (ordersSyncTimer) {
        clearInterval(ordersSyncTimer);
    }
    ordersSyncTimer = setInterval(pollActiveOrdersSync, ORDERS_SYNC_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollActiveOrdersSync();
        }
    });
}

// Function to add a sub-order
function addSubOrder(orderId) {
    const form = document.getElementById('addSubOrderForm');
    form.action = `${window.BASE_PATH}/add_sub_order/${orderId}`;
    form.dataset.draftKey = `sub:${orderId}`;

    const modal = new bootstrap.Modal(document.getElementById('addSubOrderModal'));
    modal.show();
}

// Function to view an order (read-only)
let currentViewOrderId = null;

function buildOrderExtrasLabels(data) {
    if (Array.isArray(data.extras_labels)) {
        return data.extras_labels;
    }
    return [];
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
    let sizeText = data.width && data.height
        ? `${data.width} × ${data.height} см`
        : '—';
    if (data.artwork_type && window.ARTWORK_TYPE_LABELS && window.ARTWORK_TYPE_LABELS[data.artwork_type]) {
        sizeText = sizeText === '—'
            ? window.ARTWORK_TYPE_LABELS[data.artwork_type]
            : `${sizeText} · ${window.ARTWORK_TYPE_LABELS[data.artwork_type]}`;
    }
    const sizeExtras = [];
    if (data.frame_measure_label) sizeExtras.push(`мерене: ${data.frame_measure_label}`);
    if (data.frame_shape_label) sizeExtras.push(`фигура: ${data.frame_shape_label}`);
    if (data.field_label) sizeExtras.push(`поле: ${data.field_label}`);
    if (sizeExtras.length > 0) {
        sizeText += ` (${sizeExtras.join('; ')})`;
    }
    document.getElementById('viewOrderSize').textContent = sizeText;
    document.getElementById('viewOrderFrameCount').textContent = displayValue(data.frame_count ?? 1);
    document.getElementById('viewOrderProfile').textContent = displayValue(data.profile);
    document.getElementById('viewOrderAdditionalProfiles').textContent = displayValue(data.additional_profiles);
    document.getElementById('viewOrderGlass').textContent = displayValue(data.glass);
    document.getElementById('viewOrderBack').textContent = displayValue(data.back);
    let hangingText = displayValue(data.hanging);
    if (data.hanger) {
        const count = data.hanger_count === null || data.hanger_count === undefined || data.hanger_count === '' ? 2 : data.hanger_count;
        const hangerText = `${data.hanger} × ${count}/рамка`;
        hangingText = hangingText === '—' ? hangerText : `${hangingText} + ${hangerText}`;
    }
    document.getElementById('viewOrderHanging').textContent = hangingText;
    document.getElementById('viewOrderCustomer').textContent = displayValue(data.customer_name);
    const viewPhone = document.getElementById('viewOrderPhone');
    if (viewPhone) {
        viewPhone.textContent = displayValue(data.customer_phone);
    }

    let passepartoutText = displayValue(data.passepartout);
    if (data.passepartout_background) {
        const bg = String(data.passepartout_background).trim();
        if (bg !== '') {
            passepartoutText = passepartoutText === '—' ? bg : `${passepartoutText} · ${bg}`;
        }
    }
    if (data.additional_passepartouts) {
        const extra = String(data.additional_passepartouts).trim();
        if (extra !== '') {
            passepartoutText = passepartoutText === '—' ? extra : `${passepartoutText} · ${extra}`;
        }
    }
    if (data.material_layers_label) {
        passepartoutText = data.material_layers_label;
    }
    const revealParts = data.material_layers_label ? [] : parsePassepartoutReveals(data.passepartout_reveal_cm);
    if (revealParts.length > 0) {
        const revealLabel = revealParts.map((r, i) => (r !== '' ? `сл.${i + 1}: ${r} см` : null)).filter(Boolean).join(', ');
        if (revealLabel !== '') {
            passepartoutText += ` (отстъп ${revealLabel})`;
        }
    }
    if (data.passepartout_bill_width && data.passepartout_bill_height) {
        passepartoutText += ` (фактурирано: ${data.passepartout_bill_width}×${data.passepartout_bill_height} см)`;
    }
    document.getElementById('viewOrderPassepartout').textContent = passepartoutText;

    renderViewOrderStatus(orderId, data);
    const printClient = document.getElementById('viewOrderPrintClient');
    if (printClient) printClient.href = `${window.BASE_PATH}/order_print/${orderId}`;
    const notifyBtn = document.getElementById('viewOrderNotifyBtn');
    if (notifyBtn) {
        notifyBtn.classList.remove('d-none');
        notifyBtn.onclick = () => openNotifyModal(orderId);
    }

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

    const extrasLabels = buildOrderExtrasLabels(data).slice();
    if (data.stock_override && Array.isArray(data.stock_shortage) && data.stock_shortage.length > 0) {
        data.stock_shortage.forEach((row) => {
            extrasLabels.push(`⚠ Недостатъчна наличност (потвърдено): ${row.label} ${row.name} — липсват ${row.missing} ${row.unit}`);
        });
    }
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

    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewOrderModal')).show();
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
    syncPickupDatePickerFromForm(form);
    form.querySelector('[name="width"]').value = data.width ?? '';
    form.querySelector('[name="height"]').value = data.height ?? '';
    form.querySelector('[name="profile"]').value = data.profile ?? '';
    form.querySelector('[name="glass"]').value = data.glass ?? '';
    const orderIdInput = form.querySelector('[name="_order_id"]');
    if (orderIdInput) orderIdInput.value = orderId;
    const stockConfirmed = form.querySelector('[name="stock_confirmed"]');
    if (stockConfirmed) stockConfirmed.value = '';

    let ppUse = data.passepartout_use || '';
    if (!ppUse) {
        const bgStored = String(data.passepartout_background ?? '').trim();
        if (bgStored !== '') {
            ppUse = 'both';
        } else if (String(data.passepartout || '').includes('(фон)')) {
            ppUse = 'background';
        } else {
            ppUse = 'passepartout';
        }
    }
    const hasField = ['all', 'l', 'r', 't', 'b'].some((key) => {
        const value = data[`field_margin_${key}`];
        return value !== null && value !== undefined && value !== '';
    });
    if (hasField && ppUse === 'passepartout') {
        ppUse = 'field';
    }
    const useSelect = form.querySelector('[name="passepartout_use"]');
    if (useSelect) {
        useSelect.value = ppUse;
    }
    syncPassepartoutUseFields(form);

    ['all', 'l', 'r', 't', 'b'].forEach((key) => {
        const field = form.querySelector(`[name="field_margin_${key}"]`);
        if (field) field.value = data[`field_margin_${key}`] ?? '';
    });

    const layers = Array.isArray(data.passepartout_layers) && data.passepartout_layers.length > 0
        ? data.passepartout_layers
        : [];
    const legacyNames = [];
    if (layers.length === 0 && ppUse !== 'background') {
        const isZero = Boolean(data.passepartout_zero) || String(data.passepartout || '').startsWith('Нулево');
        const primary = isZero ? '' : parsePrimaryPassepartoutLabel(data.passepartout || '').replace(/\s+\((паспарту|фон)\)\s*$/, '');
        if (primary) legacyNames.push(primary);
        String(data.additional_passepartouts || '').split(',').map((n) => n.trim()).filter(Boolean).forEach((n) => legacyNames.push(n));
    }
    const layerEntries = layers.length > 0 ? layers : legacyNames.map((name) => ({ name }));

    const stack = form.querySelector('.passepartout-mode-stack');
    const primaryLayer = form.querySelector('.passepartout-layer-primary');
    const ppInput = primaryLayer?.querySelector('[name="passepartout"]');
    if (ppInput) {
        ppInput.value = layerEntries[0]?.name ?? '';
    }
    setSideInputs(primaryLayer, 'pp_', layerEntries[0] || {}, 0);

    const extraPpContainer = form.querySelector('[id$="_extraPp"]');
    if (extraPpContainer) {
        extraPpContainer.innerHTML = '';
        if (ppUse !== 'background') {
            layerEntries.slice(1).forEach((entry) => {
                appendAdditionalPassepartoutField(extraPpContainer, entry.name ?? '', entry);
            });
        }
    }

    const bgOffsets = data.passepartout_bg_offsets && typeof data.passepartout_bg_offsets === 'object'
        ? data.passepartout_bg_offsets
        : {};
    const fondInput = form.querySelector('[name="passepartout_background"]');
    if (fondInput) {
        fondInput.value = data.passepartout_background ?? '';
    }
    form.querySelectorAll('.passepartout-mode-background, .passepartout-mode-both-fond').forEach((block) => {
        setSideInputs(block, 'bg_', bgOffsets);
    });
    if (ppUse === 'background') {
        const bgInput = form.querySelector('.passepartout-fond-only-input');
        if (bgInput) {
            bgInput.value = parsePrimaryPassepartoutLabel(data.passepartout || '').replace(/\s+\((паспарту|фон)\)\s*$/, '');
        }
    }
    syncPassepartoutUseFields(form);
    if (stack && ppUse === 'background' && ppInput) {
        ppInput.value = '';
    }

    const frameMeasure = form.querySelector('[name="frame_measure"]');
    if (frameMeasure) frameMeasure.value = data.frame_measure ?? '';
    const artworkSelect = form.querySelector('[name="artwork_type"]');
    if (artworkSelect) {
        artworkSelect.value = data.artwork_type ?? '';
    }
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
    const hangerSelect = form.querySelector('[name="hanger"]');
    if (hangerSelect) {
        hangerSelect.value = data.hanger ?? '';
    }
    const hangerCount = form.querySelector('[name="hanger_count"]');
    if (hangerCount) {
        hangerCount.value = data.hanger_count ?? '';
    }
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
    const readyBox = form.querySelector('[name="ready"]');
    if (readyBox) readyBox.checked = Boolean(data.ready);
    const emailInput = form.querySelector('[name="customer_email"]');
    if (emailInput) emailInput.value = data.customer_email ?? '';
    const customerIdInput = form.querySelector('[name="customer_id"]');
    if (customerIdInput) customerIdInput.value = data.customer_id ?? '';
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
    form.dataset.draftKey = `edit:${orderId}`;
    syncExtrasDynamicFields(form);
    if (typeof restoreFormDraftIfAny === 'function') {
        restoreFormDraftIfAny(form);
    }

    updateOrderPricing(form);

    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

function loadOrderExtras(form, data) {
    const openings = form.querySelector('[name="passepartout_openings"]');
    if (openings) openings.value = data.passepartout_openings ?? 1;

    const urgent = form.querySelector('[name="urgent"]');
    if (urgent) urgent.value = String(Number(data.urgent) || 0);

    const complexOrder = form.querySelector('[name="complex_order_percent"]');
    if (complexOrder) complexOrder.value = data.complex_order_percent ?? '';

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
    let manualPrices = {};
    try {
        manualPrices = typeof data.extra_services_manual === 'string'
            ? JSON.parse(data.extra_services_manual || '{}')
            : (data.extra_services_manual || {});
    } catch (e) {
        manualPrices = {};
    }

    form.querySelectorAll('.extra-service-check').forEach((checkbox) => {
        checkbox.checked = serviceIds.includes(parseInt(checkbox.value, 10));
    });
    form.querySelectorAll('[name^="extra_services_manual["]').forEach((input) => {
        const match = input.name.match(/\[(\d+)\]/);
        const id = match ? match[1] : '';
        input.value = manualPrices && manualPrices[id] !== undefined ? manualPrices[id] : '';
    });

    const setValue = (name, value) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (field) field.value = value ?? '';
    };
    setValue('spacer_profile', data.spacer_profile);
    setValue('subframe_thickness', data.subframe_thickness);
    setValue('subframe_manual_price', data.subframe_manual_price);
    setValue('box_subframe_gap_cm', data.box_subframe_gap_cm);
    setValue('canvas_support_note', data.canvas_support_note);

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
    syncExtrasDynamicFields(form);
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
        Number(data.urgent) > 0
        || Number(data.complex_order_percent) > 0
        || data.subframe_thickness
        || data.has_additional_services
        || data.student_discount
        || data.complex_passepartout
        || data.frame_box
        || data.frame_nonstandard
        || data.frame_high_complexity
        || data.client_passepartout_cutting
        || (data.frame_shape && data.frame_shape !== '')
        || (data.passepartout_openings && Number(data.passepartout_openings) > 1)
        || (data.transport_km && Number(data.transport_km) > 0)
        || (data.carry_floors && Number(data.carry_floors) > 0)
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
            body: formData,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.status === 401 && typeof showSessionLoginModal === 'function') {
            showSessionLoginModal(null);
        }
        if (!response.ok) {
            preview.style.display = 'none';
            return;
        }

        const pricing = await response.json();
        const lines = [];

        const geo = pricing.geometry;
        const geoHint = form.querySelector('.order-geometry-hint');
        if (geoHint) {
            const parts = [];
            if (geo && geo.picture_width > 0 && geo.picture_height > 0) {
                const fieldUsed = Array.isArray(geo.field) && geo.field.some((v) => v > 0);
                if (fieldUsed) {
                    parts.push(`след полето: ${geo.base_width}×${geo.base_height} см`);
                }
                (geo.layers || []).forEach((layer) => {
                    const role = layer.role === 'background' ? 'фон' : 'паспарту';
                    const label = layer.name ? `${role} ${layer.name}` : role;
                    parts.push(`${label}: ${layer.width}×${layer.height} см`);
                });
                if (geo.frame_width !== geo.picture_width || geo.frame_height !== geo.picture_height) {
                    parts.push(`рамка: ${geo.frame_width}×${geo.frame_height} см`);
                }
            }
            geoHint.textContent = parts.length > 0 ? `Размери за цена — ${parts.join('; ')}` : '';
        }

        const profileName = formData.get('profile');
        if (pricing.profile_lines && pricing.profile_lines.length > 0) {
            pricing.profile_lines.forEach((line, index) => {
                const label = `Профил ${index + 1}`;
                if (!line.found) {
                    lines.push(`<span class="text-warning">${label} („${line.name}"): не е в Склад → Профили</span>`);
                    return;
                }
                const dims = line.bill_width && line.bill_height
                    ? ` при ${line.bill_width}×${line.bill_height} см`
                    : '';
                const costPart = line.cost > 0 ? ` → ${line.cost.toFixed(2)} €` : '';
                const laborPart = line.labor_cost > 0 ? `, труд ${line.labor_cost.toFixed(2)} €` : '';
                const meterPart = line.fir_meters > 0 && line.base_meters != null
                    ? `${line.base_meters.toFixed(2)} + ${line.fir_meters.toFixed(2)} фира = ${line.meters.toFixed(2)} л.м.`
                    : `${line.meters} л.м.`;
                let text = `${label} (${line.name}): ${meterPart}${dims}${costPart}${laborPart}`;
                if (line.long_side_billing) {
                    text += ' (дълга страна → 290/300 см)';
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
                parts.push('дълга страна → 290/300 см');
            }
            if (pricing.frame_shape) {
                parts.push(pricing.frame_shape);
            }
            lines.push(`Профил (${pricing.profile_meters} л.м.): ${parts.join(' + ')}`);
        } else if (profileName && pricing.profile_meters > 0 && pricing.profile_found === false) {
            lines.push(`<span class="text-warning">Профил „${profileName}" не е в Склад → Профили (липсва цена на материал)</span>`);
        }
        if (pricing.frame_side_surcharge > 0) {
            lines.push(`Голяма рамка (вкл. в труда) → ${pricing.frame_side_surcharge.toFixed(2)} €`);
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
            if (pricing.hanger_count > 0) {
                hangingText += ` (закачалки ${pricing.hanger_count}/рамка → ${pricing.hanger_cost.toFixed(2)} €)`;
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
                laborParts.push(`труд/отвори ${pricing.passepartout_labor_cost.toFixed(2)}`);
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
                return `${roleLabel}: ${layer.passepartout_name}, ${layer.bill_width}×${layer.bill_height} см${layerPrice}${layerUsage}${costPart}`;
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
                    `Паспарту${useLabel}: ${pp.bill_width}×${pp.bill_height} см${multiSheetText}${openingsText}${priceDetail}${usageText}${labor} → ${pricing.passepartout_cost.toFixed(2)} €`
                );
            }
        } else if (pricing.passepartout_labor_cost > 0) {
            lines.push(`Рязане паспарту на клиент → ${pricing.passepartout_labor_cost.toFixed(2)} €`);
        }
        if (pricing.passepartout_extra_layers && pricing.passepartout_extra_layers.length > 0) {
            pricing.passepartout_extra_layers.forEach((layer) => {
                lines.push(`Паспарту ${layer.passepartout_name}: ${layer.bill_width}×${layer.bill_height} см → ${Number(layer.cost).toFixed(2)} € (вкл. в общото)`);
            });
        }
        if (pricing.subframe) {
            const sf = pricing.subframe;
            if (sf.manual) {
                lines.push(sf.needs_manual_price
                    ? '<span class="text-warning">Подрамка над 100/150 см — въведете договорена цена</span>'
                    : `Подрамка ${sf.thickness} см (договорена) → ${sf.cost.toFixed(2)} €`);
            } else {
                const factor = sf.factor !== 1 ? ` ×${sf.factor}` : '';
                lines.push(`Подрамка ${sf.thickness} см: ${sf.meters} л.м. × ${Number(sf.price_per_lm).toFixed(2)} €${factor} + труд ${sf.labor_cost.toFixed(2)} € → ${sf.cost.toFixed(2)} €`);
            }
        }
        if (pricing.extra_services && pricing.extra_services.length > 0) {
            pricing.extra_services.forEach((service) => {
                lines.push(`${service.name} → ${service.cost.toFixed(2)} €`);
            });
        }
        if (pricing.complex_passepartout_surcharge > 0) {
            lines.push(`Сложно паспарту (+50%) → ${pricing.complex_passepartout_surcharge.toFixed(2)} €`);
        }
        if (pricing.manual_discount_amount > 0) {
            const pct = pricing.manual_discount_percent > 0
                ? ` (${pricing.manual_discount_percent}%)`
                : '';
            lines.push(`Ръчна отстъпка${pct} → −${pricing.manual_discount_amount.toFixed(2)} €`);
        }
        if (pricing.urgent_surcharge > 0) {
            lines.push(`Спешна поръчка (+${pricing.urgent_percent}%) → +${pricing.urgent_surcharge.toFixed(2)} €`);
        }
        if (pricing.complex_order_surcharge > 0) {
            lines.push(`Сложна поръчка (+${pricing.complex_order_percent}%) → +${pricing.complex_order_surcharge.toFixed(2)} €`);
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
            postNavigate(`${window.BASE_PATH}/delete_order/${orderId}`, { restore_stock: '1' });
        }
        return;
    }

    if (confirm('Поръчката е маркирана като получена. Материалите няма да бъдат върнати. Поръчката ще отиде в архива на изтритите.\n\nСигурни ли сте?')) {
        postNavigate(`${window.BASE_PATH}/delete_order/${orderId}`);
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

    ordersFloatActions = document.querySelector('.row-float-actions');
    ordersTableResponsive = document.querySelector('.orders-container .table-responsive');
    document.querySelectorAll('.order-row').forEach((row) => bindSingleOrderRow(row));

    if (ordersFloatActions) {
        ordersFloatActions.addEventListener('mouseleave', function () {
            ordersFloatActions.style.display = 'none';
        });
    }

    initOrdersLiveSync();

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

    const NON_PRICE_FIELDS = new Set([
        'customer_name', 'customer_phone', 'description', 'price', 'advance_payment',
        'paid', 'collected', 'accepted_date', 'date', 'artwork_type', 'stock_confirmed', '_order_id',
        'box_subframe_gap_cm', 'canvas_support_note',
    ]);

    function shouldRecalculatePrice(event) {
        const el = event.target;
        if (!el || !el.name) {
            return false;
        }
        if (el.classList.contains('extra-services-search')) {
            return false;
        }
        return !NON_PRICE_FIELDS.has(el.name);
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
            if (event.target && event.target.name === 'passepartout_zero') {
                syncPassepartoutZeroFields(form);
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
            syncExtrasDynamicFields(form);
            if (shouldRecalculatePrice(event)) {
                scheduleOrderPricing(form);
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target && event.target.name === 'hanging') {
                syncHangingWeightField(form);
            }
            if (event.target && event.target.name === 'passepartout_zero') {
                syncPassepartoutZeroFields(form);
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
            syncExtrasDynamicFields(form);
            if (shouldRecalculatePrice(event)) {
                scheduleOrderPricing(form);
            }
        });

        form.addEventListener('submit', (event) => handleOrderFormSubmit(event, form));

        syncPassepartoutUseFields(form);
        syncExtrasDynamicFields(form);
        syncProfileReadyFrameFields(form);
        syncAreaTierSuggestions(form);
        bindOrderDatePicker(form);
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
            if (typeof restoreFormDraftIfAny === 'function' && restoreFormDraftIfAny(form)) {
                updateOrderPricing(form);
            }
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
            if (typeof restoreFormDraftIfAny === 'function') {
                restoreFormDraftIfAny(form);
            }
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

// --- Преизчисляване на цената с малко забавяне (по-малко заявки при писане) ---
const orderPricingTimers = new WeakMap();

function scheduleOrderPricing(form) {
    if (!form) return;
    clearTimeout(orderPricingTimers.get(form));
    orderPricingTimers.set(form, setTimeout(() => updateOrderPricing(form), 250));
}

// --- Количество 0 в склада: предупреждение и потвърждение „Материалът е наличен“ ---
let pendingStockForm = null;

async function handleOrderFormSubmit(event, form) {
    const confirmed = form.querySelector('[name="stock_confirmed"]');
    if (!confirmed || confirmed.value === '1' || form.dataset.stockChecked === '1') {
        form.dataset.stockChecked = '';
        if (typeof clearFormDraft === 'function') {
            clearFormDraft(form);
        }
        return;
    }

    event.preventDefault();
    const formData = new FormData(form);
    let shortages = [];
    try {
        const response = await fetch(`${window.BASE_PATH}/check_stock`, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.status === 401) {
            if (typeof showSessionLoginModal === 'function') {
                showSessionLoginModal(form);
            }
            return;
        }
        if (response.ok) {
            const data = await response.json();
            shortages = Array.isArray(data.shortages) ? data.shortages : [];
        }
    } catch (error) {
        shortages = [];
    }

    if (shortages.length === 0) {
        form.dataset.stockChecked = '1';
        form.requestSubmit();
        return;
    }

    pendingStockForm = form;
    const list = document.getElementById('stockShortageList');
    if (list) {
        list.innerHTML = '';
        shortages.forEach((row) => {
            const li = document.createElement('li');
            li.textContent = `${row.label} ${row.name}: нужни ${row.needed} ${row.unit}, налични ${row.available} — липсват ${row.missing} ${row.unit}`;
            list.appendChild(li);
        });
    }
    const modalEl = document.getElementById('stockShortageModal');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const confirmBtn = document.getElementById('stockShortageConfirmBtn');
    if (!confirmBtn) return;
    confirmBtn.addEventListener('click', () => {
        const form = pendingStockForm;
        pendingStockForm = null;
        const modalEl = document.getElementById('stockShortageModal');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        if (!form) return;
        const confirmed = form.querySelector('[name="stock_confirmed"]');
        if (confirmed) confirmed.value = '1';
        form.requestSubmit();
    });
});

// --- Статус с един клик: Готова / Платена / Получена ---
const STATUS_LABELS = {
    ready: ['Готова', 'В работа'],
    paid: ['Платена', 'Неплатена'],
    collected: ['Получена', 'Неполучена'],
};

function statusButtonsHtml(data) {
    const values = { ready: Boolean(data.ready), paid: Boolean(data.paid), collected: Boolean(data.collected) };
    return `<div class="status-btns flex-row flex-wrap">${Object.entries(STATUS_LABELS).map(([field, [on, off]]) => `
        <button type="button" class="status-btn status-btn--${field} ${values[field] ? 'is-on' : 'is-off'}"
            data-status-field="${field}" data-status-value="${values[field] ? '1' : '0'}">
            <i class="fas ${values[field] ? 'fa-check' : 'fa-circle'}"></i> ${values[field] ? on : off}
        </button>`).join('')}</div>`;
}

function renderViewOrderStatus(orderId, data) {
    const statusEl = document.getElementById('viewOrderStatus');
    if (!statusEl) return;
    statusEl.dataset.orderId = orderId;
    statusEl.innerHTML = statusButtonsHtml(data);
}

async function toggleOrderStatus(orderId, field, value, button) {
    button?.classList.add('is-busy');
    const body = new FormData();
    body.append('order_id', orderId);
    body.append('field', field);
    body.append('value', value ? '1' : '0');
    try {
        const response = await fetch(`${window.BASE_PATH}/api/order_status`, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.status === 401 && typeof showSessionLoginModal === 'function') {
            showSessionLoginModal(null);
            return;
        }
        const data = await response.json();
        if (!response.ok || !data.ok) {
            showOrdersToast(data.message || 'Статусът не е сменен.', 'danger');
            return;
        }
        const row = document.querySelector(`.order-row[data-order-id="${orderId}"]`);
        const label = `${data.order.order_number}${data.order.sub_order_number > 0 ? '.' + data.order.sub_order_number : ''}`;
        if (data.archived) {
            row?.remove();
            showOrdersToast(`Поръчка № ${label} е платена и получена — преместена е в архива.`, 'info');
        } else if (row && data.html) {
            const newRow = rowFromSyncHtml(data.html);
            if (newRow) {
                row.replaceWith(newRow);
                bindSingleOrderRow(newRow);
            }
        }
        const statusEl = document.getElementById('viewOrderStatus');
        if (statusEl && statusEl.dataset.orderId === String(orderId)) {
            renderViewOrderStatus(orderId, data.order);
        }
        applyOrderRowVisibility();
        if (field === 'ready' && value) {
            const toast = showOrdersToast(
                `<span>Поръчка № ${label} е готова.</span> <button type="button" class="btn btn-sm btn-primary">Уведоми клиента</button>`,
                'warning',
                10000
            );
            toast.querySelector('button').addEventListener('click', () => {
                toast.remove();
                openNotifyModal(orderId, 'ready');
            });
        }
    } catch (error) {
        showOrdersToast('Няма връзка със сървъра.', 'danger');
    } finally {
        button?.classList.remove('is-busy');
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.status-btn');
    if (!button) return;
    event.stopPropagation();
    const holder = button.closest('[data-order-id]');
    const orderId = holder?.dataset.orderId;
    if (!orderId) return;
    const field = button.dataset.statusField;
    const turnOn = button.dataset.statusValue !== '1';
    const scope = button.closest('.status-btns');
    if (field === 'ready' && !turnOn && scope?.querySelector('.status-btn--collected.is-on')) {
        if (!confirm('Поръчката е маркирана като получена. Да махна ли и „Получена“?')) return;
    }
    toggleOrderStatus(orderId, field, turnOn, button);
}, true);

// --- Клиенти: подсказки по име или телефон ---
function initCustomerLookup(form) {
    const inputs = form.querySelectorAll('.customer-lookup-input');
    const idInput = form.querySelector('[name="customer_id"]');
    if (inputs.length === 0 || !idInput) return;
    let timer = null;
    let box = null;
    const closeBox = () => { box?.remove(); box = null; };
    const pick = (customer) => {
        form.querySelector('[name="customer_name"]').value = customer.name;
        form.querySelector('[name="customer_phone"]').value = customer.phone || '';
        const email = form.querySelector('[name="customer_email"]');
        if (email && customer.email) email.value = customer.email;
        idInput.value = customer.id;
        closeBox();
        form.dispatchEvent(new Event('input', { bubbles: true }));
    };
    inputs.forEach((input) => {
        input.addEventListener('input', () => {
            idInput.value = '';
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { closeBox(); return; }
            timer = setTimeout(async () => {
                try {
                    const response = await fetch(`${window.BASE_PATH}/api/customers?q=${encodeURIComponent(q)}`, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    const data = await response.json();
                    closeBox();
                    if (!data.customers || data.customers.length === 0 || document.activeElement !== input) return;
                    box = document.createElement('div');
                    box.className = 'customer-suggest';
                    data.customers.forEach((c) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        const strong = document.createElement('strong');
                        strong.textContent = c.name;
                        const small = document.createElement('small');
                        small.textContent = [c.phone, c.email, `${c.orders_count} поръчки`, c.last_order ? `последна ${c.last_order}` : '']
                            .filter(Boolean).join(' · ');
                        b.appendChild(strong);
                        b.appendChild(document.createElement('br'));
                        b.appendChild(small);
                        b.addEventListener('mousedown', (e) => { e.preventDefault(); pick(c); });
                        box.appendChild(b);
                    });
                    input.closest('.customer-lookup').appendChild(box);
                } catch (error) {
                    closeBox();
                }
            }, 250);
        });
        input.addEventListener('blur', () => setTimeout(closeBox, 150));
        input.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeBox(); });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.order-form').forEach((form) => initCustomerLookup(form));
});
