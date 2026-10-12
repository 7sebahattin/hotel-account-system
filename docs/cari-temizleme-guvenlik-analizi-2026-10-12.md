# Cari tek seferlik veri temizleme — güvenlik ve şema analizi

Tarih: 12 Ekim 2026. Repo: `7sebahattin/hotel-account-system`.
Başlangıç main: `680ba3e4c34e2346cb6f9ae197b07f09453cacb2`.
Başlangıç release: `16`. Görev başlangıcındaki GitHub kontrolünde açık PR yoktu.
Çalışma dalı: `codex/one-time-cari-cleanup`.

Bu belge başlangıç main kaynak kodunun incelemesidir. Canlı veritabanından kayıt
sayısı veya canlı şema envanteri çıkarıldığı anlamına gelmez. Sayfanın çalışma,
test, CI ve deploy sonuçları ayrıca doğrulanmalıdır; bu belgede tamamlanmış
sayılmaz. **Canlı veri silme yapılmadı.**

## Karar

**Tam fiziksel CARİ temizliği, mevcut finansal geçmiş bulunan veritabanında
koruma kurallarıyla bağdaşmaz. Silme bloke edilmelidir.** Bir adet
`ledger_entries` veya `payment_allocations` kaydı bile fiziksel temizleme
engeli oluşturur. İptal/ters kayıt bu satırları kaldırmaz. Bakiye sıfır olması
silme izni değildir.

Bu paket yalnız Admin erişimli, salt okunur sayım ve ön kontrol ekranı olarak
hazırlanır. Silme servisi veya yürütme route'u yoktur; ekranda gösterilen
engellenmiş durum server tarafındaki bu sınırın yerine geçmez. Sıfır finansal
satırla karşılaşılması da bu pakette silmeyi açmaz. Kısmi temizlik yapılmaz.
Finansal trigger/FK korumaları ve migration geçmişi değiştirilmez.

Bağlayıcı kaynaklar: `CLAUDE.md` §6/L1, L4, L6–L9, §18; kullanıcı görev
metnindeki korumaları atlatmama ve tam temizlik mümkün değilse bloke etme
talimatı. `AGENTS.md` ve `docs/AI_WORKFLOW.md` başlangıçta okundu.

## Menü ve kaynaklar

`resources/views/partials/sidebar.blade.php:18–29`, CARİ altında şirketler,
konaklamalar, teklifler, tahsilatlar, makbuzlar, ekstre ve grafiği listeler.
HESAP `:33–36`, Personel `:39–48`, Yönetim `:51–59` ayrı gruplardır.

Ekstre/Grafik için ayrı tablo yoktur. `StatementController.php:160–170`
(`app/Modules/Companies/Http/Controllers/`) şirketleri ve ledger tabanlı bakiye/
son hareket servislerini okur. `LedgerStatementService.php:84–125`
(`app/Modules/Ledger/Services/`) toplu sorguları `ledger_entries` üzerinde yapar.
Şirket tablosunda saklanmış bakiye yoktur.

## Tam tablo sınıflandırması

Kaynak migration'lar 47 tablo tanımlar. Laravel'in kendi `migrations`
metadata tablosuyla beklenen katalog 48 tablodur. Bu liste canlıdaki ekstra
tabloları dışlamaz: canlı şemada bulunan **diğer her tablo varsayılan korunur**.
Yeni/bilinmeyen bağımlılık güvenli sayılmaz. Silinebilirlik sütunu bağımsız
şema değerlendirmesidir; hiçbir satır bu paket için silme izin listesi değildir.

Kısaltmalar: R = `RESTRICT`; C = `CASCADE`; N = `SET NULL`; U/D = UPDATE/DELETE.
FK tablosu belirtilen yerler hedef tablodur; aksi açıklanmıyorsa R geçerlidir.
“Trigger yok” kaynak migration'larda o tablo için koruma trigger'ı bulunmadığını
anlatır, canlıda ek trigger bulunmadığı iddiası değildir.

### CARİ inceleme adayları — 10 tablo

