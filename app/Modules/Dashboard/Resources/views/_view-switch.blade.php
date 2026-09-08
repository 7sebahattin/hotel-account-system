{{--
    Operasyon | Finans geçişi. Yalnızca Admin/Patron panosunda (`dashboard.financial`
    yetkisi olan) render edilir; personel bu geçişi hiç görmez — controller
    personeli bu view'lara hiç yönlendirmediği için ayrıca bir kontrol GEREKMEZ
    (kullanıcı talimatı §18).

    Server-rendered, sade bağlantılardır: URL/query parametresi ile çalışır
    (`?view=operations` / `?view=finance`), böylece bookmark ve geri tuşu
    doğal olarak çalışır — ayrı bir JS state makinesi gerekmez.

    Beklenen değişken: $active ('operations' | 'finance').
--}}
<nav aria-label="Pano görünümü" class="flex items-center gap-1 rounded-lg bg-well p-1">
    <a href="{{ route('home', ['view' => 'operations']) }}"
       @if ($active === 'operations') aria-current="page" @endif
       @class([
           'min-h-9 rounded-md px-3 py-1.5 text-sm transition-colors',
           'bg-card font-medium text-tx ring-1 ring-line' => $active === 'operations',
           'text-mut hover:text-tx' => $active !== 'operations',
       ])>Operasyon</a>

    <a href="{{ route('home', ['view' => 'finance']) }}"
       @if ($active === 'finance') aria-current="page" @endif
       @class([
           'min-h-9 rounded-md px-3 py-1.5 text-sm transition-colors',
           'bg-card font-medium text-tx ring-1 ring-line' => $active === 'finance',
           'text-mut hover:text-tx' => $active !== 'finance',
       ])>Finans</a>
</nav>
