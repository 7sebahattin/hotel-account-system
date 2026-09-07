@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $error = $errors->first($name);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm text-mut">
        {{ $label }}
        @if ($required)
            <span class="text-acc" aria-hidden="true">*</span>
            <span class="sr-only">(zorunlu)</span>
        @endif
    </label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'mt-1 block min-h-11 w-full rounded-lg bg-well px-3 text-tx',
            'border focus:border-acc',
            $error ? 'border-danger' : 'border-field',
        ]) }}
    >

    @if ($hint)
        <p class="mt-1 text-xs text-mut">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-danger">{{ $error }}</p>
    @endif
</div>
