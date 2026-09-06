@extends('layouts.app')
@section('title', 'Roller ve Yetkiler')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Roller ve Yetkiler</h1>
        <a href="{{ route('roles.create') }}"
           class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
            Yeni rol
        </a>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-medium">{{ $role->label() }}</h2>
                    @if ($role->isSystem())
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Sistem rolü</span>
                    @endif
                </div>

                <p class="mt-1 text-sm text-slate-600">{{ $role->description() ?? 'Özel rol.' }}</p>

                <p class="mt-3 text-sm text-slate-500">
                    {{ $role->permissions->count() }} / {{ $permissionCount }} yetki
                </p>

                <div class="mt-4 flex items-center gap-3">
                    <a href="{{ route('roles.edit', $role) }}" class="text-sm font-medium text-slate-700 hover:underline">
                        Yetkileri düzenle
                    </a>

                    @can('delete', $role)
                        <form method="POST" action="{{ route('roles.destroy', $role) }}"
                              onsubmit="return confirm('Bu rol silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Sil</button>
                        </form>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>
@endsection
