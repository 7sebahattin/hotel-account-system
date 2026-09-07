@extends('layouts.app')
@section('title', $stay->guest_name.' — konaklama')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">{{ $stay->guest_name }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $stay->company?->name ?? '—' }}
                @if ($stay->room_no) · Oda {{ $stay->room_no }} @endif
            </p>
            <div class="mt-2">@include('stays::_status', ['stay' => $stay])</div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @can('update', $stay)
                <a href="{{ route('stays.edit', $stay) }}"
                   class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                    Düzenle
                </a>
            @endcan

            @can('cancel', $stay)
                <form method="POST" action="{{ route('stays.cancel', $stay) }}"
                      onsubmit="return confirm('Bu konaklama iptal edilsin mi? Cari hareketi ters kaydedilecek ve bu işlem geri alınamayacak.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="min-h-11 px-2 text-sm font-medium text-red-700 hover:underline">İptal et</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($stay->isCancelled())
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Bu konaklama iptal edilmiştir. Cari hareketi ters kaydedilmiştir ve kayıt düzenlenemez.
        </div>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-700">Konaklama bilgileri</h2>

            <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Giriş</dt>
                    <dd class="mt-0.5 text-sm">{{ $stay->check_in_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Çıkış</dt>
                    <dd class="mt-0.5 text-sm">{{ $stay->check_out_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Gece</dt>
                    <dd class="mt-0.5 text-sm tabular-nums">{{ $stay->nights }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Tutar</dt>
                    <dd class="mt-0.5 text-sm font-medium tabular-nums">
                        {{ $stay->amountMoney()->format($stay->currency) }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Notlar</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm">{{ $stay->notes ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-sm font-semibold text-slate-700">Kayıt bilgisi</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Oluşturan</dt>
                    <dd class="mt-0.5">{{ $stay->creator?->name ?? '—' }}
                        <span class="text-slate-500">· {{ $stay->created_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Son güncelleyen</dt>
                    <dd class="mt-0.5">{{ $stay->updater?->name ?? '—' }}
                        <span class="text-slate-500">· {{ $stay->updated_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
            </dl>

            @if ($stay->company)
                <a href="{{ route('companies.show', $stay->company) }}"
                   class="mt-6 inline-flex min-h-11 items-center text-sm font-medium text-slate-700 hover:underline">
                    Firma kartına git →
                </a>
            @endif
        </div>
    </div>
@endsection
