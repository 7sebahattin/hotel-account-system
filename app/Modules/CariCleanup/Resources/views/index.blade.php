@extends('layouts.app')
@section('title', 'Cari Veri Temizleme')

@section('content')
    @php
        $displayCount = static fn (?int $count): string => $count === null ? 'Okunamadı' : number_format($count, 0, ',', '.');
        $labels = [
            'stays' => 'Konaklamalar',
            'offers' => 'Teklif belgeleri',
            'offer_periods' => 'Teklif dönemleri',
            'offer_room_types' => 'Teklif oda türleri',
            'offer_prices' => 'Teklif fiyatları',
            'payments' => 'Tahsilatlar',
            'receipt_book_entries' => 'Tahsilat makbuzları',
            'ledger_entries' => 'Cari defter hareketleri',
            'payment_allocations' => 'Tahsilat dağılımları',
        ];
        $counts = array_column($report['tables'], 'count', 'name');
        $completed = $report['completed'];
        $canConfirm = ! $completed && $report['ready'] && is_string($token) && $token !== '';
    @endphp

    <x-ui.page-header title="Cari Veri Temizleme"
                      subtitle="Cari hesap kartları korunur. Bu tek seferlik işlem yalnızca cari girişlerini temizler; HESAP, PERSONEL ve YÖNETİM verileri korunacaktır." />

    @if ($completed)
        <x-ui.alert tone="success" title="İşlem tamamlandı" class="mt-4">
            <p class="mt-1">Cari girişleri temizlendi. Cari hesap kartları ve kapsam dışındaki veriler korundu. Bu işlem tekrar çalıştırılamaz.</p>
            @if ($report['completion_report'] !== null)
                <p class="mt-2">Tamamlanma: {{ $report['completion_report']['completed_at'] }}</p>
            @endif
        </x-ui.alert>

        @if ($report['completion_report'] !== null)
            <x-ui.card title="Silinen kayıtlar" class="mt-5">
                <ul class="divide-y divide-line">
                    @foreach ($report['completion_report']['deleted'] as $table => $count)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-tx">{{ $labels[$table] ?? $table }}</span>
                            <span class="tabular-nums text-tx">{{ $displayCount($count) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    @else
        <x-ui.alert tone="danger" title="Kalıcı ve tek seferlik silme" class="mt-4">
            <p class="mt-1">Aşağıdaki cari girişleri ve finansal geçmişi fiziksel olarak silinecektir. Bu işlem uygulama içinden geri alınamaz. Cari hesap kartları silinmez; temizlenen girişlere ait bakiyeler kalkar.</p>
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger" title="İşlem tamamlanamadı" class="mt-4">
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="mt-5 grid items-start gap-5 xl:grid-cols-2">
        @unless ($completed)
            <x-ui.card title="Silinecek cari girişleri">
                <p class="mb-4 text-sm text-mut">Kayıt sayıları mevcut veritabanından okunur. İptal edilmiş girişler ve geçmiş finansal hareketler de kapsamdadır.</p>
                <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 border-b border-line pb-2 text-xs font-medium text-mut" aria-hidden="true">
                    <span>Bölüm</span><span>Kayıt sayısı</span>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($labels as $table => $label)
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 py-3">
                            <span class="text-sm text-tx">{{ $label }}</span>
                            <span class="text-right text-sm tabular-nums text-tx" aria-label="{{ $label }} kayıt sayısı">{{ $displayCount($counts[$table] ?? null) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-mut">Cari Ekstre ve Grafik, cari defterden hesaplanır. Temizleme sonrasında bu girişler bu ekranlarda görünmez.</p>
            </x-ui.card>
        @endunless

        <x-ui.card title="Korunacak veriler">
            <p class="mb-4 text-sm text-mut">Cari hesap kartları, Hesap geçmişi, personel, yönetim ve kapsam dışındaki bütün veriler korunur. Marka logoları ve sistem ayarları da korunacaktır.</p>
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

    @unless ($completed)
        <x-ui.card title="İşlem ön kontrolü" class="mt-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-mut">Ön kontrol, silinecek girişleri ve korunacak verilerin ilişkilerini denetler. Bu adım hiçbir veri silmez.</p>
                    <p class="mt-1 text-xs text-mut">Son okuma: {{ $report['checked_at'] }}</p>
                </div>
                <form method="POST" action="{{ route('cari-cleanup.preflight') }}" class="shrink-0">
                    @csrf
                    <x-ui.button class="w-full sm:w-auto">Ön Kontrolü Çalıştır</x-ui.button>
                </form>
            </div>

            @if ($preflight && $canConfirm)
                <x-ui.alert tone="success" title="Ön kontrol başarılı" class="mt-4">
                    <p class="mt-1">Silme kapsamı doğrulandı. Hiçbir kayıt değiştirilmedi. Son onayı 10 dakika içinde tamamlayın; bu süre geçerse ön kontrolü yeniden çalıştırın.</p>
                </x-ui.alert>
            @endif

            @if ($report['blockers'] !== [])
                <x-ui.alert tone="danger" title="Silmeye engel durumlar" class="mt-4">
                    <ul class="mt-2 list-disc space-y-2 pl-5">
                        @foreach ($report['blockers'] as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-3">Engeller çözülmeden hiçbir kayıt silinmez. Kısmi temizlik yapılmaz.</p>
                </x-ui.alert>
            @endif
        </x-ui.card>

        <x-ui.card title="Son onay" class="mt-5">
            @if ($canConfirm)
                <p class="mb-4 text-sm text-mut">Listelenen cari girişleri kalıcı olarak silinecektir. Cari hesap kartları ve korunacak veriler bu işlemin dışındadır.</p>
                <form method="POST" action="{{ route('cari-cleanup.execute') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="preflight_token" value="{{ $token }}">

                    <label class="flex items-start gap-3 text-sm text-tx" for="confirm_irreversible">
                        <input id="confirm_irreversible" name="confirm_irreversible" type="checkbox" value="1" required
                               class="mt-0.5 size-5 shrink-0 rounded border-field accent-acc">
                        <span>Güncel veritabanı yedeğini aldım. Yalnızca listelenen cari girişlerinin geri alınamaz biçimde silineceğini, cari hesap kartlarının korunacağını onaylıyorum.</span>
                    </label>

                    <div>
                        <label for="cleanup-password" class="block text-sm text-mut">Admin parolanız <span class="text-acc-tx" aria-hidden="true">*</span></label>
                        <input id="cleanup-password" name="password" type="password" autocomplete="current-password" required
                               class="mt-1 block min-h-11 w-full rounded-lg border border-field bg-card px-3 text-tx focus:border-acc-tx">
                    </div>

                    <x-ui.input name="confirmation" label="Doğrulama ifadesi" required
                                autocomplete="off" spellcheck="false" hint="Aynen yazın: CARİ GİRİŞLERİNİ SİL" />

                    <x-ui.button variant="danger" class="w-full sm:w-auto">CARİ GİRİŞLERİNİ SİL</x-ui.button>
                </form>
            @else
                <p id="cleanup-disabled-reason" class="mb-3 text-sm text-mut">Son onayı açmak için ön kontrolün başarıyla tamamlanması gerekir. Hiçbir veri otomatik olarak silinmez.</p>
                <x-ui.button type="button" variant="danger" disabled aria-describedby="cleanup-disabled-reason" class="w-full sm:w-auto">CARİ GİRİŞLERİNİ SİL</x-ui.button>
            @endif
        </x-ui.card>
    @endunless
@endsection
