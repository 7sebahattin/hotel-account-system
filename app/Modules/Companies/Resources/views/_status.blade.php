@if ($company->trashed())
    <span class="inline-flex shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Silinmiş</span>
@elseif ($company->is_active)
    <span class="inline-flex shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
@else
    <span class="inline-flex shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">Pasif</span>
@endif
