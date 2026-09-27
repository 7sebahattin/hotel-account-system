/** Personnel kiosk reader. USB-HID reader input is intentionally scoped to its
 * dedicated field: it never captures normal keyboard input elsewhere. */
export function canonicalCardUid(value) {
    const uid = String(value ?? '').trim().replace(/[\s:.-]/g, '').toUpperCase();
    return /^[0-9A-F]{8,40}$/.test(uid) && uid.length % 2 === 0 ? uid : null;
}

export function newRequestId() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
    else for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 0x0f) | 0x40; bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function createKioskClient({ fetchImpl = globalThis.fetch, scanUrl, csrfToken, onResult = () => {}, onError = () => {}, storage = null, storageKey = 'personnel-kiosk-pending' }) {
    let pending = false;
    let retryPayload = (() => { try { return storage?.getItem(storageKey) ? JSON.parse(storage.getItem(storageKey)) : null; } catch { return null; } })();
    const send = async (payload) => {
        if (pending || (retryPayload && JSON.stringify(retryPayload) !== JSON.stringify(payload))) return null;
        pending = true;
        retryPayload = payload; try { storage?.setItem(storageKey, JSON.stringify(payload)); } catch { /* storage is optional */ }
        try {
            const response = await fetchImpl(scanUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(payload) });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                // Validation/conflict/auth decisions are definite: retaining their UUID would only replay a rejected request.
                if ([400, 401, 403, 409, 422].includes(response.status)) { retryPayload = null; try { storage?.removeItem(storageKey); } catch {} }
                const error = new Error(body.message || 'Okutma şu anda kaydedilemedi.'); error.definite = [400, 401, 403, 409, 422].includes(response.status); throw error;
            }
            if (body.request_id !== payload.request_id || !['accepted', 'duplicate', 'unknown_card', 'inactive_employee', 'inactive_card', 'invalid_transition'].includes(body.result)) throw new Error('Sunucudan doğrulanamayan bir yanıt geldi. Aynı isteği yeniden deneyin.');
            retryPayload = null;
            try { storage?.removeItem(storageKey); } catch {}
            onResult(body);
            return body;
        } catch (error) {
            if (!error.definite) retryPayload = payload; // same UUID/body is retried after an uncertain network result
            onError(error, () => retryPayload && send(retryPayload), Boolean(error.definite));
            return null;
        } finally { pending = false; }
    };
    return { send, isPending: () => pending, hasRetry: () => retryPayload !== null, retry: () => retryPayload && send(retryPayload) };
}

function initKiosk(root) {
    const input = root.querySelector('[data-personnel-reader]');
    const form = root.querySelector('[data-personnel-kiosk-form]');
    const status = root.querySelector('[data-personnel-kiosk-status]');
    const retry = root.querySelector('[data-personnel-kiosk-retry]');
    if (!input || !form) return;
    let action = root.dataset.defaultAction || 'auto';
    const key = `personnel-kiosk-pending:${root.dataset.sessionKey || 'session'}`;
    const announce = (message, kind = 'muted') => { status.textContent = message; status.className = `mt-4 rounded-lg px-4 py-3 text-sm ${kind === 'success' ? 'bg-success-bg text-success' : kind === 'error' ? 'bg-danger-bg text-danger' : 'bg-well text-mut'}`; };
    const client = createKioskClient({ scanUrl: root.dataset.scanUrl, csrfToken: root.dataset.csrfToken, storage: globalThis.sessionStorage, storageKey: key, onResult: (body) => { const accepted = body.result === 'accepted'; const detail = accepted ? `${body.employee_name || 'Çalışan'} · ${body.action === 'in' ? 'Giriş' : 'Çıkış'} · ${body.occurred_at_label || body.occurred_at || ''}${body.shift_label ? ' · ' + body.shift_label : ''}` : ''; announce(`${body.message || 'Okutma işlendi.'}${detail ? ` ${detail}` : ''}`, accepted ? 'success' : 'error'); retry.hidden = true; input.value = ''; input.disabled = false; input.focus({ preventScroll: true }); }, onError: (error, retrySend, definite) => { announce(error.message, 'error'); retry.hidden = definite; input.disabled = !definite; retry.onclick = () => { retry.hidden = true; retrySend(); }; if (definite) { input.value = ''; input.focus({ preventScroll: true }); } } });
    if (client.hasRetry()) { announce('Önceki okutma henüz doğrulanmadı. Aynı isteği yeniden deneyin.', 'error'); input.disabled = true; retry.hidden = false; retry.onclick = () => { retry.hidden = true; client.retry(); }; }
    root.querySelectorAll('[data-personnel-action]').forEach((button) => button.addEventListener('click', () => { action = button.dataset.personnelAction; root.querySelectorAll('[data-personnel-action]').forEach((item) => item.setAttribute('aria-pressed', String(item === button))); input.focus({ preventScroll: true }); }));
    form.addEventListener('submit', (event) => { event.preventDefault(); if (client.isPending() || client.hasRetry()) return; const uid = canonicalCardUid(input.value); if (!uid) { announce('Kart kodu geçerli bir hexadecimal UID olmalıdır.', 'error'); return; } input.disabled = true; announce('Kart doğrulanıyor…'); client.send({ request_id: newRequestId(), card_uid: uid, action }).finally(() => { if (client.isPending()) return; }); });
    // Focus only when the user is not already working in another control.
    if (!document.activeElement || document.activeElement === document.body) input.focus({ preventScroll: true });
}

