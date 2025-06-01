function confirmDelete(passepartoutId) {
    if (confirm('Сигурни ли сте, че искате да изтриете това паспарту?')) {
        window.location.href = `/delete_passepartout/${passepartoutId}`;
    }
}

function editPassepartout(passepartoutId) {
    // Get passepartout data from the table row
    const row = document.querySelector(`tr[data-passepartout-id="${passepartoutId}"]`);
    const data = {
        name: row.querySelector('.name').textContent,
        price: row.querySelector('.price').textContent,
        stock: row.querySelector('.badge').textContent.trim()
    };

    // Fill the edit form
    const form = document.getElementById('editPassepartoutForm');
    form.querySelector('[name="name"]').value = data.name;
    form.querySelector('[name="price"]').value = data.price;
    form.querySelector('[name="stock"]').value = data.stock;

    // Update form action
    form.action = `/edit_passepartout/${passepartoutId}`;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editPassepartoutModal'));
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