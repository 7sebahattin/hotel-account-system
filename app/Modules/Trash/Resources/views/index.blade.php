@extends('layouts.app')
@section('title', 'Çöp Kutusu')

@section('content')
    <x-ui.page-header title="Çöp Kutusu"
                      :subtitle="number_format(array_sum($counts), 0, ',', '.').' kayıt çöp kutusunda'" />

    <p class="mt-2 text-sm text-mut">
        Silinen kayıtlar türüne göre ayrı gruplarda listelenir. Geri alınan kayıt eski yerine döner
        (silme sırasında iptal edilen tahsilat/konaklama iptal olarak kalır).
        @if ($canForceDelete)
            <strong class="text-tx">Kalıcı sil geri alınamaz</strong>; tahsilat ve konaklamada bağlı defter ve dağılım kayıtları da silinir.
        @else
            Kalıcı silme yalnızca Admin tarafından yapılır.
        @endif
    </p>

    @if ($groups === [])
        <x-ui.card class="mt-4 p-6"><p class="text-sm text-mut">Görüntüleyebileceğiniz bir çöp kutusu grubu yok.</p></x-ui.card>
    @else
        <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Çöp kutusu grupları">
            @foreach ($groups as $key => $group)
                <a href="{{ route('trash.index', ['tur' => $key]) }}"
                   role="tab" aria-selected="{{ $key === $selected ? 'true' : 'false' }}"
                   @class([
                       'inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 text-sm',
                       'border-acc bg-acc-bg font-semibold text-tx' => $key === $selected,
                       'border-line bg-card text-mut hover:bg-well' => $key !== $selected,
                   ])>
                    {{ $group->label() }}
                    <x-ui.badge tone="{{ $counts[$key] > 0 ? 'accent' : 'neutral' }}">{{ $counts[$key] }}</x-ui.badge>
                </a>
            @endforeach
        </div>

        <x-ui.card class="mt-4 overflow-hidden p-0">
            @forelse ($items as $item)
                <div @class(['flex flex-wrap items-center gap-3 px-4 py-3', 'border-t border-line' => ! $loop->first])>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-tx">
                            @if ($item->url)
                                <a href="{{ $item->url }}" class="underline underline-offset-2">{{ $item->title }}</a>
                            @else
                                {{ $item->title }}
                            @endif
                        </p>
                        <p class="truncate text-sm text-mut">{{ $item->detail }}</p>
                        <p class="text-xs text-mut">
                            Silinme:
                            <span class="tabular-nums">{{ $item->trashedAt?->format('d.m.Y H:i') ?? '—' }}</span>
                            @if ($item->trashedBy) · {{ $item->trashedBy }} @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canRestore)
                            <form method="POST" action="{{ route('trash.restore', [$selected, $item->id]) }}">
                                @csrf @method('PATCH')
                                <x-ui.button type="submit" variant="secondary" class="min-h-9 px-3">Geri al</x-ui.button>
                            </form>
                        @endif
                        @if ($canForceDelete)
                            <form method="POST" action="{{ route('trash.destroy', [$selected, $item->id]) }}"
                                  onsubmit="return confirm('Bu kayıt veritabanından KOMPLE ve KALICI olarak silinecek. Bu işlem GERİ ALINAMAZ. Emin misiniz?')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="danger" class="min-h-9 px-3">Kalıcı sil</x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-10 text-center text-sm text-mut">Bu grupta çöp kutusunda kayıt yok.</p>
            @endforelse
        </x-ui.card>
    @endif
@endsection
