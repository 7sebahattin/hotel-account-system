/*
 * Mobil kenar çubuğu çekmecesi.
 *
 * Harici bir JS paketi eklenmedi: bu davranış için birkaç satır yeterli.
 * Masaüstünde kenar çubuğu zaten sabit; bu kod yalnızca mobil çekmeceyi açar/kapatır.
 *
 * Gün 9.6: çekmece artık BİRİNCİL gezinme değil — alt gezinme çubuğundaki
 * "Daha Fazla" sekmesiyle açılan ikincil menüdür. Mobil PWA/temizlik turunda
 * üst çubuktaki hamburger KALDIRILDI (kullanıcı talimatı §2) — artık onu açan
 * tek tetikleyici alt çubuktaki "Daha Fazla"dır. Kod yine de `[data-sidebar-
 * toggle]` ile işaretlenmiş HER öğeyi genel olarak yönetir (sabit bir sayıya
 * bağlı değildir), böylece tetikleyici sayısı değişse de bozulmaz.
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
 * Global "+ Hızlı İşlem" menüsü (Gün 11).
 *
 * Mobil PWA/temizlik turunda mobil üst çubuktaki tetikleyici KALDIRILDI
 * (kullanıcı talimatı §2 — her kısayolun kendi index sayfasında zaten aynı
 * "oluştur" butonu var); kalan tek tetikleyici masaüstü Lemon Bar'dadır
 * (`hidden md:flex`). Kod genel kalır — `drawer` nesnesindeki desenin aynısı:
 * sıfır veya daha fazla tetikleyiciyle çalışır, her biri kendi panelini
 * açar/kapatır, aynı anda yalnızca bir panel açık kalır.
 */
