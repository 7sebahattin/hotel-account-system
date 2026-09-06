@extends('layouts.app')
@section('title', 'Kullanıcılar')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Kullanıcılar</h1>

        @can('create', \App\Models\User::class)
            <a href="{{ route('users.create') }}"
               class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                Yeni kullanıcı
            </a>
        @endcan
    </div>

    <form method="GET" class="mt-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Ad veya e-posta ara"
               class="min-h-11 w-full max-w-sm rounded-md border border-slate-300 px-3 text-sm">
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">E-posta</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3">Son giriş</th>
                    <th class="px-4 py-3"><span class="sr-only">İşlemler</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $listedUser)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $listedUser->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $listedUser->email }}</td>
                        <td class="px-4 py-3">{{ $listedUser->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $listedUser->is_active,
                                'bg-slate-200 text-slate-700' => ! $listedUser->is_active,
                            ])>{{ $listedUser->is_active ? 'Aktif' : 'Pasif' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $listedUser->last_login_at?->format('d.m.Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('update', \App\Models\User::class)
                                <a href="{{ route('users.edit', $listedUser) }}"
                                   class="text-sm font-medium text-slate-700 hover:underline">Düzenle</a>

                                <form method="POST" action="{{ route('users.toggle-active', $listedUser) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ml-3 text-sm font-medium text-slate-700 hover:underline">
                                        {{ $listedUser->is_active ? 'Pasifleştir' : 'Aktifleştir' }}
                                    </button>
                                </form>
                            @endcan

                            @can('delete', $listedUser)
                                <form method="POST" action="{{ route('users.destroy', $listedUser) }}" class="inline"
                                      onsubmit="return confirm('Bu kullanıcı silinsin mi?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 text-sm font-medium text-red-700 hover:underline">Sil</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
