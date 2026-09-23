@props(['name', 'class' => 'size-4'])

{{-- NavIcons yolundan çizgi ikon — yalnızca süs; anlam her zaman yanındaki metindedir. --}}
<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="{{ $class }} shrink-0" aria-hidden="true"><path d="{{ \App\Modules\Core\Support\NavIcons::path($name) }}" stroke-linecap="round" stroke-linejoin="round"/></svg>
