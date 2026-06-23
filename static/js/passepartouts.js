function confirmDelete(passepartoutId) {
    if (confirm('Сигурни ли сте, че искате да изтриете това паспарту?')) {
        window.location.href = `${window.BASE_PATH}/delete_passepartout/${passepartoutId}`;
    }
}

function editPassepartout(passepartoutId) {
    const row = document.querySelector(`tr[data-passepartout-id="${passepartoutId}"]`);
    const data = {
        name: row.querySelector('.name').textContent,
        price: row.querySelector('.price').textContent,
        sheetStocks: JSON.parse(row.dataset.sheetStocks || '{}')
    };

    const form = document.getElementById('editPassepartoutForm');
    form.querySelector('[name="name"]').value = data.name;
    form.querySelector('[name="price"]').value = data.price;

    form.querySelectorAll('.edit-sheet-stock').forEach((input) => {
        const sheetId = input.dataset.sheetId;
        input.value = data.sheetStocks[sheetId] ?? data.sheetStocks[parseInt(sheetId, 10)] ?? 0;
    });

    form.action = `${window.BASE_PATH}/edit_passepartout/${passepartoutId}`;

    const modal = new bootstrap.Modal(document.getElementById('editPassepartoutModal'));
    modal.show();
}

function openBulkEditPassepartouts(ids) {
    const form = document.getElementById('bulkEditPassepartoutsForm');
    fillBulkHiddenIds(form, ids);
    resetBulkForm('#bulkEditPassepartoutsForm');

    const modal = new bootstrap.Modal(document.getElementById('bulkEditPassepartoutsModal'));
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

    bindBulkFieldToggles('#bulkEditPassepartoutsForm');

    initBulkSelection({
        selectAllSelector: '#selectAllPassepartouts',
        bulkButtonSelector: '#bulkEditPassepartoutsBtn',
        bulkCountSelector: '#bulkPassepartoutsCount',
        onBulkClick: openBulkEditPassepartouts,
    });

    initMultiAddForm({
        containerSelector: '#passepartoutRowsContainer',
        addButtonSelector: '#addPassepartoutRowBtn',
        modalSelector: '#addPassepartoutModal',
        rowLabelText: 'Паспарту',
    });
});