function initQuickActions() {
    const roots = Array.from(document.querySelectorAll('[data-quick-action]'));
    if (roots.length === 0) return;

    const entries = roots.map((root) => ({
        root,
        toggle: root.querySelector('[data-quick-action-toggle]'),
        panel: root.querySelector('[data-quick-action-panel]'),
    })).filter((entry) => entry.toggle && entry.panel);

    const closeAll = ({ except = null, restoreFocus = false } = {}) => {
        entries.forEach((entry) => {
            if (entry === except) return;
            if (entry.panel.hidden) return;

            entry.panel.hidden = true;
            entry.toggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus) entry.toggle.focus();
        });
    };

    entries.forEach((entry) => {
        entry.toggle.addEventListener('click', () => {
            const isOpen = !entry.panel.hidden;
            closeAll();

            if (!isOpen) {
                entry.panel.hidden = false;
                entry.toggle.setAttribute('aria-expanded', 'true');
                entry.panel.querySelector('a')?.focus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        const insideAny = entries.some((entry) => entry.root.contains(target));
        if (!insideAny) closeAll();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll({ restoreFocus: true });
    });
}

document.addEventListener('DOMContentLoaded', initQuickActions);

/**
 * "Teslim alan" alanı yalnızca `requires_recipient` kategorisinde görünür.
 *
 * TEK, adlandırılmış bir fonksiyondur — hem normal sayfa yüklemesinde
 * (`document` kökü) hem de Kasa Hareketi modalı içerik enjekte ettikten
 * SONRA (o parçanın kökü) elle çağrılır. `<dialog>` içeriği `innerHTML` ile
 * yazıldığından, o parça içindeki bir `<script>` etiketi ASLA ÇALIŞMAZ —
 * bu yüzden mantık burada, app.js'te tek kopya olarak durur (v2.1.1-B.1 §4:
 * kategori/system-type kuralları JS'te tekrarlanmaz/kopyalanmaz).
 */
function initRecipientToggle(root) {
    const select = root.querySelector('select[name="category_id"]');
    const field = root.querySelector('[data-recipient-field]');
    if (!select || !field) return;

    const sync = () => {
        const option = select.options[select.selectedIndex];
        const requires = option?.dataset.requiresRecipient === '1';
        field.hidden = !requires;
    };

    select.addEventListener('change', sync);
    sync();
}

document.addEventListener('DOMContentLoaded', () => initRecipientToggle(document));

/**
 * Kasa Hareketi modal/sheet'i (v2.1.1-B.1).
 *
 * Hızlı İşlem'deki "Kasa Hareketi" ARTIK tam sayfa gezinmesi DEĞİL, hafif
 * bir modal/sheet açar. Kabuk (`<dialog>`) her sayfada BOŞ durur; içerik
 * yalnızca kullanıcı GERÇEKTEN tıkladığında `fetch()` ile yüklenir — bu
 * yüzden global bir kasa/kategori sorgusu her sayfa yüklemesinde ÇALIŞMAZ.
 *
 * Form GERÇEK bir <form> olarak POST edilir (SPA/AJAX mimarisi YOKTUR):
 * başarılı gönderim tam sayfa yönlendirmesiyle Kasa Defteri'ne döner (modal
 * kendiliğinden ekrandan gider); doğrulama hatası standalone "Hareket ekle"
 * sayfasına yönlendirilir (bkz. `StoreCashMovementRequest::failedValidation()`
 * / `CashMovementController::backToCreate()`) — Referer'a göre BELİRSİZ bir
 * sayfaya değil, HER ZAMAN formu render eden aynı sayfaya.
 *
 * Vardiya yoksa veya yetki yoksa: `fetch()` yönlendirmeyi kendiliğinden
 * takip eder (`response.redirected`) ya da 403 döner (`!response.ok`) —
 * her iki durumda da modal açılmaz, tarayıcı GERÇEK bir sayfaya gider. Böyle
 * bir "yarım modal" asla görünmez.
 */
function initCashMovementDialog() {
    const dialog = document.querySelector('#kasa-hareketi-dialog');
    const content = dialog?.querySelector('[data-cash-dialog-content]');
    const triggers = Array.from(document.querySelectorAll('[data-cash-movement-trigger]'));
    if (!dialog || !content || triggers.length === 0) return;

    let focusOnClose = null;

    const goTo = (url) => { window.location.href = url; };

    /**
     * Hızlı İşlem panelini kapatır — ama tetikleyicinin KENDİ menü öğesine
     * değil, o panelin görünür kalan TOGGLE düğmesine odak döndürülecektir.
     *
     * Panel `hidden` olunca içindeki tetikleyici `<a>` artık render edilmez
     * (`display:none`) ve tarayıcı odağı ORADAN ZORLA alır (görünmeyen bir
     * elemana odak KALAMAZ) — bu yüzden dialog kapandığında o `<a>`'ya
     * `.focus()` çağırmak sessizce başarısız olurdu (hâlâ gizli bir atanın
     * içindedir). Odak bunun yerine panel her zaman görünür kalan toggle
     * düğmesine döner — önceki mobil çekmece odak hatasının (K-086/K-088)
     * AYNI sınıfı burada TEKRAR AÇILMAZ.
     */
    const closeQuickActionPanels = (trigger) => {
        const ownToggle = trigger.closest('[data-quick-action]')?.querySelector('[data-quick-action-toggle]') ?? null;

        document.querySelectorAll('[data-quick-action-panel]').forEach((panel) => { panel.hidden = true; });
        document.querySelectorAll('[data-quick-action-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', 'false');
        });

        return ownToggle;
    };

    const open = async (trigger) => {
        focusOnClose = closeQuickActionPanels(trigger);

        content.innerHTML = '<p class="p-6 text-sm text-mut">Yükleniyor…</p>';
        dialog.showModal();

        let response;
        try {
            response = await fetch(trigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            dialog.close();
            goTo(trigger.href);
            return;
        }

        // Vardiya yok (kasaya yönlendirme) veya yetki yok (403/diğer) —
        // ikisinde de modal DEĞİL, gerçek bir sayfa gösterilir.
        if (response.redirected || !response.ok) {
            dialog.close();
            goTo(response.url || trigger.href);
            return;
        }

        content.innerHTML = await response.text();
        initRecipientToggle(content);
        content.querySelector('select, input, textarea')?.focus();
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(event.currentTarget);
        });
    });

    // Arka plana (::backdrop) tıklama = kapatma; içerik dışına tıklamayı
    // `event.target === dialog` ile ayırt ederiz (dialog'un KENDİSİ yalnızca
    // backdrop alanında hedef olabilir, içindeki `<div>` her zaman kendi
    // hedefini taşır).
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
        if (event.target instanceof Element && event.target.closest('[data-cash-dialog-close]')) {
            dialog.close();
        }
    });

    // Kapanış hem ESC/backdrop hem programatik `close()` için TEK yerden
    // geçer: içerik boşaltılır (kapalı dialogun içinde odaklanabilir hiçbir
    // şey KALMAZ) ve odak, Hızlı İşlem'i açan TOGGLE düğmesine döner —
    // tetikleyici menü öğesinin kendisi DEĞİL, çünkü o öğe artık gizli bir
    // panelin içindedir ve gizli bir elemana odaklanmak sessizce başarısız
    // olur (yukarıdaki `closeQuickActionPanels()` notuna bakınız).
    dialog.addEventListener('close', () => {
        content.innerHTML = '';
        focusOnClose?.focus();
    });
}

