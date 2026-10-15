function restoreArchivedOrder(orderId) {
    if (confirm('Да възстановя ли поръчката в списъка „Поръчки“?')) {
        window.location.href = `${window.BASE_PATH}/restore_order/${orderId}`;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('archiveSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const searchRaw = this.value;
            document.querySelectorAll('.archive-table tbody .order-row').forEach((row) => {
                const haystack = typeof buildOrderRowSearchHaystack === 'function'
                    ? buildOrderRowSearchHaystack(row)
                    : (row.textContent || '');
                const match = typeof rowMatchesTokenSearch === 'function'
                    ? rowMatchesTokenSearch(haystack, searchRaw)
                    : haystack.toLowerCase().includes(searchRaw.trim().toLowerCase());
                row.style.display = match ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.archive-table .order-row').forEach((row) => {
        row.addEventListener('click', function () {
            viewOrder(row.dataset.orderId);
        });
    });
});
