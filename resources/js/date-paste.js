/*
 * Tarih alanlarına YAPIŞTIRMA (7 Kasım 2026, kullanıcı talimatı).
 *
 * Tarayıcının yerel `<input type="date">` alanı panodan metin kabul etmez; başka
 * bir programdan (ör. Elektraweb) kopyalanan "31.10.2026" bu yüzden yapıştırılamıyordu.
 * Burada pano metninden tarih ayıklanır ve alana ISO biçiminde (YYYY-MM-DD) yazılır.
 * Alanın gerçek değeri HER ZAMAN yerel input'tur; sunucu doğrulaması aynen sürer.
 */

/**
 * Pano metninden ilk geçerli tarihi bulur.
 * Kabul: 31.10.2026, 1.2.2026, 31/10/2026, 31-10-2026, 2026-10-31 (yanında saat/gün adı olabilir).
 *
 * @returns {string|null} YYYY-MM-DD veya geçerli tarih yoksa null
 */
export function parseClipboardDate(text) {
    if (typeof text !== 'string') return null;

    let day;
    let month;
    let year;
    let match = text.match(/(?<!\d)(\d{4})-(\d{1,2})-(\d{1,2})(?!\d)/);

    if (match) {
        [, year, month, day] = match;
    } else {
        match = text.match(/(?<!\d)(\d{1,2})[./-](\d{1,2})[./-](\d{4})(?!\d)/);
        if (!match) return null;
        [, day, month, year] = match;
    }

    const y = Number(year);
    const m = Number(month);
    const d = Number(day);
    const date = new Date(Date.UTC(y, m - 1, d));

    if (date.getUTCFullYear() !== y || date.getUTCMonth() !== m - 1 || date.getUTCDate() !== d) return null;

    return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function applyDate(input, iso) {
    input.value = iso;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function isDateInput(target) {
    return target instanceof HTMLInputElement && target.type === 'date' && !target.disabled && !target.readOnly;
}

if (typeof document !== 'undefined') {
    let lastPasteAt = 0;

    // Yerel `paste` olayı tetiklenirse (Ctrl/Cmd+V) pano metni doğrudan okunur.
    document.addEventListener('paste', (event) => {
        if (!isDateInput(event.target)) return;

        const iso = parseClipboardDate(event.clipboardData?.getData('text') ?? '');
        if (iso === null) return;

        event.preventDefault();
        lastPasteAt = Date.now();
        applyDate(event.target, iso);
    }, true);

    // Bazı tarayıcılar tarih alanında `paste` olayı göndermez; Ctrl/Cmd+V'de pano API'si yedek yoldur.
    document.addEventListener('keydown', (event) => {
        if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'v') return;
        const target = event.target;
        if (!isDateInput(target) || !navigator.clipboard?.readText) return;

        navigator.clipboard.readText().then((text) => {
            if (Date.now() - lastPasteAt < 500) return; // `paste` olayı zaten işledi
            const iso = parseClipboardDate(text);
            if (iso !== null) applyDate(target, iso);
        }).catch(() => {});
    });
}
