@php
    $tone = match ($status) {
        \App\Modules\Offers\Enums\OfferStatus::Approved => 'bg-emerald-100 text-emerald-800',
        \App\Modules\Offers\Enums\OfferStatus::Sent => 'bg-sky-100 text-sky-800',
        \App\Modules\Offers\Enums\OfferStatus::Ready => 'bg-amber-100 text-amber-800',
        \App\Modules\Offers\Enums\OfferStatus::Cancelled => 'bg-rose-100 text-rose-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $status->label() }}</span>
