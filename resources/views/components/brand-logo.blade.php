@props(['variant' => 'on-light', 'decorative' => false])

@php
    $name = (string) config('hotel.brand.name');
    $src = app(\App\Modules\Branding\Services\BrandLogoService::class)->url($variant);
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $decorative ? '' : $name.' logosu' }}"
         @if ($decorative) aria-hidden="true" @endif
         data-brand-variant="{{ $variant }}"
         {{ $attributes->class('max-w-full object-contain') }}>
@else
    <span data-brand-variant="{{ $variant }}" {{ $attributes }}>{{ $name }}</span>
@endif
