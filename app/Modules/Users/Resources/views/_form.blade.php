@csrf

<x-ui.errors />

<div class="space-y-4">
    <x-ui.input label="Ad soyad" name="name" required :value="$user->name ?? null" />

    <x-ui.input label="E-posta" name="email" type="email" required
                inputmode="email" :value="$user->email ?? null" />

    <x-ui.input label="Şifre" name="password" type="password"
                :required="! isset($user)" autocomplete="new-password"
                :hint="isset($user) ? 'Boş bırakılırsa şifre değişmez.' : null" />

    <x-ui.input label="Şifre (tekrar)" name="password_confirmation" type="password"
                :required="! isset($user)" autocomplete="new-password" />

    <x-ui.select label="Rol" name="role" required>
        @foreach ($roles as $value => $label)
            <option value="{{ $value }}"
                @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $value)>
                {{ $label }}
            </option>
        @endforeach
    </x-ui.select>

    @if (! empty($isLastActiveAdmin))
        <x-ui.alert tone="danger">
            Bu kullanıcı sistemdeki son aktif yöneticidir; rolü değiştirilemez ve pasifleştirilemez.
        </x-ui.alert>
    @endif

    <x-ui.checkbox label="Hesap aktif" name="is_active" :checked="old('is_active', $user->is_active ?? true)" />
</div>
