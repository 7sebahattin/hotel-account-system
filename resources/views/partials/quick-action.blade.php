@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Core\Support\NavIcons;

    /*
        Global "+ Hızlı İşlem" (Gün 11 §10, v2.1.1-B §6 ile SADELEŞTİRİLDİ).
        Rol/yetki duyarlıdır: her satır KENDİ yetkisini kontrol eder, hiçbiri
        yeni bir işlem mantığı YAZMAZ — mevcut create route'larına yönlendirir.

        v2.1.1-B ÖNCESİ: personel ve patron/admin için TAMAMEN farklı liste
        vardı (personel için kasa hareket TÜRÜ başına ayrı kısayol —
        Oda Geliri/Ek Gelir/Gider/Kasa Çıkışı). Kasa hareket türü artık admin
        tanımlı, dinamik bir kategoridir (bkz. CashMovementCategory) — sabit
        bir enum değeri gibi TEK bir kategoriye önceden bağlanamaz (aynı sistem
        davranışına birden fazla aktif kategori eşlenebilir, ör. "Mini Bar" ve
        "Market" ikisi de extra_income'dur). Bu yüzden liste TEK, herkes için
        aynı ve rol farkı yalnızca hangi satırın yetki kontrolünden geçtiğiyle
        ortaya çıkar — kullanıcı talimatının önerdiği liste budur (docs/
        kararlar.md K-097).
    */
    $user = auth()->user();

    $candidates = [
        ['route' => 'cashbook.movements.create', 'params' => [], 'label' => 'Kasa Hareketi', 'icon' => 'cash', 'permission' => Permission::CashBookOperate->value],
        ['route' => 'payments.create', 'params' => [], 'label' => 'Cari Tahsilat', 'icon' => 'cash', 'permission' => Permission::PaymentsCreate->value],
        ['route' => 'stays.create', 'params' => [], 'label' => 'Yeni Konaklama', 'icon' => 'bed', 'permission' => Permission::StaysCreate->value],
        ['route' => 'companies.create', 'params' => [], 'label' => 'Yeni Firma', 'icon' => 'building', 'permission' => Permission::CompaniesCreate->value],
        ['route' => 'cashbook.transfers.create', 'params' => [], 'label' => 'Kasa Devri', 'icon' => 'swap', 'permission' => Permission::CashBookOperate->value],
    ];

    $items = array_values(array_filter($candidates, fn (array $item): bool => $user->can($item['permission'])));
@endphp

@if ($items !== [])
    <div data-quick-action class="relative shrink-0">
        <button type="button" data-quick-action-toggle
                class="flex size-11 items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx md:size-9 md:border md:border-field"
                aria-label="Hızlı işlem" aria-haspopup="true" aria-expanded="false" aria-controls="hizli-islem-menu">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5" aria-hidden="true">
                <path d="{{ NavIcons::path('plus') }}" stroke-linecap="round"/>
            </svg>
        </button>

        <div id="hizli-islem-menu" data-quick-action-panel hidden
             class="absolute right-0 top-full z-30 mt-2 w-56 rounded-xl border border-line bg-card py-1.5 shadow-none ring-1 ring-line">
            <p class="px-3 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-mut">
                Hızlı işlem
            </p>

            <ul>
                @foreach ($items as $item)
                    <li>
                        {{--
                            v2.1.1-B.1 §2/§3: "Kasa Hareketi" tam sayfa
                            gezinmesi YERİNE bir modal/sheet açar —
                            `data-cash-movement-trigger` app.js'teki
                            `initCashMovementDialog()` tarafından yakalanır.
                            `href` GERÇEK bir yedektir: JS kapalıysa veya
                            fetch başarısız olursa tarayıcı normal şekilde
                            standalone sayfaya gider (kullanıcı talimatı §1).
                        --}}
                        <a href="{{ route($item['route'], $item['params']) }}"
                           @if ($item['route'] === 'cashbook.movements.create') data-cash-movement-trigger @endif
                           class="flex min-h-11 items-center gap-2.5 px-3 text-sm text-tx hover:bg-well">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                                 class="size-4 shrink-0 text-mut" aria-hidden="true">
                                <path d="{{ NavIcons::path($item['icon']) }}" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
