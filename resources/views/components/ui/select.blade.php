@props([
    'label',
    'name',
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
            <span class="text-acc-tx" aria-hidden="true">*</span>
            <span class="sr-only">(zorunlu)</span>
        @endif
    </label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'mt-1 block min-h-11 w-full rounded-lg bg-card px-3 text-tx',
            'border focus:border-acc-tx',
            $error ? 'border-danger' : 'border-field',
        ]) }}
    >{{ $slot }}</select>

    @if ($hint)
        <p class="mt-1 text-xs text-mut">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-danger">{{ $error }}</p>
    @endif
</div>
