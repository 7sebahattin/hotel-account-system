@php
    $tone = match ($status) {
        \App\Modules\Offers\Enums\OfferStatus::Approved => 'border-success/20 bg-success-bg text-success',
        \App\Modules\Offers\Enums\OfferStatus::Sent => 'border-line bg-info-bg text-tx',
        \App\Modules\Offers\Enums\OfferStatus::Ready => 'border-acc/40 bg-acc-bg text-acc-tx',
        \App\Modules\Offers\Enums\OfferStatus::Cancelled => 'border-danger/20 bg-danger-bg text-danger',
        default => 'border-line bg-well text-tx',
    };
@endphp
<span class="inline-flex shrink-0 items-center rounded-md border px-2.5 py-1 text-xs font-semibold leading-5 {{ $tone }}">{{ $status->label() }}</span>
