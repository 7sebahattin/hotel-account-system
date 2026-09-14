@extends('layouts.app')
@section('title', 'Banka / Ödeme Kuruluşları')

@section('content')
    <x-ui.page-header title="Banka / Ödeme Kuruluşları"
                      :subtitle="$activeCount.' aktif kuruluş / toplam '.number_format($providers->total(), 0, ',', '.')" />

    <form method="POST" action="{{ route('monthly-account.providers.store') }}" class="mt-4 flex max-w-2xl flex-wrap items-end gap-2 rounded-lg border border-line bg-card p-3">
        @csrf
        <div class="min-w-64 flex-1">
            <x-ui.input label="Yeni kuruluş adı" name="name" required maxlength="100" autocomplete="off" />
        </div>
        <x-ui.button type="submit">Ekle</x-ui.button>
    </form>

    <x-ui.errors class="mt-3" />

    <x-ui.table class="mt-4">
        <thead class="border-b border-line bg-well text-tx">
            <tr>
                <x-ui.th>Kuruluş adı</x-ui.th>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">İşlemler</span></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($providers as $provider)
                @php($used = in_array($provider->name, $usedNames, true))
                <tr class="border-b border-line last:border-0">
                    <td class="px-4 py-3">
                        @if ($used)
                            <p class="font-medium text-tx">{{ $provider->name }}</p>
                            <p class="mt-1 text-xs text-mut">Geçmiş kayıtlarda kullanıldığı için adı korunur.</p>
                        @else
                            <form method="POST" action="{{ route('monthly-account.providers.update', $provider) }}" class="flex max-w-xl items-center gap-2">
                                @csrf @method('PUT')
                                <label class="sr-only" for="provider-name-{{ $provider->id }}">Kuruluş adı</label>
                                <input id="provider-name-{{ $provider->id }}" name="name" value="{{ $provider->name }}" required maxlength="100"
                                       class="min-h-10 min-w-0 flex-1 rounded-lg border border-field bg-card px-3 text-sm text-tx focus:border-acc-tx">
                                <x-ui.button type="submit" variant="secondary">Kaydet</x-ui.button>
                            </form>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$provider->is_active ? 'success' : 'neutral'">{{ $provider->is_active ? 'Aktif' : 'Pasif' }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <form method="POST" action="{{ route('monthly-account.providers.toggle-active', $provider) }}" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="min-h-11 text-sm text-mut hover:text-tx">{{ $provider->is_active ? 'Pasifleştir' : 'Aktifleştir' }}</button>
                        </form>
                        @unless ($used)
                            <form method="POST" action="{{ route('monthly-account.providers.destroy', $provider) }}" class="inline" onsubmit="return confirm('Bu kuruluş tanımı silinsin mi?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 min-h-11 text-sm text-danger hover:underline">Sil</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-10 text-center text-mut">Henüz kuruluş tanımlanmadı.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-2 lg:hidden">
        @forelse ($providers as $provider)
            @php($used = in_array($provider->name, $usedNames, true))
            <div class="rounded-lg border border-line bg-card p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-medium text-tx">{{ $provider->name }}</p>
                    <x-ui.badge :tone="$provider->is_active ? 'success' : 'neutral'">{{ $provider->is_active ? 'Aktif' : 'Pasif' }}</x-ui.badge>
                </div>
                @unless ($used)
                    <form method="POST" action="{{ route('monthly-account.providers.update', $provider) }}" class="mt-3 flex items-end gap-2">
                        @csrf @method('PUT')
                        <div class="min-w-0 flex-1">
                            <label class="text-xs text-mut" for="mobile-provider-name-{{ $provider->id }}">Kuruluş adı</label>
                            <input id="mobile-provider-name-{{ $provider->id }}" name="name" value="{{ $provider->name }}" required maxlength="100"
                                   class="mt-1 min-h-10 w-full rounded-lg border border-field bg-card px-3 text-sm text-tx focus:border-acc-tx">
                        </div>
                        <x-ui.button type="submit" variant="secondary">Kaydet</x-ui.button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-mut">Geçmiş kayıtlarda kullanıldığı için adı korunur.</p>
                @endunless
                <div class="mt-2 flex items-center gap-3">
                    <form method="POST" action="{{ route('monthly-account.providers.toggle-active', $provider) }}">
                        @csrf @method('PATCH')
                        <x-ui.button type="submit" variant="secondary">{{ $provider->is_active ? 'Pasifleştir' : 'Aktifleştir' }}</x-ui.button>
                    </form>
                    @unless ($used)
                        <form method="POST" action="{{ route('monthly-account.providers.destroy', $provider) }}" onsubmit="return confirm('Bu kuruluş tanımı silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="min-h-11 text-sm text-danger hover:underline">Sil</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <x-ui.empty />
        @endforelse
    </div>

    <div class="mt-4">{{ $providers->links() }}</div>
@endsection