| No | Tablo / sahibi | FK ve silme bağımlılıkları | Geçmiş / trigger / sonuç |
|---:|---|---|---|
| 1 | `companies` — Cari hesaplar | `created_by`, `updated_by` → users R. Ledger/stays/payments/offers → companies R. | Şirket+bireysel hesap; soft-delete var. Finansal geçmiş varsa silinemez. |
| 2 | `stays` — Konaklamalar | company/users/nullable room R; allocations → stays R. | Aktif/iptal/soft-delete geçmiş. Geçmiş dağılım, ters dağılım olsa da silmeyi engeller. |
| 3 | `payments` — Tahsilatlar | company/users/nullable cash_session R; allocations → payments R. | Aktif/iptal finansal belge. Ledger kaynak işaretçisi ve eski kasa referansları da incelenir. |
| 4 | `payment_allocations` — Tahsilat dağılımları | payment/stay/created_by/self reversal R. | Pozitif dağılım ve ters dağılım; U/D trigger ile yasak. Tam temizlik engeli. |
| 5 | `ledger_entries` — Cari defter | company/created_by/self reversal R. | Append-only finansal defter; U/D trigger ile yasak. Tam temizlik engeli. |
| 6 | `offers` — Teklifler | nullable company R; nullable creator/updater N; çocuk teklif tabloları C. | Snapshot belge; finansal defter üretmez. Bağımsız silinebilirlik tam CARİ temizliğini mümkün kılmaz. |
| 7 | `offer_periods` — Teklif dönemleri | offer C; prices → periods C. | Belge alt kaydı; trigger yok. |
| 8 | `offer_room_types` — Teklif oda türleri | offer C; prices → room_types C. | Belge alt kaydı; rooms siciline FK yok; trigger yok. |
| 9 | `offer_prices` — Teklif fiyatları | offer/period/room_type C. | Belge alt kaydı; trigger yok. |
| 10 | `receipt_book_entries` — Tahsilat makbuzları | creator/updater users R. Payment/ledger/cash FK yok. | Bağımsız yazdırma belgesi; güncel Admin delete route/servisi var. Normal silme denetim satırı ekler. Kısmi temizlik olarak silinmez. |

### HESAP — 4 tablo, tamamı korunacak

| No | Tablo | FK / trigger / niteliği |
|---:|---|---|
| 11 | `monthly_account_days` | owner/creator/updater users R. DELETE yasak; owner/date UPDATE guard. Gün ve mevcut sürüm bilgisi. |
| 12 | `monthly_account_revisions` | day/users R. U/D yasak; tarihsel revizyon/tutar/not. |
| 13 | `monthly_account_items` | revision R. U/D yasak; yöntem/kur/tutar tarihçesi. |
| 14 | `monthly_payment_providers` | creator/updater users R. CARİ FK yok; banka/ödeme kuruluşları aynen korunur. |

### Personel — 11 tablo, tamamı korunacak

Bu tablolardan CARİ şirket/konaklama/tahsilat/ledger tablolarına FK yoktur.
Personel finansı bağımsızdır.

| No | Tablo | FK / trigger / niteliği |
|---:|---|---|
| 15 | `personnel_employees` | creator/updater users R. Çalışan ana kaydı; personel alt kayıtları bağlı. |
| 16 | `personnel_shifts` | creator/updater users R. Vardiya ana kaydı; versiyonlar bağlı. |
| 17 | `personnel_shift_versions` | shift/creator users R. U/D yasak; vardiya geçmişi. |
| 18 | `personnel_shift_assignments` | employee/shift_version/creator R. U/D yasak; atama geçmişi. |
| 19 | `personnel_wage_assignments` | employee/creator R. U/D yasak; ücret geçmişi. |
| 20 | `personnel_cards` | creator/updater users R. UID değişikliği yasak; kart ana kaydı. |
| 21 | `personnel_card_assignments` | card/employee/assigned_by/released_by R. DELETE yasak; yalnız bir kez sonlandırma UPDATE guard. |
| 22 | `personnel_terminals` | creator users R. Terminal/sır hash'i ve kimlik bilgileri. |
| 23 | `personnel_scan_events` | nullable card/card_assignment/employee/terminal/actor_user ve self duplicate R. U/D yasak; ham okutma geçmişi. |
| 24 | `personnel_attendance_corrections` | employee/creator/self supersedes R. U/D yasak; puantaj düzeltme geçmişi. |
| 25 | `personnel_payroll_entries` | employee/creator/self reversal R. U/D yasak; bağımsız finansal geçmiş. |

Kaynak: `app/Modules/Personnel/Database/Migrations/2026_09_25_000001_create_personnel_tables.php`;
immutable liste `:18–22`, trigger'lar `:190–198`.

### Eski kasa arşivi — 6 tablo, tamamı korunacak

| No | Tablo | FK / trigger / niteliği |
|---:|---|---|
| 26 | `cash_sessions` | opened_by/closed_by users R. Movements/counts/transfers ve eski payments bağlı. Tarihsel oturum. |
| 27 | `cash_movements` | session/creator/category/self reversal R. U/D yasak; INSERT yalnız açık oturum. FK'siz reference_type/reference_id. Tarihsel para hareketi. |
| 28 | `cash_transfers` | from/to session, from/to user R. Transfer amounts bağlı; tarihsel devir. |
| 29 | `cash_transfer_amounts` | cash_transfer R. U/D yasak; devir tutarları. |
| 30 | `cash_counts` | cash_session/created_by users R. U/D yasak; sayım tutarları. |
| 31 | `cash_movement_categories` | creator/updater users R; movements → category R. Kategori sicili. |

