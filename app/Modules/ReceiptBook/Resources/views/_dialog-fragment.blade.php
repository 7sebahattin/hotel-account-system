{{--
    "Yeni Makbuz" dialog/sheet İÇERİĞİ (v2.1.1-C §5) — Kasa Hareketi
    dialog'unun (v2.1.1-B.1) AYNI deseni. Kabuk `receipt-book::index`
    sayfasında BİR KEZ, boş durur; bu içerik yalnızca kullanıcı tıkladığında
    `fetch()` ile enjekte edilir.
--}}
<div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
    <h2 id="makbuz-dialog-baslik" class="text-base font-semibold text-tx">Yeni Makbuz</h2>

    <button type="button" data-receipt-dialog-close
            class="flex size-9 shrink-0 items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx"
            aria-label="Kapat">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
        </svg>
    </button>
</div>

<div class="px-5 py-4">
    <form method="POST" action="{{ route('receipt-book.store') }}">
        @include('receipt-book::_form')
    </form>
</div>
