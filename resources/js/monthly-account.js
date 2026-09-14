// Aylık Hesap giriş biçimi ve önizlemeleri. Kesin kayıt sunucuda doğrulanır.
export const MAX_MONEY = 999999999999999n;

function decimalParts(value, precision, maxWholeDigits) {
    if (typeof value !== 'string') return null;
    const text = value.trim();
    if (!text) return null;
    let normalized;
    if (text.includes(',')) {
        const pattern = new RegExp(`^(?:\\d+|\\d{1,3}(?:\\.\\d{3})+),\\d{1,${precision}}$`);
        if (!pattern.test(text)) return null;
        normalized = text.replaceAll('.', '').replace(',', '.');
    } else {
        const pattern = new RegExp(`^\\d+(?:\\.\\d{1,${precision}})?$`);
        if (!pattern.test(text)) return null;
        normalized = text;
    }
    let [whole, fraction = ''] = normalized.split('.');
    whole = whole.replace(/^0+(?=\d)/, '');
    if (whole.length > maxWholeDigits) return null;
    return { whole, fraction: fraction.padEnd(precision, '0') };
}

export function parseMoney(value) {
    const parts = decimalParts(value, 2, 13);
    if (!parts) return null;
    const minor = BigInt(parts.whole) * 100n + BigInt(parts.fraction);
    return minor <= MAX_MONEY ? minor : null;
}

export function parseRate(value) {
    const parts = decimalParts(value, 4, 6);
    if (!parts) return null;
    const rate = BigInt(parts.whole) * 10000n + BigInt(parts.fraction);
    return rate > 0n ? rate : null;
}

export function formatMoney(minor) {
    const sign = minor < 0n ? '-' : '';
    const absolute = minor < 0n ? -minor : minor;
    return `${sign}${(absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${(absolute % 100n).toString().padStart(2, '0')}`;
}

export function formatRate(rate) {
    return `${rate / 10000n},${(rate % 10000n).toString().padStart(4, '0')}`;
}

export function convertByRate(amount, rate) {
    if (amount <= 0n || rate <= 0n) return null;
    const equivalent = (amount * rate + 5000n) / 10000n;
    return equivalent > 0n && equivalent <= MAX_MONEY ? equivalent : null;
}

export function rateFromTotal(amount, total) {
    if (amount <= 0n || total <= 0n) return null;
    const rate = (total * 10000n + amount / 2n) / amount;
    return rate > 0n && rate <= 9999999999n ? rate : null;
}

const invalidMoney = 'Tutarı yalnız rakamlarla ve en fazla iki ondalıkla girin. Örnek: 3.000,00';

function caretFromRaw(formatted, rawPosition) {
    let raw = 0;
    for (let position = 0; position < formatted.length; position++) {
        if (raw >= rawPosition) return position;
        if (formatted[position] !== '.') raw++;
    }
    return formatted.length;
}

function finishEdit(whole, fraction, position) {
    if (whole === '' && fraction.replaceAll('0', '') === '') return { value: '', caret: 0 };
    const leading = whole.length - whole.replace(/^0+(?=\d)/, '').length;
    whole = whole.replace(/^0+(?=\d)/, '') || '0';
    if (whole.length > 13 || fraction.length > 2) return { error: invalidMoney };
    const parsed = parseMoney(`${whole},${fraction.padEnd(2, '0')}`);
    if (parsed === null) return { error: invalidMoney };
    const value = formatMoney(parsed);
    return { value, caret: caretFromRaw(value, Math.max(0, position - leading)) };
}

