/*
 * Mobil kenar çubuğu çekmecesi.
 *
 * Harici bir JS paketi eklenmedi: bu davranış için birkaç satır yeterli.
 * Masaüstünde kenar çubuğu zaten sabit; bu kod yalnızca mobil çekmeceyi açar/kapatır.
 *
 * Gün 9.6: çekmece artık BİRİNCİL gezinme değil — alt gezinme çubuğundaki
 * "Daha Fazla" sekmesiyle açılan ikincil menüdür. Bu yüzden onu açan İKİ tetikleyici
 * var (üst çubuktaki hamburger + alt çubuktaki "Daha Fazla"); `[data-sidebar-toggle]`
 * ile işaretlenmiş HER öğe aynı çekmeceyi açar/kapatır ve `aria-expanded` durumunu
 * birlikte taşır.
 */
const drawer = {
    panel: null,
    overlay: null,
    bottomNav: null,
    toggles: [],
    lastToggle: null,
    mobileQuery: null,

    isOpen() {
        return !this.panel?.classList.contains('-translate-x-full');
    },

    /**
     * Kapalıyken (ekran dışındayken) panel `inert` olur: içindeki linkler ve
     * "Menüyü kapat" düğmesi Tab sırasından ve ekran okuyucudan çıkar.
     *
     * Bu olmadan klavye kullanıcısı "İçeriğe atla"dan sonra görünmeyen, ekran
     * dışındaki çekmece içeriğine atlıyordu — gerçek bir klavye tuzağıydı.
     *
     * Masaüstünde (md ve üzeri) panel CSS ile her zaman görünür ve sabittir;
     * orada `inert` hiçbir zaman uygulanmaz. Sınır, Tailwind'in `md:` kırılım
     * noktasıyla (768px) birebir eşleşir — `md:translate-x-0` da aynı sınırı kullanır.
     */
    syncInert() {
        if (!this.panel) return;

        const shouldBeInert = this.mobileQuery !== null && !this.mobileQuery.matches && !this.isOpen();
        this.panel.toggleAttribute('inert', shouldBeInert);
    },

    open(trigger) {
        this.lastToggle = trigger ?? this.lastToggle;
        this.panel?.classList.remove('-translate-x-full');
        this.overlay?.classList.remove('hidden');
        this.toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'true'));
        document.body.classList.add('overflow-hidden', 'md:overflow-auto');
        // Çekmece açıkken alt gezinme çubuğu görsel olarak overlay'in ALTINDA kalır
        // (bkz. z-index); `inert` bunu klavye/ekran okuyucu için de doğru yapar —
        // aksi hâlde çekmecedeki son bağlantıdan sonra Tab, örtülü alt çubuğa düşerdi.
        this.bottomNav?.setAttribute('inert', '');
        this.syncInert();
    },

    close({ restoreFocus = false } = {}) {
        if (!this.isOpen()) {
            this.syncInert();

            return;
        }

        this.panel?.classList.add('-translate-x-full');
        this.overlay?.classList.add('hidden');
        this.toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        document.body.classList.remove('overflow-hidden', 'md:overflow-auto');
        this.bottomNav?.removeAttribute('inert');
        this.syncInert();

        // Klavye kullanıcısı çekmeceyi kapattığında odak boşlukta kalmamalı;
        // hangi tetikleyici açtıysa odak ona döner.
        if (restoreFocus) this.lastToggle?.focus();
    },
};

document.addEventListener('DOMContentLoaded', () => {
    drawer.panel = document.querySelector('[data-sidebar]');
    drawer.overlay = document.querySelector('[data-sidebar-overlay]');
    drawer.bottomNav = document.querySelector('[data-bottom-nav]');
    drawer.toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));

    if (!drawer.panel || drawer.toggles.length === 0) {
        return;
    }

    drawer.mobileQuery = window.matchMedia('(min-width: 768px)');
    drawer.mobileQuery.addEventListener('change', () => drawer.syncInert());
    drawer.syncInert();

    drawer.toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            drawer.isOpen() ? drawer.close() : drawer.open(event.currentTarget);
        });
    });

    drawer.overlay?.addEventListener('click', () => drawer.close());

    document
        .querySelector('[data-sidebar-close]')
        ?.addEventListener('click', () => drawer.close({ restoreFocus: true }));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            drawer.close({ restoreFocus: true });
        }
    });
});

/**
 * Çift gönderim koruması.
 *
 * Aynı formun iki kez gönderilmesi, bu uygulamada iki konaklama ya da iki
 * tahsilat kaydı demektir; cari defter append-only olduğu için sonucu elle
 * ters kayıtla düzeltmek gerekir. Bu yüzden ikinci gönderim engellenir.
 *
 * Buton `disabled` yapılırken iki tuzak vardır ve ikisi de burada çözülür:
 *
 *  1. Devre dışı bırakılan bir submit butonunun `name`/`value` çifti istek
 *     gövdesine GİRMEZ. Bu yüzden devre dışı bırakma `setTimeout(0)` ile
 *     tarayıcı gövdeyi topladıktan SONRA yapılır.
 *  2. Doğrulama hatası veya `formnovalidate` yüzünden gönderim iptal olursa
 *     form kilitli kalmamalıdır; `invalid` olayında kilit açılır.
 */
function initSubmitGuard() {
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-allow-resubmit')) return;

            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = '1';

            const buttons = form.querySelectorAll('button[type="submit"], button:not([type])');

            window.setTimeout(() => {
                buttons.forEach((button) => {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                });
            }, 0);
        },
        true
    );

    // Tarayıcı doğrulaması gönderimi durdurduysa form yeniden denenebilmeli.
    document.addEventListener(
        'invalid',
        (event) => {
            const form = event.target instanceof HTMLElement ? event.target.closest('form') : null;
            if (!form) return;

            delete form.dataset.submitting;
            form.querySelectorAll('button[aria-disabled="true"]').forEach((button) => {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
            });
        },
        true
    );

    // Geri tuşuyla bfcache'ten dönen sayfada butonlar kilitli kalmasın.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;

        document.querySelectorAll('form[data-submitting]').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('button[aria-disabled="true"]').forEach((button) => {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initSubmitGuard);

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

    // Sembol JS'de sabit bir listeye bağlanmaz: her <option> kendi sembolünü
    // `data-symbol` ile taşır (bkz. Currency::symbol(), PHP tarafındaki tek kaynak).
    // Sembolü bilinmeyen/eşleşmeyen bir para birimi için ISO kodu gösterilir.
    const currencySymbol = () => {
        const option = currencySelect?.selectedOptions?.[0];
        return option?.dataset.symbol || currencySelect?.value || 'TRY';
    };

    // '2.500,50' / '2500.5' → 250050 kuruş. Geçersizse null.
    const toMinorUnits = (raw) => {
        const value = String(raw ?? '').trim().replace(/\s/g, '').replace(',', '.');
        const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value);
        if (!match) return null;
        return parseInt(match[1], 10) * 100 + parseInt((match[2] ?? '').padEnd(2, '0'), 10);
    };

    const formatMinorUnits = (minor, symbol) => {
        const whole = String(Math.trunc(minor / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = String(minor % 100).padStart(2, '0');
        return `${whole},${fraction} ${symbol}`;
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
        const symbol = currencySymbol();
        totalOutput.textContent =
            `${count} gece × ${formatMinorUnits(rate, symbol)} = ${formatMinorUnits(rate * count, symbol)}`;
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