document.addEventListener('DOMContentLoaded', initCashMovementDialog);

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
    const amountInput = root.querySelector('[name="amount"]');
    const exchangeRateGroup = root.querySelector('[data-exchange-rate-group]');
    const exchangeRateInput = root.querySelector('[data-exchange-rate]');
    const exchangeRatePreview = root.querySelector('[data-exchange-rate-preview]');

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
        amountInput?.toggleAttribute('required', !daily);
        rateInput?.toggleAttribute('required', daily);
        renderTotal();
    };

    // Kur alanı yalnızca TRY DIŞI para biriminde görünür/zorunludur
    // (v2.1.1-C §2). TRY'ye dönülürse alan hem gizlenir hem TEMİZLENİR —
    // sunucu zaten TRY için gönderilse bile bu alanı reddeder, ama
    // formda görünmemesi kullanıcı için daha dürüsttür.
    const renderExchangeRatePreview = () => {
        if (!exchangeRatePreview) return;
        const amountMinor = toMinorUnits(amountInput?.value);
        const rateMinor = toMinorUnits(exchangeRateInput?.value);
        if (amountMinor === null || rateMinor === null) {
            exchangeRatePreview.textContent = '';
            return;
        }
        // rateMinor kuruş cinsinden 'kur × 100'dür (ör. 51,20 → 5120).
        // TL karşılığı = tutar(kuruş) × kur(yüzde) / 100 / 100 — tamsayı bölme.
        const equivalentMinor = Math.round((amountMinor * rateMinor) / 100 / 100);
        exchangeRatePreview.textContent = `≈ ${formatMinorUnits(equivalentMinor, '₺')} TL karşılığı (bilgi amaçlıdır; kesin tutar kayıtta hesaplanır)`;
    };

    const applyCurrency = () => {
        if (!exchangeRateGroup || !exchangeRateInput) return;
        const isTry = currencySelect?.value === 'TRY';
        exchangeRateGroup.hidden = isTry;
        exchangeRateInput.toggleAttribute('required', !isTry);
        if (isTry) {
            exchangeRateInput.value = '';
        }
        renderExchangeRatePreview();
    };

    modeSelect?.addEventListener('change', applyMode);
    currencySelect?.addEventListener('change', applyCurrency);
    [rateInput, currencySelect, checkIn, checkOut].forEach((el) =>
        el?.addEventListener('input', renderTotal)
    );
    [amountInput, exchangeRateInput].forEach((el) =>
        el?.addEventListener('input', renderExchangeRatePreview)
    );

    applyMode();
    applyCurrency();
}

document.addEventListener('DOMContentLoaded', initStayPricing);

/**
 * Tahsilat formu — kur alanının TL dışı para biriminde gösterilmesi
 * (v2.1.1-C §2). Yalnızca UX: sunucu tarafı `StorePaymentRequest`/
 * `PaymentService` doğrulamayı ve hesaplamayı BAĞIMSIZ olarak tekrar yapar.
 */
