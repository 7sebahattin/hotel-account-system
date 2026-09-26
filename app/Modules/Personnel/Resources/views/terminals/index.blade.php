@extends('layouts.app')
@section('content')
<x-ui.page-header title="Terminaller" subtitle="Terminal anahtarı yalnız oluşturulduğu anda gösterilir." icon="card" />
<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <x-ui.card title="Kayıtlı terminaller">
        @forelse($terminals as $terminal)
            <article class="mb-3 rounded-lg border border-line p-3">
                <strong>{{ $terminal->name }}</strong>
                <p class="mt-1 break-all text-xs text-mut">{{ $terminal->platform }} · {{ $terminal->device_uuid }}</p>
                <p class="mt-1 text-xs text-mut">Son görülen: {{ $terminal->last_seen_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Henüz yok' }}</p>
                @if(!$terminal->revoked_at)
                    <form method="POST" action="{{ route('personnel.terminals.revoke',$terminal) }}" class="mt-3 grid gap-2" data-personnel-confirm="Terminal anahtarı kalıcı olarak iptal edilsin mi?">
                        @csrf
                        <x-ui.input name="reason" id="terminal-reason-{{ $terminal->id }}" label="İptal gerekçesi" required maxlength="500" />
                        <x-ui.button variant="secondary">Terminali iptal et</x-ui.button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-mut">İptal edildi · {{ $terminal->revoked_at }}</p>
                @endif
            </article>
        @empty
            <p class="text-sm text-mut">Terminal kaydı yok.</p>
        @endforelse
        {{ $terminals->links() }}
    </x-ui.card>
    <x-ui.card title="Yeni terminal">
        @if($newCredential ?? false)
            <div class="mb-4 rounded-lg bg-acc-bg p-3 text-sm text-acc-tx" role="status">
                <strong>Bu anahtar bir daha gösterilmez. Yalnız ilgili cihaza aktarın.</strong>
                <code class="mt-2 block break-all rounded bg-card p-2 text-xs">{{ $newCredential }}</code>
            </div>
        @endif
        <p class="mb-3 text-sm text-mut">Android yardımcı uygulamasının gösterdiği cihaz UUID'sini girin. Web kiosk, mevcut kullanıcı oturumuyla çalışır; Android anahtarı gerekmez.</p>
        <form method="POST" action="{{ route('personnel.terminals.store') }}" class="space-y-3">
            @csrf
            <x-ui.input name="name" label="Terminal adı" required maxlength="100" />
            <x-ui.select name="platform" label="Platform"><option value="android">Android NFC</option></x-ui.select>
            <x-ui.input name="device_uuid" label="Cihaz UUID" required maxlength="36" />
            <x-ui.button>Terminal oluştur</x-ui.button>
        </form>
    </x-ui.card>
</div>
@endsection
