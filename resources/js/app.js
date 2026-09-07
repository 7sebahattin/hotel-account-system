/*
 * Mobil kenar çubuğu çekmecesi.
 *
 * Harici bir JS paketi eklenmedi: bu davranış için birkaç satır yeterli.
 * Masaüstünde kenar çubuğu zaten sabit; bu kod yalnızca mobil çekmeceyi açar/kapatır.
 */
const drawer = {
    panel: null,
    overlay: null,
    toggle: null,

    open() {
        this.panel?.classList.remove('-translate-x-full');
        this.overlay?.classList.remove('hidden');
        this.toggle?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden', 'md:overflow-auto');
    },

    close() {
        this.panel?.classList.add('-translate-x-full');
        this.overlay?.classList.add('hidden');
        this.toggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden', 'md:overflow-auto');
    },
};

document.addEventListener('DOMContentLoaded', () => {
    drawer.panel = document.querySelector('[data-sidebar]');
    drawer.overlay = document.querySelector('[data-sidebar-overlay]');
    drawer.toggle = document.querySelector('[data-sidebar-toggle]');

    if (!drawer.panel || !drawer.toggle) {
        return;
    }

    drawer.toggle.addEventListener('click', () => {
        drawer.panel.classList.contains('-translate-x-full') ? drawer.open() : drawer.close();
    });

    drawer.overlay?.addEventListener('click', () => drawer.close());

    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => drawer.close());

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            drawer.close();
        }
    });
});

/**
 * Konaklama formu — ücret giriş şekli.
 *
 * Yalnızca UX içindir: alanları gösterip gizler ve hesaplanan toplamı yazar.
 * Kaydedilen tutar HER ZAMAN sunucuda yeniden hesaplanır (StayService);
 * buradaki değer istek gövdesine hiç girmez.
 *
 * Hesap tamsayı kuruş üzerinden yapılır — kayan nokta kullanılmaz.
 */
function initStayPricing() {
    const root = document.querySelector('[data-stay-pricing]');
    if (!root) return;

    const modeSelect = root.querySelector('[data-pricing-mode]');
    const rateInput = root.querySelector('[data-daily-rate]');
    const currencySelect = root.querySelector('[data-currency]');
    const checkIn = root.querySelector('[data-check-in]');
    const checkOut = root.querySelector('[data-check-out]');
    const totalGroups = root.querySelectorAll('[data-pricing-total]');
    const dailyGroups = root.querySelectorAll('[data-pricing-daily]');
    const totalOutput = root.querySelector('[data-daily-total]');
    const symbols = { TRY: '₺', EUR: '€', GBP: '£' };

    // '2.500,50' / '2500.5' → 250050 kuruş. Geçersizse null.
    const toMinorUnits = (raw) => {
        const value = String(raw ?? '').trim().replace(/\s/g, '').replace(',', '.');
        const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value);
        if (!match) return null;
        return parseInt(match[1], 10) * 100 + parseInt((match[2] ?? '').padEnd(2, '0'), 10);
    };

    const formatMinorUnits = (minor, currency) => {
        const whole = String(Math.trunc(minor / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = String(minor % 100).padStart(2, '0');
        return `${whole},${fraction} ${symbols[currency] ?? currency}`;
    };

    const nights = () => {
        if (!checkIn?.value || !checkOut?.value) return null;
        const from = Date.parse(`${checkIn.value}T00:00:00Z`);
        const to = Date.parse(`${checkOut.value}T00:00:00Z`);
        if (Number.isNaN(from) || Number.isNaN(to)) return null;
        const diff = Math.round((to - from) / 86400000);
        return diff >= 1 ? diff : null;
    };

    const renderTotal = () => {
        if (!totalOutput) return;
        const count = nights();
        const rate = toMinorUnits(rateInput?.value);
        if (count === null || rate === null || rate === 0) {
            totalOutput.textContent = '—';
            return;
        }
        const currency = currencySelect?.value ?? 'TRY';
        totalOutput.textContent =
            `${count} gece × ${formatMinorUnits(rate, currency)} = ${formatMinorUnits(rate * count, currency)}`;
    };

    const applyMode = () => {
        const daily = modeSelect?.value === 'daily';
        totalGroups.forEach((group) => { group.hidden = daily; });
        dailyGroups.forEach((group) => { group.hidden = !daily; });
        // Gizli alan zorunlu kalırsa tarayıcı form gönderimini engeller.
        root.querySelector('[name="amount"]')?.toggleAttribute('required', !daily);
        rateInput?.toggleAttribute('required', daily);
        renderTotal();
    };

    modeSelect?.addEventListener('change', applyMode);
    [rateInput, currencySelect, checkIn, checkOut].forEach((el) =>
        el?.addEventListener('input', renderTotal)
    );

    applyMode();
}

document.addEventListener('DOMContentLoaded', initStayPricing);
