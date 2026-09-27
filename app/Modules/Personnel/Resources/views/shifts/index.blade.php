@extends('layouts.app')
@section('content')
<x-ui.page-header title="Vardiyalar" subtitle="Değişiklikler yeni sürüm oluşturur; atanmış eski sürümler korunur." icon="clock" />
<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <x-ui.card title="Kayıtlı vardiyalar">
        @forelse($shifts as $shift)
            <details class="mb-3 rounded-lg border border-line p-3">
                <summary class="min-h-11 font-semibold">{{ $shift->name }} · {{ $shift->is_active ? 'Aktif' : 'Pasif' }}</summary>
                @php $versions = $versionsByShift->get($shift->id, collect()); @endphp
                <div class="space-y-2 text-sm">
                    @foreach($versions as $item)<p>Sürüm {{ $item->version }} · {{ $item->start_time }}–{{ $item->end_time }}{{ $item->ends_next_day ? ' (+1 gün)' : '' }} · Mola {{ $item->break_minutes }} dk · Tolerans {{ $item->late_grace_minutes }}/{{ $item->early_grace_minutes }} dk</p>@endforeach
                </div>
                @if(data_get($permissions,'manage'))
                    <form method="POST" action="{{ route('personnel.shifts.active',$shift) }}" class="mt-3" data-personnel-confirm="Vardiya durumu değiştirilsin mi? Eski atamalar korunacak.">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $shift->is_active ? 0 : 1 }}">
                        <x-ui.button variant="secondary">{{ $shift->is_active ? 'Pasife al' : 'Etkinleştir' }}</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('personnel.shifts.versions.store',$shift) }}" class="mt-4 space-y-3">
                        @csrf
                        @include('personnel::shifts._fields',['version'=>$versions->first(),'prefix'=>'version-'.$shift->id])
                        <p class="text-xs text-mut">Yeni sürüm, personele yeni yürürlük tarihiyle ayrıca atanmalıdır.</p>
                        <x-ui.button>Yeni sürüm oluştur</x-ui.button>
                    </form>
                @endif
            </details>
        @empty
            <p class="text-sm text-mut">Henüz vardiya tanımlanmamış.</p>
        @endforelse
        {{ $shifts->links() }}
    </x-ui.card>
    @if(data_get($permissions,'manage'))
        <x-ui.card title="Yeni vardiya">
            <form method="POST" action="{{ route('personnel.shifts.store') }}" class="space-y-3">
                @csrf
                <x-ui.input name="name" label="Vardiya adı" required maxlength="100" />
                @include('personnel::shifts._fields',['version'=>null,'prefix'=>'new-shift'])
                <x-ui.button>Vardiya oluştur</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
@endsection
