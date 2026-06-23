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
    document.getElementById('viewOrderDate').textContent = row.querySelector('.date')?.textContent.trim() || data.date || '—';
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
    document.getElementById('viewOrderDiscount').textContent = data.discount != null && data.discount !== ''
        ? `${data.discount} €`
        : '—';

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

    const editBtn = document.getElementById('viewOrderEditBtn');
    if (editBtn) {
        editBtn.style.display = window.ARCHIVE_PAGE ? 'none' : '';
    }

    const modal = new bootstrap.Modal(document.getElementById('viewOrderModal'));
    modal.show();
}

function showFullDescription(description) {
    const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
    document.getElementById('fullDescription').textContent = description
        .replace(/\\r\\n/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\\r/g, '\n');
    modal.show();
}
