function restoreArchivedOrder(orderId) {
    if (confirm('Да възстановя ли поръчката в списъка „Поръчки“?')) {
        window.location.href = `${window.BASE_PATH}/restore_order/${orderId}`;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('archiveSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const searchText = this.value.toLowerCase();
            document.querySelectorAll('.archive-table tbody .order-row').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(searchText) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.archive-table .order-row').forEach((row) => {
        row.addEventListener('click', function () {
            viewOrder(row.dataset.orderId);
        });
    });
});
