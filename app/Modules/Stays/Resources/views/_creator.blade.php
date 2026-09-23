{{-- Kaydı oluşturan kişi: baş harf rozeti + ad + işlem zamanı. --}}
@php
    $creatorName = $stay->creator?->name;
    $initials = '';

    if ($creatorName) {
        foreach (array_slice(preg_split('/\s+/u', trim($creatorName)) ?: [], 0, 2) as $part) {
            // Türkçe büyük harf: i → İ (mb_strtoupper 'I' üretirdi).
            $initials .= mb_strtoupper(str_replace('i', 'İ', mb_substr($part, 0, 1)));
        }
    }
@endphp

<span class="hidden size-8 shrink-0 items-center justify-center rounded-full bg-well min-[1340px]:flex text-xs font-semibold text-tx" title="{{ $creatorName }}" aria-hidden="true">{{ $initials !== '' ? $initials : '—' }}</span>
<span class="min-w-0 text-tx">
    {{-- Dar masaüstünde (1280–1340 px) ad ve rozet gizlenir, yalnız işlem zamanı görünür (ad ekran okuyucuya kalır). --}}
    <span class="sr-only min-[1340px]:not-sr-only min-[1340px]:block min-[1340px]:truncate">{{ $creatorName ?? '—' }}</span>
    <span class="block whitespace-nowrap text-xs tabular-nums text-mut">{{ $stay->created_at->format('d.m.Y · H:i') }}</span>
</span>
