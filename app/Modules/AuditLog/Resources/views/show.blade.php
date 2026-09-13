@extends('layouts.app')
@section('title', 'Denetim kaydı #'.$log->id)

@section('content')
    {{--
        Salt okunur detay (kullanıcı talimatı, 13 Eylül 2026). Listedeki
        "Değişiklik" kolonu yalnızca alan ADLARINI gösterebiliyordu; burada
        aynı kaydın eski/yeni DEĞERLERİ alan alan açılır.

        Teknik bir ekran olduğu için alan adları ve teknik değerler monospace
        gösterilir (CLAUDE.md §11.6: monospace YALNIZCA denetim kaydı gibi
        teknik ekranlarda).
    --}}
    <x-ui.page-header :title="'Denetim kaydı #'.$log->id"
                      subtitle="Bu kayıt değiştirilemez ve silinemez.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('audit.index')">Listeye dön</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-ui.panel title="İşlem" class="lg:col-span-1">
            <dl class="divide-y divide-line">
                <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                    <dt class="text-sm text-mut">Olay</dt>
                    <dd class="text-right text-sm text-tx">{{ $log->event->label() }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                    <dt class="text-sm text-mut">Tarih</dt>
                    <dd class="text-right text-sm tabular-nums text-tx">
                        {{ $log->created_at->format('d.m.Y H:i:s') }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                    <dt class="text-sm text-mut">Kullanıcı</dt>
                    <dd class="min-w-0 text-right text-sm break-words text-tx">
                        {{-- Snapshot: kullanıcı sonradan silinse bile ad korunur (§5.6). --}}
                        {{ $log->user?->name ?? $log->user_name ?? '—' }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                    <dt class="text-sm text-mut">Kayıt</dt>
                    <dd class="min-w-0 text-right text-sm text-tx">
                        {{ $log->subjectTypeLabel() }}
                        @if ($log->auditable_id !== null)
                            <span class="font-mono text-xs text-mut">#{{ $log->auditable_id }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.panel>

        <x-ui.panel title="Değişen alanlar" class="lg:col-span-2">
            @if ($log->changes() === [])
                <p class="px-4 py-6 text-sm text-mut">
                    Bu olay için alan değişikliği kaydedilmemiştir (ör. giriş/çıkış olayları).
                </p>
            @else
                {{-- Dar ekranda tablo yerine kart listesi (CLAUDE.md §11.5). --}}
                <x-ui.table>
                    <thead class="border-b border-line">
                        <tr>
                            <x-ui.th>Alan</x-ui.th>
                            <x-ui.th>Eski değer</x-ui.th>
                            <x-ui.th>Yeni değer</x-ui.th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($log->changes() as $change)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-4 py-3 align-top font-mono text-xs break-all text-mut">
                                    {{ $change['field'] }}
                                </td>
                                <td class="px-4 py-3 align-top font-mono text-xs break-all text-mut">
                                    {{ $change['old'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 align-top font-mono text-xs break-all text-tx">
                                    {{ $change['new'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <div class="divide-y divide-line lg:hidden">
                    @foreach ($log->changes() as $change)
                        <div class="px-4 py-3">
                            <p class="font-mono text-xs break-all text-mut">{{ $change['field'] }}</p>
                            <p class="mt-1 font-mono text-xs break-all text-mut">
                                Eski: {{ $change['old'] ?? '—' }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs break-all text-tx">
                                Yeni: {{ $change['new'] ?? '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.panel>
    </div>

    <x-ui.panel title="İstek bilgisi" class="mt-4">
        <dl class="divide-y divide-line">
            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                <dt class="text-sm text-mut">IP adresi</dt>
                <dd class="text-right font-mono text-xs text-tx">{{ $log->ip_address ?? '—' }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                <dt class="text-sm text-mut">Adres</dt>
                <dd class="min-w-0 text-right font-mono text-xs break-all text-tx">{{ $log->url ?? '—' }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                <dt class="text-sm text-mut">Tarayıcı</dt>
                <dd class="min-w-0 text-right font-mono text-xs break-all text-tx">{{ $log->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui.panel>
@endsection
