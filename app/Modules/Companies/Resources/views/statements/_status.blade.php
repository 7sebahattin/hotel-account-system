{{-- Cari durum rozeti. Renk tek başına anlam taşımaz — etiket her zaman yazılıdır. --}}
@php
    use App\Modules\Companies\Services\StatementListService as StatementList;

    $tones = [
        StatementList::STATUS_DEBTOR => 'bg-tint-amber-bg text-tint-amber-tx ring-tint-amber-line',
        StatementList::STATUS_CREDITOR => 'bg-tint-blue-bg text-tint-blue-tx ring-tint-blue-line',
        StatementList::STATUS_CLOSED => 'bg-success-bg text-success ring-success/20',
        StatementList::STATUS_NONE => 'bg-well text-tx ring-line',
    ];
@endphp

<span @class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
    $tones[$status] ?? $tones[StatementList::STATUS_NONE],
]) data-statement-status="{{ $status }}">
    <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    {{ StatementList::STATUSES[$status] ?? 'Hareket yok' }}
</span>
