function confirmDelete(profileId) {
    if (confirm('Сигурни ли сте, че искате да изтриете този профил?')) {
        window.location.href = `/delete_profile/${profileId}`;
    }
}

function editProfile(profileId) {
    // Get profile data from the table row
    const row = document.querySelector(`tr[data-profile-id="${profileId}"]`);
    const data = {
        name: row.querySelector('.name').textContent,
        price: row.querySelector('.price').textContent,
        stock: row.querySelector('.badge').textContent.trim()
    };

    // Fill the edit form
    const form = document.getElementById('editProfileForm');
    form.querySelector('[name="name"]').value = data.name;
    form.querySelector('[name="price"]').value = data.price;
    form.querySelector('[name="stock"]').value = data.stock;

    // Update form action
    form.action = `/edit_profile/${profileId}`;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
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