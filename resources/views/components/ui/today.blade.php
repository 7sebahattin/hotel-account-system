{{--
    Liste başlıklarındaki tarih/saat bloğu ("23 Eylül 2026 · Çarşamba, 15:40").
    Gün/ay adları yerel ayardan bağımsızdır (Core\Support\TurkishDate).
--}}
@php
    use App\Modules\Core\Support\TurkishDate;

    $now = now();
@endphp

<div {{ $attributes->class('hidden items-center gap-2 pr-2 text-xs text-tx sm:flex') }}>
    <x-ui.icon name="calendar" class="size-5 text-mut" />
    <span>
        <span class="block font-medium">{{ TurkishDate::long($now) }}</span>
        <span class="block text-mut">{{ TurkishDate::weekday($now) }}, {{ $now->format('H:i') }}</span>
    </span>
</div>
