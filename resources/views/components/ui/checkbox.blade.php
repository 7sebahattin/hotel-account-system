@props(['label', 'name', 'checked' => false, 'value' => '1'])

@php $id = $attributes->get('id') ?? $name; @endphp

{{--
    Dokunma alanı görsel kutuyla (16px) sınırlı değildir: `<label>` metni de
    kapsadığı ve `min-h-11` taşıdığı için tüm satır dokunulabilirdir.
--}}
<label for="{{ $id }}" class="flex min-h-11 items-center gap-2 text-sm text-tx">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="checkbox"
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes->except('id')->class('size-4 shrink-0 rounded border-field bg-card text-acc-tx focus:ring-acc-tx') }}
    >
    <span>{{ $label }}{{ $slot }}</span>
</label>
