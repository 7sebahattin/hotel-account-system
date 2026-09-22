@extends('layouts.print')
@section('title', $day->account_date->format('d.m.Y').' — Günlük Hesap Raporu')

@section('content')
    @php
        $summary = $revision->summary();
        $try = \App\Modules\Core\Enums\Currency::TRY;
    @endphp

    <nav class="toolbar" aria-label="Rapor işlemleri">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('monthly-account.show', $day) }}">Günlük hesaba dön</a>
        <a href="{{ route('monthly-account.index', ['month' => $day->account_date->format('Y-m'), 'owner_id' => $day->owner_id]) }}">Aylık liste</a>
    </nav>

    <header class="head">
        <h1>GÜNLÜK HESAP RAPORU</h1>
        <p>{{ $day->account_date->translatedFormat('d F Y, l') }} · Hazırlayan: {{ $preparedBy?->name ?? 'Kullanıcı' }} · {{ $generatedAt->format('d.m.Y H:i') }} · Sürüm {{ $revision->version }}</p>
    </header>

    @if ($day->isDeleted())
        <section class="carry"><strong>Silinmiş kayıt</strong><span>Bu gün ay toplamlarına dahil edilmez; tarihsel kayıt korunur.</span></section>
    @endif

    <section class="summaries" aria-label="Gün özeti">
        <div class="summary"><h2>Günlük Liste Toplamı</h2><dl><div class="line total"><dt>Toplam</dt><dd class="num">{{ $summary['daily_total']->format($try) }}</dd></div></dl><p class="meaning">Elle girilen liste tutarı</p></div>
        <div class="summary"><h2>Alınan Ödemeler · TL Karşılığı</h2><dl><div class="line total"><dt>Toplam</dt><dd class="num">{{ $summary['receipt_total']->format($try) }}</dd></div></dl><p class="meaning">Liste tutarı bu toplama ayrıca eklenmez</p></div>
    </section>

    @if ($revision->note !== null)
        <section class="carry"><strong>Günlük not</strong><span>{{ $revision->note }}</span></section>
    @endif

    @if ($summary['banks'] !== [])
        <section class="print-section">
            <h2>Banka ve ödeme kuruluşu toplamları</h2>
            <table class="daily-print-table">
                <colgroup><col style="width: 22%"><col style="width: 34%"><col style="width: 16%"><col style="width: 28%"></colgroup>
                <thead><tr><th>Ödeme yöntemi</th><th>Banka / ödeme kuruluşu</th><th>Para birimi</th><th class="num">Tutar</th></tr></thead>
                <tbody>
                    @foreach ($summary['banks'] as $method => $methodBanks)
                        @foreach ($methodBanks as $bankName => $amounts)
                            @foreach ($amounts as $currencyCode => $amountsForCurrency)
                                @php($currency = \App\Modules\Core\Enums\Currency::from($currencyCode))
                                <tr><td>{{ \App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod::from($method)->label() }}</td><td>{{ $bankName }}</td><td>{{ $currency->value }}</td><td class="num">{{ $amountsForCurrency['amount']->format($currency) }}@if ($currency !== $try)<span class="fx">≈ {{ $amountsForCurrency['try_equivalent']->format($try) }}</span>@endif</td></tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="print-section">
        <h2>Ödeme ayrıntıları ({{ $revision->items->count() }})</h2>
        <table class="daily-print-table">
            <colgroup><col style="width: 16%"><col style="width: 18%"><col style="width: 28%"><col style="width: 19%"><col style="width: 19%"></colgroup>
            <thead><tr><th>Ödeme yöntemi</th><th>Banka / hesap</th><th>Açıklama / not</th><th class="num">Orijinal tutar</th><th class="num">TL karşılığı</th></tr></thead>
            <tbody>
                @forelse ($revision->items as $item)
                    <tr>
                        <td>{{ $item->method->label() }}</td>
                        <td>{{ $item->bank_name ?? '—' }}</td>
                        <td>{{ $item->note ?? '—' }}</td>
                        <td class="num">{{ $item->amountMoney()->format($item->currency) }}</td>
                        <td class="num">{{ $item->tryEquivalentMoney()->format($try) }}@if ($item->currency !== $try)<span class="fx">Kur: {{ str_replace('.', ',', rtrim(rtrim($item->exchange_rate_to_try ?? '', '0'), '.')) }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Bu gün için ödeme satırı girilmedi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="print-section">
        <h2>Kayıt geçmişi</h2>
        <table>
            <thead><tr><th>Sürüm</th><th>İşlem türü</th><th>Kullanıcı</th><th>Tarih / saat</th></tr></thead>
            <tbody>
                @foreach ($day->revisions as $history)
                    <tr><td>Sürüm {{ $history->version }}</td><td>{{ $history->status->value === 'deleted' ? 'Silindi' : ($history->version === 1 ? 'Oluşturuldu' : 'Düzenlendi') }}</td><td>{{ $history->creator?->name ?? 'Kullanıcı' }}</td><td class="num">{{ $history->created_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <footer class="foot"><span>Lemon Cari Takip · Günlük Hesap Raporu</span><span>{{ $generatedAt->format('d.m.Y H:i') }} tarihinde oluşturuldu</span></footer>
@endsection
