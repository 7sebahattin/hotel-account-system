{{--
    Hızlı İşlem'in açtığı modal/sheet'in İÇERİĞİ (v2.1.1-B.1 §2/§3).

    layouts.app'te BİR KEZ, boş bir <dialog> kabuğu vardır (partials/
    cash-movement-dialog.blade.php); bu görünüm o kabuğun içine `fetch()` ile
    enjekte edilir — SAYFA YÜKLENMEDEN bu içerik hiç sorgulanmaz/render
    edilmez (global bir kasa sorgusu her sayfada ÇALIŞTIRILMAZ, CLAUDE.md
    §8/Dashboard "Lemon Bar" performans kararıyla AYNI ilke).

    Başlık + kapatma düğmesi burada, formun KENDİSİ paylaşılan `_form`
    partial'ındadır (standalone sayfa ile TEK kaynak, kullanıcı talimatı §4).
--}}
<div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
    <div>
        <h2 id="kasa-hareketi-dialog-baslik" class="text-base font-semibold text-tx">Kasa Hareketi Ekle</h2>
        <p class="mt-0.5 text-sm text-mut">{{ $session->openedBy?->name }} vardiyası</p>
    </div>

    <button type="button" data-cash-dialog-close
            class="flex size-9 shrink-0 items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx"
            aria-label="Kapat">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
        </svg>
    </button>
</div>

<div class="px-5 py-4">
    @include('cashbook::movements._form', ['categories' => $categories, 'currencies' => $currencies, 'selectedCategoryId' => $selectedCategoryId ?? null])
</div>
