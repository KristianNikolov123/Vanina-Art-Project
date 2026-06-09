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

// Function to add a sub-order
function addSubOrder(orderId) {
    const form = document.getElementById('addSubOrderForm');
    form.action = `${window.BASE_PATH}/add_sub_order/${orderId}`;

    const modal = new bootstrap.Modal(document.getElementById('addSubOrderModal'));
    modal.show();
}

// Function to edit an order
function editOrder(orderId) {
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!row || !row.dataset.order) return;

    const data = JSON.parse(row.dataset.order);
    const form = document.getElementById('editOrderForm');

    form.querySelector('[name="date"]').value = toISODate(data.date);
    form.querySelector('[name="width"]').value = data.width ?? '';
    form.querySelector('[name="height"]').value = data.height ?? '';
    form.querySelector('[name="profile"]').value = data.profile ?? '';
    form.querySelector('[name="glass"]').value = data.glass ?? '';
    const passepartoutSelect = form.querySelector('[name="passepartout_id"]');
    if (passepartoutSelect && data.passepartout && window.PASSEPARTOUT_MAP) {
        const passepartoutId = window.PASSEPARTOUT_MAP[data.passepartout];
        passepartoutSelect.value = passepartoutId ? String(passepartoutId) : '';
    }
    form.querySelector('[name="back"]').value = data.back ?? '';
    form.querySelector('[name="hanging"]').value = data.hanging ?? '';
    form.querySelector('[name="customer_name"]').value = data.customer_name ?? '';
    form.querySelector('[name="price"]').value = data.price ?? '';
    form.querySelector('[name="advance_payment"]').value = data.advance_payment ?? '';
    form.querySelector('[name="discount"]').value = data.discount ?? '';
    form.querySelector('[name="frame_count"]').value = data.frame_count ?? 1;
    form.querySelector('[name="paid"]').checked = Boolean(data.paid);
    form.querySelector('[name="collected"]').checked = Boolean(data.collected);
    form.querySelector('[name="description"]').value = data.description ?? '';

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

function collectOrderFormData(form) {
    const formData = new FormData(form);
    return formData;
}

async function updateOrderPricing(form) {
    const preview = form.querySelector('.order-pricing-preview');
    if (!preview) return;

    const formData = new FormData(form);
    const width = parseFloat(formData.get('width'));
    const height = parseFloat(formData.get('height'));

    if (!width || !height) {
        preview.style.display = 'none';
        return;
    }

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

        if (pricing.profile_cost > 0) {
            lines.push(`Профил: ${pricing.profile_meters} л.м. → ${pricing.profile_cost.toFixed(2)} €`);
        }
        if (pricing.glass_cost > 0) {
            lines.push(`Стъкло: ${pricing.glass_sqm} кв.м. → ${pricing.glass_cost.toFixed(2)} €`);
        }
        if (pricing.passepartout) {
            const pp = pricing.passepartout;
            lines.push(
                `Паспарту: фактурирано ${pp.bill_width}×${pp.bill_height} cm (${pp.sheet_name}) → ${pricing.passepartout_cost.toFixed(2)} €`
            );
        }
        if (pricing.total > 0) {
            lines.push(`<strong>Общо материали: ${pricing.total.toFixed(2)} €</strong>`);
            const priceInput = form.querySelector('[name="price"]');
            if (priceInput && priceInput.value === '') {
                priceInput.placeholder = pricing.total.toFixed(2);
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
        if (confirm('Поръчката не е маркирана като получена.\n\nМатериалите ще бъдат върнати в наличност при изтриване.\n\nСигурни ли сте, че искате да изтриете?')) {
            window.location.href = `${window.BASE_PATH}/delete_order/${orderId}?restore_stock=1`;
        }
        return;
    }

    if (confirm('Поръчката е маркирана като получена. Материалите няма да бъдат върнати в наличност.\n\nСигурни ли сте, че искате да изтриете?')) {
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

// Initialize tooltips and filters
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.order-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }

    const filterButtons = document.querySelectorAll('[data-filter]');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            const rows = document.querySelectorAll('.order-row');

            rows.forEach(row => {
                const order = row.dataset.order ? JSON.parse(row.dataset.order) : null;
                const paid = order ? Boolean(order.paid) : row.querySelector('.badge.bg-success') !== null;
                const collected = order ? Boolean(order.collected) : row.querySelector('.badge.bg-info') !== null;
                const shouldShow = (() => {
                    switch(filter) {
                        case 'all': return true;
                        case 'paid': return paid;
                        case 'unpaid': return !paid;
                        case 'collected': return collected;
                        case 'uncollected': return !collected;
                        default: return true;
                    }
                })();

                row.style.display = shouldShow ? '' : 'none';
            });
        });
    });

    const floatActions = document.querySelector('.row-float-actions');
    const orderRows = document.querySelectorAll('.order-row');
    const tableResponsive = document.querySelector('.table-responsive');

    orderRows.forEach(row => {
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

    document.querySelectorAll('.order-form').forEach((form) => {
        form.addEventListener('input', function (event) {
            if (event.target.classList.contains('order-price-trigger') || event.target.name === 'additional_profiles[]') {
                updateOrderPricing(form);
            }
        });
        form.addEventListener('change', function (event) {
            if (event.target.classList.contains('order-price-trigger') || event.target.name === 'passepartout_id') {
                updateOrderPricing(form);
            }
        });
    });
});

function toISODate(dateStr) {
    if (!dateStr) return '';
    if (dateStr.includes('/')) {
        const [d, m, y] = dateStr.split('/');
        return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
    }
    return dateStr;
}
