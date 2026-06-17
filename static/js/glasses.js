function confirmDelete(glassId) {
    if (confirm('Сигурни ли сте, че искате да изтриете това стъкло?')) {
        window.location.href = `${window.BASE_PATH}/delete_glass/${glassId}`;
    }
}

function editGlass(glassId) {
    // Get glass data from the table row
    const row = document.querySelector(`tr[data-glass-id="${glassId}"]`);
    const form = document.getElementById('editGlassForm');
    form.querySelector('[name="name"]').value = row.querySelector('.name').textContent;
    form.querySelector('[name="price"]').value = row.querySelector('.price').textContent;
    form.querySelector('[name="min_price"]').value = row.querySelector('.min-price').textContent;
    form.querySelector('[name="stock"]').value = row.querySelector('.badge').textContent.trim();

    // Update form action
    form.action = `${window.BASE_PATH}/edit_glass/${glassId}`;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editGlassModal'));
    modal.show();
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchText = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
