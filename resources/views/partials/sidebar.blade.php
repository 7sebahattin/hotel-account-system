@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Authorization\Enums\RoleName;

    /*
        Menü yetkiye duyarlıdır: kullanıcının erişemediği bölüm hiç render edilmez
        (gizlenmez). Gruplar okunabilirlik için başlıklıdır; yalnızca içinde
        görünür öğe kalan grup çizilir.
    */
    $groups = [
        [
            'label' => null, // ana grup başlıksızdır
            'items' => [
                ['route' => 'home', 'label' => 'Ana Sayfa', 'can' => null, 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'Cari',
            'items' => [
                ['route' => 'companies.index', 'label' => 'Cari hesaplar', 'can' => Permission::CompaniesView->value, 'icon' => 'building'],
                ['route' => 'stays.index', 'label' => 'Konaklamalar', 'can' => Permission::StaysView->value, 'icon' => 'bed'],
                ['route' => 'payments.index', 'label' => 'Tahsilatlar', 'can' => Permission::PaymentsView->value, 'icon' => 'cash'],
                // "TAHSİLAT MAKBUZLARI" — mevcut Tahsilatlar'dan AYRI bir
                // yazdırma defteri (v2.1.1-C §4/§8). İsim bilerek FARKLI
                // tutuldu, karışıklık riskine karşı (§9).
                ['route' => 'receipt-book.index', 'label' => 'Tahsilat Makbuzları', 'can' => Permission::ReceiptBookView->value, 'icon' => 'log'],
                ['route' => 'statements.index', 'label' => 'Cari Ekstre', 'can' => Permission::LedgerViewAll->value, 'icon' => 'ledger'],
            ],
        ],
        [
            'label' => 'Yönetim',
            'items' => [
                ['route' => 'users.index', 'label' => 'Kullanıcılar', 'can' => Permission::UsersView->value, 'icon' => 'users'],
                ['route' => 'roles.index', 'label' => 'Roller / Yetkiler', 'can' => Permission::RolesManage->value, 'icon' => 'shield'],
                ['route' => 'rooms.index', 'label' => 'Odalar', 'can' => Permission::RoomsManage->value, 'icon' => 'bed'],
                ['route' => 'fx-correction.index', 'label' => 'Geçmiş Döviz Düzeltme', 'can' => Permission::FxCorrectHistorical->value, 'icon' => 'log'],
                ['route' => 'audit.index', 'label' => 'Denetim Kayıtları', 'can' => Permission::AuditView->value, 'icon' => 'log'],
            ],
        ],
    ];

    $user = auth()->user();

    $visibleGroups = [];

    foreach ($groups as $group) {
        $items = array_values(array_filter(
            $group['items'],
            fn (array $item): bool => $item['can'] === null || $user->can($item['can']),
        ));

        if ($items !== []) {
            $visibleGroups[] = ['label' => $group['label'], 'items' => $items];
        }
    }

    /*
        Aktiflik deseni TEK yerde tanımlanır (önceden sidebar-link.blade.php
        içindeydi, burada TEKRARLANMIYOR): her rota kendi alt sayfalarını da
        kapsayan bir joker desene sahiptir. Bu liste ayrıca hangi GRUBUN
        açık başlayacağına karar vermek için de kullanılır — bu yüzden burada,
        gruplama mantığının yanında durur.

    */
    $patterns = [
        'home' => ['home'],
        'companies.index' => ['companies.*'],
        'stays.index' => ['stays.*'],
        'payments.index' => ['payments.*'],
        'receipt-book.index' => ['receipt-book.*'],
        'statements.index' => ['statements.*'],
        'users.index' => ['users.*'],
        'roles.index' => ['roles.*'],
        'rooms.index' => ['rooms.*'],
        'fx-correction.index' => ['fx-correction.*'],
        'audit.index' => ['audit.*'],
    ];

    $isActive = static fn (string $route): bool => request()->routeIs(...($patterns[$route] ?? [$route]));

    $roleName = $user->getRoleNames()->first();
    $roleLabel = $roleName ? (RoleName::tryFrom($roleName)?->label() ?? $roleName) : 'Rol atanmamış';
    $initials = mb_strtoupper(mb_substr($user->name, 0, 1));
@endphp

<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Ana menü">
    @foreach ($visibleGroups as $group)
        @if ($group['label'] === null)
            {{-- Ana grup başlıksız ve HER ZAMAN açıktır — katlanabilir değildir. --}}
            <ul class="mb-1 space-y-0.5">
                @foreach ($group['items'] as $item)
                    @include('partials.sidebar-link', ['item' => $item, 'active' => $isActive($item['route'])])
                @endforeach
            </ul>
        @else
            @php
                $groupActive = collect($group['items'])->contains(fn (array $item): bool => $isActive($item['route']));
            @endphp

            {{--
                Cari/Yönetim: koyu renkli, tıklanabilir üst başlık — alt
                öğeler VARSAYILAN OLARAK KAPALI. Aktif sayfanın grubu sunucu
                tarafında `open` ile başlar ve o grupta kaldığı sürece (her
                sayfa yüklemesinde yeniden hesaplanan `$groupActive` sayesinde)
                açık kalır — ayrı bir JS durum makinesi GEREKMEZ (kullanıcı
                talimatının kabul ettiği sade desen, `_view-switch` ile AYNI
                ilke). Native `<details>/<summary>`: Alpine.js kurulu
                DEĞİLDİR (CLAUDE.md §11.7), tıklama/klavye/erişilebilirlik
                tarayıcının kendi davranışıdır.
            --}}
            <details class="nav-group mt-5" @if ($groupActive) open @endif>
                <summary class="flex min-h-9 cursor-pointer list-none items-center justify-between gap-2 rounded-lg bg-nav-well px-3 text-[11px] font-semibold uppercase tracking-[0.08em] text-nav-tx select-none">
                    <span>{{ $group['label'] }}</span>

                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                         class="nav-group-chevron size-3.5 shrink-0 text-nav-mut" aria-hidden="true">
                        <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </summary>

                <ul class="mt-1 space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @include('partials.sidebar-link', ['item' => $item, 'active' => $isActive($item['route'])])
                    @endforeach
                </ul>
            </details>
        @endif
    @endforeach
</nav>

<div>
    {{--
        PWA kurulumu — "Daha Fazla" (kullanıcı talimatı §4): kalıcı büyük bir
        pano kartı DEĞİLDİR, bu çekmecenin ikincil bir eylemidir. `md:hidden`:
        masaüstü kenar çubuğu bu turda DEĞİŞTİRİLMEMELİDİR (kullanıcı
        talimatı) — bu blok yalnızca mobilde (< 768px) görünür olabilir.
        Varsayılan olarak `hidden`dır; `app.js`teki `initPwaInstall()` yalnızca
        kurulum GERÇEKTEN mümkünse (`beforeinstallprompt` ateşlenirse) veya
        iOS Safari'de henüz kurulu değilse gösterir — tarayıcı desteklemiyorsa
        veya uygulama ZATEN kuruluysa hiç görünmez.
    --}}
    <div data-pwa-install hidden class="mt-5 md:hidden">
        <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-nav-mut">Uygulama</p>
        <button type="button" data-pwa-install-button
                class="mx-3 flex min-h-11 w-[calc(100%-1.5rem)] items-center gap-2.5 rounded-lg px-3 text-sm text-nav-tx hover:bg-nav-well">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 shrink-0 text-nav-mut" aria-hidden="true">
                <path d="M10 3v10m0 0l-4-4m4 4l4-4M4 15v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Uygulamayı Yükle
        </button>
    </div>

    <div data-pwa-ios-hint hidden class="mt-5 px-3 md:hidden">
        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-nav-mut">Uygulama</p>
        <p class="mt-1.5 text-xs text-nav-mut">
            Bu cihaza yüklemek için: <strong class="text-nav-tx">Paylaş</strong> →
            <strong class="text-nav-tx">Ana Ekrana Ekle</strong>
        </p>
    </div>
</div>

<div class="border-t border-nav-line p-3">
    <div class="flex min-w-0 items-center gap-2.5 px-1 pb-2.5">
        <span aria-hidden="true"
              class="flex size-8 shrink-0 items-center justify-center rounded-full bg-nav-well text-xs font-semibold text-nav-tx">
            {{ $initials }}
        </span>

        <span class="min-w-0">
            <span class="block truncate text-sm text-nav-tx">{{ $user->name }}</span>
            <span class="block truncate text-xs text-nav-mut">{{ $roleLabel }}</span>
        </span>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-nav-line text-sm text-nav-mut transition-colors hover:bg-nav-well hover:text-nav-tx">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4" aria-hidden="true">
                <path d="M12 14l4-4-4-4M16 10H7M8 3H5a1 1 0 00-1 1v12a1 1 0 001 1h3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Çıkış
        </button>
    </form>
</div>
