function confirmDelete(passepartoutId) {
    if (confirm('Сигурни ли сте, че искате да изтриете това паспарту?')) {
        window.location.href = `${window.BASE_PATH}/delete_passepartout/${passepartoutId}`;
    }
}

function updatePassepartoutTierLabels(root, scheme) {
    const labels = (window.PASSEPARTOUT_TIER_LABELS || {})[scheme] || [];
    root.querySelectorAll('[data-pp-tier-label]').forEach((labelEl) => {
        const tier = Number(labelEl.dataset.ppTierLabel);
        const tierLabel = labels[tier - 1] || `Ниво ${tier}`;
        labelEl.textContent = tierLabel;
    });
}

function applyPassepartoutPriceKind(root, kind, options = {}) {
    const { preserveTiers = false } = options;
    const definitions = window.PASSEPARTOUT_PRICE_KINDS || {};
    const kindSelect = root.querySelector('[data-pp-price-kind]');
    const schemeSelect = root.querySelector('[data-pp-tier-scheme]');
    const manualScheme = root.querySelector('[data-pp-manual-scheme]');

    if (kindSelect && kindSelect.value !== kind) {
        kindSelect.value = kind;
    }

    const isManual = kind === 'manual';
    if (manualScheme) {
        manualScheme.style.display = isManual ? '' : 'none';
    }

    if (!isManual && definitions[kind]) {
        const definition = definitions[kind];
        if (schemeSelect) {
            schemeSelect.value = definition.scheme;
        }
        if (!preserveTiers) {
            definition.tiers.forEach((price, index) => {
                const input = root.querySelector(`[data-pp-tier-input="${index + 1}"]`);
                if (input) {
                    input.value = price;
                }
            });
        }
        updatePassepartoutTierLabels(root, definition.scheme);
        return;
    }

    const scheme = schemeSelect ? schemeSelect.value : '80x100';
    updatePassepartoutTierLabels(root, scheme);
}

function bindPassepartoutPricingRoot(root) {
    if (!root || root.dataset.ppPricingBound === '1') {
        return;
    }
    root.dataset.ppPricingBound = '1';

    const kindSelect = root.querySelector('[data-pp-price-kind]');
    const schemeSelect = root.querySelector('[data-pp-tier-scheme]');

    if (kindSelect) {
        kindSelect.addEventListener('change', () => {
            applyPassepartoutPriceKind(root, kindSelect.value);
        });
    }

    if (schemeSelect) {
        schemeSelect.addEventListener('change', () => {
            if ((kindSelect?.value || 'manual') === 'manual') {
                updatePassepartoutTierLabels(root, schemeSelect.value);
            }
        });
    }

    applyPassepartoutPriceKind(root, kindSelect?.value || 'manual', { preserveTiers: true });
}

function setPassepartoutPricingValues(root, data) {
    const kindSelect = root.querySelector('[data-pp-price-kind]');
    const schemeSelect = root.querySelector('[data-pp-tier-scheme]');
    const kind = data.priceKind || 'manual';
    const definitions = window.PASSEPARTOUT_PRICE_KINDS || {};
    let tiers = data.tiers || [];

    if (kind !== 'manual' && definitions[kind]) {
        tiers = definitions[kind].tiers || tiers;
    }

    if (kindSelect) {
        kindSelect.value = kind;
    }
    if (schemeSelect) {
        schemeSelect.value = data.tierScheme || '80x100';
    }

    tiers.forEach((price, index) => {
        const input = root.querySelector(`[data-pp-tier-input="${index + 1}"]`);
        if (input) {
            input.value = price;
        }
    });

    applyPassepartoutPriceKind(root, kind, { preserveTiers: kind === 'manual' });
}

function editPassepartout(passepartoutId) {
    const row = document.querySelector(`tr[data-passepartout-id="${passepartoutId}"]`);
    const data = {
        name: row.querySelector('.name').textContent,
        sheetStocks: JSON.parse(row.dataset.sheetStocks || '{}'),
        priceKind: row.dataset.priceKind || 'manual',
        tierScheme: row.dataset.tierScheme || '80x100',
        tiers: JSON.parse(row.dataset.priceTiers || '[]'),
    };

    const form = document.getElementById('editPassepartoutForm');
    form.querySelector('[name="name"]').value = data.name;

    form.querySelectorAll('.edit-sheet-stock').forEach((input) => {
        const sheetId = input.dataset.sheetId;
        input.value = data.sheetStocks[sheetId] ?? data.sheetStocks[parseInt(sheetId, 10)] ?? 0;
    });

    const pricingRoot = form.querySelector('[data-pp-pricing-root]');
    if (pricingRoot) {
        setPassepartoutPricingValues(pricingRoot, data);
    }

    form.action = `${window.BASE_PATH}/edit_passepartout/${passepartoutId}`;

    const modal = new bootstrap.Modal(document.getElementById('editPassepartoutModal'));
    modal.show();
}

function bindBulkPassepartoutPricingToggle() {
    const form = document.getElementById('bulkEditPassepartoutsForm');
    if (!form || form.dataset.bulkPricingToggleBound === '1') {
        return;
    }
    form.dataset.bulkPricingToggleBound = '1';

    const checkbox = form.querySelector('#bulk_pp_apply_pricing');
    const block = form.querySelector('[data-bulk-pricing-block]');
    if (!checkbox || !block) {
        return;
    }

    const toggle = () => {
        const enabled = checkbox.checked;
        block.querySelectorAll('input, select, textarea').forEach((el) => {
            el.disabled = !enabled;
        });
    };

    checkbox.addEventListener('change', toggle);
    toggle();
}

function openBulkEditPassepartouts(ids) {
    const form = document.getElementById('bulkEditPassepartoutsForm');
    fillBulkHiddenIds(form, ids);
    resetBulkForm('#bulkEditPassepartoutsForm');

    const pricingRoot = form.querySelector('[data-bulk-pricing-block] [data-pp-pricing-root]');
    if (pricingRoot) {
        bindPassepartoutPricingRoot(pricingRoot);
        applyPassepartoutPriceKind(pricingRoot, 'manual');
    }
    bindBulkPassepartoutPricingToggle();

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
    bindBulkPassepartoutPricingToggle();

    initBulkSelection({
        selectAllSelector: '#selectAllPassepartouts',
        bulkButtonSelector: '#bulkEditPassepartoutsBtn',
        bulkCountSelector: '#bulkPassepartoutsCount',
        onBulkClick: openBulkEditPassepartouts,
    });

    document.querySelectorAll('[data-pp-pricing-root]').forEach(bindPassepartoutPricingRoot);

    const addModal = document.getElementById('addPassepartoutModal');
    if (addModal) {
        addModal.addEventListener('shown.bs.modal', () => {
            document.querySelectorAll('#passepartoutRowsContainer [data-pp-pricing-root]').forEach((root) => {
                bindPassepartoutPricingRoot(root);
                applyPassepartoutPriceKind(root, 'manual');
            });
        });
    }

    const rowsContainer = document.getElementById('passepartoutRowsContainer');
    if (rowsContainer) {
        const observer = new MutationObserver(() => {
            rowsContainer.querySelectorAll('[data-pp-pricing-root]').forEach((root) => {
                bindPassepartoutPricingRoot(root);
            });
        });
        observer.observe(rowsContainer, { childList: true, subtree: true });
    }

    initMultiAddForm({
        containerSelector: '#passepartoutRowsContainer',
        addButtonSelector: '#addPassepartoutRowBtn',
        modalSelector: '#addPassepartoutModal',
        rowLabelText: 'Паспарту',
    });
});
