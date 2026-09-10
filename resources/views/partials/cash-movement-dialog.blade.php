@can(\App\Modules\Authorization\Enums\Permission::CashBookOperate->value)
    {{--
        Hızlı İşlem'in "Kasa Hareketi" seçimi için TEK, boş kabuk (v2.1.1-B.1
        §2/§3). Kabuk her sayfada bir kez vardır (layouts.app) ama BOŞTUR —
        içerik yalnızca kullanıcı GERÇEKTEN tıkladığında `fetch()` ile
        yüklenir (bkz. app.js). Bu yüzden bu partial'ın kendisi hiçbir sorgu
        ÇALIŞTIRMAZ; global bir kasa/kategori sorgusu her sayfa yüklemesinde
        koşmaz (Lemon Bar ile AYNI performans ilkesi, CLAUDE.md §8/Dashboard).

        Native <dialog>: `showModal()` odağı içeri hapseder ve ESC/`close()`
        odağı tetikleyen elemana geri verir — bu, önceki mobil çekmece
        odak/inert hatasının (K-086/K-088) AYNI sınıfını burada TEKRAR
        AÇMADAN elde eder, çünkü tarayıcının kendi davranışıdır.

        Masaüstünde ortalanmış modal, mobilde alt sayfa (bottom sheet) —
        stil `resources/css/app.css`'teki `.cash-dialog` bileşen sınıfındadır.
    --}}
    <dialog id="kasa-hareketi-dialog" class="cash-dialog" aria-labelledby="kasa-hareketi-dialog-baslik">
        <div data-cash-dialog-content></div>
    </dialog>
@endcan
