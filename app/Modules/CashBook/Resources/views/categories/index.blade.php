@extends('layouts.app')
@section('title', 'Kasa Hareket Türleri')

@section('content')
    <x-ui.page-header title="Kasa Hareket Türleri"
                      :subtitle="$categories->count().' tür tanımlı'">
        <x-slot:actions>
            <x-ui.button :href="route('cashbook.categories.create')">Yeni tür</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" class="mt-4">
        Her tür sabit bir sistem davranışına (gelir/gider/kasa yönü) eşlenir; bu eşleme
        oluşturulduktan sonra değiştirilemez. Kullanılmış bir tür silinemez — yalnızca pasifleştirilir.
    </x-ui.alert>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Ad</x-ui.th>
                <x-ui.th>Sistem davranışı</x-ui.th>
                <x-ui.th>Teslim alan</x-ui.th>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 text-tx">{{ $category->name }}</td>
                    <td class="px-4 py-3 text-mut">{{ $category->system_type->label() }}</td>
                    <td class="px-4 py-3 text-mut">{{ $category->requires_recipient ? 'Zorunlu' : '—' }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">
                            {{ $category->is_active ? 'Aktif' : 'Pasif' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('cashbook.categories.edit', $category) }}"
                           class="text-sm text-acc-tx underline underline-offset-2 hover:text-tx">Düzenle</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-mut">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- Mobil kart listesi --}}
    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($categories as $category)
            <a href="{{ route('cashbook.categories.edit', $category) }}" class="block">
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-tx">{{ $category->name }}</p>
                            <p class="text-sm text-mut">{{ $category->system_type->label() }}</p>
                        </div>
                        <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">
                            {{ $category->is_active ? 'Aktif' : 'Pasif' }}
                        </x-ui.badge>
                    </div>
                </x-ui.card>
            </a>
        @empty
            <x-ui.empty>Kayıt bulunamadı.</x-ui.empty>
        @endforelse
    </div>
@endsection
