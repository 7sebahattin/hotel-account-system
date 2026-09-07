@if ($stay->isCancelled())
    <span class="inline-flex shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">İptal</span>
@else
    <span class="inline-flex shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
@endif