function initPaymentExchangeRate() {
    const root = document.querySelector('[data-payment-exchange-rate]');
    if (!root) return;

    const currencySelect = root.querySelector('[data-currency]');
    const amountInput = root.querySelector('[data-amount]');
    const group = root.querySelector('[data-exchange-rate-group]');
    const rateInput = root.querySelector('[data-exchange-rate]');
    const preview = root.querySelector('[data-exchange-rate-preview]');

    const toMinorUnits = (raw) => {
        const value = String(raw ?? '').trim().replace(/\s/g, '').replace(',', '.');
        const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value);
        if (!match) return null;
        return parseInt(match[1], 10) * 100 + parseInt((match[2] ?? '').padEnd(2, '0'), 10);
    };

    const formatMinorUnits = (minor) => {
        const whole = String(Math.trunc(minor / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = String(minor % 100).padStart(2, '0');
        return `${whole},${fraction}`;
    };

    const renderPreview = () => {
        if (!preview) return;
        const amountMinor = toMinorUnits(amountInput?.value);
        const rateMinor = toMinorUnits(rateInput?.value);
        if (amountMinor === null || rateMinor === null) {
            preview.textContent = '';
            return;
        }
        const equivalentMinor = Math.round((amountMinor * rateMinor) / 100 / 100);
        preview.textContent = `≈ ${formatMinorUnits(equivalentMinor)} TL karşılığı (bilgi amaçlıdır; kesin tutar kayıtta hesaplanır)`;
    };

    const applyCurrency = () => {
        if (!group || !rateInput) return;
        const isTry = currencySelect?.value === 'TRY';
        group.hidden = isTry;
        rateInput.toggleAttribute('required', !isTry);
        if (isTry) {
            rateInput.value = '';
        }
        renderPreview();
    };

    currencySelect?.addEventListener('change', applyCurrency);
    [amountInput, rateInput].forEach((el) => el?.addEventListener('input', renderPreview));

    applyCurrency();
}

document.addEventListener('DOMContentLoaded', initPaymentExchangeRate);

/**
 * Tahsilat Makbuzu Defteri formu — kur alanı (v2.1.1-C §5). Payments'takiyle
 * AYNI mantık, ayrı bir kök seçici (`[data-receipt-book-form]`) üzerinden —
 * iki form birbirinden bağımsız DOM'da yaşayabilir (dialog + standalone sayfa
 * aynı anda mevcut olabilir).
 */
function initReceiptBookExchangeRate() {
    document.querySelectorAll('[data-receipt-book-form]').forEach((root) => {
        const currencySelect = root.querySelector('[data-currency]');
        const amountInput = root.querySelector('[data-amount]');
        const group = root.querySelector('[data-exchange-rate-group]');
        const rateInput = root.querySelector('[data-exchange-rate]');
        const preview = root.querySelector('[data-exchange-rate-preview]');
        if (!currencySelect || !group || !rateInput) return;

        const toMinorUnits = (raw) => {
            const value = String(raw ?? '').trim().replace(/\s/g, '').replace(',', '.');
            const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value);
            if (!match) return null;
            return parseInt(match[1], 10) * 100 + parseInt((match[2] ?? '').padEnd(2, '0'), 10);
        };

        const formatMinorUnits = (minor) => {
            const whole = String(Math.trunc(minor / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const fraction = String(minor % 100).padStart(2, '0');
            return `${whole},${fraction}`;
        };

        const renderPreview = () => {
            if (!preview) return;
            const amountMinor = toMinorUnits(amountInput?.value);
            const rateMinor = toMinorUnits(rateInput?.value);
            if (amountMinor === null || rateMinor === null) {
                preview.textContent = '';
                return;
            }
            const equivalentMinor = Math.round((amountMinor * rateMinor) / 100 / 100);
            preview.textContent = `≈ ${formatMinorUnits(equivalentMinor)} TL karşılığı`;
        };

        const applyCurrency = () => {
            const isTry = currencySelect.value === 'TRY';
            group.hidden = isTry;
            rateInput.toggleAttribute('required', !isTry);
            if (isTry) rateInput.value = '';
            renderPreview();
        };

        currencySelect.addEventListener('change', applyCurrency);
        [amountInput, rateInput].forEach((el) => el?.addEventListener('input', renderPreview));

        applyCurrency();
    });
}

document.addEventListener('DOMContentLoaded', initReceiptBookExchangeRate);

/**
 * "Yeni Makbuz" dialog/sheet'i (v2.1.1-C §5) — Kasa Hareketi dialog'unun
 * (v2.1.1-B.1) SADELEŞTİRİLMİŞ AYNI deseni: bu modülde Hızlı İşlem paneli
 * YOKTUR, tetikleyici doğrudan "Tahsilat Makbuzu Defteri" sayfasındadır.
 *
 * Form GERÇEK bir <form> POST'udur; başarılı gönderim tam sayfa
 * yönlendirmesiyle makbuz detayına döner (modal kendiliğinden ekrandan
 * gider). Doğrulama hatası HER ZAMAN standalone "Yeni Makbuz" sayfasına
 * yönlendirilir (bkz. `StoreReceiptBookEntryRequest::failedValidation()`).
 */
function initReceiptBookDialog() {
    const dialog = document.querySelector('#makbuz-dialog');
    const content = dialog?.querySelector('[data-receipt-dialog-content]');
    const triggers = Array.from(document.querySelectorAll('[data-receipt-dialog-trigger]'));
    if (!dialog || !content || triggers.length === 0) return;

    let focusOnClose = null;

    const open = async (trigger) => {
        focusOnClose = trigger;

        content.innerHTML = '<p class="p-6 text-sm text-mut">Yükleniyor…</p>';
        dialog.showModal();

        let response;
        try {
            response = await fetch(trigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            dialog.close();
            window.location.href = trigger.href;
            return;
        }

        if (response.redirected || !response.ok) {
            dialog.close();
            window.location.href = response.url || trigger.href;
            return;
        }

        content.innerHTML = await response.text();
        initReceiptBookExchangeRate();
        content.querySelector('input, select, textarea')?.focus();
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(event.currentTarget);
        });
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
        if (event.target instanceof Element && event.target.closest('[data-receipt-dialog-close]')) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        content.innerHTML = '';
        focusOnClose?.focus();
    });
}

