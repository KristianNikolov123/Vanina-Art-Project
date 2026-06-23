function confirmDelete(profileId) {
    if (confirm('Сигурни ли сте, че искате да изтриете този профил?')) {
        window.location.href = `${window.BASE_PATH}/delete_profile/${profileId}`;
    }
}

function editProfile(profileId) {
    const row = document.querySelector(`tr[data-profile-id="${profileId}"]`);
    const widthText = row.querySelector('.width-cm').textContent.trim();
    const data = {
        name: row.querySelector('.name').textContent,
        width_cm: widthText === '—' ? '' : widthText,
        price: row.querySelector('.price').textContent,
        stock: row.querySelector('.badge').textContent.trim()
    };

    const form = document.getElementById('editProfileForm');
    form.querySelector('[name="name"]').value = data.name;
    form.querySelector('[name="profile_type"]').value = row.dataset.profileType || 'wood';
    form.querySelector('[name="width_cm"]').value = data.width_cm;
    form.querySelector('[name="price"]').value = data.price;
    form.querySelector('[name="stock"]').value = data.stock;
    form.action = `${window.BASE_PATH}/edit_profile/${profileId}`;

    const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    modal.show();
}

function openBulkEditProfiles(ids) {
    const form = document.getElementById('bulkEditProfilesForm');
    fillBulkHiddenIds(form, ids);
    resetBulkForm('#bulkEditProfilesForm');

    const modal = new bootstrap.Modal(document.getElementById('bulkEditProfilesModal'));
    modal.show();
}

document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchText = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    bindBulkFieldToggles('#bulkEditProfilesForm');

    initBulkSelection({
        selectAllSelector: '#selectAllProfiles',
        bulkButtonSelector: '#bulkEditProfilesBtn',
        bulkCountSelector: '#bulkProfilesCount',
        onBulkClick: openBulkEditProfiles,
    });

    initMultiAddForm({
        containerSelector: '#profileRowsContainer',
        addButtonSelector: '#addProfileRowBtn',
        modalSelector: '#addProfileModal',
        rowLabelText: 'Профил',
    });
});
