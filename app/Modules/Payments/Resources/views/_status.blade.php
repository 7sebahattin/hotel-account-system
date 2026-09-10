@if ($payment->isCancelled())
    <x-ui.badge tone="danger">İptal</x-ui.badge>
@else
    <x-ui.badge tone="success">Aktif</x-ui.badge>
@endif
