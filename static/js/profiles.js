function confirmDelete(profileId) {
    if (confirm('Сигурни ли сте, че искате да изтриете този профил?')) {
        saveProfileListViewState();
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
    saveProfileListViewState();

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

const PROFILE_LIST_VIEW_KEY = 'vaninaProfilesListView';

function getActiveProfileSeriesFilter() {
    const active = document.querySelector('.profile-series-btn.active');
    return active ? (active.dataset.series || '') : '';
}

function getProfileListViewState() {
    return {
        series: getActiveProfileSeriesFilter(),
        sort: document.getElementById('profileSortSelect')?.value || 'name',
        search: document.getElementById('searchInput')?.value || '',
        scrollY: window.scrollY || 0,
    };
}

function saveProfileListViewState() {
    try {
        sessionStorage.setItem(PROFILE_LIST_VIEW_KEY, JSON.stringify(getProfileListViewState()));
    } catch (e) {
        // ignore quota / private mode
    }
}

function readProfileListViewStateFromStorage() {
    try {
        const raw = sessionStorage.getItem(PROFILE_LIST_VIEW_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function readProfileListViewStateFromUrl() {
    const params = new URLSearchParams(window.location.search);

    return {
        series: params.get('series') || '',
        sort: params.get('sort') || 'name',
        search: params.get('q') || '',
        scrollY: 0,
    };
}

function setActiveProfileSeriesButton(series) {
    document.querySelectorAll('.profile-series-btn').forEach((btn) => {
        const isMatch = (btn.dataset.series || '') === series;
        btn.classList.toggle('active', isMatch);
        btn.classList.toggle('btn-secondary', isMatch);
        btn.classList.toggle('btn-outline-secondary', !isMatch);
    });
}

function restoreProfileListViewState() {
    const params = new URLSearchParams(window.location.search);
    const hasUrlState = params.has('series') || params.has('sort') || params.has('q');
    const state = hasUrlState ? readProfileListViewStateFromUrl() : readProfileListViewStateFromStorage();

    const series = state.series ?? '';
    const sort = state.sort || 'name';
    const search = state.search ?? '';

    setActiveProfileSeriesButton(series);

    const sortSelect = document.getElementById('profileSortSelect');
    if (sortSelect && sortSelect.querySelector(`option[value="${CSS.escape(sort)}"]`)) {
        sortSelect.value = sort;
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = search;
    }

    return typeof state.scrollY === 'number' ? state.scrollY : 0;
}

function compareProfileRows(a, b, sortMode) {
    const nameA = a.querySelector('.name').textContent.trim();
    const nameB = b.querySelector('.name').textContent.trim();

    switch (sortMode) {
        case 'number-asc': {
            const diff = (parseInt(a.dataset.sortNumber, 10) || 0) - (parseInt(b.dataset.sortNumber, 10) || 0);
            return diff !== 0 ? diff : nameA.localeCompare(nameB, 'bg', { sensitivity: 'base' });
        }
        case 'number-desc': {
            const diff = (parseInt(b.dataset.sortNumber, 10) || 0) - (parseInt(a.dataset.sortNumber, 10) || 0);
            return diff !== 0 ? diff : nameA.localeCompare(nameB, 'bg', { sensitivity: 'base' });
        }
        case 'price-asc': {
            const diff = parseFloat(a.dataset.sortPrice || 0) - parseFloat(b.dataset.sortPrice || 0);
            return diff !== 0 ? diff : nameA.localeCompare(nameB, 'bg', { sensitivity: 'base' });
        }
        case 'price-desc': {
            const diff = parseFloat(b.dataset.sortPrice || 0) - parseFloat(a.dataset.sortPrice || 0);
            return diff !== 0 ? diff : nameA.localeCompare(nameB, 'bg', { sensitivity: 'base' });
        }
        case 'name':
        default:
            return nameA.localeCompare(nameB, 'bg', { numeric: true, sensitivity: 'base' });
    }
}

function applyProfileTableView(onBulkUpdate) {
    const tbody = document.getElementById('profilesTableBody');
    if (!tbody) {
        return;
    }

    const searchText = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const seriesFilter = getActiveProfileSeriesFilter();
    const sortMode = document.getElementById('profileSortSelect')?.value || 'name';

    const rows = [...tbody.querySelectorAll('tr')];
    rows.sort((a, b) => compareProfileRows(a, b, sortMode));
    rows.forEach((row) => tbody.appendChild(row));

    rows.forEach((row) => {
        const name = row.querySelector('.name').textContent.trim().toLowerCase();
        const series = row.dataset.series || '';
        const matchesSearch = searchText === '' || name.includes(searchText) || row.textContent.toLowerCase().includes(searchText);
        const matchesSeries = seriesFilter === '' || series === seriesFilter;
        row.style.display = matchesSearch && matchesSeries ? '' : 'none';
    });

    if (typeof onBulkUpdate === 'function') {
        onBulkUpdate();
    }

    saveProfileListViewState();
}

document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    bindBulkFieldToggles('#bulkEditProfilesForm');

    const bulkSelection = initBulkSelection({
        selectAllSelector: '#selectAllProfiles',
        bulkButtonSelector: '#bulkEditProfilesBtn',
        bulkCountSelector: '#bulkProfilesCount',
        onBulkClick: openBulkEditProfiles,
    });

    const refreshTable = () => applyProfileTableView(bulkSelection?.updateBulkUI);

    document.getElementById('searchInput')?.addEventListener('input', refreshTable);

    document.getElementById('profileSortSelect')?.addEventListener('change', refreshTable);

    document.querySelectorAll('.profile-series-btn').forEach((button) => {
        button.addEventListener('click', () => {
            setActiveProfileSeriesButton(button.dataset.series || '');
            refreshTable();
        });
    });

    const savedScrollY = restoreProfileListViewState();
    applyProfileTableView(bulkSelection?.updateBulkUI);
    if (savedScrollY > 0) {
        requestAnimationFrame(() => {
            window.scrollTo(0, savedScrollY);
            saveProfileListViewState();
        });
    }

    document.getElementById('editProfileForm')?.addEventListener('submit', saveProfileListViewState);
    document.getElementById('addProfileForm')?.addEventListener('submit', saveProfileListViewState);
    document.getElementById('bulkEditProfilesForm')?.addEventListener('submit', saveProfileListViewState);
    document.getElementById('raiseProfilePricesForm')?.addEventListener('submit', saveProfileListViewState);

    const raisePricesForm = document.getElementById('raiseProfilePricesForm');
    if (raisePricesForm && bulkSelection) {
        raisePricesForm.addEventListener('submit', function (event) {
            const ids = bulkSelection.getSelectedIds();
            fillBulkHiddenIds(raisePricesForm, ids);

            const message = ids.length > 0
                ? `Повишаване с 10% (закръгляне нагоре до 0.10 €) за ${ids.length} избрани профила?\n\nДействието не може да се отмени автоматично.`
                : 'Повишаване с 10% (закръгляне нагоре до 0.10 €) за ВСИЧКИ профили в склада?\n\nДействието не може да се отмени автоматично.';

            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    }

    initMultiAddForm({
        containerSelector: '#profileRowsContainer',
        addButtonSelector: '#addProfileRowBtn',
        modalSelector: '#addProfileModal',
        rowLabelText: 'Профил',
    });
});
