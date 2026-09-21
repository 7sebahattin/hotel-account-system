@extends('layouts.print')
@section('title', $day->account_date->format('d.m.Y').' — Günlük Hesap Raporu')

@section('content')
    @php
        $summary = $revision->summary();
        $try = \App\Modules\Core\Enums\Currency::TRY;
        $methodLabels = [
            'cash' => 'Nakit',
            'credit_card' => 'Kredi kartı',
            'link' => 'Link ödemesi',
            'bank_transfer' => 'Havale',
            'extra' => 'Ekstra tahsilat',
        ];
    @endphp

    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('monthly-account.show', $day) }}">Günlük hesaba dön</a>
        <a href="{{ route('monthly-account.index', ['month' => $day->account_date->format('Y-m'), 'owner_id' => $day->owner_id]) }}">Aylık liste</a>
    </div>

    <header class="head">
        <h1>GÜNLÜK HESAP RAPORU</h1>
        <p>
            {{ $day->account_date->translatedFormat('d F Y, l') }}
            · Sürüm {{ $revision->version }}
            · Hazırlayan: {{ $preparedBy?->name ?? 'Kullanıcı' }}
            · {{ $generatedAt->format('d.m.Y H:i') }}
        </p>
    </header>

    @if ($day->isDeleted())
        <div class="carry">
            <strong>Silinmiş kayıt</strong>
            <span>Bu gün ay toplamlarına dahil edilmez; tarihsel kayıt korunur.</span>
        </div>
    @endif

    <section class="summaries" aria-label="Gün özeti">
        <div class="summary">
            <h2>Günlük Liste Toplamı</h2>
            <dl>
                <div class="line total">
                    <dt>Toplam</dt>
                    <dd class="num">{{ $summary['daily_total']->format($try) }}</dd>
                </div>
            </dl>
            <p class="meaning">Elle girilen liste tutarı</p>
        </div>

        <div class="summary">
            <h2>Alınan Ödemeler · TL Karşılığı</h2>
            <dl>
                <div class="line total">
                    <dt>Toplam</dt>
                    <dd class="num">{{ $summary['receipt_total']->format($try) }}</dd>
                </div>
            </dl>
            <p class="meaning">Liste tutarı bu toplama ayrıca eklenmez</p>
        </div>
    </section>

    @if ($revision->note)
        <section class="carry">
            <strong>Günlük not</strong>
            <span>{{ $revision->note }}</span>
        </section>
    @endif

    @if ($summary['banks'] !== [])
        <section>
            <h2>Banka ve ödeme kuruluşu toplamları</h2>
            <table>
                <thead>
                    <tr>
                        <th>Ödeme yöntemi</th>
                        <th>Banka / ödeme kuruluşu</th>
                        <th class="num">Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['banks'] as $method => $methodBanks)
                        @foreach ($methodBanks as $bankName => $amounts)
                            <tr>
                                <td>{{ $methodLabels[$method] ?? $method }}</td>
                                <td>{{ $bankName }}</td>
                                <td class="num">
                                    @foreach ($amounts as $code => $amount)
                                        @php($currency = \App\Modules\Core\Enums\Currency::from($code))
                                        <div>
                                            {{ $amount['amount']->format($currency) }}
                                            @if ($code !== 'TRY')
                                                <span class="fx">≈ {{ $amount['try_equivalent']->format($try) }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="print-section">
        <h2>Ödeme ayrıntıları ({{ $revision->items->count() }})</h2>
        <table>
            <thead>
                <tr>
                    <th>Ödeme yöntemi</th>
                    <th>Banka / hesap</th>
                    <th>Açıklama / not</th>
                    <th class="num">Tutar</th>
                    <th class="num">TL karşılığı</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($revision->items as $item)
                    <tr>
                        <td>{{ $methodLabels[$item->method->value] ?? $item->method->value }}</td>
                        <td>
                            {{ $item->bank_name ?: '—' }}
                            <span class="fx">{{ $item->currency->value === 'TRY' ? 'TL hesabı' : $item->currency->value.' hesabı' }}</span>
                        </td>
                        <td>{{ $item->note ?: '—' }}</td>
                        <td class="num">{{ \App\Modules\Core\Support\Money::fromDecimal($item->amount)->format($item->currency) }}</td>
                        <td class="num">
                            @if ($item->currency === $try)
                                {{ \App\Modules\Core\Support\Money::fromDecimal($item->amount)->format($try) }}
                            @else
                                {{ \App\Modules\Core\Support\Money::fromDecimal($item->try_equivalent)->format($try) }}
                                @if ($item->exchange_rate_to_try)
                                    <span class="fx">Kur: {{ rtrim(rtrim(number_format((float) $item->exchange_rate_to_try, 4, ',', '.'), '0'), ',') }}</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Bu gün için ödeme satırı girilmedi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="print-section">
        <h2>Kayıt geçmişi</h2>
        <table>
            <thead>
                <tr>
                    <th>Sürüm</th>
                    <th>İşlem</th>
                    <th>Kullanıcı</th>
                    <th>Tarih / saat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($day->revisions as $history)
                    <tr>
                        <td>Sürüm {{ $history->version }}</td>
                        <td>{{ $history->status->value === 'deleted' ? 'Silindi' : ($history->version === 1 ? 'Oluşturuldu' : 'Düzenlendi') }}</td>
                        <td>{{ $history->creator?->name ?? 'Kullanıcı' }}</td>
                        <td>{{ $history->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <footer class="foot">
        <span>Lemon Cari Takip · Günlük Hesap Raporu</span>
        <span>{{ $generatedAt->format('d.m.Y H:i') }} tarihinde oluşturuldu</span>
    </footer>
@endsection
