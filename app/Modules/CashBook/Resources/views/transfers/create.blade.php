@extends('layouts.app')
@section('title', 'Kasayı devret')

@section('content')
    @php $session = $summary->session; @endphp

    <x-ui.page-header title="Kasayı devret"
                      subtitle="Teslim alan kişi kabul edene kadar devir tamamlanmaz" />

    @include('cashbook::_expected', ['summary' => $summary, 'title' => 'Kasada beklenen'])

    <x-ui.card class="mt-4">
        @if ($users === [])
            <x-ui.empty class="border-0 bg-transparent">
                Devredilebilecek başka bir kasa yetkilisi bulunamadı.
            </x-ui.empty>
        @else
            <form method="POST" action="{{ route('cashbook.transfers.store') }}" class="space-y-4">
                @csrf
                <x-ui.errors />

                <x-ui.select label="Teslim alan" name="to_user_id" required>
                    <option value="">Seçiniz…</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('to_user_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </x-ui.select>

                @include('cashbook::_amount-fields', [
                    'currencies' => \App\Modules\Core\Enums\Currency::active(),
                    'expected' => $summary->expected(),
                    'legend' => 'Teslim edilen tutar',
                    'hint' => 'Bu tutarlar aynı zamanda kapanış sayımınızdır: beklenen ile farkı sistem hesaplar.',
                ])

                <x-ui.input label="Not" name="note" maxlength="255" :value="old('note')" />

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit">Devri başlat</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('cashbook.index')">Vazgeç</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
@endsection
