@extends('layouts.app')
@section('title', 'Aylık Hesap')

@section('content')
    @php
        $model = \App\Modules\MonthlyAccount\Models\MonthlyAccountDay::class;
        $try = \App\Modules\Core\Enums\Currency::TRY;
        $bankMethods = ['credit_card' => 'Kredi kartı', 'link' => 'Link ödeme', 'bank_transfer' => 'Havale'];
        $columns = [['method' => 'cash', 'bank' => null, 'label' => 'Nakit']];

        foreach ($bankMethods as $method => $label) {
            $bankNames = collect(array_keys($summary['banks'][$method] ?? []))
                ->sortBy(fn (string $bank): string => mb_strtolower($bank, 'UTF-8'))
                ->values();

            if ($bankNames->isEmpty()) {
                $columns[] = ['method' => $method, 'bank' => null, 'label' => $label];
                continue;
            }

            foreach ($bankNames as $bank) {
                $columns[] = ['method' => $method, 'bank' => $bank, 'label' => $label];
            }
        }

        $columns[] = ['method' => 'extra', 'bank' => null, 'label' => 'Ekstra'];
        $monthParams = ['owner_id' => $owner->id];
        $recorded = $days->filter(fn ($day) => ! $day->isDeleted())->count();
        $storedDates = $days->keys()->sort()->values();
        $listedDayNumbers = $storedDates->isEmpty()
            ? []
            : range((int) substr($storedDates->last(), 8, 2), (int) substr($storedDates->first(), 8, 2));
    @endphp

    <div class="monthly-account monthly-account-index">
        <x-ui.page-header title="Aylık Hesap" :subtitle="$owner->name.' · '.$month->translatedFormat('F Y').' · '.$recorded.' gün kayıtlı'">
            <x-slot:actions>
                @can('createForOwner', [$model, $owner])
                    <x-ui.button :href="route('monthly-account.create', ['owner_id' => $owner->id, 'date' => now()->isSameMonth($month) ? now()->toDateString() : $month->toDateString()])">+ Günlük kayıt</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        <form method="GET" action="{{ route('monthly-account.index') }}" class="mt-4 grid grid-cols-2 items-end gap-2 rounded-lg border border-line bg-card p-3 sm:flex sm:flex-wrap">
            <div class="min-w-0 sm:flex-none">
                <x-ui.input label="Ay" name="month" type="month" :value="$month->format('Y-m')" required />
            </div>
            @if ($owners->isNotEmpty())
                <div class="min-w-0 sm:min-w-48 sm:flex-none">
                    <x-ui.select label="Hesap sahibi" name="owner_id">
                        @foreach ($owners as $candidate)
                            <option value="{{ $candidate->id }}" @selected($candidate->id === $owner->id)>{{ $candidate->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @else
                <input type="hidden" name="owner_id" value="{{ $owner->id }}">
            @endif
            <x-ui.button variant="secondary" class="col-span-2">Göster</x-ui.button>
            <div class="col-span-2 flex flex-wrap items-center justify-between gap-1 sm:ml-auto sm:justify-start" aria-label="Ay seçimi">
                <x-ui.button variant="ghost" :href="route('monthly-account.index', $monthParams + ['month' => $month->subMonth()->format('Y-m')])" aria-label="Önceki ay">←</x-ui.button>
                <x-ui.button variant="secondary" :href="route('monthly-account.index', $monthParams + ['month' => now()->format('Y-m')])">Bu ay</x-ui.button>
                <x-ui.button variant="ghost" :href="route('monthly-account.index', $monthParams + ['month' => $month->addMonth()->format('Y-m')])" aria-label="Sonraki ay">→</x-ui.button>
            </div>
        </form>

        <div class="mt-3 grid gap-3 rounded-lg border border-line bg-card p-3 sm:grid-cols-3">
            <x-ui.stat label="Günlük liste toplamları" :value="$summary['daily_total']->format($try)" hint="Elle girilen liste tutarları" />
            <x-ui.stat label="Alınan ödemeler · TL karşılığı" :value="$summary['receipt_total']->format($try)" hint="Nakit, kart, link, havale ve ekstra" />
            <x-ui.stat label="Listeye göre fark" :value="$summary['difference']->format($try)" hint="Ödemeler − günlük liste toplamları" />
        </div>

        <div class="monthly-table-wrap mt-3 hidden rounded-lg border border-line bg-card lg:block">
            <table class="monthly-table w-full text-xs">
                <caption class="sr-only">{{ $owner->name }} — {{ $month->translatedFormat('F Y') }} günlük hesap kayıtları</caption>
                <thead class="border-b border-line bg-well text-tx">
                    <tr>
                        <th scope="col" class="text-left">Gün</th>
                        <th scope="col">Günlük liste<br><span class="font-normal">TL</span></th>
                        @foreach ($columns as $column)
                            <th scope="col">
                                <span class="block">{{ $column['label'] }}</span>
                                @if ($column['bank'])
                                    <span class="font-normal">({{ $column['bank'] }})</span>
                                @endif
                            </th>
                        @endforeach
                        <th scope="col">Ödemeler<br><span class="font-normal">TL karşılığı</span></th>
                        <th scope="col"><span class="sr-only">İşlemler</span></th>
                    </tr>
                    <tr class="monthly-total-row border-t border-nav-line bg-nav-well font-medium text-nav-tx">
                        <th scope="row" class="text-left">Ay toplamı</th>
                        <td class="tabular-nums">{{ $summary['daily_total']->format($try) }}</td>
                        @foreach ($columns as $column)
                            @php
                                $amounts = $column['bank']
                                    ? ($summary['banks'][$column['method']][$column['bank']] ?? [])
                                    : ($summary['methods'][$column['method']] ?? []);
                            @endphp
                            <td>@include('monthly-account::_amounts', ['amounts' => $amounts])</td>
                        @endforeach
                        <td class="tabular-nums">{{ $summary['receipt_total']->format($try) }}</td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listedDayNumbers as $number)
                        @php
                            $date = $month->setDay($number);
                            $day = $days->get($date->toDateString());
                            $hasEntry = $day && ! $day->isDeleted();
                            $daySummary = $hasEntry ? $day->currentRevision->summary() : null;
                        @endphp
                        <tr data-monthly-date="{{ $date->toDateString() }}" class="monthly-day-row border-b border-line last:border-0 hover:bg-well">
                            <th scope="row" class="text-left font-normal">
                                <span class="block font-medium tabular-nums">{{ $date->translatedFormat('d F') }}</span>
                                <span class="text-xs text-mut">{{ $date->translatedFormat('l') }}</span>
                            </th>
                            <td class="tabular-nums">{{ $daySummary ? $daySummary['daily_total']->format($try) : '—' }}</td>
                            @foreach ($columns as $column)
                                @php
                                    $amounts = $column['bank']
                                        ? ($daySummary['banks'][$column['method']][$column['bank']] ?? [])
                                        : ($daySummary['methods'][$column['method']] ?? []);
                                @endphp
                                <td>@include('monthly-account::_amounts', ['amounts' => $amounts])</td>
                            @endforeach
                            <td class="font-semibold tabular-nums">{{ $daySummary ? $daySummary['receipt_total']->format($try) : '—' }}</td>
                            <td>
                                @if ($day)
                                    <a href="{{ route('monthly-account.show', $day) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-1 text-xs font-medium text-tx underline decoration-line underline-offset-4 hover:decoration-tx">{{ $day->isDeleted() ? 'Silindi' : 'Aç' }}</a>
                                @else
                                    @can('createForOwner', [$model, $owner])
                                        <a href="{{ route('monthly-account.create', ['owner_id' => $owner->id, 'date' => $date->toDateString()]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-1 text-xs text-mut hover:text-tx" aria-label="{{ $date->format('d.m.Y') }} için kayıt ekle">+ Ekle</a>
                                    @else
                                        <span class="text-xs text-mut">Boş</span>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 4 }}" class="py-8 text-center text-sm text-mut">Bu ay için kayıt yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="monthly-card-list mt-3 space-y-2 lg:hidden">
            @forelse ($listedDayNumbers as $number)
                @php
                    $date = $month->setDay($number);
                    $day = $days->get($date->toDateString());
                    $hasEntry = $day && ! $day->isDeleted();
                    $daySummary = $hasEntry ? $day->currentRevision->summary() : null;
                @endphp
                <article class="monthly-day-card rounded-lg border border-line bg-card p-3" data-monthly-card-date="{{ $date->toDateString() }}">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-medium">{{ $date->translatedFormat('d F l') }}</h2>
                        @if ($day)
                            <x-ui.button variant="secondary" :href="route('monthly-account.show', $day)">{{ $day->isDeleted() ? 'Silindi' : 'Görüntüle' }}</x-ui.button>
                        @else
                            @can('createForOwner', [$model, $owner])
                                <x-ui.button variant="ghost" :href="route('monthly-account.create', ['owner_id' => $owner->id, 'date' => $date->toDateString()])">+ Ekle</x-ui.button>
                            @else
                                <span class="text-xs text-mut">Kayıt yok</span>
                            @endcan
                        @endif
                    </div>
                    @if ($daySummary)
                        <dl class="mt-2 space-y-1.5 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-mut">Günlük liste</dt><dd class="text-right tabular-nums">{{ $daySummary['daily_total']->format($try) }}</dd></div>
                            @foreach ($columns as $column)
                                @php
                                    $amounts = $column['bank']
                                        ? ($daySummary['banks'][$column['method']][$column['bank']] ?? [])
                                        : ($daySummary['methods'][$column['method']] ?? []);
                                @endphp
                                @if (! empty($amounts))
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-mut">
                                            {{ $column['label'] }}@if ($column['bank']) ({{ $column['bank'] }})@endif
                                        </dt>
                                        <dd class="text-right">@include('monthly-account::_amounts', ['amounts' => $amounts])</dd>
                                    </div>
                                @endif
                            @endforeach
                            <div class="flex justify-between gap-3 border-t border-line pt-2 font-medium"><dt>Ödemeler · TL</dt><dd class="text-right tabular-nums">{{ $daySummary['receipt_total']->format($try) }}</dd></div>
                        </dl>
                    @endif
                </article>
            @empty
                <div class="rounded-lg border border-line bg-card px-4 py-8 text-center text-sm text-mut">Bu ay için kayıt yok.</div>
            @endforelse
        </div>
    </div>
@endsection
