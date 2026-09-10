@extends('layouts.app')
@section('title', 'Odalar')

@section('content')
    <x-ui.page-header title="Odalar"
                      :subtitle="$activeCount.' aktif oda / toplam '.number_format($rooms->total(), 0, ',', '.')">
        <x-slot:actions>
            @can('create', \App\Modules\Rooms\Models\Room::class)
                <x-ui.button :href="route('rooms.create')">Yeni oda</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Oda no</x-ui.th>
                <x-ui.th>Ad</x-ui.th>
                <x-ui.th>Tip</x-ui.th>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">İşlemler</span></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rooms as $room)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 tabular-nums text-tx">{{ $room->room_no }}</td>
                    <td class="px-4 py-3 text-mut">{{ $room->room_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $room->room_type ?: '—' }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$room->is_active ? 'success' : 'neutral'">
                            {{ $room->is_active ? 'Aktif' : 'Pasif' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @can('update', $room)
                            <a href="{{ route('rooms.edit', $room) }}" class="text-sm text-mut hover:text-tx">Düzenle</a>

                            <form method="POST" action="{{ route('rooms.toggle-active', $room) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="ml-3 text-sm text-mut hover:text-tx">
                                    {{ $room->is_active ? 'Pasifleştir' : 'Aktifleştir' }}
                                </button>
                            </form>
                        @endcan

                        @can('delete', $room)
                            <form method="POST" action="{{ route('rooms.destroy', $room) }}" class="inline"
                                  onsubmit="return confirm('Bu oda silinsin mi? Konaklama geçmişinde kullanılmışsa silme işlemi reddedilecektir.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-sm text-danger hover:underline">Sil</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-mut">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($rooms as $room)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-tx">{{ $room->label() }}</p>
                        <p class="truncate text-sm text-mut">{{ $room->room_type ?: '—' }}</p>
                    </div>
                    <x-ui.badge :tone="$room->is_active ? 'success' : 'neutral'">
                        {{ $room->is_active ? 'Aktif' : 'Pasif' }}
                    </x-ui.badge>
                </div>

                @can('update', $room)
                    <div class="mt-3">
                        <x-ui.button variant="secondary" :href="route('rooms.edit', $room)">Düzenle</x-ui.button>
                    </div>
                @endcan
            </x-ui.card>
        @empty
            <x-ui.empty />
        @endforelse
    </div>

    <div class="mt-4">{{ $rooms->links() }}</div>
@endsection
