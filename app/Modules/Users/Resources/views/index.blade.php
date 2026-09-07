@extends('layouts.app')
@section('title', 'Kullanıcılar')

@section('content')
    <x-ui.page-header title="Kullanıcılar" :count="$users->total()">
        <x-slot:actions>
            @can('create', \App\Models\User::class)
                <x-ui.button :href="route('users.create')">Yeni kullanıcı</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mt-4 max-w-sm">
        <label for="q" class="sr-only">Ad veya e-posta ara</label>
        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Ad veya e-posta ara"
               class="block min-h-11 w-full rounded-lg border border-field bg-well px-3 text-tx focus:border-acc">
    </form>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Ad</x-ui.th>
                <x-ui.th>E-posta</x-ui.th>
                <x-ui.th>Rol</x-ui.th>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th>Son giriş</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">İşlemler</span></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $listedUser)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 text-tx">{{ $listedUser->name }}</td>
                    <td class="px-4 py-3 text-mut">{{ $listedUser->email }}</td>
                    <td class="px-4 py-3 text-mut">{{ $listedUser->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$listedUser->is_active ? 'success' : 'neutral'">
                            {{ $listedUser->is_active ? 'Aktif' : 'Pasif' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 tabular-nums text-mut">
                        {{ $listedUser->last_login_at?->format('d.m.Y H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @can('update', \App\Models\User::class)
                            <a href="{{ route('users.edit', $listedUser) }}" class="text-sm text-mut hover:text-tx">Düzenle</a>

                            <form method="POST" action="{{ route('users.toggle-active', $listedUser) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="ml-3 text-sm text-mut hover:text-tx">
                                    {{ $listedUser->is_active ? 'Pasifleştir' : 'Aktifleştir' }}
                                </button>
                            </form>
                        @endcan

                        @can('delete', $listedUser)
                            <form method="POST" action="{{ route('users.destroy', $listedUser) }}" class="inline"
                                  onsubmit="return confirm('Bu kullanıcı silinsin mi?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-sm text-danger hover:underline">Sil</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-mut">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($users as $listedUser)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-tx">{{ $listedUser->name }}</p>
                        <p class="truncate text-sm text-mut">{{ $listedUser->email }}</p>
                    </div>
                    <x-ui.badge :tone="$listedUser->is_active ? 'success' : 'neutral'">
                        {{ $listedUser->is_active ? 'Aktif' : 'Pasif' }}
                    </x-ui.badge>
                </div>

                <p class="mt-2 text-sm text-mut">
                    {{ $listedUser->roles->pluck('name')->implode(', ') ?: 'Rol atanmamış' }}
                </p>

                @can('update', \App\Models\User::class)
                    <div class="mt-3">
                        <x-ui.button variant="secondary" :href="route('users.edit', $listedUser)">Düzenle</x-ui.button>
                    </div>
                @endcan
            </x-ui.card>
        @empty
            <x-ui.empty />
        @endforelse
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
