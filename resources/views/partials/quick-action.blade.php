@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Core\Support\NavIcons;

    /*
        Global "+ Hızlı İşlem" (Gün 11, kullanıcı talimatı §10). Role/permission
        aware: her satır kendi yetkisini kontrol eder, hiçbiri yeni bir işlem
        mantığı YAZMAZ — mevcut create route'larına yönlendirir.

        Personel ve Patron/Admin FARKLI listeler görür: personel için öncelik
        kasadır (Oda Geliri/Ek Gelir/Gider/Kasa Çıkışı/Devir); Patron/Admin
        için öncelik kayıt oluşturmadır (Kasa Hareketi/Tahsilat/Konaklama/
        Firma/Devir). Aynı ekranda ikisi birden gösterilmez.
    */
    $user = auth()->user();

    $canCash = $user->can(Permission::CashBookOperate->value);
    $isFinancialRole = $user->can(Permission::DashboardFinancial->value);

    $items = [];

    if ($canCash && ! $isFinancialRole) {
        $items = [
            ['route' => 'cashbook.movements.create', 'params' => ['type' => 'room_income'], 'label' => 'Oda Geliri', 'icon' => 'cash'],
            ['route' => 'cashbook.movements.create', 'params' => ['type' => 'extra_income'], 'label' => 'Ek Gelir', 'icon' => 'cash'],
            ['route' => 'cashbook.movements.create', 'params' => ['type' => 'expense'], 'label' => 'Gider', 'icon' => 'cash'],
            ['route' => 'cashbook.movements.create', 'params' => ['type' => 'cash_out'], 'label' => 'Kasa Çıkışı', 'icon' => 'cash'],
            ['route' => 'cashbook.transfers.create', 'params' => [], 'label' => 'Kasa Devri', 'icon' => 'swap'],
        ];
    } else {
        if ($canCash) {
            $items[] = ['route' => 'cashbook.movements.create', 'params' => [], 'label' => 'Kasa Hareketi', 'icon' => 'cash'];
        }
        if ($user->can(Permission::PaymentsCreate->value)) {
            $items[] = ['route' => 'payments.create', 'params' => [], 'label' => 'Tahsilat', 'icon' => 'cash'];
        }
        if ($user->can(Permission::StaysCreate->value)) {
            $items[] = ['route' => 'stays.create', 'params' => [], 'label' => 'Konaklama', 'icon' => 'bed'];
        }
        if ($user->can(Permission::CompaniesCreate->value)) {
            $items[] = ['route' => 'companies.create', 'params' => [], 'label' => 'Firma', 'icon' => 'building'];
        }
        if ($canCash) {
            $items[] = ['route' => 'cashbook.transfers.create', 'params' => [], 'label' => 'Kasa Devri', 'icon' => 'swap'];
        }
    }
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
                        <a href="{{ route($item['route'], $item['params']) }}"
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
