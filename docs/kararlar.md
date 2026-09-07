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
