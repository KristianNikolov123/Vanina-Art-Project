function confirmDelete(hangingId) {
    if (confirm('Сигурни ли сте, че искате да изтриете тази опция?')) {
        window.location.href = `${window.BASE_PATH}/delete_hanging/${hangingId}`;
    }
}

function editHanging(hangingId) {
    const row = document.querySelector(`tr[data-hanging-id="${hangingId}"]`);
    const form = document.getElementById('editHangingForm');
    form.querySelector('[name="name"]').value = row.querySelector('.name').textContent;
    form.querySelector('[name="price"]').value = row.querySelector('.price').textContent;
    form.querySelector('[name="min_price"]').value = row.querySelector('.min-price').textContent;
    form.querySelector('[name="stock"]').value = row.dataset.stock || row.querySelector('.stock-badge').textContent.trim();
    form.querySelector('[name="pricing_unit"]').value = row.dataset.unit || 'piece';
    const stockHint = document.getElementById('editHangingStockHint');
    if (stockHint) {
        stockHint.style.display = row.dataset.sharedPool === '1' ? 'block' : 'none';
    }
    form.action = `${window.BASE_PATH}/edit_hanging/${hangingId}`;
    new bootstrap.Modal(document.getElementById('editHangingModal')).show();
}

document.getElementById('searchInput').addEventListener('input', function (e) {
    const searchText = e.target.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchText) ? '' : 'none';
    });
});