Modül kaldırılmış olsa da migration'lar
`app/Providers/AppServiceProvider.php:42` ile yüklenmeye devam eder. Arşiv
koruması kaldırılmış değildir.

### Yönetim, kimlik, tercihler ve denetim — 9 tablo, tamamı korunacak

| No | Tablo | FK / trigger / niteliği |
|---:|---|---|
| 32 | `users` | nullable created_by self R; modüllerin actor/kimlik kaynağı. Soft-delete var. |
| 33 | `user_quick_menus` | user C. Kişisel shortcuts JSON'u; users silinmediği için cascade çalışmaz. |
| 34 | `roles` | Yetki pivotları bağlı; sistem rolü bilgisi. |
| 35 | `permissions` | Yetki pivotları bağlı. |
| 36 | `model_has_roles` | role C; model_type/model_id FK'siz polimorfik bağ. |
| 37 | `model_has_permissions` | permission C; model_type/model_id FK'siz polimorfik bağ. |
| 38 | `role_has_permissions` | role/permission C. |
| 39 | `rooms` | creator/updater users R; stays → room R. Oda sicili. |
| 40 | `audit_logs` | nullable user N; auditable_type/auditable_id FK'siz tarihsel işaretçi. Model/policy U/D'yi yasaklar; kaynakta DB U/D trigger yok. |

### Framework — 8 tablo, tamamı korunacak

| No | Tablo | Niteliği / bağımlılığı |
|---:|---|---|
| 41 | `password_reset_tokens` | Kimlik kurtarma; CARİ ilişkisi yok. |
| 42 | `sessions` | Oturum; nullable user_id indeksli, migration'da FK yok. |
| 43 | `cache` | Framework cache; CARİ temizliği hedefi değildir. |
| 44 | `cache_locks` | Framework kilitleri; temizleme/ön kontrol adına değiştirilmez. |
| 45 | `jobs` | Kuyruk işleri; silinmez. |
| 46 | `job_batches` | Kuyruk batch metadata; silinmez. |
| 47 | `failed_jobs` | Hata geçmişi; silinmez. |
| 48 | `migrations` | Laravel migration geçmişi; uygulama migration'ında yaratılmaması kapsam dışı kalması anlamına gelmez. |

## Fiziksel temizlemeyi engelleyen kanıt zinciri

1. `app/Modules/Ledger/Database/Migrations/2026_09_07_050100_protect_ledger_entries_from_writes.php:24–37`
   koşulsuz UPDATE ve DELETE `SIGNAL SQLSTATE '45000'` trigger'larını kurar.
   `2026_09_07_050000_create_ledger_entries_table.php:17,32–35`, şirket ve ters
   kayıt/kullanıcı FK'lerini R kurar. Ledger bırakılıp şirketler silinemez.
2. `app/Modules/Payments/Database/Migrations/2026_09_18_100000_add_payment_allocations.php:23–35`
   payment/stay/self/kullanıcı R FK'lerini ve koşulsuz U/D yasaklarını kurar.
   Ters dağılım da geçmiş dağılımdır; payment/stay silme kilidini kaldırmaz.
3. `app/Modules/Payments/Database/Migrations/2026_09_14_060000_add_cash_session_id_to_payments.php:32–34`
   eski ödemeyi korunan kasa oturumuna nullable R ile bağlar. Bu FK yönünde
   payment silmek kasa satırını cascade silmez; fakat ödeme belgesi kaybolur.
4. `app/Modules/CashBook/Database/Migrations/2026_09_11_060100_create_cash_movements_table.php:57–58`
   FK'siz `reference_type/reference_id` tutar. Kaynak ödemeye referans veren
   hareketler yalnız FK taramasıyla keşfedilemez.
   `tests/Feature/Payments/CashBookRemovalTest.php:109–140`, gerçek legacy
   deseni `reference_type='payment'` ile örnekler ve iptal sonrası arşivin aynı
   kalmasını bekler. Bu test kaynak inceleme kanıtıdır; bu görevde koşulduğu
   iddiası değildir.
