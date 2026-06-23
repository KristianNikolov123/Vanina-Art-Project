function initMultiAddForm(config) {
    const {
        containerSelector,
        addButtonSelector,
        modalSelector,
        rowSelector = '.multi-add-row',
        clearFields = ['name'],
        rowLabelSelector = '.multi-add-row__label',
        rowLabelText = 'Ред',
        namePattern = /\[\d+\]/,
    } = config;

    const container = document.querySelector(containerSelector);
    const addButton = document.querySelector(addButtonSelector);
    const modal = modalSelector ? document.querySelector(modalSelector) : null;

    if (!container || !addButton) {
        return;
    }

    function getRows() {
        return [...container.querySelectorAll(rowSelector)];
    }

    function readRowValues(row) {
        const values = {};
        row.querySelectorAll('[data-field]').forEach((input) => {
            const key = input.dataset.field;
            values[key] = input.type === 'checkbox' ? input.checked : input.value;
        });
        return values;
    }

    function writeRowValues(row, values, fieldsToClear = clearFields) {
        row.querySelectorAll('[data-field]').forEach((input) => {
            const key = input.dataset.field;
            if (fieldsToClear.includes(key)) {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
                return;
            }

            if (values[key] !== undefined) {
                if (input.type === 'checkbox') {
                    input.checked = Boolean(values[key]);
                } else {
                    input.value = values[key];
                }
            }
        });
    }

    function updateRowLabels() {
        getRows().forEach((row, index) => {
            row.dataset.rowIndex = String(index);
            const label = row.querySelector(rowLabelSelector);
            if (label) {
                label.textContent = `${rowLabelText} ${index + 1}`;
            }

            row.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(namePattern, `[${index}]`);
                if (input.id) {
                    input.id = input.id.replace(/_\d+$/, `_${index}`);
                }
            });

            row.querySelectorAll('[for]').forEach((labelEl) => {
                if (labelEl.htmlFor) {
                    labelEl.htmlFor = labelEl.htmlFor.replace(/_\d+$/, `_${index}`);
                }
            });

            const removeBtn = row.querySelector('.multi-add-remove');
            if (removeBtn) {
                removeBtn.style.display = getRows().length > 1 ? '' : 'none';
            }
        });
    }

    function addRow() {
        const rows = getRows();
        const lastRow = rows[rows.length - 1];
        if (!lastRow) {
            return;
        }

        const values = readRowValues(lastRow);
        const newRow = lastRow.cloneNode(true);
        container.appendChild(newRow);
        updateRowLabels();
        writeRowValues(newRow, values, clearFields);

        const nameInput = newRow.querySelector('[data-field="name"]');
        if (nameInput) {
            nameInput.focus();
        }
    }

    function removeRow(row) {
        if (getRows().length <= 1) {
            return;
        }
        row.remove();
        updateRowLabels();
    }

    container.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('.multi-add-remove');
        if (removeBtn) {
            const row = removeBtn.closest(rowSelector);
            if (row) {
                removeRow(row);
            }
        }
    });

    addButton.addEventListener('click', addRow);

    if (modal) {
        modal.addEventListener('show.bs.modal', () => {
            getRows().slice(1).forEach((row) => row.remove());
            const firstRow = getRows()[0];
            if (firstRow) {
                firstRow.querySelectorAll('[data-field]').forEach((input) => {
                    if (input.type === 'checkbox') {
                        input.checked = false;
                    } else if (input.dataset.field === 'name') {
                        input.value = '';
                    } else if (input.tagName === 'SELECT' && input.dataset.field === 'profile_type') {
                        input.value = 'wood';
                    } else if (input.type === 'number') {
                        input.value = '0';
                    } else {
                        input.value = '';
                    }
                });
            }
            updateRowLabels();
        });
    }

    updateRowLabels();
}