document.addEventListener('DOMContentLoaded', initReceiptBookDialog);

/**
 * Service worker kaydı (mobil PWA turu).
 *
 * Kayıt başarısız olursa (tarayıcı desteklemiyor, HTTPS dışı bağlam vb.)
 * uygulama service worker OLMADAN normal çalışmaya devam eder — bu yüzden
 * hata sessizce yutulur, kullanıcıya hiçbir şey gösterilmez.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

/**
 * PWA kurulum eylemi ("Daha Fazla" çekmecesi, kullanıcı talimatı §4).
 *
 * Kalıcı bir buton DEĞİLDİR: `beforeinstallprompt` desteklenmiyorsa veya
 * uygulama ZATEN standalone modda kuruluysa hiçbir şey gösterilmez. iOS/
 * iPadOS Safari bu olayı HİÇ ateşlemez (platform sınırlaması) — bunun
 * yerine, henüz kurulu değilse, bağlamsal bir "Paylaş → Ana Ekrana Ekle"
 * ipucu gösterilir.
 */
function initPwaInstall() {
    const installSection = document.querySelector('[data-pwa-install]');
    const installButton = document.querySelector('[data-pwa-install-button]');
    const iosHint = document.querySelector('[data-pwa-ios-hint]');

    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (isStandalone) {
        return;
    }

    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        if (installSection) installSection.hidden = false;
    });

    installButton?.addEventListener('click', async () => {
        if (!deferredPrompt) return;

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        if (installSection) installSection.hidden = true;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        if (installSection) installSection.hidden = true;
    });

    // iOS/iPadOS Safari tespiti — `beforeinstallprompt` orada hiç ateşlenmez.
    // iPadOS masaüstü modunda Mac UA'sını taklit eder; dokunma noktası sayısı
    // gerçek Mac'ten ayırt eder (Mac'te 0, iPad'de > 1).
    const ua = window.navigator.userAgent;
    const isIos = /iphone|ipad|ipod/i.test(ua)
        || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
    const isSafari = /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua);

    if (isIos && isSafari && iosHint) {
        iosHint.hidden = false;
    }
}

