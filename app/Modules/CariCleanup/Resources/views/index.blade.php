@extends('layouts.app')
@section('title', 'Cari Veri Temizleme')

@section('content')
    @php
        $displayCount = static fn (?int $count): string => $count === null ? 'Okunamadı' : number_format($count, 0, ',', '.');
    @endphp

    <x-ui.page-header title="Cari Veri Temizleme"
                      subtitle="Bu işlem yalnızca CARİ modülüne ait veriler için hazırlanmıştır. HESAP, PERSONEL ve YÖNETİM modülleri korunacaktır." />

    <x-ui.alert tone="danger" title="Tam cari temizliği bloke edildi" class="mt-4">
        <p class="mt-1">Finansal geçmişi koruyan kurallar tam fiziksel silmeye izin vermiyor. Bu sayfa yalnızca mevcut kayıtları ve engelleri inceler. Hiçbir kayıt temizlenmez.</p>
    </x-ui.alert>

    <div class="mt-5 grid items-start gap-5 xl:grid-cols-2">
        <x-ui.card title="Silme kapsamı adayları">
            <p class="mb-4 text-sm text-mut">Aşağıdaki sayılar mevcut veritabanından okunur. Bu liste silme izni vermez; tam temizlik engellendiği için tüm kayıtlar korunur.</p>
            <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-x-3 border-b border-line pb-2 text-xs font-medium text-mut" aria-hidden="true">
                <span>Bölüm</span><span>Kayıt sayısı</span><span>Durum</span>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($report['categories'] as $category)
                    <li class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-x-3 py-3">
                        <span class="text-sm text-tx">{{ $category['label'] }}</span>
                        <span class="text-right text-sm tabular-nums text-tx" aria-label="{{ $category['label'] }} kayıt sayısı">{{ $displayCount($category['count']) }}</span>
                        <x-ui.badge tone="danger">BLOKE</x-ui.badge>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm text-mut">Teklifler sayısı, belgeler ve bağlı dönem, oda tipi ve fiyat satırlarının toplamıdır. Cari Ekstre ve Grafik, cari defterden hesaplanır; ayrı bir ekstre veya grafik tablosu temizlenmez. Tahsilat makbuzu defteri ayrıca sayılır.</p>
        </x-ui.card>

        <x-ui.card title="Korunacak veriler">
            <p class="mb-4 text-sm text-mut">Hesap geçmişi, personel, yönetim ve kapsam dışındaki bütün tablolar koruma kapsamındadır. Marka logo dosyaları ve sistem ayarları da korunur.</p>
            <ul class="divide-y divide-line">
                @foreach ($report['protected_groups'] as $group)
                    <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:gap-x-3">
                        <span class="text-sm text-tx">{{ $group['label'] }}</span>
                        <span class="text-right text-sm tabular-nums text-tx" aria-label="{{ $group['label'] }} kayıt sayısı">{{ $displayCount($group['count']) }}</span>
                        <x-ui.badge class="col-span-2 w-fit sm:col-span-1">KORUNACAK</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>

    <x-ui.card title="İşlem ön kontrolü" class="mt-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-mut">Ön kontrol kayıt sayılarını, ilişkileri ve finansal korumaları yeniden okur. Veritabanında değişiklik yapmaz.</p>
                <p class="mt-1 text-xs text-mut">Son okuma: {{ $report['checked_at'] }}</p>
            </div>
            <form method="POST" action="{{ route('cari-cleanup.preflight') }}" class="shrink-0">
                @csrf
                <x-ui.button class="w-full sm:w-auto">Ön Kontrolü Çalıştır</x-ui.button>
            </form>
        </div>

        @if ($preflight)
            <p class="mt-4 text-sm font-medium text-danger" role="status">{{ $report['available'] ? 'Ön kontrol tamamlandı: tam temizlik engellendi.' : 'Ön kontrolün tüm verileri okunamadı: tam temizlik kapalı kalır.' }} Hiçbir kayıt değiştirilmedi.</p>
        @endif

        <x-ui.alert tone="danger" title="Silmeye engel durumlar" class="mt-4">
            <ul class="mt-2 list-disc space-y-2 pl-5">
                @foreach ($report['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </x-ui.alert>

        <div class="mt-5 border-t border-line pt-4">
            <p id="cleanup-disabled-reason" class="mb-3 text-sm text-mut">Güvenli tam temizlik mümkün olmadığı için son onay kapalıdır. Yedek alınmış olması finansal korumaları kaldırmaz. Kısmi temizlik uygulanmaz.</p>
            <x-ui.button type="button" variant="danger" disabled aria-describedby="cleanup-disabled-reason" class="w-full sm:w-auto">CARİ VERİLERİNİ TEMİZLE</x-ui.button>
        </div>
    </x-ui.card>

    <div class="mt-5 space-y-3">
        <details class="rounded-xl border border-line bg-card">
            <summary class="min-h-11 cursor-pointer px-4 py-3 text-sm font-medium text-tx">Tablo kapsamı ve kayıt sayıları</summary>
            <div class="border-t border-line p-4">
                <p class="mb-3 text-sm text-mut">Tanımlı adayların dışında bulunan tablolar da otomatik olarak korunur. Aday tablolarda da silme kapalıdır.</p>
                @if ($report['tables'] === [])
                    <p class="text-sm text-danger">Tablo envanteri okunamadı. Tam temizlik kapalı kalır.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($report['tables'] as $table)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:gap-x-4">
                                <div class="min-w-0">
                                    <p class="break-all font-mono text-xs text-tx">{{ $table['name'] }}</p>
                                    <p class="mt-1 text-xs text-mut">{{ $table['module'] }}</p>
                                </div>
                                <span class="text-right text-sm tabular-nums text-tx">{{ $displayCount($table['count']) }}</span>
                                <x-ui.badge :tone="$table['protected'] ? 'neutral' : 'danger'" class="col-span-2 w-fit sm:col-span-1">{{ $table['protected'] ? 'KORUNACAK' : 'BLOKE' }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>

        <details class="rounded-xl border border-line bg-card">
            <summary class="min-h-11 cursor-pointer px-4 py-3 text-sm font-medium text-tx">Veritabanı bağımlılıkları</summary>
            <div class="border-t border-line p-4">
                <p class="mb-3 text-sm text-mut">Yabancı anahtarlar aşağıda gösterilir. Cari defterin kaynak bağlantıları, denetim geçmişi ve eski kasa referansları ayrıca korunmalıdır; bu tarihsel bağlantıların tamamı yabancı anahtarla tanımlı değildir.</p>
                @if ($report['dependencies'] === [])
                    <p class="text-sm text-mut">Bağımlılık envanterinde gösterilecek kayıt yok. Bu durum silme izni vermez.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($report['dependencies'] as $dependency)
                            <li class="py-3 text-xs">
                                <p class="break-all font-mono text-tx">{{ $dependency['table'] }}.{{ $dependency['column'] }} → {{ $dependency['referenced_table'] }}.{{ $dependency['referenced_column'] }}</p>
                                <p class="mt-1 text-mut">Silme kuralı: {{ $dependency['delete_rule'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>

        <details class="rounded-xl border border-line bg-card">
            <summary class="min-h-11 cursor-pointer px-4 py-3 text-sm font-medium text-tx">Finansal koruma tetikleyicileri</summary>
            <div class="border-t border-line p-4">
                <p class="mb-3 text-sm text-mut">Defter ve ödeme dağılımı geçmişi değiştirilemez veya silinemez. Bu korumalar kaldırılmaz. Tetikleyicinin eksik olması da silme izni vermez.</p>
                @if ($report['triggers'] === [])
                    <p class="text-sm text-danger">Gösterilecek tetikleyici bilgisi bulunamadı. Tam temizlik kapalı kalır.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($report['triggers'] as $trigger)
                            <li class="py-3 text-xs">
                                <p class="break-all font-mono text-tx">{{ $trigger['name'] }}</p>
                                <p class="mt-1 break-all text-mut">{{ $trigger['table'] }} · {{ $trigger['event'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    </div>
@endsection
