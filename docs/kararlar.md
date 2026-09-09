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

## K-009 · AuditLog Gün 11'den Gün 2'ye alındı
**Bağlam:** CLAUDE.md §8 başarısız girişlerin loglanmasını istiyordu ama §14 AuditLog'u
Gün 11'e koyuyordu; yani Gün 2–10 arasındaki tüm giriş ve yetki değişiklikleri kayıtsız kalacaktı.
**Karar:** AuditLog altyapısı Users/Roles ile aynı fazda kuruldu.
**Gerekçe:** Denetim kaydını sonradan eklemek geçmiş veriyi kurtarmaz. Maliyeti bir tablo,
bir servis ve bir trait.

## K-010 · `Gate::before` yalnızca argümansız yetki kontrollerinde kısa devre yapar
**Bağlam:** CLAUDE.md §7.1 "admin her yetkiyi geçer" diyordu. Bunun düz uygulaması
admin için **tüm Policy'leri** devre dışı bıraktı: test sırasında admin kendi hesabını
silebildi ve sistem rolünü silme çağrısı Policy'yi atladı.
**Karar:** `Gate::before` yalnızca argümansız yetki kontrollerini (ör. `can:users.manage`)
kısa devre yapar. Model/kayıt argümanı taşıyan Policy yetenekleri normal akışta değerlendirilir.
Denetim kaydının değiştirilmesi/silinmesi kesin `false` döner.
**Ek kural:** Erişim kaybettirebilecek iş kuralları Policy'ye ek olarak Service katmanında
da uygulanır (`SelfActionException`, `LastActiveAdminException`). Policy arayüzü ve HTTP'yi
korur; Service son savunmadır.
**Gerekçe:** Blanket `Gate::before` sessiz bir güvenlik açığıdır — en yetkili rolde en az
koruma sağlar. İki test bunu somut olarak yakaladı.
**Sonuç:** CLAUDE.md §7.1 güncellendi.

## K-011 · Bir kullanıcı tek role sahiptir
**Karar:** `UserService::assignRole()` `syncRoles([$role])` kullanır; rol değişimi eskisini kaldırır.
**Gerekçe:** 20'den az kullanıcılı bir işletmede çoklu rol birleşimi yetki matrisini
anlaşılmaz kılar. Admin gerektiğinde özel rol tanımlayabilir.
**Geri alınabilir:** Çoklu rol gerekirse `syncRoles` → `assignRole` yeterli; şema değişmez.

## K-012 · Denetim kaydı üç katmanda korunur
**Karar:** (1) `audit_logs` tablosunda `updated_at` yok; (2) `AuditLog` modeli `update()` ve
`delete()` çağrılarında exception fırlatır; (3) Policy ve `Gate::before` admin dahil herkese
`false` döner; (4) yazma yapan hiçbir HTTP ucu yoktur.
**Gerekçe:** CLAUDE.md'nin en katı kuralı; tek bir katmana güvenilmez. Dördü de testle doğrulandı.

## K-013 · Seed verisi ortama göre değişir
**Karar:** Rol/yetki matrisi her ortamda seed edilir. Admin hesabı `.env` üzerinden gelir.
`patron@example.com` ve `personel@example.com` örnek hesapları **yalnızca production dışında** oluşturulur.
**Gerekçe:** Canlıda varsayılan şifreli demo hesap bırakmak açık kapıdır.

## K-014 · Deploy SSH'ya bağlı değil; FTPS + GitHub Actions
**Karar:** Derleme GitHub Actions runner'ında, aktarım FTPS ile. `.env` ve `storage/app/`
deploy tarafından hiç dokunulmaz. Ayrıntı: `docs/deploy-github-actions.md`.
**Açık kalan:** SSH olmadan `migrate --force` nasıl tetiklenecek? cPanel cron + işaret dosyası
(önerilen) veya token korumalı HTTP ucu. Karar verilmeden workflow etkinleştirilmeyecek.

## K-015 · Session-start hook yalnızca yerel ortamı hazırlar
**Karar:** `.claude/hooks/session-start.sh` MariaDB'yi kurar/başlatır, test veritabanlarını
Türkçe collation ile oluşturur, composer/npm bağımlılıklarını ve frontend derlemesini kontrol eder,
migration'ları uygular.
**Sınır:** Hook hiçbir production veya hosting işlemi yapmaz — deploy yok, uzak veritabanı yok,
dış servis çağrısı yok. Yalnızca `CLAUDE_CODE_REMOTE=true` iken çalışır.

## K-016 · SSH'sız migration: cPanel cron + işaret dosyası
**Karar:** Deploy `storage/app/deploy-pending` işaret dosyası bırakır; cPanel cron bunu
görünce `migrate --force` çalıştırır, sonucu loglar, başarılıysa dosyayı siler, başarısızsa korur.
**Reddedilen:** Token korumalı HTTP migrate ucu — internete açık, migration çalıştırabilen bir uç,
token sızarsa veritabanı şemasının uzaktan değiştirilmesine izin verir.
**Durum:** Yalnızca karar. `finalize.php` ve deploy workflow'u deploy fazına kadar yazılmayacak.

## K-017 · Türkçe aramada iki i sorunu
**Bağlam:** Türkçe'de büyük harfe çevirim çift yönlüdür (i↔İ, ı↔I) ve firma verisi çoğunlukla
tümü büyük harfle girilir. Ölçüm sonuçları:

| Arama | `turkish_ci` | `unicode_ci` | `uca1400_ai_ci` |
|---|:--:|:--:|:--:|
| "ilgaz" → `ILGAZ` kaydı | ✗ | ✓ | ✓ |
| "çınar" → `ÇINAR` kaydı | ✓ | ✗ | ✗ |

**Karar:** Karşılaştırmadan önce her iki tarafta noktalı/noktasız i tek karaktere indirgenir:
`REPLACE(REPLACE(col,'ı','i'),'İ','i') COLLATE utf8mb4_unicode_ci LIKE ?`; terim PHP tarafında
`str_replace(['ı','İ'], 'i', ...)` ile normalize edilir.
**Sıralama etkilenmez:** `ORDER BY` kolonun `utf8mb4_turkish_ci` collation'ını kullanmaya devam eder.
**Maliyet:** İfade indeks kullanmaz — ancak `LIKE '%...%'` zaten kullanmıyordu, ek kayıp yok.
**Sonuç:** CLAUDE.md §9 güncellendi; iki yönü de doğrulayan testler yazıldı.

## K-018 · `companies.restore` ayrı bir yetkidir
**Karar:** Silme ve geri alma ayrıldı. Admin her ikisine, Patron yalnızca silmeye sahiptir.
Silinmiş firmaları listeleme de `companies.restore` yetkisine bağlıdır — Personel ve Patron
silinmiş kayıtları görmez.
**Gerekçe:** Kullanıcı kararı (Patron silebilir, geri alamaz). Rol adı yerine yetki kullanmak
CLAUDE.md §7.2 isimlendirmesine uyar ve Admin'in ileride bu yetkiyi özel bir role vermesini mümkün kılar.
**Sonuç:** CLAUDE.md §7.2 ve §7.3 güncellendi (yetki sayısı 19 → 20).

## K-019 · PHPStan level 5, baseline yok
**Karar:** `larastan/larastan` v3.11.0 + `phpstan/phpstan` 2.2.13, level 5, CI'da zorunlu adım.
İlk çalıştırmada 38 bulgu çıktı; hepsi kaynağından düzeltildi — baseline dosyası veya
`@phpstan-ignore` yorumu kullanılmadı.
**Düzeltilenler:** model kolonları için `@property` blokları, spatie `Contracts\Role` yerine proje
modeli tipi (`Role::byName()`, `Role::query()->create()`), trait closure'larında `self` tipi,
`registerModelEvent('restored')`, `env()` çağrılarının `config/hotel.php`'ye taşınması,
`??` solunda gereksiz `?->` kullanımı.

## K-020 · Bu container'da bazı composer paketleri git ile getirilir
**Bağlam:** Claude Code on the web ortamında GitHub zipball indirmeleri (api.github.com, codeload)
proxy tarafından 403 ile engellenir; `git clone` çalışır. `phpstan/phpstan` paketinin packagist'te
**source tanımı yoktur**, yalnızca zipball vardır — bu yüzden `composer install` bu ortamda tamamlanamaz.
**Karar:** `.claude/hooks/fetch-blocked-packages.php`, composer.lock'taki sürümü git ile getirip
`vendor/composer/installed.json`'a kaydeder. Yalnızca `vendor/` dizinini değiştirir; `composer.json`
ve `composer.lock` dokunulmaz.
**Kapsam:** Ortama özgü onarım. CI ve geliştirici makinelerinde normal `composer install` çalışır,
script hiçbir şey yapmaz.

## K-021 · Şifre değişikliği denetim kaydında görünür, değer görünmez
**Bağlam:** Maskelenen alanlar denetim kaydından tamamen çıkarılıyordu; bu durumda admin bir
kullanıcının şifresini değiştirdiğinde denetim kaydı "güncellendi" diyor ama neyin değiştiğini göstermiyordu.
**Karar:** Maskeleme artık **anahtarı korur, değeri `***` ile değiştirir**. Böylece "şifre değiştirildi"
bilgisi denetlenebilir; şifrenin kendisi veya hash'i hiçbir zaman yazılmaz.
**Kapsam:** `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`.
E-posta ile şifre sıfırlama V1'de yoktur; admin kullanıcı düzenleme ekranından yeni şifre belirler.

## K-022 · Denetim anlık görüntüsünde uzunluk sınırı
**Karar:** Denetim kaydına yazılan metin değerleri 500 karakterle sınırlıdır; aşan değerin sonuna
`… (kısaltıldı)` eklenir.
**Sınır yalnızca denetim kaydına uygulanır** — gerçek `companies.notes` kaydı tam uzunlukta saklanır.
**Gerekçe:** `notes` gibi uzun alanlar `audit_logs` tablosunu kontrolsüz büyütür.

## K-023 · Demo hesaplar yalnızca local ve testing ortamında
**Karar:** `patron@example.com` / `personel@example.com` hesapları yalnızca
`app()->environment(['local','testing'])` iken oluşturulur. Staging ve production'da
oluşturulmaz. Admin hesabı `config('hotel.seed.*')` üzerinden gelir.
**Değişiklik:** Önceki koşul yalnızca production'ı dışlıyordu; staging'de demo hesap oluşabilirdi.

## K-024 · Cari hareketi olan firmanın silinememesi ertelendi
**Durum:** CLAUDE.md §5.2 bu kuralı gerektirir ancak `ledger_entries` tablosu henüz yoktur.
**Karar:** Kural Gün 4'te `CompanyService::delete()` içinde `LedgerService` üzerinden uygulanacaktır.
Bugün taklit bir kontrol yazılmamıştır — sahte kontrol, gerçek koruma varmış izlenimi verirdi.
Karar `CompanyService` sınıf yorumunda da kayıtlıdır.

## K-025 · `Money`: tamsayı kuruş, sıfır bağımlılık
**Karar:** `Core\Support\Money` tutarı `int` kuruş olarak tutar. Ayrıştırma metin üzerinden
(regex) yapılır, biçimlendirme tam sayı bölmesiyle — **hiçbir adımda kayan nokta yoktur**.
`DECIMAL(15,2)` sınırı kuruş cinsinden 999.999.999.999.999'dur ve 64 bit tam sayıya sığar.
**Sonuç:** `bcmath` eklentisine ihtiyaç yok (K-002). 22 birim testi, 0.1+0.2 sapması dahil doğruluyor.

## K-026 · Ledger dört katmanda korunur
**Karar:** (1) `CHECK` kısıtları — negatif tutar ve iki yönün aynı anda dolu/boş olması engellenir;
(2) `UNIQUE (reverses_entry_id)` — ikinci ters kayıt imkânsız; (3) `BEFORE UPDATE`/`BEFORE DELETE`
tetikleyicileri — ham SQL ve query builder toplu güncellemesi dahil her yazma reddedilir;
(4) model olayları ve `LedgerService`.
**Gerekçe:** Uygulama katmanı Eloquent'ten geçmeyen yazmaları göremez. Finansal defterde
"kod düzgün yazılırsa güvenli" yeterli değildir.
**Dağıtım riski:** Tetikleyiciler TRIGGER yetkisi gerektirir. Yetki yoksa migration hata verir ve
deploy durur — sessizce atlanmaz. `docs/deploy-cpanel.md` §1'e eklendi.

## K-027 · Ters kayıt orijinalin tarihini taşır
**Karar:** Ters kayıt orijinalin `entry_date` değerini kullanır; iki kayıt her tarih aralığında
birbirini götürür.
**Bilinen etki:** Geçmiş bir dönemin bakiyesi geriye dönük değişir. Örneğin Mart'ta girilen bir
konaklama Mayıs'ta iptal edilirse Mart bakiyesi de değişir.
**Neden şimdilik böyle:** Kullanıcı "tam tersini üretmeli" dedi ve V1'de dönem kilidi yoktur.
**Yeniden değerlendirilecek:** Cari ekstre (Gün 8) ve dönem kapatma geldiğinde. `reverse()`
metodunun açıklama parametresi gibi bir tarih parametresi eklemek tek satırlık iştir.

## K-028 · Ledger hiçbir modülü tanımaz
**Karar:** `LedgerService` firma kimliğini `int $companyId` olarak alır, `Company` modelini
kullanmaz. Kaynak kayıt `?string $sourceType, ?int $sourceId` olarak geçirilir — `Stay`/`Payment`
modellerine bağımlılık yoktur.
**Gerekçe:** CLAUDE.md §4.3/3. Bağımlılık yönü tek yönlüdür: Companies → Ledger, Stays → Ledger,
Payments → Ledger. Ters yön yoktur.

## K-029 · Açılış bakiyesi için ayrı tablo yok
**Karar:** `source_type` serbest metindir; Excel göçü `'opening'` değerini teknik olarak
kullanabilir. Ayrı `opening_balances` tablosu **açılmadı** (CLAUDE.md §3.3).
**Durum:** Yalnızca teknik imkân; arayüz ve göç kodu yazılmadı. Testle doğrulandı.

## K-030 · Firma silme koruması iki katmanda
**Karar:** Cari hareketi olan firma silinemez. Asıl koruma `CompanyService::delete()` içindedir
(HTTP dışı çağrı yollarını da kapsar); `CompanyPolicy::delete()` aynı kuralı arayüzde butonu
gizlemek ve isteği erken reddetmek için tekrarlar.
**Gerekçe:** K-010 — `Gate::before` admin için Policy'leri kısa devre yapabilir; tek katman yetmez.
**Kapsam:** Bakiye sıfır olsa bile (ters kayıtla kapatılmış) hareket geçmişi varsa silme yasaktır.
Kullanımdan kaldırmak için doğru işlem `is_active = false`.

## K-031 · Aynı gün giriş/çıkış kabul edilir (0 gece)
**Çelişki:** Kullanıcının Gün 5 kuralları `check_out_date >= check_in_date` ve
`nights = check_out - check_in` diyor; CLAUDE.md §8 ise "0 gece kabul edilmez (min 1)" diyordu.
**Karar:** Kullanıcının daha yeni ve açık talimatı uygulandı — aynı gün giriş/çıkış geçerlidir
ve 0 gece üretir (günübirlik kullanım). Tutar yine `> 0` olmak zorundadır, dolayısıyla
günübirlik kayıt da cariye normal şekilde işlenir.
**Risk:** Aynı tarihi iki kez yazan bir kullanıcı hatası 0 geceli kayıt üretir ve engellenmez.
Günübirlik kullanım istenmiyorsa `check_out_date` kuralı `after:check_in_date` yapılarak
tek satırda geri alınır.
**Sonuç:** CLAUDE.md §8 güncellendi.

## K-032 · İptal `status` ile, soft delete ile değil
**Karar:** Konaklama iptali `status = cancelled` alanıyla yapılır. `deleted_at` kolonu şemada
durur ama iptal akışında kullanılmaz; görevi yalnızca kazara yapılacak bir `delete()` çağrısının
fiziksel silme olmasını engellemektir.
**Gerekçe:** İptal edilmiş konaklama listede görünmeye devam etmeli ve "Durum" filtresiyle
ayrılabilmelidir; soft delete onu varsayılan sorgulardan tamamen düşürürdü.
**V1'de iptal geri alınamaz:** restore route'u, servis metodu veya arayüzü yoktur — testle doğrulandı.

## K-033 · Stay denetim kaydı serviste yazılır, trait ile değil
**Karar:** `Stay` modeli `Auditable` trait'ini KULLANMAZ; denetim kayıtları `StayService`
tarafından açıkça yazılır.
**Gerekçe:** Finansal düzeltmenin denetim satırı `reversed_ledger_entry_id`,
`reversal_ledger_entry_id` ve `new_ledger_entry_id` referanslarını taşımak zorundadır. Bu
kimlikler ancak ters kayıt ve yeni kayıt oluştuktan SONRA bilinir; model olayları ise
`save()` anında tetiklenir. Trait ile bunu ifade etmek ya iki ayrı denetim satırı ya da
modele geçici durum taşıyan bir hile gerektirirdi.
**Sonuç:** CLAUDE.md §8 (Basic Audit Log) güncellendi.

