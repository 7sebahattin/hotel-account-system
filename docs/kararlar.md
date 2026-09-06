# Mimari Kararlar

Her karar: bağlam → karar → gerekçe. Yeni karar sona eklenir, eskisi silinmez.

---

## K-001 · Laravel 13
**Bağlam:** Sıfırdan proje. Yerel PHP 8.4.19; cPanel'de PHP 8.3+ mevcut.
**Karar:** Laravel 13 (13.30.1).
**Gerekçe:** `spatie/laravel-permission` 8.x ve `maatwebsite/excel` 4.x güncel major
sürümleri Laravel 13'ü destekliyor — hiçbir paketi eski sürüme çakmak gerekmiyor.
En yeni major, en uzun destek ömrünü verir. Sıfırdan projede yeni major'ın maliyeti yok.
**Alternatif:** Laravel 12 (PHP 8.2 tabanı). cPanel 8.3+ desteklediği için gerekmedi.

## K-002 · Para aritmetiği: tamsayı kuruş, `bcmath` yok
**Bağlam:** Geliştirme ortamında `bcmath` ve `gmp` eklentileri yok.
**Karar:** `Core\Support\Money` tutarı tamsayı kuruş olarak tutar; toplama, çıkarma ve
karşılaştırma tam sayı aritmetiğiyle yapılır. Veritabanı alanı `DECIMAL(15,2)` kalır,
toplamlar SQL `SUM()` ile alınır.
**Gerekçe:** Aynı kesinliği sıfır eklenti bağımlılığıyla verir. `bcmath`'e bağlanmak,
hosting sağlayıcısının eklenti ayarına bağımlılık yaratır; eklenti kapanırsa uygulama çalışmaz.
**Sonuç:** CLAUDE.md §13 güncellendi.

## K-003 · Testler gerçek MySQL/MariaDB üzerinde koşar
**Bağlam:** Laravel varsayılanı SQLite `:memory:`.
**Karar:** `phpunit.xml` `hotel_account_test` (MySQL/MariaDB, `utf8mb4_turkish_ci`) kullanır.
**Gerekçe:** SQLite'ta gerçek `DECIMAL` yoktur (NUMERIC affinity → float), `SUM()` float döner.
Türkçe collation ve `RESTRICT` foreign key davranışı taklit edilemez. CLAUDE.md §12'deki
cari testleri ile "cari hareketi olan firma silinemez" kuralı ancak canlıyla aynı motorda anlam taşır.
**Doğrulama:** `utf8mb4_turkish_ci` altında `ı` = `I` ve `i` = `İ` eşleşiyor, `i` ≠ `I`.
`utf8mb4_general_ci` bunu yanlış yapıyor.
**Bedeli:** Test koşumu için çalışan bir MySQL sunucusu gerekir (CI'da servis olarak sağlanır).

## K-004 · Uygulama saat dilimi `Europe/Istanbul`
**Bağlam:** Laravel 13 `config/app.php` içinde timezone'u `'UTC'` sabit yazıyor.
**Karar:** `env('APP_TIMEZONE', 'UTC')` olarak değiştirildi; `.env` içinde `Europe/Istanbul`.
**Gerekçe:** Tek ülke, tek otel. Türkiye 2016'dan beri kalıcı UTC+3, yaz saati uygulaması yok —
belirsizlik doğmaz. Zaman damgaları veritabanında da personelin gördüğü saatle aynı okunur;
denetim kaydı incelenirken çevrim gerekmez.

## K-005 · Dış font servisi kullanılmıyor
**Bağlam:** Laravel 13 iskeleti `laravel-vite-plugin` ile derleme anında
`fonts.bunny.net`'ten Instrument Sans çekiyor.
**Karar:** Font eklentisi kaldırıldı; sistem font yığını kullanılıyor.
**Gerekçe:** Derleme anında dış servise bağlı olmak CI'ı ve cPanel deploy'unu ağ kesintisine
açık hale getirir (bu ortamda zaten 403 ile başarısız oldu). Kullanıcı tarayıcısından
üçüncü taraf CDN'e istek de gitmez.
**Geri alınabilir:** Belirli bir font istenirse, font dosyaları repoya konup yerelden servis edilir.

## K-006 · Modül autoload'u için ek PSR-4 girdisi yok
**Bağlam:** CLAUDE.md §4.1 `"App\\Modules\\": "app/Modules/"` girdisinden söz ediyordu.
**Karar:** Eklenmedi. Mevcut `"App\\": "app/"` eşlemesi `App\Modules\*` sınıflarını zaten kapsıyor.
**Gerekçe:** İkinci girdi gereksizdir ve Composer'da "ambiguous class resolution" uyarısı üretir.
**Sonuç:** CLAUDE.md §4.1 güncellendi.

## K-007 · İskelet temizliği
**Karar:** `laravel/laravel` deposuna ait ve uygulamayla ilgisi olmayan dosyalar silindi:
`CHANGELOG.md`, `.styleci.yml`, `.github/dependabot.yml` ve laravel/laravel'in kendi
workflow'ları. İskeletin `CLAUDE.md` / `AGENTS.md` dosyaları projeye ait içerikle değiştirildi.
**Gerekçe:** Bunlar framework deposunun bakım dosyaları; bu projede yanlış yönlendirme yapar.
StyleCI yerine Pint kullanılıyor.

## K-008 · Boş modül klasörü açılmıyor
**Karar:** V1'in 11 modülünden şu an yalnızca `Core` ve `Auth` mevcut. Diğerleri kendi
fazları geldiğinde oluşturulur.
**Gerekçe:** CLAUDE.md §3.3 ve §4.1 — kullanılmayan klasör/dosya açmak yasak.
`Core` şu anda yalnızca `ModuleServiceProvider` taban sınıfını barındırır.
