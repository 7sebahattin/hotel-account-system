{{--
    Masaüstü tablo kabuğu.

    Tablolar yalnızca `lg` (1024px) ve üzerinde gösterilir; altında liste ekranları
    kart görünümüne düşer. `md` (768px) kullanılsaydı 256px kenar çubuğundan sonra
    ~460px kalır ve 6-9 kolonluk tablo yatay kaydırmaya mahkûm olurdu.

    Kaydırma tek bir kapta yapılır: iç içe overflow kabı, taşmanın belge düzeyine
    sızmasına yol açıyordu.
--}}
<div {{ $attributes->class('hidden overflow-x-auto rounded-xl border border-line bg-card lg:block') }}>
    <table class="min-w-full text-sm">
        {{ $slot }}
    </table>
</div>
