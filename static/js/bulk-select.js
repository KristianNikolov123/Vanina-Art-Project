function initBulkSelection(config) {
    const {
        tableSelector = 'tbody',
        rowCheckboxSelector = '.bulk-row-checkbox',
        selectAllSelector,
        bulkButtonSelector,
        bulkCountSelector,
        onBulkClick,
    } = config;

    const table = document.querySelector(tableSelector);
    if (!table) {
        return { getSelectedIds: () => [] };
    }

    const selectAll = document.querySelector(selectAllSelector);
    const bulkBtn = document.querySelector(bulkButtonSelector);
    const bulkCount = document.querySelector(bulkCountSelector);

    function getVisibleCheckboxes() {
        return [...table.querySelectorAll(rowCheckboxSelector)].filter((cb) => {
            const row = cb.closest('tr');
            return row && row.style.display !== 'none';
        });
    }

    function getSelectedIds() {
        return getVisibleCheckboxes()
            .filter((cb) => cb.checked)
            .map((cb) => cb.value);
    }

    function updateBulkUI() {
        const count = getSelectedIds().length;

        if (bulkBtn) {
            bulkBtn.disabled = count === 0;
        }
        if (bulkCount) {
            bulkCount.textContent = count > 0 ? ` (${count})` : '';
        }
        if (selectAll) {
            const visible = getVisibleCheckboxes();
            selectAll.checked = visible.length > 0 && visible.every((cb) => cb.checked);
            selectAll.indeterminate = visible.some((cb) => cb.checked) && !selectAll.checked;
        }
    }

    table.addEventListener('change', (event) => {
        if (event.target.matches(rowCheckboxSelector)) {
            updateBulkUI();
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            getVisibleCheckboxes().forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            updateBulkUI();
        });
    }

    if (bulkBtn && onBulkClick) {
        bulkBtn.addEventListener('click', () => onBulkClick(getSelectedIds()));
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            setTimeout(updateBulkUI, 0);
        });
    }

    updateBulkUI();

    return { getSelectedIds, updateBulkUI };
}

function bindBulkFieldToggles(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) {
        return;
    }

    form.querySelectorAll('[data-bulk-toggle]').forEach((checkbox) => {
        const targetName = checkbox.dataset.bulkToggle;
        const input = form.querySelector(`[name="${targetName}"]`);
        const extraNames = (checkbox.dataset.bulkToggleExtras || '')
            .split(',')
            .map((name) => name.trim())
            .filter(Boolean);

        const toggle = () => {
            const enabled = checkbox.checked;
            if (input) {
                input.disabled = !enabled;
            }
            extraNames.forEach((name) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el) {
                    el.disabled = !enabled;
                }
            });
        };

        checkbox.addEventListener('change', toggle);
        toggle();
    });
}

function fillBulkHiddenIds(form, ids, inputName = 'ids[]') {
    const container = form.querySelector('.bulk-ids-container');
    if (!container) {
        return;
    }

    container.innerHTML = '';
    ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = id;
        container.appendChild(input);
    });
}

function resetBulkForm(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) {
        return;
    }

    form.reset();
    form.querySelectorAll('[data-bulk-toggle]').forEach((checkbox) => {
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change'));
    });
}