## K-034 · Konaklama "silme" yerine "iptal"
**Karar:** Arayüzde ve route'ta silme yoktur; `PATCH /stays/{stay}/cancel` vardır. Yetki adı
CLAUDE.md §7.2'deki `stays.delete` olarak KALIR (yeni yetki eklenmedi); policy yeteneği `cancel`
adını taşır ve bu yetkiye bağlıdır.
**Gerekçe:** Yetki matrisini değiştirmeden domain dilini doğru kullanmak. Admin ve Patron iptal
edebilir, Personel edemez — mevcut matrisle birebir örtüşür.

## K-035 · Personel finansal alanları değiştirebilir
**Karar:** Personel kendi kaydını ve düzenleme penceresi içinde, **finansal alanlar dahil**
değiştirebilir. Bu değişiklik ters kayıt + yeni hareket üretir.
**Uygulama yeri:** Kısıt frontend'de değil `StayPolicy` ve `StayService` katmanındadır;
`nights` ve `status` alanları kullanıcı girdisinden hiçbir koşulda alınmaz.

## K-036 · Tek tema, tek token kaynağı
**Karar:** Koyu tema uygulamanın tek temasıdır. Light mode, tema değiştirici ve kullanıcı
tema tercihi yoktur; `prefers-color-scheme` sorgusu hiçbir yerde kullanılmaz.
Tüm renk/tipografi token'ları yalnızca `resources/css/app.css` içindeki `@theme` bloğunda
tanımlıdır ve Tailwind bunlardan yardımcı sınıfları (`bg-card`, `border-line`, `text-mut`…)
üretir. Blade dosyalarında ham hex kodu yazılmaz.
**Gerekçe:** Tek kaynak, palet değişiminde tek dosya düzenlemesi demektir; testle de
doğrulanmıştır (`DesignSystemTest::tek_tema_kullanilir`).

## K-037 · `--color-field`: form sınırları için ayrı token (WCAG 1.4.11)
**Sorun:** Belirlenen palette hiçbir form kontrolü sınırı 3:1 kontrast eşiğini geçmiyordu —
`line`/`card` 1.41:1, `line`/`well` 1.81:1, dolgu farkı `well`/`card` 1.28:1. WCAG 2.1
1.4.11 arayüz bileşeni sınırları için en az 3:1 ister; input'un nerede başladığı görünmüyordu.
**Karar:** `--color-field: #64748b` eklendi ve **yalnızca** form kontrolü (`input`, `select`,
`textarea`, `checkbox`), ikincil buton ve sayfalama bağlantısı sınırlarında kullanılır.
Kullanıcının verdiği `--color-line` değeri **değiştirilmedi**; ayraç ve kart sınırı olarak
aynen kalır.
**Ölçüm:** Tüm metin çiftleri ≥ 4.5:1, tüm arayüz sınırları ≥ 3:1 (headless Chromium ile
hesaplandı).

## K-038 · Tablo kırılımı `md` değil `lg`
**Karar:** Veri tabloları 1024px (`lg`) ve üzerinde gösterilir; altında kart listesine düşer.
**Gerekçe:** 768px'te 256px kenar çubuğundan sonra içeriğe ~460px kalıyor ve tablo sayfayı
yatay kaydırmaya zorluyordu (ölçüldü: `window.scrollX = 285`). `lg` kırılımıyla 375/768/1366/1920
kırılımlarının tamamında yatay sayfa kaydırması sıfırdır.
**Sonuç:** CLAUDE.md §11.5 güncellendi ("tablolar `md` altında karta düşer" → `lg`).

## K-039 · Alpine.js yerine sade JavaScript
**Karar:** Kenar çubuğu çekmecesi ~40 satır sade JavaScript ile yazıldı; Alpine.js **kurulmadı**.
**Gerekçe:** Kullanıcı "yeni npm/UI framework paketi ekleme" dedi ve Alpine projede zaten kurulu
değildi. Tek etkileşim gereksinimi bir çekmece aç/kapa idi; bunun için paket eklemek gerekmiyor.
**İleride:** Modal, tutar maskesi veya dinamik firma arama kutusu gerektiğinde Alpine kullanıcı
onayıyla eklenir (CLAUDE.md §2 yığında duruyor).

## K-040 · Görsel doğrulama araçları proje dışında
**Karar:** Ekran görüntüsü, kontrast ve taşma doğrulaması için `playwright-core` proje
`package.json`'ına **eklenmedi**; geçici bir dizine kurulup ortamda hazır bulunan Chromium
sürücülendi.
**Gerekçe:** Doğrulama aracı uygulamanın bağımlılığı değildir; `npm ci` süresini ve deploy
yükünü artırmamalıdır.

## K-041 · Konaklama ücreti iki modlu: toplam veya günlük
**Karar:** `stays.pricing_mode` (`total` | `daily`) ve `stays.daily_rate` eklendi.
`amount` her iki modda da NİHAİ TOPLAM'dır ve cariye yazılan değer odur; `daily`
modunda `amount = nights × daily_rate` olarak **sunucuda** hesaplanır.
**Güvenlik (iki katman):** İstek gövdesine sahte bir `amount` yazılsa bile kullanılmaz.
1. `StayRequest` içinde `exclude_if:pricing_mode,daily` — alan doğrulanmış veriden düşer,
   servise hiç ulaşmaz.
2. `StayService::resolvePricing()` tutarı her koşulda yeniden hesaplar — servis doğrudan
   çağrılsa (import, konsol) bile kural geçerlidir.
Testle doğrulandı: HTTP üzerinden sahte tutar ve doğrudan servis çağrısı, ikisi de ezilir.
**Hesap:** `Money::multiply(int)` eklendi; çarpım tamsayı kuruş üzerinden yapılır, float yok.

## K-042 · 0 gece kararı geri alındı — asgari 1 gece
**Önceki karar (K-031):** Aynı gün giriş/çıkış kabul edilir, 0 gece üretirdi.
**Yeni karar:** `check_out_date > check_in_date`; asgari gece sayısı 1'dir.
**Gerekçe:** Günübirlik (day-use) kullanım ayrı bir iş kuralıdır ve V1'de PMS kapsamına
girilmiyor (CLAUDE.md §3.2). Ayrıca günlük ücretlendirmede 0 gece, sıfır tutarlı bir cari
hareketi üretirdi — `CHECK (amount > 0)` ile çelişirdi.
**Uygulama yerleri:** validasyon `after:check_in_date`, veritabanında `stays_date_range`
(`>` olarak güncellendi) ve yeni `stays_nights_positive` CHECK kısıtı.
**Geri alma:** Migration'ın `down()` metodu eski `>=` kısıtını geri koyar.
**Not:** Tabloda 0 geceli kayıt varsa migration hata verir — bu bilinçlidir, veri sessizce
kabul edilmez.

## K-043 · Türkçe doğrulama mesajları (bulunan hata)
**Sorun:** `APP_LOCALE=tr` ve `APP_FALLBACK_LOCALE=tr` olmasına rağmen `lang/tr/validation.php`
yoktu. FormRequest'in `messages()` metodunda ezilmeyen her kural kullanıcıya ham çeviri
anahtarı olarak görünüyordu: "validation.required", "validation.max.string".
Bu, bu turdan önce de var olan bir hataydı; yeni zorunlu `pricing_mode` alanı görünür kıldı.
**Karar:** `lang/tr/validation.php` eklendi — yalnızca projede fiilen kullanılan kurallar.
Alan adları (`:attribute`) her FormRequest'in kendi `attributes()` metodundan gelmeye devam
eder; dosyada `attributes` listesi tutulmaz (tek kaynak ilkesi).

## K-044 · Tahsilatta finansal alan değişmezliği üç katmanlı
**Karar:** `company_id`, `payment_date`, `amount`, `currency`, `payment_method` oluşturulduktan
sonra hiçbir rol tarafından değiştirilemez. Düzeltme yolu tektir: iptal + yeniden giriş.
**Katmanlar** (hiçbiri tek başına yeterli sayılmadı):
1. `UpdatePaymentRequest` bu alanları hiç tanımaz — `validated()` onları döndürmez, servise ulaşmazlar.
2. `PaymentService::updateMetadata()` gelen veride farklı bir finansal değer görürse
   `PaymentFinancialFieldsAreImmutable` fırlatır (servis doğrudan çağrılsa bile).
3. `Payment::booted()` içindeki `updating` dinleyicisi aynı kuralı zorlar — `$payment->update()`
   çağrısı bile reddedilir.
**Gerekçe:** HTTP/UI seviyesinde gizlemek yeterli değildir; ileride Excel import kodu ve konsol
komutları servisi doğrudan çağıracak. Üç katman da ayrı ayrı testlidir.
**Not:** Aynı değerin tekrar gönderilmesi (form gizli alanı) sessizce kabul edilir; yalnızca
GERÇEK farklılık reddedilir. Çözümlenemeyen bir girdi (bozuk tutar) "değişiklik yok" sayılmaz,
reddedilir.

