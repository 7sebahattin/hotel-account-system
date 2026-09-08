@extends('layouts.app')
@section('title', 'Vardiyayı kapat')

@section('content')
    @php $session = $summary->session; @endphp

    <x-ui.page-header title="Vardiyayı kapat"
                      :subtitle="$session->openedBy?->name.' · '.$session->opened_at->format('d.m.Y H:i')" />

    <x-ui.alert tone="info" class="mt-4">
        Kasadaki parayı sayıp her para birimi için fiilen SAYDIĞINIZ tutarı girin.
        Beklenen tutar sunucuda yeniden hesaplanır; fark otomatik çıkar ve kayıt
        kapandıktan sonra değiştirilemez.
    </x-ui.alert>

    @include('cashbook::_expected', ['summary' => $summary, 'title' => 'Sistemdeki beklenen kasa'])

    <x-ui.card class="mt-4">
        <form method="POST" action="{{ route('cashbook.sessions.close.store', $session) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-ui.errors />

            @include('cashbook::_amount-fields', [
                'currencies' => \App\Modules\Core\Enums\Currency::active(),
                'expected' => $summary->expected(),
                'legend' => 'Sayılan tutar',
                'hint' => 'Alanlar beklenen tutarla doldurulmuştur; farklıysa gerçekte saydığınız tutarı yazın.',
            ])

            <x-ui.input label="Kapanış notu" name="closing_note" maxlength="255" :value="old('closing_note')" />

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit"
                             onclick="return confirm('Vardiya kapatılsın mı? Kapanan vardiyaya yeni hareket eklenemez.')">
                    Vardiyayı kapat
                </x-ui.button>
                <x-ui.button variant="ghost" :href="route('cashbook.index')">Vazgeç</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
