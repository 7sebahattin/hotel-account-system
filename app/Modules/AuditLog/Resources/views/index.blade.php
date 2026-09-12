@extends('layouts.app')
@section('title', 'Denetim kaydı')

@section('content')
    <x-ui.page-header title="Denetim kaydı" :count="$logs->total()"
                      subtitle="Kayıtlar salt okunurdur; değiştirilemez ve silinemez." />

    <x-ui.filter-panel :count="$activeFilterCount" :clear-href="route('audit.index')">
        <x-ui.select label="Olay" name="event">
            <option value="">Tümü</option>
            @foreach ($events as $event)
                <option value="{{ $event->value }}" @selected(request('event') === $event->value)>
                    {{ $event->label() }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Başlangıç" name="date_from" type="date" :value="request('date_from')" />
        <x-ui.input label="Bitiş" name="date_to" type="date" :value="request('date_to')" />
    </x-ui.filter-panel>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Tarih</x-ui.th>
                <x-ui.th>Kullanıcı</x-ui.th>
                <x-ui.th>Olay</x-ui.th>
                <x-ui.th>Kayıt</x-ui.th>
                <x-ui.th>Değişiklik</x-ui.th>
                <x-ui.th>IP</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-mut">
                        {{ $log->created_at->format('d.m.Y H:i:s') }}
                    </td>
                    <td class="px-4 py-3 text-tx">{{ $log->user?->name ?? $log->user_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-tx">{{ $log->event->label() }}</td>
                    <td class="px-4 py-3 text-mut">{{ $log->subjectLabel() }}</td>
                    <td class="px-4 py-3">
                        @if ($log->new_values)
                            {{-- Teknik ekran: alan adları monospace gösterilir. --}}
                            <code class="font-mono text-xs break-all text-mut">
                                {{ implode(', ', array_keys($log->new_values)) }}
                            </code>
                        @else
                            <span class="text-mut">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-mut">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-mut">Kayıt bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($logs as $log)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <span class="text-tx">{{ $log->event->label() }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-mut">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <p class="mt-1 text-sm text-mut">{{ $log->user?->name ?? $log->user_name ?? '—' }}</p>
                <p class="mt-1 font-mono text-xs break-all text-mut">{{ $log->subjectLabel() }}</p>
            </x-ui.card>
        @empty
            <x-ui.empty />
        @endforelse
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