## K-045 · `payment_method` de değişmezdir
**Karar:** Ödeme yöntemi finansal alanlar arasındadır ve değiştirilemez.
**Gerekçe ve karşı görüş:** Bu, operasyon açısından katıdır — "havale" yerine yanlışlıkla
"nakit" seçildiğinde, tutarı doğru olan bir tahsilatın iptal edilip yeniden girilmesi gerekir
ve cari defterde iki gereksiz satır oluşur. Buna rağmen değişmez tutuldu: ödeme yöntemi ileride
kasa/banka/POS hesaplarına bağlanacak alandır (§3.2'de bu modüller kapsam dışı); bugün serbestçe
değiştirilebilir olması, o modüller geldiğinde mutabakatı bozan bir geçmiş bırakır.
**Gevşetme yolu:** Tek satır — `Payment::IMMUTABLE_FIELDS` listesinden çıkarılıp
`EDITABLE_FIELDS`'a alınması ve `UpdatePaymentRequest`'e eklenmesi yeterlidir.

## K-046 · Metadata düzeltmesi için yeni yetki açılmadı
**Karar:** `payments.edit/update` route'ları `payments.create` yetkisine bağlıdır.
**Gerekçe:** Tahsilat girebilen herkes kendi girdiği kaydın referans numarasını
düzeltebilmelidir; matriste "girebilen ama düzeltemeyen" bir rol yoktur. Kayıt bazlı kısıt
(kendi kaydı + süre penceresi) `PaymentPolicy::update`'tedir. Konaklama iptalinin
`stays.delete`'e bağlanmasıyla (K-034) aynı yaklaşım.

## K-047 · Personel tüm tahsilatları görür (matris değişikliği)
**Önceki (CLAUDE.md §7.3):** `payments.view` Personel için "⚠️ kendi tahsilatları".
**Yeni karar:** Personel tahsilat listesinin TAMAMINI görür.
**Gerekçe:** Kullanıcının Gün 7 talimatı Personel yetkilerini tek tek saydı ve görüntülemede
kısıt belirtmedi ("tahsilatları görür"), buna karşılık düzenleme kısıtını ayrıntısıyla yazdı.
Konaklamalarda da Personel tüm kayıtları görüyor; tahsilatta farklı davranmak tutarsız olurdu.
**Etkisi:** `PaymentFilter`'da `created_by` daraltması YOKTUR. Personel yine bakiye, toplam
alacak ve finansal dashboard kartlarını göremez (§7.4 değişmedi).
**Geri alma:** `PaymentPolicy::viewAny` ve controller sorgusuna tek bir `created_by` koşulu
eklemek yeterlidir.

## K-048 · Türkçe biçimli tutar girdisi kabul edilir (bulunan hata)
**Sorun:** Uygulama tutarları `5.000,50 ₺` diye GÖSTERİYOR ama form aynı biçimi geri kabul
etmiyordu: `prepareForValidation` yalnızca virgülü noktaya çeviriyor, binlik ayırıcı noktayı
bırakıyordu → `5.000.50` → doğrulama hatası. Gün 5'ten beri Stays'te de vardı.
**Karar:** `Money::normalizeInput()` Core'a eklendi ve hem `StayRequest` hem
`StorePaymentRequest` bunu kullanıyor (CLAUDE.md §4.3/4 — tekrar eden şey Core'a taşınır).
**Kural:** Nokta yalnızca virgül de varsa binlik ayırıcı sayılıp atılır. Aksi hâlde nokta
ondalık ayırıcıdır — `5.50` yazan kullanıcının beş buçuk lirası sessizce 550 liraya
dönüşmemelidir. Virgülsüz `5.000` bilinçli olarak çözümlenmez; doğrulama reddeder ve
kullanıcı düzeltir. Para tutarında tahmin etmek kabul edilemez.

## K-049 · `cheque` (Çek) ödeme yöntemi eklendi
**Karar:** `PaymentMethod` enum'una `cheque = 'Çek'` eklendi. DB CHECK kısıtı, oluşturma
migration'ı GERİYE DÖNÜK DEĞİŞTİRİLMEDEN, ileri bir migration ile güncellendi (CLAUDE.md §15).
**Not:** Migration'ın `down()` metodu, halihazırda `cheque` taşıyan satır varsa başarısız olur.
Bu bilinçlidir — veri sessizce kaybedilmez, önce dönüştürülmesi gerekir.

## K-050 · Ekstre ekranları Companies modülünde, hesap Ledger'da
**Sorun:** Cari ekstre firma adını göstermek zorunda; ancak §4.3/3 "Ledger hiçbir modülü
tanımaz" diyor. Ekranı Ledger'a koymak bu kuralı kırardı.
**Karar:** Controller, route ve view'lar `Companies` modülünde; hesabın tamamı
`Ledger\Services\LedgerStatementService`'te. Servis firmayı yalnızca `int $companyId`
olarak bilir ve hiçbir modülü tanımaz.
**Sonuç:** Ledger çekirdeği bağımlılıksız kalır; Companies zaten Stays ve Payments
servislerine bağımlıydı, Ledger da aynı kapıdan eklendi.

## K-051 · Filtreler ikiye ayrılır: bakiye evreni vs. gösterim
**Sorun:** Kullanıcı "yalnızca tahsilatlar" filtrelerse, yürüyen bakiye kolonu ne göstermeli?
Filtrelenmiş satırlar üzerinden hesaplanan bir bakiye gerçek bakiye DEĞİLDİR ve kullanıcıyı
yanıltır.
**Karar:** İki filtre türü ayrıldı:
- `date_from`/`date_to`/`currency` → bakiyenin EVRENİ. SQL'de uygulanır, devreden bakiye
  bunlara göre hesaplanır. Defteri temiz biçimde bölerler.
- `type`/`q` → yalnızca GÖSTERİM. Yürüyen bakiye hesaplandıktan SONRA PHP'de uygulanır.
  Görünen her satır, defterin tamamı üzerinden yürüyen GERÇEK bakiyesini taşır.
**Ekranda:** Gösterim filtresi etkinken bir bilgi kutusu bunu açıkça söyler.
**Performans:** Ağır filtre (tarih + para birimi) SQL'dedir; PHP'ye yalnızca aralık içindeki
satırlar gelir.

## K-052 · Ekstre sayfalanmaz
**Karar:** Cari ekstrede sayfalama yoktur.
**Gerekçe:** Ekstre bütün olarak okunan ve yazdırılan bir belgedir; sayfa 2'nin başında
devreden bakiye göstermek gerekir ve bu, kullanıcı sıralamayı ters çevirdiğinde anlamsızlaşır.
Veri hacmi de buna izin veriyor (CLAUDE.md §1: yılda ~binlerce konaklama, firma başına çok
daha az). Tarih filtresi büyük dönemleri bölmek için yeterlidir.
**Yeniden değerlendirme:** Bir firmanın tek aralıktaki hareket sayısı binleri bulursa,
sayfalama yerine önce zorunlu tarih aralığı düşünülmelidir.

## K-053 · Ekstre görüntüleme denetim kaydı üretmez
**Karar:** Ekstre sayfası her açılışında `audit_logs`'a yazılmaz.
**Gerekçe:** Salt okuma olayını loglamak, gerçek değişiklikleri gürültü içinde boğar.
`exported` olayı Excel export geldiğinde (Gün 10) kullanılacaktır. Yazdırma tarayıcı
işlevidir; sunucu tarafında ayrı bir olay yoktur.

## K-054 · Yazdırma görünümü Vite çıktısını yüklemez
**Karar:** `layouts/print.blade.php` kendi kendine yeten sade CSS taşır; uygulamanın
Tailwind/koyu tema derlemesini yüklemez.
**Gerekçe:** `app.css` `html { color-scheme: dark }` ve koyu gövde zemini tanımlar; bunları
yazdırma için tek tek geri almak kırılgan olurdu. Kağıt beyazdır. Sonuç testle doğrulanır:
yazdırma çıktısında `bg-bg`, kenar çubuğu ve `data-sidebar` bulunmaz.

## K-055 · Deploy workflow'u üç kilitle devre dışı doğar
**Karar:** `.github/workflows/deploy.yml` depoya eklendi ancak bu hâliyle gerçek
aktarım yapamaz. Üç bağımsız kilit:
1. Yalnızca `workflow_dispatch` — `push`/`pull_request` tetiklemesi yok.
2. Varsayılan biçim `dry-run`; araç bağlanır, raporlar, hiçbir şey yazmaz.
3. `DEPLOY_ENABLED` repository variable'ı `true` değilse `guard` işi durur.
   Bu değişken henüz oluşturulmadı.
Ek olarak gerçek deploy, `confirm_target` alanına hedef alan adının birebir
yazılmasını ister.
**Gerekçe:** Paylaşımlı hostingde yanlış deploy'un geri alınması pahalıdır ve
aynı hesapta başka aktif siteler vardır. Workflow'un "kazara çalışabilir" hâlde
depoda durması kabul edilemez.

## K-056 · Hedef dizin, kalıp doğrulamasından geçmeden kullanılmaz
**Sorun:** `SamKirkland/FTP-Deploy-Action` hedef dizinde senkronizasyon yapar.
`server-dir` yanlış olursa aynı cPanel hesabındaki DİĞER SİTELER etkilenebilir.
**Karar:** `guard` işi, hedef dizinleri aktarımdan önce doğrular: sonu `/` ile
bitmeli, `public_html` altında olmamalı, `..` içermemeli ve en az
`/home/<hesap>/<dizin>/` derinliğinde olmalı. Laravel kökü ile DocumentRoot
aynı olamaz. Değerler loglanmaz; yalnızca geçti/kaldı yazılır.
**Uygulama notu:** Kabuk `case` kalıplarında `*` eğik çizgiyi de eşler; bu yüzden
"hesap kökü" kontrolü kalıpla değil derinlik ölçümüyle yapılır. Kalıplar yerel
olarak dokuz senaryoyla sınandı.
**`dangerous-clean-slate` hiçbir koşulda kullanılmaz** — hedef dizinin tüm
içeriğini, `exclude` edilenler dahil siler.
**Bilinmeyen:** Aracın, durum dosyası yokken (ilk çalıştırma) sunucuda var olan
dosyalara ne yaptığı belgelenmemiştir ve kaynak koda inilerek doğrulanmadı.
Bu yüzden ilk çalıştırma zorunlu olarak `dry-run` olmalıdır.

## K-057 · BLOCKER: kilitli bağımlılıklar PHP 8.3'te çalışmaz
**Bulgu:** `composer.lock` Symfony 8.1.x'e kilitli ve bu paketler `php >=8.4.1`
istiyor. Hosting'de PHP 8.3 var. Kilit, PHP 8.4.19 üzerinde çözüldüğü için
Laravel'in izin verdiği `^7.4.0 || ^8.0.0` aralığından yenisi seçilmiş.
`laravel/framework` v13.30.1'in kendisi `^8.3` istiyor — sorun çerçevede değil,
çözümlemede.
**Doğrulama:** `composer update --dry-run` (platform 8.3'e sabitlenmiş kopyada)
19 Symfony paketini 7.4.x'e indiren geçerli bir çözüm buldu.
**Seçenekler:** (A) hosting'i PHP 8.4'e almak — önerilen, kod değişikliği yok;
(B) `config.platform.php = "8.3.99"` ekleyip kilidi yeniden çözmek.
**Bu turda `composer.lock` DEĞİŞTİRİLMEDİ** — karar kullanıcınındır.
**Kalıcı önlem:** Deploy workflow'u vendor'ı hedef PHP sürümüyle derler ve
`composer check-platform-reqs --no-dev` ile doğrular. Uyumsuzluk artık sessizce
üretime gidemez; build kırmızıya döner.

## K-058 · Dizin mimarisi kararı kullanıcıya bırakıldı
**Durum:** Lemon'un DocumentRoot'u şu anda `/home/derspros/lemon.derspros.com.tr`.
İki mimari değerlendirildi:
- **A (önerilen):** DocumentRoot Laravel'in `public/` dizinine yönlendirilir.
  `index.php` hiç değiştirilmez, `public_path()`/`asset()`/`storage:link` doğal
  çalışır, deploy tek hedefe yazar.
- **B:** DocumentRoot değiştirilemiyorsa Laravel kökü web dışında tutulur,
  DocumentRoot'a yalnızca `index.php`, `.htaccess` ve `build/` konur.
  Maliyeti: `public_path()` servis edilmeyen bir dizini gösterir, `storage:link`
  elle kurulur, Vite çıktısı iki yere gider.
**Karar verilmedi:** cPanel'de DocumentRoot'un değiştirilebildiği doğrulanmadan
workflow'un son hâli yazılamaz — tahmine dayalı `server-dir` yanlış dizine yazma
riski taşır. Workflow her iki mimariyi de destekleyecek biçimde yazıldı;
Mimari A seçilirse ikinci aktarım adımı kaldırılacaktır.

## K-059 · Lemon'a `develop` dalı deploy edilir
**Öneri:** Lemon ilk kabul/staging ortamı olduğundan `develop` dalı deploy edilir.
`main`'i Lemon'a bağlamak, gerçek production geldiğinde iki ortamın aynı dalı
paylaşmasına ve geriye dönük ayrıştırma işine yol açar.
**Otomatik push deployment hiçbir dal için etkinleştirilmedi.** Onay verilse bile
öneri `workflow_dispatch`'te kalmaktır: paylaşımlı hostingde geri alma pahalıdır.

## K-060 · Uygulama PHP 8.3'e kilitlendi (K-057 blocker'ı çözüldü)
**Bağlam:** Hosting'de PHP 8.4 kurulu ama **alan adı bazlı PHP izolasyonu kapalı**;
sürüm yalnızca hesabın tamamı için değiştirilebiliyor ve aynı hesapta başka aktif
siteler var. Global yükseltme onları da etkilerdi. Bu yüzden K-057'nin "Çözüm A"sı
(hosting'i 8.4'e almak) uygulanabilir değil; **Çözüm B seçildi.**

**Yapılan:** `composer.json` → `config.platform.php = "8.3.0"`, kilit yeniden çözüldü.

**Yama sürümü neden `8.3.0`:** `8.3.0` ve `8.3.99` ile çözüm birebir aynı çıktı
(ölçüldü — bu ağaçtaki hiçbir paket belirli bir 8.3 yamasına bağlı değil).
Eşit sonuçta en muhafazakârı seçildi: hosting hangi 8.3.x yamasını çalıştırırsa
çalıştırsın kilit geçerlidir.

**Değişen paketler (114 → 115):**
- **16 düşüş, hepsi Symfony 8.1.x → 7.4.x (LTS):** clock, console, css-selector,
  error-handler, event-dispatcher, finder, http-foundation, http-kernel, mailer,
  mime, process, routing, string, translation, uid, var-dumper
- **3 küçük yükseliş:** league/commonmark 2.10.0→2.10.1, laravel/pao, laravel/pint
- **1 yeni:** symfony/polyfill-php83 (7.4 paketlerinin bağımlılığı)
- **0 kaldırma.** Laravel v13.30.1 ve spatie/laravel-permission 8.3.0 DEĞİŞMEDİ.

**Doğrulama:**
- `composer why-not php 8.3` → "There is no installed package depending on 'php'
  in versions not matching 8.3"
- Kilitte (dev dahil) PHP 8.4+ isteyen paket kalmadı
- `composer validate --strict` geçti, `composer audit` güvenlik uyarısı vermedi
- Laravel 13 zaten `symfony/* ^7.4.0 || ^8.0.0` kabul ediyor — uyumluluk korunuyor

**Üç kapı, farkın sessizce üretime sızmasını engeller:**
1. `config.platform.php` — 8.4 isteyen paket kilide giremez
2. `phpstan.neon` → `phpVersion: 80300` — 8.4'e özgü işlev/sözdizimi analizde kırılır
3. CI ve deploy build'i PHP 8.3 kullanır + `composer check-platform-reqs`

**Sınır:** `composer check-platform-reqs` ÇALIŞAN PHP'yi denetler, `config.platform`
değerini değil. Bu yüzden 8.3 kanıtı `why-not php 8.3` + CI'ın gerçek 8.3
koşusundan gelir.

## K-061 · Faker Türkçe verisi arama testlerini rastgele kırıyordu
**Bulgu:** `CompanyListingTest::all_caps_kaydedilmis_firma_kucuk_harfle_bulunur`
aralıklı olarak kırılıyordu. Kök neden: faker'ın tr_TR **isim** listesinde 'Çınar'
var (`Person.php`), `CompanyFactory` de `contact_person`'ı oradan dolduruyor.
`CompanyFilter::searchable()` `contact_person`'ı kapsadığı için "çınar" araması
fazladan bir kayıt buluyordu — **uygulama doğru davranıyor**, kırılgan olan testin
sayım beklentisiydi. Bu turdaki PHP 8.3 değişikliğiyle ilgisi yok; K-038'de
düzeltilen `city`/'Trabzon' çakışmasıyla aynı sınıf.
**Karar:** Test sınıfına `searchableCompany()` yardımcısı eklendi; arama iddiası
taşıyan tüm testler ARANABİLİR alanların tamamını nötr sabitlerle doldurur.
Test zayıflatılmadı — Türkçe i-katlama davranışı aynen doğrulanmaya devam ediyor,
yalnızca sayım deterministik hâle geldi. 10 ardışık koşuda yeşil.

## K-062 · Mimari A kesinleşti; Mimari B kaldırıldı
**Karar:** cPanel'de `lemon.derspros.com.tr` alan adının Document Root alanının
değiştirilebildiği doğrulandı. Laravel kökü `/home/derspros/lemon-app/`, DocumentRoot
`/home/derspros/lemon-app/public` olacak. **Mimari B (ayrık public dizini) kullanılmayacak.**
**Kaldırılanlar:** `deploy/public_html-index.php`, `deploy/public_html-htaccess` ve boşalan
`deploy/` dizini; `phpstan.neon`'daki artık geçersiz `excludePaths: deploy` girdisi;
workflow'daki ikinci (DocumentRoot) FTP aktarım adımı; `LEMON_DOCROOT` repository variable'ı.
**Kazanç:** Laravel'in kendi `public/index.php` dosyası hiç değiştirilmiyor; `public_path()`,
`asset()` ve `storage:link` doğal çalışıyor; deploy tek hedefe yazıyor.
**Ek doğrulama:** Build adımı `public/index.php` içinde standart
`require __DIR__.'/../vendor/autoload.php'` satırını arıyor — dosya kazara uyarlanırsa
build kırmızıya döner.

## K-063 · `.htaccess` sertleştirmesi Laravel'in public/.htaccess dosyasına taşındı
**Sorun:** Kaldırılan `deploy/public_html-htaccess` şablonunda, Laravel'in stok
`public/.htaccess` dosyasında **bulunmayan** üç şey vardı: HTTPS zorunluluğu (CLAUDE.md §15),
gizli dosya erişim engeli ve güvenlik başlıkları (`X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`). Mimari A'da servis edilen dosya Laravel'inki olduğu için bunlar
**sessizce kaybolacaktı.**
**Karar:** Sertleştirme `public/.htaccess` içine taşındı. Laravel'in kendi kuralları
(Authorization, X-XSRF-Token, trailing slash, front controller, MultiViews) aynen korundu —
şablonda X-XSRF-Token kuralı yoktu, o da kaybolmadı.

## K-064 · Hedef dizin doğrulaması fail-closed ve iki biçimle sınırlı
> **Güncellendi: K-067 ile tek değere daraltıldı.** Aşağıdaki iki biçimli hâli tarihsel kayıttır.
**Bağlam:** Hesapta `public_html` (derspros.com.tr) ve en az dört başka canlı site klasörü var.
**Karar:** `guard` işi yalnızca iki değeri kabul eder: `/home/derspros/lemon-app/` (chroot yok)
veya `/lemon-app/` (FTP kökü `/home/derspros` ise). Başka her değerde **iş durur**.
Tam eşleşmenin yanı sıra bağımsız ikinci bir kontrol seti çalışır: sonu `/`, `..` yok,
`public_html` yok, diğer alan adı klasörleri yok, son dizin adı `lemon-app`.
Değer boşsa iş hemen durur — tanımsız değişken yüzünden hesap köküne yazmak imkânsızdır.
**Doğrulama:** 14 senaryo yerelde sınandı; yalnızca iki meşru biçim geçti, hesaptaki tüm
diğer site klasörleri ve `/`, `/home/derspros/`, `..` içeren yollar reddedildi.
**`dangerous-clean-slate` hiçbir koşulda kullanılmaz.**

## K-065 · DocumentRoot değişikliği EN SON yapılır
**Sorun:** `/home/derspros/lemon-app/public` bugün boştur. DocumentRoot şimdi değiştirilirse
Lemon alan adı, ilk gerçek deploy tamamlanana kadar 403/404 verir.
**Karar sırası:** dizin+`.env`+DB hazırlığı → dry-run → ilk gerçek deploy → sunucuda
`key:generate`/`migrate`/`storage:link`/cache → **ancak o zaman** cPanel'den DocumentRoot
değişikliği → §7 güvenlik doğrulaması.
**GitHub Actions bu değişikliği yapmaz ve yapamaz** — cPanel erişimi yoktur.

## K-066 · Rollback: analiz edildi, kurulmadı
**Değerlendirilen üç yol:** (A) deploy öncesi elle klasör yedeği, (B) sürümlü dizin +
symlink, (C) git ile ileri düzeltme.
**Öneri: A + C.** B elendi çünkü FTPS ile güvenilir symlink kurulamıyor ve SSH yok.
**Kritik ayrım:** dosya yedeği veritabanını geri almaz. `ledger_entries` append-only'dir
(§6/L1) ve migration'lar ileri alınmıştır; şema değiştiren bir deploy öncesi ayrıca
`mysqldump` alınmalıdır.
**Uygulama:** ilk gerçek deploy öncesi, ayrı bir turda. Bu turda hiçbir şey kurulmadı.

## K-067 · FTP kökü doğrulandı; guard tek hedef değerine daraltıldı
**Bağlam:** K-064'te hedef dizin için iki biçim kabul ediliyordu, çünkü FTP hesabının
chroot davranışı bilinmiyordu ve tahmin edilmemişti (`docs/deploy-lemon.md` §12 bir
açık soruydu).
**Doğrulama (kullanıcı, FileZilla):** ana FTP hesabına bağlanıldı, "TLS bağlantısı kuruldu";
uzak kökte `lemon-app`, `lemon.derspros.com.tr`, `nuvernatrade.com` klasörleri **doğrudan**
göründü. Yani hesap `/home/derspros` altına chroot'ludur ve Lemon uygulamasının yolu köke
göre `/lemon-app/`'tir. Salt okunur listeleme; hiçbir dosya yüklenmedi/silinmedi/değiştirilmedi.
**Karar:**
1. `deploy.yml` içindeki `EXPECTED_DIR` **tek değer** olur: `/lemon-app/`.
   `/home/derspros/lemon-app/` biçimi kaldırıldı — kabul kümesi ne kadar darsa yanlış
   hedefe yazma olasılığı o kadar küçüktür.
2. `LEMON_APP_DIR` variable'ının değeri kesinleşti: `/lemon-app/`.
3. Bağımsız ikinci kontrol seti (sonu `/`, `..` yok, `public_html` yok, diğer alan adı
   klasörleri yok, son dizin adı `lemon-app`) **aynen korunur**; tek eşleşme bir gün
   gevşetilse bile yasak hedefler yakalanmaya devam eder.
4. FTP hesabı değişirse `EXPECTED_DIR` **bilinçli olarak** güncellenir — tek satır,
   gözden geçirilebilir bir değişiklik. Guard başka hiçbir değeri kabul etmez.
**Yan bulgu — kalan yapısal risk:** ana FTP hesabı chroot'lu olduğu için hesaptaki tüm
sitelere yazabilir; koruma yalnızca workflow düzeyindedir. Yapısal çözüm, cPanel'den
yalnızca `/home/derspros/lemon-app` dizinine erişebilen **ayrı bir FTP hesabı** açmaktır.
Bunun sonucu: o hesapta hedef `/lemon-app/` değil `/` olur ve `EXPECTED_DIR` ile
"son dizin adı `lemon-app`" kontrolü yeniden düşünülmelidir. Bu yüzden bugün
**uygulanmadı**, yalnızca önerildi (`docs/deploy-lemon.md` §12).
**Deploy durumu değişmedi:** `DEPLOY_ENABLED` yok, varsayılan `dry-run`, yalnızca
`workflow_dispatch`. Bu turda dry-run dahil hiçbir workflow çalıştırılmadı.

## K-068 · TRIGGER yetkisi blocker olmaktan çıkarıldı; doğrulama migration'a taşındı
**Bağlam:** `SHOW GRANTS FOR CURRENT_USER();` çıktısında `derspros_lemon.*` için
ALL PRIVILEGES görüldü. Ancak `CURRENT_USER` phpMyAdmin oturum kullanıcısıdır;
Laravel'in kullanacağı `derspros_sebahattin` ile birebir aynı kullanıcı değildir —
yani bu çıktı dolaylı bir kanıttır. cPanel Manage Databases ekranında
`derspros_sebahattin` → `derspros_lemon` → TÜM AYRICALIKLAR atanmış durumdadır.
**Karar:** TRIGGER yetkisi artık **dry-run blocker değildir.** Dry-run veritabanına
hiç dokunmaz; onu bir DB yetkisine bağlamak gereksizdi.
**Kesin doğrulama ilk gerçek production migration'dadır.** `CREATE TRIGGER` doğrudan
kanıttır, `SHOW GRANTS` dolaylı.
**Zorunlu davranış — CREATE TRIGGER başarısız olursa:** `migrate --force` fail eder ve
orada durulur; **DocumentRoot KESİNLİKLE değiştirilmez** (yarım migrate edilmiş bir
veritabanına kimse yönlenmez); karar kullanıcıya sorulur.
**Değişmeyen:** migration'da hiçbir `try/catch`, `IF NOT EXISTS` veya sessiz atlama
yoktur ve eklenmeyecektir. **Ledger trigger koruması hiçbir koşulda bypass edilmez
veya kaldırılmaz** (CLAUDE.md §6/L1).

## K-069 · Dry-run kilitsizdir; kilitler yalnızca gerçek deploy yolundadır
**Sorun:** Kilitlerin güvenli doğrulamayı da engellemesi tasarım hatasıdır. Dry-run'ı
`DEPLOY_ENABLED`'a bağlamak, hiçbir şey yazmayan bir işlemi yazma iznine bağlamak olurdu.
**Karar — iki çalışma biçimi:**
| | `mode: dry-run` (varsayılan) | `mode: deploy` |
|---|---|---|
| `DEPLOY_ENABLED` | gerekmez, okunmaz | `true` zorunlu |
| `confirm_target` | gerekmez; dolu ve yanlışsa yalnızca uyarı | `lemon.derspros.com.tr` birebir |
| `LEMON_APP_DIR` | `/lemon-app/` (hedef doğrulaması her iki biçimde de çalışır) | `/lemon-app/` — deploy yolunda ikinci kez kontrol edilir |
| Dosya oluşturma/değiştirme/silme | yok | var |
**Fail-closed korundu:** karar iki kez, birbirinden bağımsız hesaplanır (`guard` işi ve
aktarım işi); ikisi uyuşmazsa iş durur. Aktarım adımının `dry-run` bayrağı artık guard
çıktısına değil, aktarım işinin kendi hesabına bağlıdır — guard çıktısının kaybolması
veya ileride değiştirilmesi tek başına gerçek aktarım açamaz. `'true'` dışındaki her
değer dry-run demektir. `mode: deploy` seçilip kilitlerden biri kapalıysa iş **durur**;
sessizce dry-run'a düşülmez, çünkü kullanıcı deploy istemiştir.
**Doğrulama:** 11 senaryolu karar matrisi yerelde sınandı — dry-run'ın dört varyantı da
yazmasız geçti, `deploy` yalnızca dört kilit birden açıkken gerçek aktarıma döndü.

## K-070 · FTP aracının dry-run ve ilk-yayın davranışı kaynaktan doğrulandı
**Bağlam:** İki iddia şimdiye kadar belgeye dayanıyordu, kaynağa değil.
**Yöntem:** `@samkirkland/ftp-deploy` paketi indirilip `dist/` okundu.
**Bulgu 1 — dry-run gerçekten yazmıyor.** Sunucuyu değiştiren beş işlemin tamamı
bayrakla kapalı: `createFolder` (`if (this.dryRun === true) return;`), `uploadFile`
(upload + replace), `removeFile`, `removeFolder` ve durum dosyası yazımı
(`if (this.dryRun === false) { … }`).
**Bulgu 2 — bilinen tek istisna.** `getServerFiles()`, dry-run kontrolünden ÖNCE
`ensureDir(server-dir)` çağırır; hedef dizin yoksa **oluşturulabilir**. Sonuç boş bir
dizindir; dosya yazılmaz, hiçbir şey silinmez. Hedefimiz `/lemon-app/` zaten mevcut
olduğu için pratikte no-op'tur ve guard tek değere kilitli olduğu için yanlış bir yola
dizin açılması mümkün değildir. Bu istisna belgeye açıkça yazıldı.
**Bulgu 3 — ilk yayında silme yok (K-064'teki açık soru kapandı).** Durum dosyası
bulunamazsa araç sunucu durumunu boş kabul eder (`data: []`); boş duruma göre hesaplanan
fark yalnızca **upload** üretir, **delete üretmez**. İlk gerçek aktarım sunucuda
önceden bulunan dosyaları silmez. İlk çalıştırmanın dry-run olması önerisi yine de
geçerlidir — hedefin ve aktarım listesinin beklenen şey olduğu görülmelidir.

## K-081 · Üretimde varsayılan yönetici kimlik bilgisiyle seed YASAKTIR
> Numara, henüz birleştirilmemiş dal(lar)daki K-071…K-080 ile çakışmasın diye seçildi.

**Sorun:** `SEED_ADMIN_PASSWORD` boş bırakılırsa
`config/hotel.php` varsayılanı devreye giriyor ve seeder `admin@example.com` /
`degistir-beni` hesabı oluşturuyordu. Negatif testle doğrulanmıştı: parola geçerliydi.
Belgeye yazmak yetmez — `.env` unutulursa kapı açık kalır.
**Karar — koruma iki katmanlı:**
1. `config/hotel.php` artık e-posta ve parola için **varsayılan vermiyor**; env yoksa
   `null` döner. Güvensiz değerin config'te durması, "ayarlanmış" gibi görünmesine yol açıyordu.
2. `DatabaseSeeder::guardProductionCredentials()` local/testing DIŞINDAKİ her ortamda
   çalışır ve şu dört durumda `RuntimeException` fırlatır: parola boş, parola geliştirme
   varsayılanı (`degistir-beni`), e-posta boş, e-posta geliştirme varsayılanı
   (`admin@example.com`).
**Kapsam `production` değil `! local/testing`:** dosyada zaten demo hesapları aynı koşulla
sınırlayan bir kural vardı; iki farklı "üretim" tanımı taşımak yerine tek tanım kullanıldı.
`staging` de korunur.
**Fail-closed:** guard `RolePermissionSeeder`'dan hemen sonra, **hiçbir kullanıcı
yazılmadan** çalışır. Testle doğrulandı: guard tetiklendiğinde `users` tablosu boş kalır.
Yarım kalan tek şey rol/yetki tablolarıdır; o seeder idempotenttir, yeniden çalıştırmak güvenlidir.
**Geliştirici deneyimi korundu:** varsayılanlar `DatabaseSeeder` sabitlerine taşındı ve
yalnızca local/testing'de uygulanır. `php artisan migrate:fresh --seed` `.env` olmadan
da çalışmaya devam eder.
**Doğrulama:** 12 yeni test (`tests/Feature/Security/ProductionSeedGuardTest.php`) +
gerçek CLI provası: `APP_ENV=production php artisan db:seed --force` `SEED_ADMIN_*`
olmadan RuntimeException verdi ve `users` tablosunda 0 kayıt kaldı; güçlü parolayla
tek admin oluştu. Toplam test 454 → 466, hepsi yeşil.

## K-082 · Tasarım sistemi açık temaya taşındı; kenar çubuğu kendi paletini kullanır
**Bağlam:** CLAUDE.md §11 koyu slate + turuncu temayı "tek tema" olarak tanımlıyordu.
Kullanıcı Gün 9'da açık zemin + beyaz kart + koyu lacivert kenar çubuğu + sıcak altın
vurgu istedi. §18 gereği aynı commit'te CLAUDE.md §11 yeniden yazıldı.
**Neden ucuz oldu:** tüm bileşenler zaten token kullanıyordu (`bg-card`, `text-tx`, …)
ve Blade'de ham hex yoktu. Tema değişimi büyük ölçüde `app.css` içindeki `@theme`
bloğunun değişmesiyle oldu.
**Ölçülerek seçilen renkler (WCAG 2.1 formülü):** ana metin #1F2937 kart üzerinde 14.68;
ikincil #6B7280 kart 4.83 / sayfa 4.51; form sınırı #8A8A85 kart 3.47 / sayfa 3.23 (1.4.11).
**Kritik bulgu — altın metin olarak KULLANILAMAZ:** #C8A96B beyaz üzerinde 2.25 verir.
Bu yüzden üç token ayrıldı: `--color-acc` (dolgu), `--color-acc-tx` #8A6A2F (metin ve
odak halkası, 5.02), `--color-on-acc` #1F2937 (altın dolgu üzerindeki metin, 6.53).
Odak halkası da `--acc-tx` kullanır; altın dolgu 1.4.11'in 3:1 eşiğini geçmezdi.
**Kullanım kuralı:** `--color-well` (#F1F0EC) üzerine `text-mut` yazılmaz — 4.24'te kalır.
Çukur alanlarda `text-tx` kullanılır.
**Borç kırmızı DEĞİLDİR:** kırmızı "iptal / tehlike" demektir. Borç `--color-debit`
(#B45309), alacak `--color-credit` (#15803D) ve her ikisi de daima metin etiketiyle birlikte.
**Testle korunuyor:** `DesignSystemTest::blade_icinde_ham_hex_rengi_yazilmaz` Blade'lerde
ham hex arar. Tek muafiyet `<meta name="theme-color">` (HTML düz değer ister) ve
yazdırma görünümü (kendi kendine yeten CSS taşır).
**Alpine.js EKLENMEDİ.** İstek "mevcut Blade + Tailwind + Alpine mimarisini koru" diyordu
ama Alpine bu projede hiç kurulu değildi (§11.7). Yeni paket eklemek yerine mevcut sade
JavaScript korundu ve genişletildi.

## K-083 · Dashboard modülü ve sabit sorgu bütçesi
**Karar:** Pano `app/Modules/Dashboard/` altında yeni bir modüldür; uygulama kökü (`/`,
route adı `home`) `routes/web.php`'den modülün kendi route dosyasına taşındı. Route adı
`home` KORUNDU: kenar çubuğu, giriş sonrası yönlendirme ve mevcut testler bunu kullanır.
**Modüller arası erişim yalnızca Service'ten (§4.3/1):** `DashboardService` Company, Stay,
Payment veya LedgerEntry modeline hiç dokunmaz. Gerekli raporlama metotları ilgili
servislere eklendi (`CompanyService::activeCount/namesByIds`,
`StayService::totalsByCurrencyBetween/currentAndUpcoming/…`, aynısı PaymentService'te,
`LedgerStatementService::openBalanceTotalsByCurrency/topDebtorCompanies/recentEntries`).
**Açık alacak/borç firma bazında NETLENDİKTEN SONRA toplanır.** Alt sorgu firma+para
birimi kırılımında bakiye üretir, dış sorgu yöne göre ayırır — tek sorgu, para birimi
sayısı kadar satır. Bir firmanın borcu başka firmanın alacağını götürmez.
**"Bu ay" cari hareketin `entry_date` sütunuyla aynı alandan uygulanır:** konaklamada
`check_out_date`, tahsilatta `payment_date`. Böylece ay toplamı, o ay deftere düşen
tutarla birebir tutar.
**Personel panosu tutar TAŞIMAZ:** `PersonnelSummary` nesnesinde hiç `Money` alanı yoktur
ve controller finansal servisi hiç çağırmaz. Testle doğrulandı: personel isteğinde
`ledger_entries` sorgusu sıfır.
**Sorgu bütçesi ölçüldü: 11 sorgu, firma sayısından bağımsız.** `DashboardTest` hem
N+1 yokluğunu hem 15 sorguluk üst sınırı doğrular.
**Test tuzağı:** `DB::enableQueryLog()` mevcut günlüğü TEMİZLEMEZ. İki ölçüm arka arkaya
yapıldığında ikincisi birincininkileri de sayar ve sahte bir N+1 raporlar
(11 → 22 görüldü). Ölçümden hemen önce `DB::flushQueryLog()` şart.

## K-084 · Çift gönderim koruması sade JavaScript ile
**Sorun:** Aynı formun iki kez gönderilmesi iki konaklama ya da iki tahsilat demektir;
defter append-only olduğu için düzeltmesi elle ters kayıt gerektirir.
**Çözüm:** genel `submit` dinleyicisi; ikinci gönderim `preventDefault` ile durdurulur.
**İki tuzak ve çözümleri:** (1) devre dışı bırakılan submit butonunun `name`/`value`
çifti istek gövdesine GİRMEZ — bu yüzden kilitleme `setTimeout(0)` ile gövde
toplandıktan sonra yapılır; (2) tarayıcı doğrulaması gönderimi iptal ederse form
kilitli kalmamalı — `invalid` olayında ve bfcache dönüşünde (`pageshow`) kilit açılır.
Muafiyet gerekirse forma `data-allow-resubmit` konur.

## K-085 · Gerçek tarayıcıda doğrulama iki gerçek hata yakaladı
**Yöntem:** kurulu Chromium ile 375/768/1366/1920 genişliklerinde ekran görüntüsü ve
yatay taşma ölçümü.
**Bulgu 1:** panoda 375px'te 7px yatay taşma. Neden: `<x-ui.panel>` bir grid öğesidir ve
grid öğelerinin varsayılan `min-width: auto` değeri uzun içerikle küçülmelerini engeller.
`min-w-0` eklendi.
**Bulgu 2:** dar ekranda tutarlar `truncate` yüzünden "21.250,5…" olarak kırpılıyordu.
**Bir finans ekranında kırpılmış tutar yanlış bilgidir**; `truncate` kaldırıldı, dar
ekranda yazı küçülüyor ve gerekirse satır kırılıyor.
**Ayrıca:** iptal edilmiş tahsilat panoda "alacak yeşili" ile görünüyordu; cariye etkisi
ters kayıtla sıfırlandığı için nötr ve üstü çizili gösterime alındı.

## K-086 · Mobil dokunma turu: gerçek tarayıcı ölçümü, tahmin değil
**Bağlam:** Gün 9'un "yatay taşma yok" sonucu mobil desteğin yeterli olduğunu göstermiyordu.
Bu tur 14 ekranı 6 viewport'ta (360/375/390/430/768/1366) gerçek Chromium ile denetledi:
yatay taşma, kırpılmış tutar ve dokunma hedefi boyutu programatik olarak ölçüldü.
**Sonuç: 84 kontrolün tamamında (14 ekran × 6 viewport) sıfır yatay taşma, sıfır kırpılmış
tutar.** Gün 9'daki `min-w-0`/`truncate` düzeltmeleri kalıcı kaldı.
**Gerçek bulgular (tahmini değil, ölçülmüş):**
1. Pano panellerinde (en yüksek bakiyeli firmalar, son cari hareketler, son tahsilatlar,
   yaklaşan konaklamalar) yalnızca firma adı/misafir adı tıklanabilirdi; tutar ve dolgu alanı
   değildi. Satırlar artık tutarı da kapsayan TEK `<a>` ile sarmalanıyor — liste ekranlarının
   zaten kullandığı `<a class="block"><x-ui.card>` desenine (payments/stays/companies mobil
   kart görünümü) hizalandı.
2. Firma detayında telefon/e-posta linkleri ve "Ekstreyi aç"; panel başlığındaki "Tümü"
   linki; cari ekstredeki kaynak linkleri ("Konaklama kaydı"/"Tahsilat kaydı") 14-20px
   yüksekliğindeydi. `min-h-11` ile 44px garanti edildi — `-m-N p-N` negatif kenar boşluğu
   hilesi yerine, çünkü satır yüksekliği tahmini yanlış çıktı (ilk denemede 32-36px'te kaldı,
   ölçülerek yakalandı ve düzeltildi).
3. Checkbox etiketi 20px yükseklikteydi; `<label>` artık `min-h-11 items-center` taşıyor.
   Görsel kutu (16px) kasıtlı olarak küçük kalıyor — dokunma alanı tüm satırdır.
**Kalan küçük hedefler yalnızca 1366px'te (masaüstü tablo sıralama başlıkları, satır
aksiyon düğmeleri) — 360-768px arasının HİÇBİRİNDE yok.** Bu, fare odaklı standart bir
desendir (CLAUDE.md §11.5: tablolar yalnızca `lg`/1024px ve üzerinde gösterilir); mobil
kart görünümü zaten tam satır bağlantısı kullanıyor. Değiştirilmedi.
**Gerçek klavye tuzağı bulundu ve düzeltildi:** kapalı (ekran dışı) mobil çekmece DOM'da
kalmaya devam ediyor ve `Tab` tuşu "İçeriğe atla"dan sonra doğrudan görünmeyen "Menüyü
kapat" düğmesine ve menü linklerine atlıyordu. Çözüm: `inert` özniteliği, `matchMedia
('(min-width: 768px)')` ile senkronize — masaüstünde (`md` ve üzeri, kenar çubuğu her zaman
sabit/görünür) asla uygulanmaz; mobilde yalnızca çekmece kapalıyken uygulanır. Açılışta/
kapanışta ve viewport `md` eşiğini geçtiğinde yeniden hesaplanır. Doğrulandı: mobilde Tab
artık çekmeceyi atlıyor, çekmece açıkken içine giriyor; masaüstünde davranış değişmedi
(Tab hâlâ kenar çubuğuna doğal olarak giriyor).
**Değiştirilmeyen (bilerek):** hiçbir yeni JS paketi/framework eklenmedi — `inert` yerleşik
bir HTML özniteliğidir, `matchMedia` yerleşik bir Web API'sidir. Native `confirm()` diyalogları
(silme/iptal onayı) zaten viewport-güvenlidir; özel modal hiç yoktu, eklenmedi. Backend, ledger
kuralları, authorization/policy, finansal hesaplamalar HİÇ değişmedi.
**Test:** `DesignSystemTest::mobil_cekmece_erisilebilirlik_kablolamasi_tamdir` — app.js'in
bağımlı olduğu `data-sidebar*`/`aria-*` işaretlemesinin silinmesini CI'da (tarayıcısız)
yakalar. `DashboardTest::panel_satirlari_tutari_da_kapsayan_tek_baglantidir` — regex ile
`<a href="…">` etiketinin firma adı VE tutarı `</a>`'dan önce aynı anda sardığını doğrular.
`inert`/dokunma boyutu/taşma gibi CSS-JS davranışları PHPUnit'te ölçülemediği için asıl kanıt
gerçek tarayıcı denetimidir (84/84 kontrol temiz); bu, rapor edilen sayılarla birlikte sonuçtur.

## K-087 · Para birimi kümesi ISO 4217 tabanlı ve config-driven genişletilebilir yapıldı
**Bağlam (Gün 9.6):** İş modeli TRY/EUR/GBP üçlüsüne sabitliydi — Currency enum'unda
sadece 3 case, dashboard/ekstre/form kodunda hiçbir yerde "3'ten fazla para birimi"
varsayımını bozan hard-code YOKTU (audit ile doğrulandı: hiçbir yerde `in_array($c,
['TRY','EUR','GBP'])` gibi bir kapalı liste bulunmadı), ama enum'un kendisi kapalıydı.
Hedef: en az TRY, EUR, GBP, USD, RUB, GEL desteklemek; gelecekte yeni bir ISO kodu
eklemek onlarca dosyaya dokunmayı gerektirmemeli.

**DB migration GEREKMEDİ.** `stays.currency`, `payments.currency`, `ledger_entries.currency`
zaten `CHAR(3)`; `information_schema.CHECK_CONSTRAINTS` ve canlı şema sorgusuyla doğrulandı —
değeri kısıtlayan hiçbir CHECK yok. Genişleme tamamen `App\Modules\Core\Enums\Currency`
enum'unda (TRY/EUR/GBP/USD/RUB/GEL, 6 case) ve `config/hotel.php`'de kaldı.

**İki katman, bilinçli ayrım:**
- `Currency` enum'u = TEKNİK geçerlilik kümesi (validasyon, DB güvenliği, testler).
- `config('hotel.currencies')` = İŞ KARARI — hangi kodlar aktif ve hangi sırayla
  gösterilir; enum'un alt kümesi olmak ZORUNDA (testle korunur), enum'u genişletemez.
- `Currency::active()` (config'ten, sıralı) YALNIZCA yeni kayıt formlarında (Konaklama/
  Tahsilat oluşturma/düzenleme) kullanılır. `Currency::cases()` (enum'un tamamı)
  raporlama/filtre/geçmiş ekranlarında (liste filtreleri, ekstre filtresi, dashboard
  toplamları) kullanılmaya DEVAM eder — bir kod "aktif" listeden çıkarılsa bile o kodda
  geçmiş borç/alacak dashboard'da veya ekstrede asla gizlenmemeli.
- `Currency::decimalPlaces()` eklendi: `Money`'nin sabit ×100 (kuruş) varsayımını
  ADLANDIRIR ve test eder. Money'nin kendisi değiştirilmedi — V1'in 6 para biriminin
  tamamı 2 ondalıklı; farklı ondalık basamaklı bir ISO kodu (JPY, BHD gibi) eklenmeden
  önce bu varsayım yeniden değerlendirilmeli.
- RUB/GEL için sembol yerine ISO kodu gösterilir (`symbol()` → `'RUB'`/`'GEL'`); ₽/₾
  glifleri az tanınır ve CLAUDE.md §7'nin "sembole güvenilmez" ilkesiyle çelişir.

**Regresyon:** 4 mevcut test `'USD'`'yi "geçersiz para birimi" örneği olarak kullanıyordu
— USD artık desteklendiği için bu testler yanlış pozitif üretirdi. Testler ZAYIFLATILMADI;
geçersiz örnek gerçekten enum dışı kalan `'CHF'` ile değiştirildi
(`LedgerInvariantsTest`, `PaymentCrudTest`, `StayCrudTest`, `StayListingTest`).

**Yeni test:** `tests/Feature/Core/CurrencyTest.php` — enum/config bütünlüğü, `active()`
sıralaması ve geçersiz kod filtrelemesi, RUB/GEL/USD ile uçtan uca konaklama+tahsilat+
cari hareket, farklı para birimlerinin toplanmadığı, ekstre/dashboard/formların yeni
kodları ayrı ayrı gösterdiği, ham SQL ile herhangi bir 3 harfli kodun DB'de kabul
edildiği (şema güvenliği) ve TRY/EUR/GBP'nin mevcut davranışının bozulmadığı — 24 test,
82 assertion.

**Değiştirilmeyen (bilerek):** exchange rate/kur servisi, currency conversion, Kasa
Defteri modülü — bu turun kapsamı dışında (§3.2, kullanıcı talimatı). `StatementController`
ve `DashboardService` filtre/toplama noktaları `Currency::cases()` üzerinde bırakıldı,
`active()`'e GEÇİRİLMEDİ — kasıtlı, yukarıdaki ayrım gereği.

## K-088 · Mobil birincil gezinme kenar çubuğundan alt gezinme çubuğuna taşındı
**Bağlam (Gün 9.6):** Mobilde tek gezinme yolu hamburger + soldan kayan çekmeceydi —
bu, "her ekrana bir dokunuşta erişim" beklentisine uymuyordu (native mobil uygulama
deseninin aksine). Hedef: 4 sabit sekme + Daha Fazla; Kasa Defteri (Gün 10) geldiğinde
Konaklamalar'ın Daha Fazla'ya taşınıp yerine Kasa'nın geçeceği bir yapı.

**Çekmece yeniden kullanıldı, kopyalanmadı.** "Daha Fazla" sekmesi YENİ bir menü/sayfa
DEĞİL — mevcut kenar çubuğu çekmecesini ikinci bir tetikleyiciyle açıyor. Bu, yetkiye
duyarlı grup/öğe render mantığının (`partials/sidebar.blade.php`) tekrarlanmasını
önledi ve çekmecenin zaten test edilmiş erişilebilirlik kablolamasını (Escape, dışarı
tıklama, `inert`, odak yönetimi — K-086) aynen miras aldı. `resources/js/app.js`'teki
`drawer` nesnesi tek bir `toggle` yerine `toggles` (NodeList) tutacak şekilde
genelleştirildi; hangi tetikleyici açtıysa kapanışta odak ona döner (`lastToggle`).

**Bulunan ve düzeltilen gerçek hata — katman sırası:** ilk uygulamada alt çubuk ve
çekmece overlay'i AYNI `z-30` değerini taşıyordu; DOM sırası yüzünden alt çubuk overlay'in
ÜSTÜNDE kalıyordu — hem görsel olarak (dimlenmemiş, tam parlaklıkta görünüyordu) hem
işlevsel olarak (`elementFromPoint` alt çubuğu döndürüyordu, yani çekmece açıkken bile
tıklanabilir/odaklanabilir kalıyordu). Gerçek Chromium ekran görüntüsüyle yakalandı,
tahmin edilmedi. Düzeltme: katman sırası `bottom-nav(z-20) < overlay(z-30) < panel(z-40)`
olarak sabitlendi VE çekmece açıkken alt çubuğa da `inert` uygulanır oldu — yalnızca
görsel örtüşmeyi düzeltmek yetmezdi, klavye/ekran okuyucu için de gerçek bir "arkada
kalan içerik etkileşilemez olmalı" garantisi gerekiyordu.

**İkon tekrarını önleme denemesi bir kez başarısız oldu, ikinci denemede çözüldü:**
önce ikon `d` yol verileri Blade `@include` ile paylaşılmaya çalışıldı — ama bir
`@include` içinde tanımlanan değişken üst kapsama SIZMAZ (her Blade view izole render
edilir); bu, `Undefined variable $icons` hatasıyla testte yakalandı (tahmin değil,
gerçek çalıştırma). Çözüm: `App\Modules\Core\Support\NavIcons::PATHS` sınıf sabiti —
hem kenar çubuğu hem alt çubuk aynı kaynağı okur.

**Yeni testler:** `DesignSystemTest::alt_gezinme_cubugu_bes_oge_ile_render_edilir`,
`::alt_gezinme_cubugundaki_daha_fazla_mevcut_cekmeceyi_acar`,
`::personel_alt_cubukta_ayni_dort_birincil_ogeyi_gorur`. `inert`/z-index/dokunma boyutu
gibi CSS-JS davranışları PHPUnit'te ölçülemez; asıl kanıt gerçek Chromium denetimidir
(360-768px arası, "Daha Fazla" tıklama + Escape kapama dahil, 0 bulgu).

**Değiştirilmeyen (bilerek):** masaüstü/tablet kenar çubuğu (`md` ve üzeri) hiç
değişmedi — hâlâ birincil ve sabit. Ayrı bir Profil/Hesap sayfası/route'u EKLENMEDİ;
böyle bir ekran bu turdan önce de yoktu ve yeni route açmak CLAUDE.md §18'e aykırı
olurdu — kimlik bilgisi zaten çekmecenin alt bloğunda görünüyor.

## K-089 · Mobil sayfa header standardı + pano üç bilgi katmanına ayrıldı
**Bağlam (Gün 9.6):** Liste ekranlarının başlığı sadece `Başlık (sayı)` idi —
mobilde bu, gerçek bir "durum satırı" değil, küçük bir parantez içi rakamdı.
Pano ise tek uzun bir widget yığınıydı; "şu anda bilmem gereken ne?" sorusuna
sırayla cevap vermiyordu.

**Header standardı:** `<x-ui.page-header>` zaten `title`/`count`/`subtitle`/
`actions` destekliyordu (bileşen DEĞİŞMEDİ) — kullanılmıyordu. Firmalar, Konaklamalar,
Tahsilatlar, Kullanıcılar, Roller ekranlarına `subtitle` eklendi. Firmalar'da
`subtitle` sayaç rozetinin TEKRARI değil — `CompanyService::activeCount()` (zaten
var, Dashboard'ın da kullandığı metot) ile ayrı bir anlam taşıyor: toplam (filtrelenmiş
sonuç) `count` rozetinde, "kaç tanesi aktif" `subtitle`'da. Diğer ekranlarda ikinci
bir anlamlı boyut olmadığı için `count` rozeti kaldırıldı, tek bir `subtitle`
cümlesi ("7 konaklama kaydı" gibi) kullanıldı — aynı sayıyı iki farklı görsel
biçimde tekrar etmek gerçek bilgi eklemiyordu.

**Bulunan ve düzeltilen bir test kusuru:** `DesignSystemTest::tutar_kolonlari_
tabular_nums_kullanir` hiç konaklama oluşturmadan `stays.index`'te `tabular-nums`
sınıfını arıyordu — testi YEŞİL tutan şey tablo hücresi değil, kaldırılan sayaç
rozetinin KENDİSİydi (`page-header`'ın `count` span'i de aynı sınıfı taşıyor).
Yani test, adının iddia ettiği şeyi hiç doğrulamıyordu; `count` kaldırılınca bu
GERÇEK Chromium/PHPUnit çalıştırmasıyla ortaya çıktı (tahmin değil). Düzeltme:
teste gerçek bir `Stay` eklendi, artık asıl tablo hücresini doğruluyor.

**Pano üç katmana ayrıldı — Bugün / Finansal durum / Dikkat gerekenler:**
`DashboardService`/`FinancialSummary` HİÇ değişmedi (11 sorguluk bütçe aynı
kaldı, `DashboardTest` bunu doğruluyor) — bu yalnızca `financial.blade.php`'nin
GÖRÜNÜM sırası. Aynı veri, üç adlandırılmış `<section>` altında yeniden
gruplandı: Bugün (hızlı aksiyonlar + yaklaşan konaklama + son hareketler/tahsilat),
Finansal durum (para birimi kartları + aktif firma), Dikkat gerekenler (en yüksek
bakiyeli firmalar). Personel panosuna da aynı "Bugün" başlığı eklendi (tutarlılık
için) — B ve C katmanları personele hiç uygulanmaz (CLAUDE.md §7.4), o yüzden
personel panosu tek katmanlı kalır.

**Yan düzeltme — başlık hiyerarşisi:** `<x-ui.panel>`'in dahili başlığı `<h2>`'den
`<h3>`'e indirildi; artık yeni bölüm başlıklarının (`<h2>Bugün</h2>` vb.) altında
doğru iç içe geçiyor. `panel` yalnızca Dashboard'da kullanıldığı için bu değişiklik
başka hiçbir ekranı etkilemedi (kontrol edildi: `grep -rl x-ui.panel`).

**Yeni testler:** `DashboardTest::admin_panosu_uc_bilgi_katmanini_da_gorur`,
`::admin_bugun_katmaninda_hizli_aksiyon_butonlari_gorur`,
`::personel_panosu_bugun_baslikli_tek_katmandir`.

**Değiştirilmeyen (bilerek):** "Kasada Şu An" kartı YAZILMAZ, sahte veri KONMAZ
(§3.2) — yalnızca Bugün ile Finansal durum arasına nerede ekleneceğini işaretleyen
bir yorum satırı var. Dashboard sorgu bütçesi, N+1 koruması, para birimi ayrımı
(§6/L5) hiç dokunulmadı.

## K-090 · Kasa Defteri ayrı modüldür; fiziksel para ile firma borcu aynı deftere yazılmaz
**Bağlam (Gün 10):** Otelin günlük kasa defteri ve personel devir sistemi
dijitalleştirildi. İlk karar: bu veri `ledger_entries`'e YAZILMAZ.
**Gerekçe:** cari defter firmanın BORCUNU izler (5.000 TRY'lik konaklama, para
tahsil edilmese bile borçtur); kasa defteri FİZİKSEL PARAYI izler (tahsil
edilmediyse kasada +0). İkisini tek tabloya sıkıştırmak "bakiye" kelimesini iki
anlama gelir hâle getirirdi. `CashBookService` Company/Stay/Payment/Ledger
modellerine hiç dokunmaz.

**Tek açık vardiya — kullanıcı başına DEĞİL, işletme genelinde.** Fiziksel kasa
tektir; iki açık vardiya olsaydı "kasada şu an ne var" sorusunun tek doğru
cevabı olmaz ve devir zinciri (X → Y → Z) kopardı. Kısıt veritabanındadır:
`is_open` kolonu açıkken 1, kapalıyken NULL ve UNIQUE index taşır (MySQL tekil
indexte NULL'ları tekrarlanabilir sayar). Eşzamanlı ikinci açılış DB tarafından
reddedilir — gerçek çalıştırmayla doğrulandı.

**Tutar DECIMAL(15,2), `amount_minor` DEĞİL.** Kullanıcı taslağında `amount_minor`
önerilmişti ("Alanlar örnek" notuyla). CLAUDE.md §5/§13 bağlayıcıdır: veritabanında
para DECIMAL(15,2) tutulur, tamsayı kuruş aritmetiği PHP'de `Money` ile yapılır.
Kasa için ayrı saklama birimi kullanmak aynı uygulamada iki para konvansiyonu
demek olurdu — "en az kırıcı model" tercihi budur.

**`type` ile `direction` AYRI kolonlardır, tutar her zaman POZİTİFtir.** Yön tipin
doğal yönüdür ve TERS KAYITTA tersine döner. Böylece hem beklenen kasa hem
gelir-gider raporu ters kayıtları kendiliğinden doğru sayar; negatif tutar ya da
açıklama metni okumak gerekmez. Ledger'daki debit/credit ayrımının kasa
karşılığıdır. Veritabanı CHECK'i yön-tip uyumunu zorlar: "gelir ama kasadan
çıkan" satır yazılamaz.

**Açılış bakiyesi ayrı tablo DEĞİL, `opening` tipli bir harekettir.** Beklenen kasa
tek formülle (Σ işaretli hareket) hesaplanır ve para için iki kaynak olmaz.
Ledger'da da `source_type = 'opening'` aynı amaçla kullanılabilir durumdadır.

**Ara kasa/patron teslimi bir HAREKETTİR, devir kaydı değil.** Kullanıcı talimatı
§6 bunu zaten "ayrı bir cash transfer out hareketi" diye adlandırıyor. `cash_transfers`
tablosu YALNIZCA personel devri içindir: devir iki taraflı onay ister ve iki
vardiyayı birbirine bağlar; ara kasa teslimi tek taraflıdır ve anında olur.
İkisini tek tabloya koymak "kabul bekliyor" durumunu anlamsız kılardı.
**Ve en kritik kural:** kasa çıkışı gelir-gider raporunda GİDER ÜRETMEZ —
`CashMovementType::affectsIncomeStatement()` bu ayrımın tek kaynağıdır.

**Devir, kabul edilene kadar tamamlanmaz.** Kabul anında tek transaction içinde:
kapanış sayımı yazılır → teslim eden vardiya kapanır → teslim alan için yeni
vardiya açılır → tutarlar yeni vardiyanın açılış hareketleri olur. Sıra önemlidir:
`UNIQUE(is_open)` yüzünden eski vardiya kapanmadan yenisi açılamaz — veritabanı
sıralamayı garanti eder. Kabul `lockForUpdate` + koşullu durum kontrolüyle
alınır; iki kişi aynı devri kabul edemez.

**Sayım kabul/kapanış ANINDA yazılır, devir başlatılırken değil.** İlk tasarımda
sayım devir başlatılırken yazılacaktı; iptal edilen bir devirden sonra ikinci
devir `UNIQUE(cash_session_id, currency)` kısıtını ihlal ederdi. Sayımın tek
yazıldığı an "gerçeğin anı"dır: kabul ya da kapanış.

**Fark saklanmaz, hesaplanır.** Beklenen ve sayılan tutar değişmez olduğu için
fark her zaman aynı çıkar; saklamak üçüncü bir doğruluk kaynağı yaratırdı.

**Hesap verebilirlik:** vardiyaya hareket girmek/ters kaydetmek/devretmek YALNIZCA
vardiya sahibine açıktır — **admin dahil**. Kayıt argümanı taşıyan Policy
yetenekleri `Gate::before` ile kısa devre yapılmaz (CLAUDE.md §7.1), bu yüzden
admin kısayolu buraya sızmaz; testle doğrulandı. Tek istisna KAPATMAdır: personel
kapatmadan giderse kasa kilitli kalmasın diye `cashbook.view_all` yetkisi olan da
kapatabilir.

**Personel `cashbook.view`+`operate` alır, `view_all` ALMAZ.** Bu, §7.4'teki
"personel finansal toplamları görmez" kuralıyla çelişmez: orada yasaklanan FİRMA
cari bakiyesi ve toplam alacaktır; buradaki tutar personelin ELİNDEKİ paradır ve
saymak zorundadır. Başkasının vardiyasının tutarını göremez (ekranda kasanın
kimde olduğu görünür, tutar görünmez).

**Açılış bakiyesi ters kaydedilemez** (gerçek tarayıcı turunda fark edildi):
devirle gelen açılış, teslim edenin onayladığı devir kaydıyla birebir eşleşir;
kullanıcı onu ters kaydedebilseydi devredilen para ile kasadaki para sessizce
çelişirdi. Kural hem Policy'de hem serviste.

**Bulunan ve düzeltilen gerçek hatalar:**
1. `CashMovementPolicy::reverse()` her satır için `$movement->session`'ı lazy
   yüklüyordu — liste ekranında klasik N+1. `preventLazyLoading` testte patlattı;
   `movementsFor()` artık `session`'ı da eager yükler.
2. Pano kartı, cari defter boşken hiç çizilmiyordu: "Sistem henüz boş" bloğu
   kasayı da yutuyordu. Kasa cari hesaptan bağımsızdır — boş durum artık ikisi de
   boşken gösteriliyor.
3. Blade bileşen etiketinde `:subtitle="…\"…\"…"` — öznitelik değerindeki gömülü
   çift tırnak bileşen etiketini erken kapatıyor ve şablon sessizce derlenmiyordu.
   İfade `@php` bloğuna taşındı. (`@unless` + `@else` de Blade'de geçersizdir.)
4. Yazdırma düzeni (`layouts/print`) dar ekranda yatay kayıyordu ve araç çubuğu
   düğmeleri 33px'ti. Bu **mevcut ekstre yazdırmasında da vardı** (429>360 px,
   ölçüldü). Paylaşılan düzen düzeltildi: mm cinsinden A4 boşluğu yalnızca
   `@media print`'te, tablolar `.scroll-x` sarmalında, araç çubuğu 44px.
   Hem kasa hem ekstre kazandı.
5. "+ Hareket ekle" ana ekranda iki kez çiziliyordu (başlıkta ve aksiyon
   satırında); ikincisi kaldırıldı.

**Değiştirilmeyen (bilerek):** kur servisi/çevrim, PMS oda yönetimi, banka
entegrasyonu, muhasebe genel defteri, PDF kütüphanesi, Alpine — hiçbiri
eklenmedi (kullanıcı talimatı §22). `reference_type/reference_id` ileride
Payments/PMS bağlanabilsin diye şemada açık ama V1'de DOLDURULMUYOR; oda geliri
V1'de manuel girilir.

## K-091 · Kasa Defteri red-team doğrulaması: gerçek yarış koşulu bulundu ve kapatıldı

**Bağlam:** Gün 10'un ardından, CashBook implementasyonu gerçek otel kasa
senaryolarıyla (X→Y→ara kasa→Z devir zinciri, kasa eksiği/fazlası, eşzamanlılık,
ters kayıt, çoklu para birimi, yetki, gün sonu kapanış) kırılmaya çalışıldı.
Amaç yeni özellik değil, mevcut implementasyonun matematiğini ve devir zincirini
gerçek testlerle kanıtlamaktı.

**Bulunan gerçek hata — yarış koşulu (kritik):** `recordMovement()`,
`startHandover()` ve `closeSession()`, `guardOpenSession()` kontrolünü
`DB::transaction()` dışında, satır kilidi OLMADAN çağırıyordu; `reverseMovement()`
ise bekleyen devir kontrolünü HİÇ yapmıyordu. Kontrol ile yazma arasındaki
pencerede, tam o anda başlayan bir devir (`startHandover`) ya da tam o anda
kapatılan bir vardiya araya girebilir — devrin zaten bildirdiği tutar sessizce
stale kalırdı. **Düzeltme:** `lockOpenSession()` eklendi — vardiyayı
`lockForUpdate()` ile satır kilidiyle okur, SONRA kapalı/bekleyen-devir
durumunu kontrol eder; dördü de (`recordMovement`, `reverseMovement`,
`startHandover`, `closeSession`) artık BU metottan geçer ve kontrol+yazma
atomik bir bütündür. Gerçek InnoDB kilitlenmesi (`SELECT ... FOR UPDATE` +
kısa `innodb_lock_wait_timeout` ile ölçülen kilit bekleyişi) ile ampirik olarak
kanıtlandı — bkz. `tests/Feature/CashBook/CashBookConcurrencyTest.php`.
`closeSession()`'daki eski yol ayrıca bir ikincil hata daha üretiyordu:
bekleyen devir varken kapatma engellenmediği için `acceptHandover()`'daki
`writeCounts()` ikinci kez çağrılınca `cash_counts`'ın `UNIQUE(cash_session_id,
currency)` kısıtına çarpıp 500'e düşerdi.

**Bulunan gerçek hata — eksik değişmezlik:** `cash_transfer_amounts` ve
`cash_counts`, `cash_movements`'ın aksine UPDATE/DELETE'e karşı hiç korumasızdı.
Devir kabul edildiğinde tutar zaten yeni vardiyanın açılış bakiyesine
kopyalanmıştır; sonradan değiştirilebilseydi imzalanmış devir kaydı ile gerçek
açılış bakiyesi sessizce koparırdı. **Düzeltme:** `CashMovement` ile AYNI desen
— model `booted()` guard'ı + `update()`/`delete()` override'ı + DB tetikleyicisi
(`protect_cash_transfer_amounts_and_counts_from_writes` migration'ı). Ortak tek
`CashRecordIsImmutable` istisnası kullanıldı, varlık başına ayrı sınıf açılmadı.

**Doğrulanan, DEĞİŞTİRİLMEYEN karar — "expected mi, counted mi?":** kullanıcı
talimatının en kritik sorusu, yeni vardiyanın açılış bakiyesinin BEKLENEN
(hesaplanan) tutardan mı yoksa SAYILAN/gerçekten devredilen tutardan mı
oluştuğuydu. Denetim: `acceptHandover()` teslim edilen tutarı (`transferAmounts()`)
HEM sayım satırının "sayılan" alanına HEM yeni vardiyanın açılış hareketine
aynı değişkenden yazıyor — mevcut implementasyon zaten DOĞRUYDU, kod değişikliği
gerekmedi. Kasa eksiği/fazlasında yeni personelin kasası her zaman fiilen
teslim aldığı tutardır; fark ayrı bir `CashCount` satırında görünür, otomatik
gelir/gider hareketi ÜRETMEZ.

**Sıfır bakiyeli para birimi:** `writeCounts()` hem beklenen hem sayılan 0.00
olduğunda satır hiç yazmaz — devredilmeyen bir para biriminin ne yeni vardiyada
gereksiz bir `opening` hareketi NE DE eski vardiyada gereksiz bir sayım satırı
üretmediği doğrulandı.

**Eklenen testler:** `CashBookScenarioTest` (13, §1-§6/§9/§10/§12 senaryoları),
`CashTransferTest`'e 2 yeni test (bekleyen devir ters kaydı ve devirsiz
kapanışı da engeller — önceden yalnızca yeni hareketi engellediği test
ediliyordu), `CashBookConcurrencyTest` (4 test — `RefreshDatabase` DEĞİL
`DatabaseTruncation` kullanır, çünkü `RefreshDatabase` her testi tek bir açık
transaction'a sarar ve bu, iki GERÇEK veritabanı bağlantısının birbirini
kilitlemesini test etmeyi imkânsız kılar). Toplam: 624 test, 1959 assertion,
hepsi MySQL/MariaDB üzerinde yeşil.

**Test yazarken bulunan ortam ayrıntısı (gerçek uygulama hatası DEĞİL):**
`APP_TIMEZONE=Europe/Istanbul` iken Eloquent/Carbon ile yazılan bir zaman damgasını
ham SQL'in `NOW()`'ı ile karşılaştırmak (ikisi farklı saatleri temsil eder)
`cash_sessions_closed_after_opened` gibi bir CHECK'i sahte biçimde tetikler.
Uygulama kodu bunu hiç yapmaz (`closed_at`/`opened_at` ikisi de her zaman
Carbon üzerinden yazılır); yalnızca ham SQL kullanan test kodu buna dikkat
etmelidir — concurrency testlerinde `NOW()` yerine PHP'nin `now()`'ı bağlanır.

**Değiştirilmeyen (bilerek):** yeni özellik, UI redesign, gerçek bug dışında
refactor yapılmadı (kullanıcı talimatı bağlayıcıydı). §11 yetki testleri
zaten Gün 10'da yeterliydi (`admin_bile_baskasinin_vardiyasina_hareket_ekleyemez`,
`baskasinin_hareketi_ters_kaydedilemez` — admin'in bile finansal yazma
işlemlerinde kısayolu olmadığını zaten kanıtlıyordu); §12 gün sonu kapanışı ve
§9 ters kaydın tersinin alınamaması da Gün 10'da zaten doğru ve testliydi.

## K-092 · Dashboard V2: Operasyon/Finans ayrı sorgulanır, Lemon Bar durum verisini yeniden kullanır

**Bağlam (Gün 11):** Admin/Patron panosu tek şablonlu "klasik admin paneli"
olmaktan çıkarıldı; iki AYRI görünüme bölündü — Operasyon ("otelde şu an ne
oluyor" — kasa) ve Finans ("para ve cari hesap durumu" — ledger/firma).
Personel panosu "Vardiyam/Kasam" mantığına geçti.

**Sorgu izolasyonu kritik karardı.** `DashboardService::operations()` ve
`finance()` birbirinin verisine HİÇ dokunmaz — Operasyon seçiliyken
`ledger_entries`/`companies`/`stays`/`payments` sorgulanmaz, Finans seçiliyken
`cash_*` tabloları sorgulanmaz. Controller yalnızca seçilen dalı çağırır; eski
tek `financial()` metodu ikiye bölündü, `FinancialSummary`'den `recentEntries`
ve `cash` alanları kaldırıldı (kasa artık `OperationsSummary`'de).

**"Bugün" (Operasyon C) için yeni sorgu YAZILMADI.** Bugünün oda geliri/ek
gelir/gider/ara kasa toplamı, `CashBookService::sessionsForDate()` +
`summariesFor()`'un ZATEN hesapladığı `CashSessionTotals` nesnelerini toplayarak
üretilir (`DashboardService::aggregateTodayTotals()`). Bilinen sınır: bir
vardiya gece yarısını geçerse "bugün" o vardiyanın TÜM hareketlerini sayar
(yalnızca o günün hareketlerini değil) — V1'de vardiyalar pratikte aynı gün
kapandığı için (Gün 10 testleriyle doğrulanmış davranış) bu kabul edilebilir
bir yaklaşıklıktır; gerçek tarih-bazlı bir sorgu (yeni bir CashBookService
metodu) yalnızca bu varsayım kırılırsa eklenir.

**Lemon Bar'ın "kritik durum" metni GLOBAL bir sorgu DEĞİLDİR.** İlk tasarımda
her sayfada (ör. firma düzenleme ekranında da) kasa durumunu göstermek için bir
View Composer düşünüldü — ama bu, `cashbook.view` yetkisi olan (işletmedeki
HEMEN HEMEN HERKES) için HER sayfa yüklemesinde +1-2 sorgu demekti, sonsuza
kadar. Bunun yerine durum metni yalnızca PANO isteğinin zaten yüklediği veriden
(`@section('lemon-bar-status')`) doldurulur; diğer ekranlarda boş kalır. Bu,
paylaşımlı hosting gerçeğiyle (§2) ve "yalnızca seçilen view için gerekli veri
yüklenir" ilkesiyle (kullanıcı talimatı §17) tutarlıdır.

**Personel "Kasam"da artık Money var — bilinçli bir istisna, `PersonnelSummary`
kısıtının ihlali DEĞİL.** Gün 10'da zaten kurulmuş kural şuydu: personelin
ELİNDEKİ fiziksel para, FİRMA cari bakiyesinden ayrı bir kategoridir ve
personel bunu Kasa ekranından zaten görüyordu. Gün 11 bu veriyi panoya da
taşıdı; `PersonnelSummary` hâlâ hiçbir LEDGER/firma alanı taşımıyor — yapısal
kısıt orada, olması gereken yerde duruyor.

**Hızlı İşlem menüsü rol bazında FARKLI listeler gösterir, tek bir birleşik
liste değil.** Personel `payments.create`/`stays.create` yetkisine sahip olsa
bile global menüde bu kısayollar YOKTUR — kullanıcı talimatı §10 personel için
yalnızca 5 kasa aksiyonunu listeliyor. Bu kasıtlı bir kapsam daraltmasıdır:
panonun kendisi zaten "Bugün girdiğim kayıtlar" altında ayrı ekleme butonları
taşır, global menü kasa-öncelikli kalır.

**Bulunan gerçek test-metodolojisi hatası (uygulama hatası DEĞİL):** ilk N+1
testi "1 vardiya, 0 devir" durumunu "4 vardiya, 3 devir" durumuyla
karşılaştırıyordu. Eloquent boş bir eager-load kümesi için sorgu ÇALIŞTIRMAZ
(ör. hiç devir yoksa `toUser`/`fromUser`/`amounts` sorguları hiç atılmaz), bu
yüzden "0 devir → 3 devir" geçişi sahte bir "sorgu arttı" izlenimi veriyordu.
Düzeltme: her iki ölçüm de EN AZ BİR devir içerir (1 devirden 4 devire), asıl
soru doğru şekilde "devir sayısı arttıkça sorgu sabit mi kalıyor" oldu.

**Gerçek tarayıcı QA'de bulunan gerçek hata:** personel panosundaki "Ek Gelir"/
"Kasa Çıkışı" ghost butonları dar ekranda gizlenmiyordu — `<x-ui.button
class="hidden sm:inline-flex">` deseni, bileşenin KENDİ `inline-flex` temel
sınıfıyla AYNI elemanda çakışıyordu (ikisi de temel/önekli olmayan `display`
yardımcı sınıfı; hangisinin kazandığı Tailwind'in derleme sırasına bağlı ve
güvenilmez). Düzeltme: görünürlük SARMALAYICI `<div class="hidden sm:flex">`
düzeyine taşındı — bileşenin kendi temel sınıfıyla asla aynı elemanda çakışmaz.
Ayrıca: bekleyen bir devri olan (henüz vardiyası olmayan) kullanıcı için boş
bir "Vardiyam" başlığı altında hiçbir şey görünmediği fark edildi; o dal artık
bölümün TAMAMINI atlıyor.

## K-093 · Arama/Filtre Gün 12: yetki kapsaması filtreden önce, bakiye sıralaması PHP-taraflı

**Bağlam:** Gün 10.1'de kurulmuş olan `Core\Filters\QueryFilter` altyapısı
(Firmalar/Konaklamalar/Tahsilatlar zaten tam donanımlıydı) Kasa Defteri'ne HİÇ
uygulanmamıştı — kasa tarafında yalnızca tek-vardiya (`cashbook.index`) ve
tek-gün (`cashbook.day`) ekranları vardı, geçmişe dönük tarih/personel/tür
araması yapılamıyordu. Bu tur bunu kapattı: `cash/movements` ve
`cash/transfers` iki yeni, çapraz-vardiya, filtrelenen liste ekranı oldu.

**Yetki kapsaması sırası bağlayıcıdır: ÖNCE scope, SONRA filtre.**
`CashMovementController::index()`/`CashTransferController::index()`, temel
Eloquent sorgusunu `cashbook.view_all` yoksa `created_by`/`from_user_id OR
to_user_id = kullanıcı` ile SINIRLAR — bu adım `CashMovementFilter`/
`CashTransferFilter` hiç çalışmadan ÖNCE olur. Filtre nesnesi kendi
`user_id`/`from_user_id`/`to_user_id` parametrelerini bu ZATEN daraltılmış
sorgunun ÜZERİNE ekler. Sonuç: personel `user_id` query param'ını başka bir
personelin kimliğine değiştirse bile erişim GENİŞLEMEZ — iki koşul AND'lenmiş
olduğu için en kötü ihtimalle sonuç boş döner, asla başkasının verisi sızmaz.
Bu, "filtering authorization'dan SONRA scope üzerine uygulanmalı" ilkesinin
somut uygulamasıdır ve testle kanıtlandı
(`personel_user_id_parametresiyle_baskasinin_hareketlerini_goremez`).

**"Gelirler" hızlı filtresi iki türü birden kapsar — bunun için `type`
parametresi hem tekil hem dizi değer kabul edecek şekilde genelleştirildi**
(`type=expense` ve `type[]=room_income&type[]=extra_income` ikisi de
çalışır). Bu YENİ bir sorgu mantığı değildir, aynı beyaz listenin
`whereIn` ile birden çok değerle uygulanmasıdır — kullanıcı talimatının
"özel yeni sorgu mantığı yazma" kısıtına uygundur.

**Cari ekstre firma listesinde bakiye filtresi/sıralaması PHP tarafındadır,
SQL'de DEĞİL.** `balance` bir `companies` kolonu değildir (§6/L4 —
önbelleklenmiş bakiye kolonu V1'de kesinlikle yoktur) ve iki firmanın
bakiyesini karşılaştırmak `ledger_entries` üzerinde ayrı bir GROUP BY
gerektirir; bunu SQL'de sayfalamayla birlikte yapmak karmaşık bir alt sorgu
ister. Bunun yerine: yalnızca para birimi/bakiye filtresi ya da bakiye
sıralaması İSTENDİĞİNDE, filtreye uyan TÜM firmaların bakiyesi TEK toplu
sorguda hesaplanır (mevcut `balancesForCompanies()`, değişmedi), süzülür/
sıralanır, yalnızca GÖRÜNTÜLENECEK sayfa için firma modeli çekilir. V1 veri
hacminde (firma sayısı düşük-orta, CLAUDE.md §1) bu pragmatiktir. İSTENMEDİYSE
(çoğu ziyaret) eski hızlı SQL-sayfalama yolu DEĞİŞMEDEN çalışır — yeni kod
yolunun maliyeti yalnızca gerçekten kullanıldığında ortaya çıkar.

**Bakiyeye göre sıralama para birimi seçilmeden ÇALIŞTIRILMAZ, sessizce isim
sırasına düşer.** Para birimleri asla birleştirilmez (§6/L5) — "en yüksek
bakiye" seçilebilecek tek bir sayı olmadığı için bu istek anlamsızdır; hata
göstermek yerine öngörülebilir bir varsayılana (isim sırası) düşmek tercih
edildi.

**Filtreli ekstrenin yürüyen bakiyesi ZATEN doğruydu — denetlendi, değiştirilmedi.**
`LedgerStatementService::forCompany()` tür/arama filtrelerini yürüyen bakiye
HESAPLANDIKTAN SONRA uyguluyordu (Gün 10.1'den beri); `tur_filtresi_bakiyeyi_bozmaz`
testi bunu zaten kanıtlıyordu. Kullanıcı talimatının bu konudaki "audit et,
gerekirse düzelt" isteği bir denetimle karşılandı, kod değişikliği gerekmedi.

**Aktif filtre göstergesi (`hasActiveFilters()`/`activeFilterCount()`) TEK
yerde, `QueryFilter` temel sınıfında tanımlıdır** — sayfalama/sıralama dışında
herhangi bir query param'ın varlığına bakar, modül başına ayrı bir liste
gerekmez. Aynı prensiple mobil filtre "drawer"ı AÇILMADI: tüm ekranlar zaten
aynı `grid sm:grid-cols-2 lg:grid-cols-4` deseniyle çalışıyordu; kullanıcı
talimatının kabul ettiği "mevcut sade panel" seçeneği tutarlılık için tercih
edildi (yeni bir soyutlama katmanı CLAUDE.md §18'e aykırı olurdu).

**Gerçek tarayıcı QA'de bulunan test-metodolojisi hataları (uygulama hatası
DEĞİL):** (1) kasa hareketi güncellenemediği için (append-only DB tetikleyicisi)
bir testin `forceFill()->saveQuietly()` ile tarih "geriye alma" denemesi
gerçek bir SQL hatasıyla reddedildi — düzeltme: `recordMovement()`'ın zaten
var olan `entryAt` parametresi kullanıldı; (2) devir tutarı transferde
1000+777=1777 gibi bir toplam ürettiğinde "1.777,00 ₺" alt dizesi "777,00 ₺"yi
İÇERİYORDU, bu yüzden `assertDontSee('777,00 ₺')` yanlış geçti/kaldı — sayısal
alt dize çakışmasına karşı testler tutar yerine ayırt edici açıklama metnine
geçirildi; (3) N+1 testlerinde ısınma isteği eksikti, bu da "sorgu sayısı
AZALDI" gibi ters bir sahte sonuç üretiyordu (Gün 11'den bilinen desen) —
her ölçümden önce bir ısınma isteği eklendi.

## K-094 · v2.1.1-A: Ödeme kanalı mimarisi — nakit kasaya bağlanır, kart/banka bağlanmaz

**Bağlam:** Canlı kullanım audit'i (LEMON v2.1.1) `PaymentMethod`'un o ana
kadar salt bilgi amaçlı olduğunu, kasa ile hiç ilişkilendirilmediğini tespit
etti. Aynı audit'te P0 bir güvenlik açığı bulundu: kapanış sayımı/devir
formlarındaki tutar alanı sistemin BEKLENEN tutarıyla önceden dolduruluyordu
— personel gerçekte saymadan formu olduğu gibi gönderebilirdi.

**Karar 1 — Kanal ayrımı, yeni modül DEĞİL:** `Payments\Services\PaymentService`
artık `CashBookService`'i TEK YÖNLÜ çağırıyor (Stays/Payments'ın zaten
`LedgerService`'e yaptığı desenin aynısı, CLAUDE.md §4.3/1). `CashBookService`
Payments'ın var olduğunu hâlâ bilmiyor — `cash_movements.reference_type/
reference_id` (§3.3'ten beri açık, hiç doldurulmamış kolonlar) artık ilk kez
kullanılıyor ve CashBookService bu değerlerin anlamını bilmeden yalnızca
saklıyor. Yeni bir Model/Servis katmanı, yeni bir "ödeme" soyutlaması AÇILMADI
— mevcut `Payment` modeli kanonik tahsilat kaydı olarak KORUNDU.

**Karar 2 — Yeni `CashMovementType::CompanyCollection` ('company_collection'),
`selectable()`'da YOK:** Nakit tahsilatın kasa karşılığı, mevcut `room_income`/
`extra_income` (manuel, tahsilattan bağımsız girişler) ile KARIŞTIRILMADI —
ayrı bir tip açıldı ki iki farklı kavram (kasadan elle girilen gelir vs.
tahsilattan otomatik gelen nakit) birbirine karışmasın ve raporlanabilir
kalsın. `selectable()`'da YOK: personel Kasa Hareketi formundan elle
seçemez — aksi hâlde aynı tahsilatın kasa etkisi iki farklı yoldan (elle +
otomatik) üretilebilir, tek doğruluk kaynağı ilkesi bozulurdu.
`affectsIncomeStatement() = true`: gerçek gelirdir, firma borcu fiilen
azalmıştır — `cash_out`/`cash_in`'in "para taşır, gelir/gider üretmez"
ilkesiyle KARIŞTIRILMAMALIDIR.

**Karar 3 — Yalnızca vardiya sahibi nakit tahsilat girebilir/iptal edebilir:**
CLAUDE.md §8/Kasa Defteri "Hesap verebilirlik" kuralının ("admin/patron dahil
kimse başkasının vardiyasına hareket yazamaz") DOĞAL bir uzantısı olarak
uygulandı — yeni bir istisna İCAT EDİLMEDİ. Nakit tahsilat kaydı bir kasa
hareketi ÜRETTİĞİ için aynı kural geçerli: `PaymentService::create()` açık
vardiyayı `activeSession()`'dan okur, sahibi aktörle eşleşmiyorsa
`CashSessionNotOwnedByActor` fırlatır, açık vardiya hiç yoksa mevcut
`NoOpenCashSession` (CashBook modülünden yeniden kullanıldı, kopyalanmadı)
fırlatır — HER İKİ durumda da tüm işlem (Payment + Ledger dahil) geri alınır.

**Karar 4 — İptalde kasa düzeltmesi orijinal vardiyaya değil GÜNCEL açık
vardiyaya yazılır:** `CashBookService::reverseMovement()`'a geriye dönük
uyumlu iki yeni parametre eklendi: `?CashSession $intoSession = null`
(verilmezse eski davranış — orijinal hareketin KENDİ vardiyasına, hâlâ açık
olmalı) ve `bool $allowLinkedSource = false`. 7/24 işletmede bir tahsilat,
onu alan vardiya çoktan kapanmış/devredilmiş olduktan SONRA iptal edilebilir;
CLAUDE.md'nin "ters kayıt orijinalin tarihini taşır" ilkesi CARİ tarafı için
hâlâ geçerli (Ledger değişmedi) ama KASA tarafı için para geçmişe gidip
kapanmış bir vardiyayı değiştiremez — bunun yerine güncel vardiyaya bir
düzeltme yazılır ("bugünün kasası beklenenden X az, çünkü geçmiş bir tahsilat
iptal edildi"), tıpkı gerçek bir kasanın çalıştığı gibi. Kasa tarafı
düzeltilemiyorsa (o an açık vardiya yok) TÜM iptal reddedilir — cari ile kasa
yarım kalmış biçimde ayrışmaz (`DB::transaction()` zaten bunu garanti eder,
yeni olan yalnızca "hangi vardiyaya yazılacağı" kararı).

**Karar 5 — Bağlı hareket genel Kasa Hareketleri ekranından ters
kaydedilemez:** `allowLinkedSource` varsayılan `false`'tur — genel ekran
(`CashMovementController::reverse()`) hiçbir zaman `true` geçmez.
`CashMovementPolicy::reverse()` da aynı kısıtı UI seviyesinde uygular
(`reference_type !== null` ise buton hiç görünmez). İkisi de gerekli: Policy
HTTP'yi korur, Service son savunmadır (CLAUDE.md §7.1). Bu korumanın
gerekçesi somut: korunmasaydı personel kasa tarafını düzeltip tahsilatı/
cariyi düzeltmeden bırakabilirdi — aynı mali etki iki farklı yoldan yarım
sonlandırılmış olurdu.

**Karar 6 (P0 düzeltme) — Sayım/devir tutar alanı ASLA ön-doldurulmaz:**
`_amount-fields.blade.php`'deki `$default = $expectedMoney?->toDecimal()`
kaldırıldı; alan yalnızca `old()`'dan (doğrulama hatası sonrası kullanıcının
KENDİ yazdığı değer) doldurulur. Beklenen tutar hâlâ ipucu METNİNDE görünür
("Beklenen: …") — bilgi kaybolmaz, yalnızca giriş alanının GERÇEK değeri
olmaktan çıkarılır. `sessions/open.blade.php` zaten bu deseni doğru
uyguluyordu (hiç `expected` geçmiyordu); düzeltme yalnızca `close`/`transfers.
create` ekranlarını bu ZATEN VAR OLAN doğru desenle hizaladı.

**Karar 7 — Vardiya kanal özeti yeni bir sorgu YAZAR ama SABİT sayıdadır:**
"Bir vardiyada kim ne kadar kart/banka tahsilatı aldı" sorusu için `payments`
tablosuna `cash_session_id` kolonu EKLENMEDİ (kart/banka hiç kasaya
yazılmadığı için oturumla fiziksel bir bağı yok) — bunun yerine vardiya
sahibinin (`created_by`) o vardiyanın zaman penceresinde (`opened_at`–
`closed_at`/şu an) yazdığı kayıtlarla yaklaşıklandı; bu, CashBook'un
"yalnızca vardiya sahibi hareket yazar" kuralıyla tutarlıdır. Migration
gerekmedi.

**Karar 8 — Geriye dönük uyum:** hiçbir mevcut `payments` satırı taşınmadı/
değiştirilmedi. `payment_method` zaten `nullable` olduğu için "eski/
sınıflandırılamayan kayıt" durumu zaten `NULL` ile temsil ediliyordu — yeni
bir "legacy" değeri İCAT EDİLMEDİ. `payment_method = 'cash'` taşıyan ama bu
özellikten ÖNCE oluşturulmuş (bağlı kasa hareketi hiç olmayan) bir kayıt,
iptal edildiğinde `CashBookService::activeMovementForSource()` null döner ve
kasa tarafı sessizce atlanır — açık vardiya ZORUNLULUĞU dahi aranmaz (yoksa
var olmayan bir şeyi düzeltmeye çalışırdı). `cash_movements_type_valid` ve
`cash_movements_direction_matches_type` CHECK'leri ileri-yönlü migration'la
(DROP + yeniden CREATE, `add_cheque_to_payment_methods` ile AYNI kalıp)
genişletildi; mevcut satırlar hiç etkilenmedi.

**Karar 9 — Yeni yetki AÇILMADI:** Kural mevcut `payments.create` +
`cashbook.operate` üzerinden yürür (Personel'de zaten ikisi de var,
CLAUDE.md §7.3). `RolePermissionSeeder`'a dokunulmadı — canlı ortamda
yaşanan `cashbook.operate` eksikliği olayının AYNISININ tekrarlanmaması için
bilinçli bir tercih: yeni yetki = yeni seed riski.

**Etkilenen dosyalar (özet):** `CashMovementType`, migration (yeni CHECK),
`CashSessionTotals` (+`companyCollection`), `CashBookService` (`recordMovement`/
`reverseMovement`/`insertMovement` imzaları + `activeMovementForSource()`),
`CashMovementPolicy`, `DashboardService::combineTotals()`, `PaymentMethod`
(docblock), `PaymentService` (kanal mantığı + `shiftCollectionSummary()` +
`totalsByMethodAndCurrencyBetween()`), yeni `CashSessionNotOwnedByActor`
istisnası, yeni `ShiftCollectionSummary` DTO'su, `PaymentController`,
`_amount-fields.blade.php` (P0).

## K-095 · v2.1.1-A.1: Vardiya atfı EXPLICIT FK'dir, zaman penceresi çıkarımı DEĞİL

**Bağlam:** K-094'ün (`470009f`) `shiftCollectionSummary()`'si kart/banka
tahsilatlarını vardiya sahibinin (`created_by`) o vardiyanın zaman
penceresinde (`opened_at`–`closed_at`) yazdığı kayıtlarla YAKLAŞIKLIYORDU.
Bu, finansal/operasyonel raporlama için yeterince belirleyici değildi: aynı
kullanıcı vardiya sınırına yakın bir anda işlem yaparsa yanlış vardiyaya
atfedilebilirdi.

**Karar 1 — `payments.cash_session_id`: nullable FK, `cash_sessions`e:**
Kart/banka/çek hiçbir zaman bir `CashMovement` ÜRETMEZ (K-094'ten değişmedi
— kasa yalnızca nakit içindir), ama "hangi vardiyada alındığı" bilgisi hâlâ
operasyonel olarak değerlidir. Referans bu yüzden `cash_movements` üzerinde
DEĞİL, doğrudan `payments` üzerindedir. Nakit kanalda da AYNI kolon
kullanılır — kasaya yazılan hareketin kendi `cash_session_id`'siyle birebir
aynı vardiyayı gösterir (iki farklı fiziksel/mali kayıt, TEK ortak atıf).

**Karar 2 — Atıf kuralı TEK ve BASİTTİR:** aktör oluşturma anında açık BİR
vardiyayı YÖNETİYORSA (`activeSession()->opened_by === aktör`) o vardiyaya
atanır; aksi hâlde (açık vardiya yok VEYA başkasının vardiyası açık) NULL
kalır. Nakit kanalda bu vardiya ZORUNLUDUR (K-094'ten değişmedi —
`requireOwnedOpenSession()` istisna fırlatır); diğer kanallarda İSTEĞE
BAĞLIDIR (`resolveOwnedActiveSession()` sessizce NULL döner, hiçbir kasa
açma zorlaması YAPILMAZ — Admin/Patron açık vardiya olmadan da kart/banka
tahsilatı girebilmelidir, kullanıcı talimatı §7).

**Karar 3 — Çek (`cheque`) ve "diğer" için AYRI bir kural İCAT EDİLMEDİ:**
kullanıcı talimatı §6 açıkça "karar ver/dokümante et" diyordu. Tutarlılık
ve gereksiz özel-durum yasağı (CLAUDE.md §18) gereği, TÜM nakit-dışı kanallar
(kart/banka/çek/diğer/boş) AYNI tek kuralı paylaşır: atfedilebilirse atfet,
edilemiyorsa zorlamayan NULL. Çeke yeni bir kasa etkisi eklenmedi.

**Karar 4 — `cash_session_id` `IMMUTABLE_FIELDS`'a eklendi, `$fillable`'a
EKLENMEDİ:** iki farklı koruma katmanı, iki farklı amaç. `$fillable` dışında
tutulmak, mass-assignment (kullanıcı girdisinden `fill()`) yolunu TAMAMEN
kapatır — `payment_method`/`amount` gibi zaten `$fillable`'da olan alanlardan
FARKLI olarak, bu alan HİÇBİR ZAMAN kullanıcı girdisinden gelmez, yalnızca
servis içinde doğrudan özellik atamasıyla (`$payment->cash_session_id = ...`)
yazılır. `IMMUTABLE_FIELDS`'a eklenmesi ise modelin `updating` olay
korumasını (son savunma, CLAUDE.md §7.1) devreye sokar: servis atlanıp
doğrudan `$payment->cash_session_id = X; $payment->save();` çağrılsa bile
(zaten var olan bir kayıtta) reddedilir. İptal (`cancel()`) bu alana HİÇ
dokunmaz — kaynak işlem hangi vardiyada olduysa öyle kalır (kullanıcı
talimatı §12); kasa tarafının GÜNCEL vardiyaya yazılan düzeltmesi (K-094) bu
kararla ÇELİŞMEZ, çünkü o `CashMovement.cash_session_id`'dir, `Payment.
cash_session_id` değil — iki AYRI referans, iki AYRI anlam.

**Karar 5 — `shiftCollectionSummary()` zaman penceresi sorgusu TAMAMEN
kaldırıldı, yerine `WHERE cash_session_id = ?` konuldu:** eski sorgu SADECE
bu mimariden SONRA oluşturulan kayıtlar için doğru sonuç verirdi ve yanlış
bir kesinlik hissi yaratırdı — kaldırılması "silme" değil, yanlış bir
kesinliği ortadan kaldırmaktır. Eski (NULL `cash_session_id` taşıyan) kayıtlar
hiçbir vardiya özetine sızmaz (testle kanıtlandı); bu kayıtlar hâlâ
`totalsByMethodAndCurrencyBetween()` gibi TARİH ARALIĞI bazlı (vardiyadan
bağımsız) raporlarda görünür durumdadır — "legacy/unassigned" için ayrı bir
UI/rapor ekranı bu turda AÇILMADI (kullanıcı talimatı §17'nin "yeni UI
genişlemesi yok" ilkesiyle tutarlı); gereken yalnızca "tahmin etmemek"ti,
yeni bir görünürlük ekranı değil.

**Migration:** forward-only, `payments`e nullable `cash_session_id` FK
(`restrictOnDelete()`) + `(cash_session_id, payment_method)` bileşik index
(vardiya kanal özeti sorgu deseniyle birebir). `migrate:fresh` kullanılmadı;
mevcut satırlar hiç etkilenmedi, hepsi NULL ile başlar.

**Etkilenen dosyalar:** yeni migration, `Payment` modeli (`cashSession()`
ilişkisi, `IMMUTABLE_FIELDS`, cast), `PaymentService` (`create()`,
`resolveOwnedActiveSession()`, `postCashCollection()` imzası,
`shiftCollectionSummary()`/`collectionTotalsForSession()` yeniden yazıldı),
`ShiftCollectionSummary` DTO docblock'u.

## K-096 · v2.1.1-B: Yönetilebilir kasa kategorileri, birleşik Hızlı İşlem, Cari Tahsilat makbuzu

**Bağlam:** K-094/K-095 (`470009f`, `6419f09`) ile onaylanan ödeme kanalı
mimarisi DEĞİŞTİRİLMEDEN, dört alanda genişleme istendi: (1) admin'in kasa
hareket TÜRLERİNİ (etiket düzeyinde) yönetebilmesi, (2) Gün 11'in rol bazlı
iki ayrı Hızlı İşlem listesinin sadeleştirilmesi, (3) "Tahsilat ekle"
adlandırmasının "Cari Tahsilat" olarak birleştirilmesi, (4) yazdırılabilir bir
Tahsilat Makbuzu.

**Karar 1 — Kategori, `CashMovementType`in YERİNE geçmez; ÜZERİNE bir etiket
katmanıdır:** `cash_movement_categories.system_type` sütunu, veritabanı
CHECK'i ile `CashMovementType::selectable()`'ın 5 değeriyle (room_income,
extra_income, expense, cash_out, cash_in) SINIRLANIR — `opening` ve
`company_collection` (sistem üretimli) hiçbir zaman bir kategoriye atanamaz.
Admin yeni bir MUHASEBE DAVRANIŞI icat edemez, yalnızca var olan 5 sabit
davranıştan birine YENİ BİR İSİM/liste sırası/teslim-alan-zorunluluğu
tanımlayabilir (kullanıcı talimatı §2: "Admin must NOT be able to invent
arbitrary accounting behaviour"). `system_type` yalnızca OLUŞTURURKEN
seçilir; `UpdateCashMovementCategoryRequest` bu alanı hiç TANIMLAMAZ —
aynı ada sonradan farklı bir davranış (önce gelir, sonra gider) bağlanıp
geçmiş raporları sessizce yanıltmasın diye.

**Karar 2 — Kategori asla hard-delete edilmez; `category_label` bir ANLIK
GÖRÜNTÜdür:** `CashMovementCategoryController`'da `destroy()` YOKTUR, hiçbir
route DELETE fiili taşımaz — pasifleştirme (`is_active=false`) TEK yoldur
(§5.8'deki kasa hareketi/sayım değişmezlik ilkesiyle aynı gerekçe). Ama
kategori adı DEĞİŞTİRİLEBİLİR olduğu için, `cash_movements.category_label`
her yazımda kategorinin O ANKİ adının bir KOPYASINI taşır — `CashMovement::
label()` bu snapshot'ı kullanır, `category_id` üzerinden canlı bir JOIN
YAPMAZ. Kategori sonradan "Mini Bar"dan "Market"e yeniden adlandırılsa bile
geçmiş hareket hâlâ "Mini Bar" yazar; bu, `docs/kararlar.md`'nin kendi
ilkesiyle (geçmiş kayıt sessizce değişmez) birebir örtüşür ve testle
kanıtlanmıştır (`CashMovementCategoryTest::
kategori_yeniden_adlandirilinca_gecmis_hareketin_etiketi_degismez`).

**Karar 3 — Hızlı İşlem TEK, birleşik, yetki-filtreli bir listeye indirgendi:**
Gün 11'in personel-özel (`?type=room_income` vb. 5 kasa kısayolu) ve
yönetici-özel (kayıt oluşturma, 5 kısayol) İKİ AYRI dalı KALDIRILDI. Gerekçe:
kasa hareket TÜRÜ artık admin tanımlı, dinamik bir kategoridir — aynı sistem
davranışına (`extra_income`) birden fazla aktif kategori eşlenebilir ("Mini
Bar" ve "Market" ikisi de `extra_income`), bu yüzden tek bir kategoriye
ÖNCEDEN bağlanan bir kısayol (`?type=extra_income`) artık BELİRSİZDİR. Yeni
liste (`Kasa Hareketi` / `Cari Tahsilat` / `Yeni Konaklama` / `Yeni Firma` /
`Kasa Devri`) HERKES için AYNIDIR; rol farkı yalnızca `$user->can(...)`
filtresinden geçen satırlarla ortaya çıkar — yeni bir işlem mantığı
YAZILMADI, her satır zaten var olan create route'una yönlendirir.

**Karar 4 — Makbuz numarası, `Payment::id` üzerinden TÜRETİLİR; ayrı bir sıra
tablosu AÇILMADI:** `TM-{Y}-{id:06d}` biçimi, MySQL'in kendi AUTO_INCREMENT'i
kadar eşzamanlılığa karşı güvenlidir ve `MAX()+1` yarış koşulu riski TAŞIMAZ.
Bilinen ve KABUL EDİLEN sınırlama: sayısal kısım yıl başına 1'e SIFIRLANMAZ
(id tabanlıdır) — bu, "en küçük sağlam çözüm" tercihinin (kullanıcı talimatı
§13) bilinçli bedelidir, ayrı bir sıra sayacı + kilitleme mantığına karşı
tercih edilmiştir. Numara, `Payment::create()` İÇİNDE, `id` atandıktan HEMEN
SONRA `saveQuietly()` ile yazılır — bu, modelin `updating` olay korumasını
(IMMUTABLE_FIELDS) BİLEREK atlar, çünkü bu dışarıdan bir "düzenleme" değil,
`create()` akışının kendi TAMAMLANMASIDIR. `receipt_no`, `IMMUTABLE_FIELDS`e
eklendi — iptal DAHİL hiçbir işlem sonradan değiştiremez (K-095'teki
`cash_session_id` ile AYNI iki katmanlı koruma deseni: model + servis +
FormRequest, `$fillable`'da YOK).

**Karar 5 — Tahsilat Makbuzu bir FATURA/e-Arşiv DEĞİLDİR; bu, ekranın
KENDİSİNDE açıkça yazılıdır:** `layouts.print`'i genişletir (Vite/tema
yüklemez, A4 sade CSS — Cari Ekstre/Devir Raporu ile AYNI desen), firma yasal
başlığı `config('hotel.company.*')`'dan gelir — bu, gerçek bir Settings
modülü açılana kadar GEÇİCİ bir yerleşimdir (kod içinde "GEÇİCİ YERLEŞİM"
diye işaretlidir, kullanıcı talimatı §14'ün "en küçük merkezi çözüm, dev
Settings modülü açma" izniyle). "Yalnız" alanı (yazıyla tutar) YALNIZCA TRY
için üretilir (`Payment::amountInWords()`) — yabancı para birimleri için
UYDURMA/yanlış bir çeviri üretmek yerine `null` döner, ekran bu durumda
yalnızca sayısal tutar + ISO kodunu gösterir ("correctness over decorative
completeness", kullanıcı talimatı §15). `TurkishNumberToWords::titleCase()`
ayrı yazıldı: `mb_convert_case(MB_CASE_TITLE)` Türkçe küçük "i"yi ASCII "I"ya
çevirir ("iki"→"Iki"), doğrusu noktalı "İki"dir — bu, kod incelemesinde
kendiliğinden yakalanan bir hataydı, testle korunur.

**Karar 6 — İzlenebilirlik (`Payment`↔`CashMovement`) raw DB id ile DEĞİL,
insan-okunur metinle kurulur; CashBook Payments'ı TANIMAZ:** `CashBookService
::postCashCollection()`'ın yazdığı `CashMovement.description`'a makbuz
numarası EKLENİR (`Tahsilat: 09.09.2026 · Nakit · TM-2026-000123`) —
`CashBookService`'in kendisi bu string'in ANLAMINI hâlâ bilmez, `PaymentService`
onu HAZIRLAYIP GEÇİRİR (§4.3/3'teki tek yönlü bağımlılığın AYNISI). Kasa
Hareketleri ekranındaki satır, `reference_type === 'payment'` olduğunda bu
açıklamayı `payments.show` route ADINA (yalnızca string, hiçbir Payments
sınıfı import edilmeden) bağlar — CLAUDE.md §4.3'ün "modül başka modülün
Model'ini kullanamaz" kuralı ihlal edilmez, yalnızca Blade katmanında bir
route-adı bağlantısı kurulur. Ters yönde, `payments::show` zaten var olan
`Payment.cashSession` ilişkisiyle (K-095) bağlı vardiyaya link verir —
Payments'ın CashBook'a bağımlılığı zaten K-094'ten beri TEK YÖNLÜDÜR, bu yeni
bir bağımlılık AÇMAZ.

**Karar 7 — `CashMovementCategory` da morph map'e EKLENDİ:** `Core\Concerns\
Auditable` trait'i (denetim kaydı için) `Relation::enforceMorphMap()`'in
kesin listesine bağımlıdır (§5.7) — model listeye eklenmeden seeder/servis
ilk kategori yazımında `MorphMapException` fırlatır. `'cash_movement_category'
=> CashMovementCategory::class` eklendi; bu hem seeder'ın hem canlı admin
ekranının kategoriye audit log üretmesi için gereklidir.

**Migration'lar (3, hepsi forward-only, `migrate:fresh` KULLANILMADI):**
`cash_movement_categories` (yeni tablo, CHECK ile system_type kısıtlı),
`cash_movements.category_id`/`category_label` (nullable, restrictOnDelete),
`payments.receipt_no` (nullable unique varchar(20)) — mevcut satırlar
`receipt_no = NULL` ile başlar, geriye dönük makbuz ÜRETİLMEZ (dürüst
sınırlama, K-094/K-095'teki aynı ilke); `payments::show` ve `receipt` route'u
bu durumu 404 ile karşılar, tahsilatın kendi detay ekranını BOZMAZ.

**Yeni yetki:** `cashbook.manage_categories` — Admin (otomatik, tüm yetkiler)
ve Patron'a (açıkça) verildi; Personel'e VERİLMEDİ. `cashbook.view_all`
yeniden kullanılmadı: "tüm vardiyaları görmek" ile "kategori ana verisini
yönetmek" farklı yetki anlamlarıdır.

**Etkilenen dosyalar (özet):** 3 migration, `CashMovementCategory` modeli +
factory + policy + controller + 2 FormRequest + 3 view, `CashMovementType`
DEĞİŞMEDİ (yalnızca kullanım şekli değişti), `CashMovement` (`category_id`/
`category_label` cast + `label()`), `CashBookService` (`recordMovement()`/
`insertMovement()`/`reverseMovement()` imzalarına `categoryId`/`categoryLabel`
eklendi — ters kayıt orijinal kategorinin etiketini KORUR), `Payment` modeli
(`receipt_no`, `amountInWords()`, `receiptNumberFor()`), `PaymentService`
(makbuz üretimi, `postCashCollection()` açıklaması), `PaymentMethod::primary()`
(Cari Tahsilat formunda `Other` gösterilmez, raporlama hâlâ `cases()` kullanır),
`TurkishNumberToWords` (yeni, Core), `config/hotel.php` (`company.*`, geçici),
`AppServiceProvider` (morph map), `partials/quick-action.blade.php` (yeniden
yazıldı), sidebar (Kasa Hareket Türleri girdisi), `movements/_row.blade.php`
ve `payments::show` (çift yönlü izlenebilirlik bağlantıları).