5. `app/Modules/CashBook/Database/Migrations/2026_09_11_060500_protect_cash_movements_from_writes.php:30–43`
   kasa hareketi U/D'yi yasaklar. `2026_09_12_060000_protect_cash_transfer_amounts_and_counts_from_writes.php:26–55`
   sayım/devir tutarlarını da korur. Eski kasa geçmişini temizliğe dahil etmek
   kullanıcının koruma kapsamına aykırıdır.

`ledger_entries.source_type/source_id` yalnız izleme içindir ve kaynak belgeye
FK taşımaz (`050000` migration `:19–22`). Güncel kaynaklarda `stay`, `payment`
örnekleri bulunur; serbest tip alanı nedeniyle yeni/bilinmeyen tipler de
varsayılarak silinemez. Ledger şirket FK'si ve immutable kuralı her durumda
geçerlidir.

## Denetim izi ve makbuz düzeltmesi

`audit_logs.auditable_type/auditable_id` subject FK'si taşımaz; kaynak belge
silinse bile denetim geçmişinin kalması tasarlanmış davranıştır. Bu tarihsel
işaretçinin varlığı tek başına FK engeli değildir. `AppServiceProvider.php:52–71`
güncel morph takma adlarını listeler; eski kasa takma adları eski audit
satırlarında bulunabilir. `old_values/new_values` JSON'ları da geçmiş bilgiyi
taşır ve aynen korunmalıdır.

`app/Modules/Core/Concerns/Auditable.php:43–45` ORM silmesinde yeni audit satırı
üretir. Standart domain silme işlemlerinin korunacak tablolara yan etkisi
olmadığı varsayılamaz.

Makbuz migration'ındaki eski “silme ucu yoktur” yorumu güncel davranışı
anlatmaz. Güncel `app/Modules/ReceiptBook/Routes/web.php:32–33` DELETE route'u,
`Http/Controllers/ReceiptBookController.php:129–133` policy/servis çağrısını
tanımlar. `Services/ReceiptBookService.php:96–105` transaction içinde önce
eski değerlerle audit kaydı yazar, sonra `deleteQuietly()` çağırır.
`Policies/ReceiptBookEntryPolicy.php` Admin erişimini belirler. Bu ayrı belge
silme özelliği, tüm CARİ temizliği için izin veya güvenli kısmi başarı değildir.

## Veritabanı dışındaki korunacak içerik

Marka logoları için tablo yoktur. `app/Modules/Branding/Services/BrandLogoService.php:49,76,91`
public disk altındaki `branding/logo-{variant}.{extension}` dosyalarını kullanır.
`config/hotel.php` marka ayarları, eski public logo asset'leri, `.env`,
`storage/app` ve diğer kullanıcı dosyaları korunur. DB checksum'u dosyaların
korunduğunu tek başına kanıtlamaz. Bu paket bu dosyalara yazma yolu içermez.

## Sayım, bütünlük ve doğrulama sınırları

- Gerçek ekran sayıları mevcut bağlantıdan okunmalıdır; bu belgeden sayı
  taşınmamalıdır. `DB::table` gibi global soft-delete scope uygulamayan okuma
  kullanılarak iptal/silinmiş şirket ve konaklama geçmişi de dahil edilir.
- Koruma kümesi yalnız bilinen tablo adları değildir: runtime şemadaki bütün
  kapsam dışı tablolar otomatik korunur. Ön kontrol sırasında FK/trigger
  bilgilerinin alınamaması başarılı kontrol olarak gösterilmemelidir.
- Sırf kayıt sayısının eşitliği içerik bütünlüğü kanıtı değildir. Testlerde
  korunan tabloların bütün satır ve kolonları kararlı sırada alınarak tam
  snapshot veya kanonik SHA-256 eşitliği karşılaştırılmalıdır; NULL/değer,
  kolon adı, tip ve uzunluk ayrımı korunmalıdır.
- Salt okunur engelli işlemde önce/sonra eşitliği, yalnız sınanan istek ve
  fixture için yazma olmadığını kanıtlar. Canlıdaki bütün tarihsel verinin
  eksiksiz mutabakatı veya eşzamanlı başka kullanıcıların yazmadığı garantisi
  değildir. Çok sorgulu snapshot farklı anları okuyabileceğinden tutarlılık
  sınırı açıklanmalıdır; gerçek silme atomikliği bu paketle denenmiş sayılmaz.
- GET sayfa/POST ön kontrol için uygulama verisine DML olmaması; Admin dışı
  erişim reddi; CSRF; finansal geçmiş/legacy referans raporu; HESAP, Personel,
  yetki, oda, kasa, audit ve bilinmeyen tablo snapshot eşitliği sınanmalıdır.
  HTTP oturum altyapısının kendi normal yazmaları, domain temizliği ile
  karıştırılmamalıdır. Hiçbir temizleme servisi session/cache tablosuna yazmaz.
