@if ($errors->any())
    <x-ui.alert tone="danger" title="Form gönderilemedi" class="mb-4">
        <ul class="mt-1 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
