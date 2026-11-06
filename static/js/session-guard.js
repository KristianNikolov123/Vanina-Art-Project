/* CSRF: токенът от <meta name="csrf-token"> се праща с всяка POST fetch заявка. */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

(function () {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (input, init = {}) => {
        const method = String(init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        const url = typeof input === 'string' ? input : (input instanceof Request ? input.url : String(input));
        const sameOrigin = url.startsWith('/') || url.startsWith(window.location.origin);
        if (method !== 'GET' && method !== 'HEAD' && sameOrigin) {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', getCsrfToken());
            init = { ...init, headers };
        }
        return nativeFetch(input, init);
    };
}());

/** Изтриване/възстановяване: POST вместо линк (не може да се задейства от чужд сайт или линк). */
function postNavigate(url, params = {}) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = url;
    form.style.display = 'none';
    const fields = { ...params, _csrf: getCsrfToken() };
    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.dataset.sessionOk = '1';
    form.submit();
}

/*
 * Сесия и чернови.
 * - Сесията изтича след SESSION_IDLE_TIMEOUT_MINUTES бездействие (по подразбиране 240 мин., .env).
 * - Реална активност (клавиатура, мишка, докосване) удължава сесията чрез /api/ping.
 * - При изтекла сесия се показва прозорец за вход върху същата страница — без презареждане.
 * - Формите с data-draft-key се пазят автоматично като чернова в браузъра (localStorage).
 * - Изходът изтрива черновите на този потребител.
 */
(function () {
    const base = window.BASE_PATH || '';
    const PING_INTERVAL_MS = 5 * 60 * 1000;
    const HEARTBEAT_MS = 60 * 1000;
    const DRAFT_PREFIX = 'vaninaDraft:';
    const DRAFT_MAX_AGE_MS = 14 * 24 * 60 * 60 * 1000;

    let userId = String(window.CURRENT_USER_ID || '');
    let lastPing = Date.now();
    let loginModal = null;
    let pendingForm = null;
    let sessionLost = false;

    // ---------- Чернови ----------

    function storage() {
        try {
            const probe = '__vanina_probe__';
            window.localStorage.setItem(probe, '1');
            window.localStorage.removeItem(probe);
            return window.localStorage;
        } catch (e) {
            return null;
        }
    }

    function draftStorageKey(form) {
        const key = form?.dataset?.draftKey;
        if (!key || !userId) return '';
        return `${DRAFT_PREFIX}${userId}:${key}`;
    }

    const IGNORED_DRAFT_FIELDS = new Set(['stock_confirmed', '_order_id']);

    function saveFormDraft(form) {
        const store = storage();
        const key = draftStorageKey(form);
        if (!store || !key) return;
        const entries = [];
        new FormData(form).forEach((value, name) => {
            if (!IGNORED_DRAFT_FIELDS.has(name) && typeof value === 'string') {
                entries.push([name, value]);
            }
        });
        const meaningful = entries.some(([name, value]) => value.trim() !== ''
            && !['frame_count', 'passepartout_openings', 'passepartout_use', 'urgent', 'accepted_date'].includes(name));
        if (!meaningful) {
            store.removeItem(key);
            return;
        }
        try {
            store.setItem(key, JSON.stringify({ savedAt: Date.now(), entries }));
        } catch (e) {
            // квотата е пълна — черновата не е критична
        }
    }

    function clearFormDraft(form) {
        const store = storage();
        const key = draftStorageKey(form);
        if (store && key) {
            store.removeItem(key);
        }
        form?.querySelector('.form-draft-notice')?.remove();
    }

    function clearAllUserDrafts(id) {
        const store = storage();
        if (!store || !id) return;
        const prefix = `${DRAFT_PREFIX}${id}:`;
        const keys = [];
        for (let i = 0; i < store.length; i += 1) {
            const k = store.key(i);
            if (k && k.startsWith(prefix)) keys.push(k);
        }
        keys.forEach((k) => store.removeItem(k));
    }

    function ensureDynamicRows(form, counts) {
        const profileContainer = form.querySelector('[id^="additionalProfiles"]:not([id$="_extraPp"])');
        if (profileContainer && typeof appendAdditionalProfileField === 'function') {
            profileContainer.innerHTML = '';
            for (let i = 0; i < (counts['additional_profiles[]'] || 0); i += 1) {
                appendAdditionalProfileField(profileContainer, '');
            }
        }
        const ppContainer = form.querySelector('[id$="_extraPp"]');
        if (ppContainer && typeof appendAdditionalPassepartoutField === 'function') {
            ppContainer.innerHTML = '';
            for (let i = 0; i < (counts['additional_passepartouts[]'] || 0); i += 1) {
                appendAdditionalPassepartoutField(ppContainer, '');
            }
        }
    }

    function applyDraftEntries(form, entries) {
        const byName = new Map();
        entries.forEach(([name, value]) => {
            if (!byName.has(name)) byName.set(name, []);
            byName.get(name).push(value);
        });
        const counts = {};
        byName.forEach((values, name) => { counts[name] = values.length; });
        ensureDynamicRows(form, counts);

        const useSelect = form.querySelector('[name="passepartout_use"]');
        if (useSelect && byName.has('passepartout_use')) {
            useSelect.value = byName.get('passepartout_use')[0];
            if (typeof syncPassepartoutUseFields === 'function') syncPassepartoutUseFields(form);
        }

        form.querySelectorAll('input[type="checkbox"]').forEach((box) => {
            if (box.name && !IGNORED_DRAFT_FIELDS.has(box.name)) box.checked = false;
        });

        byName.forEach((values, name) => {
            if (name === 'passepartout_use') return;
            const fields = Array.from(form.querySelectorAll(`[name="${CSS.escape(name)}"]`)).filter((el) => !el.disabled);
            if (fields.length === 0) return;
            if (fields[0].type === 'checkbox') {
                fields.forEach((box) => { box.checked = values.includes(box.value || 'on'); });
                return;
            }
            fields.forEach((field, index) => {
                if (index < values.length) field.value = values[index];
            });
        });

        if (typeof syncPassepartoutUseFields === 'function') syncPassepartoutUseFields(form);
        if (typeof syncProfileReadyFrameFields === 'function') syncProfileReadyFrameFields(form);
        if (typeof syncExtrasDynamicFields === 'function') syncExtrasDynamicFields(form);
        if (typeof syncPickupDatePickerFromForm === 'function') syncPickupDatePickerFromForm(form);
    }

    function showDraftNotice(form, savedAt) {
        form.querySelector('.form-draft-notice')?.remove();
        const notice = document.createElement('div');
        notice.className = 'alert alert-warning py-1 px-2 small d-flex align-items-center justify-content-between gap-2 form-draft-notice';
        const time = new Date(savedAt).toLocaleString('bg-BG', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        notice.innerHTML = `<span><i class="fas fa-history me-1"></i>Възстановена е незапазена чернова от ${time}.</span>`;
        const discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'btn btn-sm btn-outline-secondary py-0';
        discard.textContent = 'Изчисти черновата';
        discard.addEventListener('click', () => {
            clearFormDraft(form);
            if (form.id === 'addOrderForm' && typeof clearAddOrderForm === 'function') {
                clearAddOrderForm();
            } else {
                notice.remove();
            }
        });
        notice.appendChild(discard);
        form.prepend(notice);
    }

    /** Възстановява черновата във формата. Връща true, ако е имало чернова. */
    function restoreFormDraftIfAny(form) {
        const store = storage();
        const key = draftStorageKey(form);
        if (!store || !key) return false;
        let draft = null;
        try {
            draft = JSON.parse(store.getItem(key) || 'null');
        } catch (e) {
            draft = null;
        }
        if (!draft || !Array.isArray(draft.entries)) return false;
        if (Date.now() - (draft.savedAt || 0) > DRAFT_MAX_AGE_MS) {
            store.removeItem(key);
            return false;
        }
        applyDraftEntries(form, draft.entries);
        showDraftNotice(form, draft.savedAt || Date.now());
        return true;
    }

    function bindDraftAutosave() {
        const timers = new WeakMap();
        const schedule = (form) => {
            clearTimeout(timers.get(form));
            timers.set(form, setTimeout(() => saveFormDraft(form), 400));
        };
        document.addEventListener('input', (event) => {
            const form = event.target.closest?.('form[data-draft-key]');
            if (form) schedule(form);
        });
        document.addEventListener('change', (event) => {
            const form = event.target.closest?.('form[data-draft-key]');
            if (form) schedule(form);
        });
        document.addEventListener('click', (event) => {
            // „+“ / „−“ бутоните променят редовете без input събитие.
            const form = event.target.closest?.('form[data-draft-key]');
            if (form && event.target.closest('button')) schedule(form);
        });
    }

    // ---------- Сесия ----------

    async function fetchJson(url, options = {}) {
        const response = await fetch(`${base}${url}`, {
            credentials: 'same-origin',
            ...options,
            headers: { Accept: 'application/json', ...(options.headers || {}) },
        });
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }
        return { status: response.status, data };
    }

    async function isAuthenticated() {
        try {
            const { data } = await fetchJson('/check_auth', { headers: { 'X-Session-Passive': '1' } });
            return Boolean(data && data.authenticated);
        } catch (e) {
            return true; // мрежова грешка — не прекъсваме работата
        }
    }

    async function pingActivity() {
        lastPing = Date.now();
        try {
            const { status } = await fetchJson('/api/ping', { method: 'POST' });
            if (status === 401) showSessionLoginModal(null);
        } catch (e) {
            // офлайн — ще опитаме пак
        }
    }

    function onActivity() {
        if (sessionLost) return;
        if (Date.now() - lastPing >= PING_INTERVAL_MS) {
            pingActivity();
        }
    }

    function buildLoginModal() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
<div class="modal session-login-modal" id="sessionLoginModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="sessionLoginForm" autocomplete="on">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-lock me-1"></i> Сесията изтече</h5>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Влезте отново, за да продължите. Въведеното във формите е запазено и няма да се загуби.</p>
          <div class="alert alert-danger py-1 small d-none" id="sessionLoginError"></div>
          <div class="mb-2">
            <label class="form-label" for="sessionLoginEmail">Имейл</label>
            <input type="email" class="form-control" id="sessionLoginEmail" name="email" autocomplete="username" required>
          </div>
          <div class="mb-2">
            <label class="form-label" for="sessionLoginPassword">Парола</label>
            <input type="password" class="form-control" id="sessionLoginPassword" name="password" autocomplete="current-password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary" id="sessionLoginSubmit">Вход</button>
        </div>
      </form>
    </div>
  </div>
</div>`;
        const modalEl = wrapper.firstElementChild;
        document.body.appendChild(modalEl);
        modalEl.querySelector('#sessionLoginForm').addEventListener('submit', submitSessionLogin);
        return modalEl;
    }

    function showSessionLoginModal(form) {
        if (form) pendingForm = form;
        sessionLost = true;
        document.querySelectorAll('form[data-draft-key]').forEach((f) => saveFormDraft(f));
        let modalEl = document.getElementById('sessionLoginModal');
        if (!modalEl) modalEl = buildLoginModal();
        if (!loginModal) loginModal = new bootstrap.Modal(modalEl);
        if (!modalEl.classList.contains('show')) {
            loginModal.show();
            setTimeout(() => modalEl.querySelector('#sessionLoginEmail')?.focus(), 300);
        }
    }

    function refreshCsrfToken(token) {
        if (!token) return;
        document.querySelectorAll('input[name="_csrf"]').forEach((input) => { input.value = token; });
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    async function submitSessionLogin(event) {
        event.preventDefault();
        const form = event.target;
        const errorBox = form.querySelector('#sessionLoginError');
        const button = form.querySelector('#sessionLoginSubmit');
        errorBox.classList.add('d-none');
        button.disabled = true;
        try {
            const { status, data } = await fetchJson('/api/login', { method: 'POST', body: new FormData(form) });
            if (status !== 200 || !data || !data.ok) {
                errorBox.textContent = (data && data.error) || 'Входът не бе успешен.';
                errorBox.classList.remove('d-none');
                return;
            }
            const newUserId = String(data.user_id || '');
            if (userId && newUserId !== userId) {
                // Друг потребител — черновите на предишния не се показват; презареждаме страницата.
                window.location.reload();
                return;
            }
            userId = newUserId;
            refreshCsrfToken(data.csrf_token);
            sessionLost = false;
            lastPing = Date.now();
            form.reset();
            loginModal.hide();
            const toSubmit = pendingForm;
            pendingForm = null;
            if (toSubmit) {
                toSubmit.dataset.sessionOk = '1';
                toSubmit.requestSubmit();
            }
        } catch (e) {
            errorBox.textContent = 'Няма връзка със сървъра. Опитайте отново.';
            errorBox.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    }

    function guardFormSubmits() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.id === 'sessionLoginForm') return;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            if (form.dataset.sessionOk === '1') {
                form.dataset.sessionOk = '';
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            const submitter = event.submitter && event.submitter.form === form ? event.submitter : null;
            if (await isAuthenticated()) {
                form.dataset.sessionOk = '1';
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
                return;
            }
            showSessionLoginModal(form);
        }, true);
    }

    function bindLogoutCleanup() {
        document.addEventListener('click', (event) => {
            const link = event.target.closest?.('a[href]');
            if (!link) return;
            const href = link.getAttribute('href') || '';
            if (href === `${base}/logout` || href.endsWith('/logout')) {
                clearAllUserDrafts(userId);
            }
        });
    }

    function startHeartbeat() {
        setInterval(async () => {
            if (sessionLost || document.hidden) return;
            if (!(await isAuthenticated())) {
                showSessionLoginModal(null);
            }
        }, HEARTBEAT_MS);
        document.addEventListener('visibilitychange', async () => {
            if (!document.hidden && !sessionLost && !(await isAuthenticated())) {
                showSessionLoginModal(null);
            }
        });
    }

    window.saveFormDraft = saveFormDraft;
    window.refreshCsrfToken = refreshCsrfToken;
    window.clearFormDraft = clearFormDraft;
    window.restoreFormDraftIfAny = restoreFormDraftIfAny;
    window.showSessionLoginModal = showSessionLoginModal;
    window.clearAllUserDrafts = clearAllUserDrafts;

    document.addEventListener('DOMContentLoaded', () => {
        if (!userId) return;
        ['keydown', 'mousedown', 'touchstart', 'input', 'wheel'].forEach((type) => {
            document.addEventListener(type, onActivity, { passive: true, capture: true });
        });
        bindDraftAutosave();
        guardFormSubmits();
        bindLogoutCleanup();
        startHeartbeat();
    });
}());
