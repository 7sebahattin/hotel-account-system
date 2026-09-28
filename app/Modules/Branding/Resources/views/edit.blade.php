@extends('layouts.app')
@section('title', 'Marka Logoları')

@section('content')
    <x-ui.page-header title="Marka Logoları" subtitle="Açık ve koyu zemin için logoları ayrı ayrı güncelleyin." />

    <div class="mt-4"><x-ui.errors /></div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @foreach (['on-light' => 'Açık zemin logosu', 'on-dark' => 'Koyu zemin logosu'] as $variant => $label)
            <x-ui.card class="p-5">
                <h2 class="text-sm font-medium text-tx">{{ $label }}</h2>
                <div @class([
                    'mt-4 flex min-h-28 items-center justify-center rounded-lg border border-line p-4',
                    'bg-card' => $variant === 'on-light',
                    'bg-nav' => $variant === 'on-dark',
                ])>
                    <x-brand-logo :variant="$variant" class="max-h-16 w-auto" />
                </div>

                <form method="POST" action="{{ route('branding.logos.update', $variant) }}"
                      enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <label for="logo-{{ $variant }}" class="block text-sm text-mut">{{ $label }} yükle</label>
                    <input id="logo-{{ $variant }}" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                           required class="block w-full text-sm text-tx">
                    <x-ui.button type="submit">Kaydet / Değiştir</x-ui.button>
                </form>
            </x-ui.card>
        @endforeach
    </div>

    <p class="mt-4 text-sm text-mut">PNG, JPG veya WebP; en fazla 2 MB. En iyi sonuç için şeffaf ve kırpılmış logo kullanın.</p>
@endsection
