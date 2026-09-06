@csrf

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="space-y-4">
    <div>
        <label for="name" class="block text-sm font-medium text-slate-700">Ad soyad</label>
        <input id="name" name="name" type="text" required
               value="{{ old('name', $user->name ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-slate-700">E-posta</label>
        <input id="email" name="email" type="email" required
               value="{{ old('email', $user->email ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-slate-700">
            Şifre @isset($user)<span class="font-normal text-slate-500">(boş bırakılırsa değişmez)</span>@endisset
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password"
               @unless(isset($user)) required @endunless
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Şifre (tekrar)</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
               @unless(isset($user)) required @endunless
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="role" class="block text-sm font-medium text-slate-700">Rol</label>
        <select id="role" name="role" required
                class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}"
                    @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @if (! empty($isLastActiveAdmin))
            <p class="mt-1 text-sm text-amber-700">
                Bu kullanıcı sistemdeki son aktif yöneticidir; rolü değiştirilemez ve pasifleştirilemez.
            </p>
        @endif
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $user->is_active ?? true))
               class="size-4 rounded border-slate-300">
        Hesap aktif
    </label>
</div>
