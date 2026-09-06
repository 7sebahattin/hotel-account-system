@extends('layouts.app')
@section('title', 'Denetim kaydı')

@section('content')
    <h1 class="text-lg font-semibold">Denetim kaydı</h1>
    <p class="mt-1 text-sm text-slate-600">Kayıtlar salt okunurdur; değiştirilemez ve silinemez.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="event" class="block text-xs font-medium text-slate-600">Olay</label>
            <select id="event" name="event" class="mt-1 min-h-11 rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                @foreach ($events as $event)
                    <option value="{{ $event->value }}" @selected(request('event') === $event->value)>
                        {{ $event->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-xs font-medium text-slate-600">Başlangıç</label>
            <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}"
                   class="mt-1 min-h-11 rounded-md border border-slate-300 px-3 text-sm">
        </div>
        <div>
            <label for="date_to" class="block text-xs font-medium text-slate-600">Bitiş</label>
            <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}"
                   class="mt-1 min-h-11 rounded-md border border-slate-300 px-3 text-sm">
        </div>
        <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Filtrele</button>
        <a href="{{ route('audit.index') }}" class="inline-flex min-h-11 items-center px-2 text-sm text-slate-600 hover:underline">Temizle</a>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tarih</th>
                    <th class="px-4 py-3">Kullanıcı</th>
                    <th class="px-4 py-3">Olay</th>
                    <th class="px-4 py-3">Kayıt</th>
                    <th class="px-4 py-3">Değişiklik</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? $log->user_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->event->label() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $log->subjectLabel() }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($log->new_values)
                                <code class="text-xs">{{ implode(', ', array_keys($log->new_values)) }}</code>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
