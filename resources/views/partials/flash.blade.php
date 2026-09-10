@if (session()->has('success'))
    <x-ui.alert tone="success" class="mb-4">{{ session('success') }}</x-ui.alert>
@endif

@if (session()->has('error'))
    <x-ui.alert tone="danger" class="mb-4">{{ session('error') }}</x-ui.alert>
@endif

@if (session()->has('status'))
    <x-ui.alert tone="info" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif
