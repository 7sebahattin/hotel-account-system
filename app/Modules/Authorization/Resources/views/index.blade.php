@extends('layouts.app')
@section('title', 'Roller ve Yetkiler')

@section('content')
    <x-ui.page-header title="Roller ve Yetkiler" :count="$roles->count()">
        <x-slot:actions>
            <x-ui.button :href="route('roles.create')">Yeni rol</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <x-ui.card>
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-medium text-tx">{{ $role->label() }}</h2>
                    @if ($role->isSystem())
                        <x-ui.badge>Sistem rolü</x-ui.badge>
                    @endif
                </div>

                <p class="mt-1 text-sm text-mut">{{ $role->description() ?? 'Özel rol.' }}</p>

                <p class="mt-3 text-sm text-mut">
                    <span class="tabular-nums text-tx">{{ $role->permissions->count() }}</span>
                    / <span class="tabular-nums">{{ $permissionCount }}</span> yetki
                </p>

                <div class="mt-4 flex items-center gap-3">
                    <x-ui.button variant="secondary" :href="route('roles.edit', $role)">Yetkileri düzenle</x-ui.button>

                    @can('delete', $role)
                        <form method="POST" action="{{ route('roles.destroy', $role) }}"
                              onsubmit="return confirm('Bu rol silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="min-h-11 px-2 text-sm text-danger hover:underline">Sil</button>
                        </form>
                    @endcan
                </div>
            </x-ui.card>
        @endforeach
    </div>
@endsection
