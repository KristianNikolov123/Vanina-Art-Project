function confirmDelete(backId) {
    if (confirm('Сигурни ли сте, че искате да изтриете този гръб?')) {
        window.location.href = `${window.BASE_PATH}/delete_back/${backId}`;
    }
}

function editBack(backId) {
    const row = document.querySelector(`tr[data-back-id="${backId}"]`);
    const form = document.getElementById('editBackForm');
    form.querySelector('[name="name"]').value = row.querySelector('.name').textContent;
    form.querySelector('[name="price"]').value = row.querySelector('.price').textContent;
    form.querySelector('[name="min_price"]').value = row.querySelector('.min-price').textContent;
    form.querySelector('[name="stock"]').value = row.querySelector('.badge').textContent.trim();
    form.action = `${window.BASE_PATH}/edit_back/${backId}`;
    new bootstrap.Modal(document.getElementById('editBackModal')).show();
}

document.getElementById('searchInput').addEventListener('input', function (e) {
    const searchText = e.target.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchText) ? '' : 'none';
    });
});
