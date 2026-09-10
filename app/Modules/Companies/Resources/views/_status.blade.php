@if ($company->trashed())
    <x-ui.badge tone="danger">Silinmiş</x-ui.badge>
@elseif ($company->is_active)
    <x-ui.badge tone="success">Aktif</x-ui.badge>
@else
    <x-ui.badge>Pasif</x-ui.badge>
@endif