document.addEventListener('DOMContentLoaded', initPwaInstall);

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-payment-allocation]');
    if (!form) return;
    const account = form.elements.company_id;
    const currency = form.elements.currency;
    const mode = form.elements.allocation_mode;
    const payer = form.elements.payer_type;
    const payerGroup = form.querySelector('[data-payer-name]');
    const selection = form.querySelector('[data-allocation-selection]');
    const list = form.querySelector('[data-allocation-options]');
    const message = form.querySelector('[data-allocation-message]');
    let previous = new Set(JSON.parse(form.dataset.selectedStays || '[]').map(String));
    let pending;
    const syncPayer = () => {
        payerGroup.hidden = payer.value !== 'individual';
        form.elements.payer_name.disabled = payerGroup.hidden;
        form.elements.payer_name.required = !payerGroup.hidden;
    };
    const refresh = async () => {
        pending?.abort();
        selection.hidden = mode.value !== 'selected';
        selection.disabled = selection.hidden;
        if (selection.hidden) return;
        list.replaceChildren();
        if (!account.value) {
            message.textContent = 'Önce cari hesap seçin.';
            return;
        }
        const controller = new AbortController();
        pending = controller;
        message.textContent = 'Konaklamalar yükleniyor…';
        const url = new URL(form.dataset.optionsUrl, window.location.href);
        url.searchParams.set('company_id', account.value);
        url.searchParams.set('currency', currency.value);
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error('Liste alınamadı');
            const data = await response.json();
            if (controller.signal.aborted) return;
            message.textContent = data.stays.length ? 'Seçilen konaklamalar arasında en eskiden başlanır.' : 'Bu hesap ve para biriminde açık konaklama bulunamadı.';
            for (const stay of data.stays) {
                const label = document.createElement('label');
                label.className = 'flex min-h-11 items-center gap-3 rounded-lg border border-line p-3 text-sm';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'stay_ids[]';
                checkbox.value = String(stay.id);
                checkbox.checked = previous.has(checkbox.value);
                checkbox.addEventListener('change', () => checkbox.checked ? previous.add(checkbox.value) : previous.delete(checkbox.value));
                const text = document.createElement('span');
                text.textContent = `#${stay.id} · ${stay.guest_name} · ${stay.check_in_date.split('-').reverse().join('.')} · Kalan ${stay.remaining_formatted} · ${stay.label}`;
                label.append(checkbox, text);
                list.append(label);
            }
        } catch (error) {
            if (error.name !== 'AbortError') message.textContent = 'Liste yüklenemedi. Bağlantınızı kontrol edip listeyi yenileyin.';
        }
    };
    account.addEventListener('change', () => { previous.clear(); refresh(); });
    currency.addEventListener('change', () => { previous.clear(); refresh(); });
    mode.addEventListener('change', refresh);
    payer.addEventListener('change', syncPayer);
    form.querySelector('[data-allocation-retry]').addEventListener('click', refresh);
    syncPayer();
    refresh();
});

document.addEventListener('DOMContentLoaded', () => {
    const company = document.querySelector('[data-stay-company]');
    const individual = document.querySelector('[data-stay-individual]');
    if (!company || !individual) return;
    const sync = () => {
        individual.hidden = company.value !== '';
        individual.querySelector('select').disabled = individual.hidden;
    };
    company.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
});

/**
 * Aranabilir seçim kutusu — "lem" yazınca "Lemon" gibi eşleşenleri listeler.
 *
 * Alpine.js kurulu DEĞİLDİR (§11.7); bu davranış için ayrı bir paket
 * eklenmedi, mevcut deseni izleyen bağımsız bir fonksiyon yeterli.
 *
 * Native <select> asla DOM'dan kaldırılmaz — yalnızca `hidden` yapılır ve
 * form değeri HER ZAMAN ondan okunur/yazılır: kullanıcı yazdığı serbest
 * metinle GERÇEKTE var olmayan bir kayda "bağlanamaz", çünkü seçim yalnızca
 * listeden bir <option>'a tıklanarak/Enter ile onaylanabilir. JS çalışmazsa
 * select normal açılır liste olarak görünmeye devam eder (progresif geliştirme).
 */