/** Girdi kuruş kaydırmalı değildir: art arda 3,0,0,0 tuşları 3.000,00 üretir. */
export function editMoney(value, start, end, inputType, data = '') {
    const existing = value === '' ? null : parseMoney(value);
    if (value !== '' && existing === null) {
        if (start !== 0 || end !== value.length) return { error: invalidMoney };
        value = '';
    }
    const raw = value.replaceAll('.', '');
    const rawStart = value.slice(0, start).replaceAll('.', '').length;
    const rawEnd = value.slice(0, end).replaceAll('.', '').length;
    const comma = raw.indexOf(',');
    let [whole, fraction = '00'] = raw.split(',');
    const fullSelection = start === 0 && end === value.length;

    if (inputType === 'insertFromPaste' || inputType === 'insertReplacementText') {
        if (!fullSelection && /^\d+$/.test(data)) return editMoney(value, start, end, 'insertText', data);
        if (!fullSelection) return { error: 'Biçimli tutar yapıştırmak için alanın tamamını seçin.' };
        const amount = parseMoney(data);
        if (amount === null) return { error: invalidMoney };
        const formatted = formatMoney(amount);
        return { value: formatted, caret: formatted.indexOf(',') };
    }

    if (inputType.startsWith('delete')) {
        if (fullSelection) return { value: '', caret: 0 };
        let from = rawStart;
        let to = rawEnd;
        if (from === to) {
            if (inputType === 'deleteContentBackward') {
                if (from === comma + 1) return { value, caret: value.indexOf(',') };
                from = Math.max(0, from - 1);
            } else {
                if (from === comma) return { value, caret: value.indexOf(',') + 1 };
                to = Math.min(raw.length, to + 1);
            }
        }
        if (to <= comma || comma === -1) {
            whole = whole.slice(0, from) + whole.slice(to);
            return finishEdit(whole, fraction, from);
        }
        if (from > comma) {
            fraction = fraction.slice(0, from - comma - 1) + fraction.slice(to - comma - 1);
            return finishEdit(whole, fraction, from);
        }
        whole = whole.slice(0, from);
        fraction = fraction.slice(to - comma - 1);
        return finishEdit(whole, fraction, from);
    }

    if (inputType !== 'insertText') return null;
    if (data === ',' || data === '.') {
        const formatted = value || '0,00';
        return { value: formatted, caret: formatted.indexOf(',') + 1 };
    }
    if (!/^\d+$/.test(data)) return { error: invalidMoney };
    if (fullSelection) return finishEdit(data, '00', data.length);
    if (comma === -1 || rawStart <= comma) {
        const wholeEnd = comma < 0 ? rawEnd : Math.min(rawEnd, comma);
        whole = whole.slice(0, rawStart) + data + whole.slice(wholeEnd);
        if (rawEnd > comma && comma >= 0) fraction = fraction.slice(rawEnd - comma - 1);
        return finishEdit(whole, fraction, rawStart + data.length);
    }
    const decimalStart = rawStart - comma - 1;
    const decimalEnd = Math.max(rawEnd - comma - 1, decimalStart + data.length);
    fraction = fraction.slice(0, decimalStart) + data + fraction.slice(decimalEnd);
    return finishEdit(whole, fraction, rawStart + data.length);
}

