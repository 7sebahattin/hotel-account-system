<div data-shortcut-row class="rounded-xl border border-line bg-card p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
        <span data-shortcut-position class="text-xs font-semibold text-mut">Kısayol</span>
        <div class="flex gap-1">
            <button type="button" data-shortcut-up class="rounded-lg border border-field px-2 py-1 text-sm text-tx hover:bg-well focus-visible:outline-2 focus-visible:outline-acc-tx" aria-label="Kısayolu yukarı taşı">↑</button>
            <button type="button" data-shortcut-down class="rounded-lg border border-field px-2 py-1 text-sm text-tx hover:bg-well focus-visible:outline-2 focus-visible:outline-acc-tx" aria-label="Kısayolu aşağı taşı">↓</button>
            <button type="button" data-shortcut-remove class="rounded-lg border border-field px-2 py-1 text-sm text-danger hover:bg-danger-bg focus-visible:outline-2 focus-visible:outline-danger" aria-label="Kısayolu sil">Sil</button>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
        <label class="block text-sm font-medium text-tx">Ad
            <input data-shortcut-field="label" name="shortcuts[{{ $index }}][label]" type="text" maxlength="50" required value="{{ $item['label'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-field bg-card px-3 py-2 text-tx focus-visible:outline-2 focus-visible:outline-acc-tx">
        </label>
        <label class="block text-sm font-medium text-tx">Hedef işlem
            <select data-shortcut-field="route" name="shortcuts[{{ $index }}][route]" required class="mt-1 block w-full rounded-lg border border-field bg-card px-3 py-2 text-tx focus-visible:outline-2 focus-visible:outline-acc-tx">
                @if (! isset($targets[$item['route'] ?? '']) && isset($item['route']))
                    <option value="{{ $item['route'] }}" selected disabled>Artık yetkili değilsiniz — değiştirin veya silin</option>
                @endif
                @foreach ($targets as $route => $target)
                    <option value="{{ $route }}" @selected(($item['route'] ?? '') === $route)>{{ $target['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-tx">İkon
            <select data-shortcut-field="icon" name="shortcuts[{{ $index }}][icon]" required class="mt-1 block w-full rounded-lg border border-field bg-card px-3 py-2 text-tx focus-visible:outline-2 focus-visible:outline-acc-tx">
                @foreach ($icons as $icon => $label)
                    <option value="{{ $icon }}" @selected(($item['icon'] ?? '') === $icon)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <label class="mt-3 inline-flex items-center gap-2 text-sm text-tx">
        <input type="hidden" data-shortcut-field="active" name="shortcuts[{{ $index }}][active]" value="0">
        <input type="checkbox" data-shortcut-field="active" name="shortcuts[{{ $index }}][active]" value="1" @checked($item['active'] ?? false) class="size-4 accent-acc-tx">
        Etkin
    </label>
</div>
