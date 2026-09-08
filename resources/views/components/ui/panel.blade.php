@props(['title', 'href' => null, 'linkLabel' => 'Tümü'])

{{-- Başlıklı liste kartı: pano bölümleri aynı iskeleti kullansın diye. --}}
<section {{ $attributes->class('flex min-w-0 flex-col rounded-xl border border-line bg-card') }}>
    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <h3 class="text-sm font-semibold text-tx">{{ $title }}</h3>

        @if ($href)
            {{--
                Görsel metin xs kalsın diye küçük, ama `min-h-11` dokunma
                yüksekliğini garanti eder — satır çapraz ekseni içerik
                boyutuna göre belirlendiği için padding tahminine güvenilmez.
            --}}
            <a href="{{ $href }}"
               class="inline-flex min-h-11 shrink-0 items-center rounded px-2 text-xs text-acc-tx underline underline-offset-2 hover:text-tx">
                {{ $linkLabel }}
            </a>
        @endif
    </div>

    <div class="flex-1">{{ $slot }}</div>
</section>
