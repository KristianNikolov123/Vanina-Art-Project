// Function to add additional profile fields
function addProfileField(containerId = 'additionalProfiles') {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'input-group mt-2';
    div.innerHTML = `
        <input type="text" class="form-control" name="additional_profiles[]">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
            <i class="fas fa-minus"></i>
        </button>
    `;
    container.appendChild(div);
}

// Function to add a sub-order
function addSubOrder(orderId) {
    const form = document.getElementById('addSubOrderForm');
    form.action = `/add_sub_order/${orderId}`;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('addSubOrderModal'));
    modal.show();
}

// Function to edit an order
function editOrder(orderId) {
    // Get order data from the table row
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!row) return;

    const data = {
        date: row.querySelector('.date').textContent,
        width: row.querySelector('.width').textContent,
        height: row.querySelector('.height').textContent,
        profile: row.querySelector('.profile').textContent,
        additional_profiles: row.querySelector('.additional-profiles').textContent,
        glass: row.querySelector('.glass').textContent,
        passepartout: row.querySelector('.passepartout').textContent,
        back: row.querySelector('.back').textContent,
        hanging: row.querySelector('.hanging').textContent,
        customer_name: row.querySelector('.customer-name').textContent,
        price: row.querySelector('.price').textContent,
        advance_payment: row.querySelector('.advance-payment').textContent,
        discount: row.querySelector('.discount').textContent,
        frame_count: row.querySelector('.frame-count').textContent,
        paid: row.querySelector('.badge.bg-success') !== null,
        collected: row.querySelector('.badge.bg-info') !== null
    };

    console.log('Data from table:', data);

    const form = document.getElementById('editOrderForm');
    form.querySelector('[name="date"]').value = toISODate(data.date);
    form.querySelector('[name="width"]').value = data.width;
    form.querySelector('[name="height"]').value = data.height;
    form.querySelector('[name="profile"]').value = data.profile;
    
    // Set select values by finding the option with matching value attribute
    const glassSelect = form.querySelector('[name="glass"]');
    console.log('Glass select options:', Array.from(glassSelect.options).map(opt => ({ value: opt.value, text: opt.text })));
    Array.from(glassSelect.options).forEach(option => {
        if (option.value === data.glass) {
            option.selected = true;
        }
    });
    
    form.querySelector('[name="passepartout"]').value = data.passepartout;
    
    const backSelect = form.querySelector('[name="back"]');
    console.log('Back select options:', Array.from(backSelect.options).map(opt => ({ value: opt.value, text: opt.text })));
    Array.from(backSelect.options).forEach(option => {
        if (option.value === data.back) {
            option.selected = true;
        }
    });
    
    const hangingSelect = form.querySelector('[name="hanging"]');
    console.log('Hanging select options:', Array.from(hangingSelect.options).map(opt => ({ value: opt.value, text: opt.text })));
    Array.from(hangingSelect.options).forEach(option => {
        if (option.value === data.hanging) {
            option.selected = true;
        }
    });
    
    form.querySelector('[name="customer_name"]').value = data.customer_name;
    form.querySelector('[name="price"]').value = data.price;
    form.querySelector('[name="advance_payment"]').value = data.advance_payment;
    form.querySelector('[name="discount"]').value = data.discount;
    form.querySelector('[name="frame_count"]').value = data.frame_count;
    form.querySelector('[name="paid"]').checked = data.paid;
    form.querySelector('[name="collected"]').checked = data.collected;

    // Clear and populate additional profiles
    const additionalProfilesContainer = document.getElementById('additionalProfilesEdit');
    additionalProfilesContainer.innerHTML = '';
    if (data.additional_profiles) {
        const profiles = data.additional_profiles.split(', ');
        profiles.forEach(profile => {
            const div = document.createElement('div');
            div.className = 'input-group mt-2';
            div.innerHTML = `
                <input type="text" class="form-control" name="additional_profiles[]" value="${profile}">
                <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-minus"></i>
                </button>
            `;
            additionalProfilesContainer.appendChild(div);
        });
    }

    // Попълване на описанието
    let description = '';
    const descCell = row.querySelector('.description');
    if (descCell) {
        const preview = descCell.querySelector('.description-preview');
        if (preview && preview.dataset.description) {
            description = preview.dataset.description;
        } else {
            description = descCell.textContent;
        }
    }
    form.querySelector('[name="description"]').value = description;

    // Update form action
    form.action = `/edit_order/${orderId}`;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

// Function to confirm order deletion
function confirmDelete(orderId) {
    if (confirm('Сигурни ли сте, че искате да изтриете тази поръчка?')) {
        window.location.href = `/delete_order/${orderId}`;
    }
}

// Function to show full description in modal
function showFullDescription(description) {
    const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
    document.getElementById('fullDescription').textContent = description;
    modal.show();
}

// Initialize tooltips and filters
document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });


    // Initialize search functionality
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

    // Initialize filter buttons
    const filterButtons = document.querySelectorAll('[data-filter]');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                const paid = row.querySelector('.badge.bg-success') !== null;
                const collected = row.querySelector('.badge.bg-info') !== null;
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

    // Floating action buttons logic
    const floatActions = document.querySelector('.row-float-actions');
    const orderRows = document.querySelectorAll('.order-row');
    const tableResponsive = document.querySelector('.table-responsive');

    orderRows.forEach(row => {
        row.addEventListener('mouseenter', function () {
            const orderId = row.dataset.orderId;
            const rowRect = row.getBoundingClientRect();
            const containerRect = tableResponsive.getBoundingClientRect();
            console.log('Hover row:', orderId, rowRect.top, containerRect.top);
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
});

function toISODate(dateStr) {
    // Приема DD/MM/YYYY или YYYY-MM-DD, връща YYYY-MM-DD
    if (!dateStr) return '';
    if (dateStr.includes('/')) {
        const [d, m, y] = dateStr.split('/');
        return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
    }
    return dateStr;
}