function initSearchableSelects() {
    document.querySelectorAll('select[data-searchable-select]').forEach((select) => {
        if (select.dataset.enhanced === '1') return;
        select.dataset.enhanced = '1';

        const label = select.parentElement?.querySelector('label');
        const options = () => Array.from(select.options);
        const fold = (value) => value.toLocaleLowerCase('tr-TR');

        const input = document.createElement('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.id = `${select.id}_search`;
        input.className = select.className;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', `${select.id}_listbox`);
        input.placeholder = 'Yazarak arayın…';

        const listbox = document.createElement('ul');
        listbox.id = `${select.id}_listbox`;
        listbox.setAttribute('role', 'listbox');
        listbox.hidden = true;
        listbox.className = 'absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-line bg-card py-1 text-sm';

        const wrap = document.createElement('div');
        wrap.className = 'relative';
        wrap.append(input, listbox);

        select.hidden = true;
        select.insertAdjacentElement('afterend', wrap);
        if (label) label.htmlFor = input.id;

        let activeIndex = -1;
        let matches = [];

        const currentLabel = () => select.options[select.selectedIndex]?.text.trim() ?? '';

        const close = () => {
            listbox.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };

        const commit = (option) => {
            select.value = option.value;
            input.value = option.text.trim();
            // 'change' olayı GERÇEK bir kullanıcı seçimini taklit eder — formdaki
            // diğer dinleyiciler (ör. bireysel hesap alanının gizlenmesi) tetiklenir.
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        };

        const highlight = (index) => {
            activeIndex = index;
            const items = Array.from(listbox.querySelectorAll('[role="option"]'));
            items.forEach((item, i) => {
                item.classList.toggle('bg-well', i === index);
                item.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            if (index >= 0 && items[index]) {
                input.setAttribute('aria-activedescendant', items[index].id);
                items[index].scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        };

        const render = () => {
            const term = fold(input.value.trim());
            matches = options().filter((option) => fold(option.text).includes(term));
            listbox.replaceChildren();

            if (matches.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'px-3 py-2 text-mut';
                empty.textContent = 'Eşleşme yok';
                listbox.append(empty);
            } else {
                matches.forEach((option, index) => {
                    const item = document.createElement('li');
                    item.id = `${select.id}_opt_${index}`;
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', 'false');
                    item.textContent = option.text.trim();
                    item.className = 'flex min-h-11 cursor-pointer items-center px-3 py-2 hover:bg-well';
                    // 'mousedown' (click DEĞİL) kullanılır: click, input'un blur
                    // olayından SONRA ateşlenir ve o an liste zaten kapanmış olur.
                    item.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        commit(option);
                    });
                    listbox.append(item);
                });
            }

            listbox.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            highlight(-1);
        };

        input.addEventListener('input', render);
        input.addEventListener('focus', render);

        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (listbox.hidden) { render(); return; }
                highlight(Math.min(activeIndex + 1, matches.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter') {
                if (!listbox.hidden && activeIndex >= 0 && matches[activeIndex]) {
                    event.preventDefault();
                    commit(matches[activeIndex]);
                }
            } else if (event.key === 'Escape') {
                close();
            }
        });

        input.addEventListener('blur', () => {
            // Yazılan metin bir seçime dönüşmediyse görünen metin GERÇEK
            // seçili değere (select.value) geri döner — kullanıcı yanlışlıkla
            // yazdığı bir metinle var olmayan bir kayda "bağlanmış" GÖRÜNEMEZ.
            window.setTimeout(() => {
                input.value = currentLabel();
                close();
            }, 0);
        });

        input.value = currentLabel();
    });
}

document.addEventListener('DOMContentLoaded', initSearchableSelects);
