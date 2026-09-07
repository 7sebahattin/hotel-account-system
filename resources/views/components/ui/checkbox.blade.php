@props(['label', 'name', 'checked' => false, 'value' => '1'])

@php $id = $attributes->get('id') ?? $name; @endphp

<label for="{{ $id }}" class="flex items-start gap-2 text-sm text-tx">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="checkbox"
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes->except('id')->class('mt-0.5 size-4 shrink-0 rounded border-field bg-well text-acc focus:ring-acc') }}
    >
    <span>{{ $label }}{{ $slot }}</span>
</label>
