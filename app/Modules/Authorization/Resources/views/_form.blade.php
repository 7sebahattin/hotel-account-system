@csrf

<x-ui.errors />

<x-ui.input
    label="Rol adı" name="name" required
    :value="$role->name ?? null"
    @readonly(isset($role) && $role->isSystem())
    :hint="isset($role) && $role->isSystem()
        ? 'Sistem rolünün adı değiştirilemez; yetkileri düzenlenebilir.'
        : 'Küçük harf, rakam, tire ve alt çizgi kullanılabilir.'"
    class="max-w-sm {{ isset($role) && $role->isSystem() ? 'cursor-not-allowed text-mut' : '' }}"
/>

<div class="mt-6 space-y-4">
    @foreach ($grouped as $group => $permissions)
        <fieldset class="rounded-lg border border-line p-4">
            <legend class="px-1 text-sm text-mut">{{ $group }}</legend>

            <div class="space-y-2">
                @foreach ($permissions as $permission)
                    <x-ui.checkbox
                        name="permissions[]"
                        :id="'perm-'.$permission->value"
                        :value="$permission->value"
                        :label="$permission->label()"
                        :checked="in_array($permission->value, old('permissions', $assigned ?? []), true)"
                    >
                        <code class="ml-1 text-xs text-mut">{{ $permission->value }}</code>
                    </x-ui.checkbox>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