function installMoneyInput(input) {
    if (input.dataset.moneyReady) return;
    input.dataset.moneyReady = '1';
    const existing = parseMoney(input.value);
    if (existing !== null) input.value = formatMoney(existing);

    const apply = (result) => {
        if (!result) return;
        if (result.error) {
            input.setCustomValidity(result.error);
            input.reportValidity();
            return;
        }
        input.setCustomValidity('');
        input.value = result.value;
        input.setSelectionRange(result.caret, result.caret);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    input.addEventListener('beforeinput', (event) => {
        if (!event.cancelable || event.isComposing || event.inputType === 'insertFromPaste') return;
        const result = editMoney(input.value, input.selectionStart ?? 0, input.selectionEnd ?? 0, event.inputType, event.data ?? '');
        if (result) {
            event.preventDefault();
            apply(result);
        }
    });
    input.addEventListener('paste', (event) => {
        event.preventDefault();
        apply(editMoney(input.value, input.selectionStart ?? 0, input.selectionEnd ?? 0, 'insertFromPaste', event.clipboardData?.getData('text') ?? ''));
    });
    input.addEventListener('input', () => {
        // Native autofill ve cancelable olmayan mobil girişler de aynı doğrulamadan geçer.
        const minor = parseMoney(input.value);
        input.setCustomValidity(input.value === '' || minor !== null ? '' : invalidMoney);
    });
    input.addEventListener('blur', () => {
        const minor = parseMoney(input.value);
        if (minor !== null) input.value = formatMoney(minor);
    });
}

function initMonthlyAccount(form) {
    if (form.dataset.monthlyReady) return;
    form.dataset.monthlyReady = '1';
    const container = form.querySelector('[data-monthly-items]');
    const template = form.querySelector('[data-monthly-item-template]');
    let nextIndex = Math.max(-1, ...Array.from(container.children, (row) => Number(row.dataset.itemIndex) || 0)) + 1;

    const preview = () => {
        let total = 0n;
        let incomplete = false;
        for (const row of container.querySelectorAll('[data-monthly-item]')) {
            const amount = parseMoney(row.querySelector('[data-item-amount]').value);
            const currency = row.querySelector('[data-item-currency]').value;
            const mode = row.querySelector('[data-item-mode]').value;
            const output = row.querySelector('[data-item-preview]');
            output.textContent = '';
            if (amount === null || amount <= 0n) {
                incomplete = true;
                continue;
            }
            let equivalent = amount;
            if (currency !== 'TRY') {
                if (mode === 'total') {
                    equivalent = parseMoney(row.querySelector('[data-item-total]').value);
                    const rate = equivalent === null ? null : rateFromTotal(amount, equivalent);
                    if (rate === null) equivalent = null;
                    else output.textContent = `Kur: ${formatRate(rate)} · ${formatMoney(equivalent)} TL`;
                } else {
                    const rate = parseRate(row.querySelector('[data-item-rate]').value);
                    equivalent = rate === null ? null : convertByRate(amount, rate);
                    if (equivalent !== null) output.textContent = `≈ ${formatMoney(equivalent)} TL`;
                }
            }
            if (equivalent === null) incomplete = true;
            else total += equivalent;
        }
        form.querySelector('[data-monthly-total]').textContent = `${formatMoney(total)} TL${incomplete ? ' · eksik satır var' : ''}`;
        const daily = parseMoney(form.querySelector('[data-daily-total]').value);
        form.querySelector('[data-monthly-summary-hint]').textContent = incomplete
            ? 'Toplamın tamamlanması için eksik tutar veya kur alanlarını doldurun.'
            : daily === null ? 'Listeye göre farkı görmek için günlük liste toplamını girin.'
                : `Listeye göre fark: ${formatMoney(total - daily)} TL (ödemeler − liste toplamı)`;
    };

    const configureRow = (row) => {
        const method = row.querySelector('[data-item-method]').value;
        const currency = row.querySelector('[data-item-currency]').value;
        const foreign = currency !== 'TRY';
        const bank = row.querySelector('[data-item-bank]');
        const needsBank = ['credit_card', 'link', 'bank_transfer'].includes(method);
        row.querySelector('[data-item-bank-group]').hidden = !needsBank;
        bank.required = needsBank;
        bank.disabled = !needsBank;
        row.querySelector('[data-item-currency-label]').textContent = currency === 'TRY' ? 'TL' : currency;
        row.querySelector('[data-item-fx]').hidden = !foreign;
        const mode = row.querySelector('[data-item-mode]');
        mode.disabled = !foreign;
        const rateMode = mode.value === 'rate';
        row.querySelector('[data-item-rate-group]').hidden = !rateMode;
        row.querySelector('[data-item-total-group]').hidden = rateMode;
        const rate = row.querySelector('[data-item-rate]');
        const equivalent = row.querySelector('[data-item-total]');
        rate.disabled = !foreign || !rateMode;
        rate.required = foreign && rateMode;
        equivalent.disabled = !foreign || rateMode;
        equivalent.required = foreign && !rateMode;
        const note = row.querySelector('[data-item-note]');
        row.querySelector(`label[for="${note.id}"]`).textContent = method === 'extra' ? 'Ekstra tahsilat notu' : 'Açıklama / not';
        preview();
    };

    const renumber = () => {
        container.querySelectorAll('[data-item-number]').forEach((number, index) => { number.textContent = String(index + 1); });
        form.querySelector('[data-no-items]').hidden = container.children.length > 0;
    };
    const installRow = (row) => {
        row.querySelectorAll('[data-money-input]').forEach(installMoneyInput);
        row.querySelectorAll('select').forEach((select) => select.addEventListener('change', () => configureRow(row)));
        row.querySelector('[data-remove-item]').addEventListener('click', () => {
            row.remove();
            renumber();
            preview();
            form.querySelector('[data-add-item]').focus();
        });
        configureRow(row);
    };
    form.querySelectorAll('[data-money-input]').forEach(installMoneyInput);
    container.querySelectorAll('[data-monthly-item]').forEach(installRow);
    form.querySelector('[data-add-item]').addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-monthly-item]');
        const index = String(nextIndex++);
        for (const element of [row, ...row.querySelectorAll('*')]) {
            for (const attribute of Array.from(element.attributes)) {
                if (attribute.value.includes('__INDEX__')) element.setAttribute(attribute.name, attribute.value.replaceAll('__INDEX__', index));
            }
        }
        container.append(fragment);
        installRow(row);
        renumber();
        row.querySelector('[data-item-method]').focus();
    });
    form.addEventListener('input', preview);
    renumber();
    preview();
}

if (typeof document !== 'undefined') {
    const initialize = () => document.querySelectorAll('[data-monthly-account-form]').forEach(initMonthlyAccount);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
