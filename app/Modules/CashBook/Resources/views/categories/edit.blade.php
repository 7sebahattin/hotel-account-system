@extends('layouts.app')
@section('title', 'Kasa hareket türünü düzenle')

@section('content')
    <x-ui.page-header title="Kasa hareket türünü düzenle" :subtitle="$category->name" />

    <form method="POST" action="{{ route('cashbook.categories.update', $category) }}" class="mt-6 max-w-2xl">
        @csrf
        @method('PUT')

        <x-ui.card class="p-6 space-y-4">
            <x-ui.errors />

            <x-ui.input label="Ad" name="name" required maxlength="100" :value="old('name', $category->name)" />

            <div>
                <p class="block text-sm text-mut">Sistem davranışı</p>
                <p class="mt-1 text-tx">{{ $category->system_type->label() }}</p>
                <p class="mt-1 text-xs text-mut">
                    Oluşturulduktan sonra değiştirilemez — aynı ad geçmişte farklı bir
                    davranışa karşılık gelirse rapor sessizce yanıltıcı olurdu.
                </p>
            </div>

            <x-ui.checkbox label="Bu tür için teslim alan bilgisi zorunlu olsun"
                           name="requires_recipient" :checked="old('requires_recipient', $category->requires_recipient)" />

            <x-ui.checkbox label="Aktif — yeni hareketlerde seçilebilir"
                           name="is_active" :checked="old('is_active', $category->is_active)" />

            @if (! $category->is_active)
                <x-ui.alert tone="info">
                    Pasif türler yeni hareket formunda görünmez ama bu türle daha önce
                    kaydedilmiş hareketler listede/raporda olduğu gibi görünmeye devam eder.
                </x-ui.alert>
            @endif

            <x-ui.input label="Sıra" name="sort_order" type="number" min="0"
                        :value="old('sort_order', $category->sort_order)" />
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('cashbook.categories.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
