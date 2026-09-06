@csrf

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div>
    <label for="name" class="block text-sm font-medium text-slate-700">Rol adı</label>
    <input id="name" name="name" type="text" required
           value="{{ old('name', $role->name ?? '') }}"
           @if (isset($role) && $role->isSystem()) readonly @endif
           class="mt-1 block min-h-11 w-full max-w-sm rounded-md border border-slate-300 px-3 text-sm
                  {{ isset($role) && $role->isSystem() ? 'bg-slate-100 text-slate-500' : '' }}">
    @if (isset($role) && $role->isSystem())
        <p class="mt-1 text-sm text-slate-500">Sistem rolünün adı değiştirilemez; yetkileri düzenlenebilir.</p>
    @else
        <p class="mt-1 text-sm text-slate-500">Küçük harf, rakam, tire ve alt çizgi kullanılabilir.</p>
    @endif
</div>

<div class="mt-6 space-y-5">
    @foreach ($grouped as $group => $permissions)
        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-sm font-medium text-slate-700">{{ $group }}</legend>

            <div class="space-y-2">
                @foreach ($permissions as $permission)
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}"
                               @checked(in_array($permission->value, old('permissions', $assigned ?? []), true))
                               class="mt-0.5 size-4 rounded border-slate-300">
                        <span>
                            {{ $permission->label() }}
                            <code class="ml-1 text-xs text-slate-400">{{ $permission->value }}</code>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