function initCardCapture(form) {
    const input = form.querySelector('[data-personnel-card-reader]');
    if (!input) return;
    input.addEventListener('blur', () => { const uid = canonicalCardUid(input.value); if (input.value && !uid) input.setCustomValidity('Kart UID değeri hexadecimal ve çift sayıda karakter olmalıdır.'); else { input.setCustomValidity(''); if (uid) input.value = uid; } });
}

function initCorrection(form) {
    const sessions = form.querySelector('[data-correction-sessions]');
    const clear = form.querySelector('[data-clear-day]');
    const day = form.querySelector('[name="work_date"]');
    const revision = form.querySelector('[data-correction-revision]');
    const add = form.querySelector('[data-add-session]');
    const template = sessions.firstElementChild.cloneNode(true);
    const days = JSON.parse(form.dataset.correctionMap || '{}');
    const localTime = (iso) => {
        if (!iso) return '';
        const parts = new Intl.DateTimeFormat('sv-SE', {timeZone:'Europe/Istanbul',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(iso));
        return parts.replace(' ', 'T');
    };
    const sync = () => {
        [...sessions.children].forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                const field = input.name.includes('in_at') ? 'in_at' : 'out_at';
                input.name = 'sessions['+index+']['+field+']';
                input.id = 'correction-'+field+'-'+index;
                input.previousElementSibling?.setAttribute('for',input.id);
                input.disabled = clear.checked;
                input.required = !clear.checked;
            });
        });
        add.disabled = clear.checked || sessions.children.length >= 12;
    };
    const append = (session = {}) => {
        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            input.value = localTime(session[input.name.includes('in_at') ? 'in_at' : 'out_at']);
        });
        sessions.append(row); sync();
    };
    clear.addEventListener('change',sync);
    add.addEventListener('click',()=>{ if (!add.disabled) append(); });
    sessions.addEventListener('click',(event)=>{
        const remove = event.target.closest('[data-remove-session]');
        if (!remove) return;
        remove.closest('[data-correction-session]').remove();
        if (!sessions.children.length) append();
        sync();
    });
    day.addEventListener('change',()=>{
        const selected = days[day.value] || {revision:0,sessions:[]};
        revision.value = selected.revision;
        clear.checked = false;
        sessions.replaceChildren();
        if (selected.sessions.length) selected.sessions.forEach(append); else append();
        sync();
    });
    sync();
}

if (typeof document !== 'undefined') {
    const initialize = () => {
        document.querySelectorAll('[data-personnel-kiosk]').forEach(initKiosk);
        document.querySelectorAll('[data-personnel-card-form]').forEach(initCardCapture);
        document.querySelectorAll('[data-personnel-request-id]').forEach(input => { if (!input.value) input.value = newRequestId(); });
        document.querySelectorAll('[data-personnel-correction]').forEach(initCorrection);
        document.querySelectorAll('[data-personnel-confirm]').forEach(form => form.addEventListener('submit',event=>{
            if (!globalThis.confirm(form.dataset.personnelConfirm)) event.preventDefault();
        }));
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded',initialize) : initialize();
}
