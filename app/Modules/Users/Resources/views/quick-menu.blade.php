@extends('layouts.app')
@section('title', 'Hızlı Menü Ayarları')

@section('content')
    <x-ui.page-header title="Hızlı Menü Ayarları" subtitle="Masaüstündeki hızlı işlemlerinizi düzenleyin." />

    <form method="POST" action="{{ route('quick-menu.update') }}" class="mt-6 max-w-3xl" data-shortcut-form>
        @csrf
        @method('PUT')
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-xl border border-danger bg-danger-bg p-4 text-sm text-danger">
                {{ $errors->first() }}
            </div>
        @endif
        <div data-shortcut-list class="space-y-3">
            @foreach ($shortcuts as $index => $item)
                @include('users::_quick-shortcut', ['index' => $index, 'item' => $item])
            @endforeach
        </div>
        <p data-shortcut-empty class="rounded-xl border border-line bg-card p-5 text-sm text-mut {{ $shortcuts === [] ? '' : 'hidden' }}">Kısayol yok. Yeni bir işlem ekleyebilirsiniz.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            <x-ui.button type="button" variant="secondary" data-shortcut-add data-no-targets="{{ $targets === [] ? 'true' : 'false' }}">+ Kısayol ekle</x-ui.button>
            <x-ui.button type="submit">Kaydet</x-ui.button>
        </div>
        <p class="mt-3 text-sm text-mut">En fazla 12 kısayol. Sıralamak için okları kullanın; değişiklikler Kaydet ile uygulanır.</p>
    </form>

    <template data-shortcut-template>
        @include('users::_quick-shortcut', ['index' => '__INDEX__', 'item' => ['label' => '', 'route' => '', 'icon' => 'plus', 'active' => true]])
    </template>
@endsection