- Silme route'u bulunmadığından parola/son onay formu, tek kullanımlık silme
  durumu ve temizleme eşzamanlılık kilidi açılmaz. Bunları test edilmiş bir
  silme akışı gibi raporlamak yanlış olur. Kullanıcıya bloke gerekçesi açık
  gösterilir; tekrar ziyaret silme özelliğini etkinleştirmez.

Yerel doğrulama PHP 8.3.33 ve ayrı, yalnız test için oluşturulan MariaDB
11.4.8 şemasında yapıldı. Yeni `CariCleanupTest` sınıfı 10 test ve 261
doğrulama ile geçti; bütün tabloların tam satır içerikleri karşılaştırıldı.
Bu sınıf `DatabaseTransactions` kullanır; tek başına çalıştırılmadan önce boş,
ayrılmış test şemasına `php artisan migrate --force` uygulanmalıdır. Şema
sıfırlama veya finansal koruma atlatma bu yeni testlerin parçası değildir.

Normal HTTP yolundaki READ ONLY / REPEATABLE READ transaction ayrıca test
ortamında çağrıldı: 48 tablo ve 32 tetikleyici okundu, transaction kapandı.
InnoDB tablolarının sayımları aynı snapshot içindedir. Bu sayılar kaynak/test
envanteridir; canlı kayıt sayıları olarak kullanılmaz.

`npm run build`, Aylık Hesap ve Personel JS testleri, Pint, PHPStan,
Composer platform kontrolü, Blade ve route cache derlemeleri başarılıdır.
Yerel Blade önizlemesi 360 / 375 / 390 / 430 / 768 / 1366 / 1920 CSS piksel
genişliklerinde tarayıcıda incelendi; yatay taşma yoktu ve silme düğmesi
devre dışı kaldı. Bağımsız inceleme salt okunur paketi onayladı.

Tam Laravel paketi, GitHub CI ve deploy bu not yazılırken henüz
tamamlanmamıştı; son sonuçlar final raporda ayrı bildirilir. “Hesap ve kapsam
dışı modüllerin korunması testlerle doğrulandı” sonucu yalnız engelli,
salt okunur akış ve sınanan fixture için geçerlidir. Gerçek silme veya canlı
veri checksum mutabakatı yapıldığı anlamına gelmez.

Mevcut dependency kilidinde `npm audit` üç geliştirme bağımlılığı bulgusu
bildirdi (`concurrently`, `shell-quote`, `source-map-js`). Bu görev bağımlılık
sürümlerini değiştirmez; bulgular ayrı bir bakım işi olarak değerlendirilmelidir.

## Yeni açık kapsam gerektiren alternatifler

1. Mevcut finansal sistemi/veritabanını arşiv olarak koruyup yeni operasyon
   dönemi için ayrı sistem/veritabanı hazırlamak. HESAP ve Personel taşınması,
   kimliklerin korunması ve denetim mutabakatı ayrıca tasarlanmalıdır.
2. Finansal geçmişi koruyan arşivleme/görünüm filtresi veya dönem yaklaşımı
   geliştirmek. Bu fiziksel silme değildir; yeni UI/iş kuralı onayı gerektirir.
3. Bağımsız, finansal olmayan belgelerin sınırlı temizliği. Mevcut görev tam
   başarı şartı koyduğu için bu paket bunu yapmaz; ayrı izin listesi, denetim
   davranışı ve yeni açık onay gerekir.

Yedek alındığı bilgisi trigger/FK kaldırma, TRUNCATE, genel reset, migration
geçmişi değiştirme veya finansal hard-delete için yetki sağlamaz.

## Geçici kodların kaldırılması

Karar/inceleme süreci tamamlandığında ayrı geliştirme paketiyle geçici
diagnostic modülü, provider kaydı, Yönetim menü bağlantısı, bu özelliğe özel
route/view/policy/servis ve testler birlikte kaldırılır. Kalan uygulama
route/cache bağımlılıkları, yetkiler ve menü regresyonları doğrulanır;
production değişikliği normal release/CI/deploy sürecinden geçirilir.

Bu engelli pakette çalıştırılabilir silme yeteneği veya yeniden
etkinleştirilecek kalıcı işlem durumu yoktur. Sonraki bir değişiklik bunu
konfigürasyon anahtarıyla açmamalı; yeni açık görev ve güvenlik incelemesi
gerektirir. Kaldırma planı finansal trigger'ları, FK'leri, migration geçmişini
ve finansal/denetim verisini hiçbir zaman kaldırmayı içermez. Analiz belgesi
kararın gerekçesi olarak korunabilir.
