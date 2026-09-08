# CLAUDE.md

Bu dosya, bu repoda çalışan Claude Code oturumları için bağlayıcı kılavuzdur.
Buradaki kurallar ile bir istek çelişiyorsa **önce sor**, kendi başına kapsam genişletme.

---

## 1. Proje

**Hotel Account System** — bir otelin **firma (cari) alacak ve tahsilat takibi**.
Bugün Excel ile yürütülen süreçlerin yerini alacak.

**Ana hedef:** 7–14 gün içinde, Excel'in tamamen bırakılabileceği **çalışan bir V1**.
Bu hedefe hizmet etmeyen hiçbir şey V1'e girmez.

Kullanıcılar: otel sahibi (Patron), yönetici (Admin), resepsiyon/muhasebe (Personel). Toplam kullanıcı sayısı düşük (< 20), veri hacmi orta (yılda ~binlerce konaklama).

**Arayüz dili Türkçe'dir.** Kod, tablo, kolon, sınıf ve değişken adları **İngilizce**dir.

---

## 2. Teknoloji Yığını

| Katman | Seçim |
|---|---|
| Dil | **PHP 8.3** — üretim runtime'ı ve resmi hedef. CI ve deploy aynı sürümü kullanır. |
| Framework | **Laravel 13** |
| Veritabanı | MySQL 8 / MariaDB 10.6+ |
| Şablon | Blade |
| CSS | Tailwind CSS |
| JS | Alpine.js — **sadece gerektiğinde** |
| Build | Vite |
| Yetki | `spatie/laravel-permission` |
| Excel | `maatwebsite/excel` |
| Mimari | Modular monolith |
| Arayüz | Responsive web + temel PWA |
| Barındırma | Linux cPanel (hosting.com.tr) |

**Kullanılmayacaklar:** SPA framework (React/Vue), Livewire, Inertia, Redis, Docker tabanlı prod, harici modül paketi (`nwidart/laravel-modules`), mikroservis.

Paylaşımlı hosting gerçeği: `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync` (V1'de kuyruk kullanılmıyor).

**PHP sürümü bağlayıcıdır ve 8.3'tür.** Hosting'de PHP 8.4 kurulu olsa da
**alan adı bazlı PHP izolasyonu kapalıdır**: sürüm yalnızca hesabın tamamı için
değiştirilebilir ve aynı hesapta başka aktif siteler vardır. Bu yüzden uygulama
8.4'e yükseltilmez, 8.3'e kilitlenir:

- `composer.json` → `config.platform.php = "8.3.0"`. Composer bağımlılıkları
  8.3 ortamını simüle ederek çözer; 8.4 isteyen bir paket kilide giremez.
  Yama sürümü olarak `8.3.0` seçilmiştir (en muhafazakâr: hosting'in yama
  sürümü ne olursa olsun çalışır; `8.3.99` ile çözüm aynı çıktığı ölçülmüştür).
- CI (`ci.yml`) ve deploy workflow'u PHP **8.3** kullanır; testler hedef
  runtime'da koşar.
- `phpstan.neon` → `phpVersion: 80300`. Yalnızca 8.4'te var olan bir işlev veya
  sözdizimi kullanılırsa statik analiz sunucuda fatal error olmadan önce kırılır.
- CI ve deploy build'i `composer check-platform-reqs` ile kilidi doğrular.

Geliştirme ortamı 8.4 çalıştırabilir; bu üç kapı sayesinde fark sessizce
üretime sızamaz.

---

## 3. V1 Kapsamı

### 3.1 Yapılacak modüller

1. **Authentication** — login, logout, şifre değiştirme, oturum güvenliği
2. **Users** — kullanıcı CRUD, aktif/pasif
3. **Roles & Permissions** — Spatie tabanlı rol/yetki yönetimi
4. **Companies** — firma (cari kart) CRUD
5. **Stays** — konaklama kaydı
6. **Payments** — tahsilat kaydı
7. **Ledger / Current Account** — cari hareket defteri ve bakiye
8. **Dashboard** — role duyarlı özet ekran
9. **Search & Filters** — listelerde arama/filtre/sıralama/sayfalama
10. **Excel Export** — listelerin ve ekstrenin dışa aktarımı
11. **Basic Audit Log** — kim, ne zaman, neyi değiştirdi
12. **Cash Book (Kasa Defteri)** — vardiya, kasa hareketi, personel devri, sayım (Gün 10)

### 3.2 ŞİMDİ YAPILMAYACAKLAR (kesin)

Aşağıdakiler için **tablo, migration, model, servis, route, view, config anahtarı veya "ileride lazım olur" placeholder'ı üretme:**

- Guest CRM / misafir kartı (konaklamada sadece `guest_name` metni var)
- TC kimlik / pasaport saklama, KVKK altyapısı, şifreleme
- KBS entegrasyonu
- Rezervasyon
- Oda yönetimi (`room_no` sadece serbest metin)
- Housekeeping
- Stay charges / ekstra harcamalar
- Banka hesap yönetimi, banka entegrasyonu, mutabakat
  (**Fiziksel kasa defteri Gün 10'da kapsama ALINDI** — vardiya/devir/sayım.
  Kapsam dışı olan yalnızca BANKA tarafıdır; bkz. §8/Kasa Defteri.)
- Kur servisi, TCMB entegrasyonu, döviz çevrimi
- Yaşlandırma (aging) raporları
- Fatura / e-Fatura / e-Arşiv
- Channel manager
- Çevrimdışı veri girişi
- Bildirim/e-posta gönderimi (şifre sıfırlama hariç)
- Çok dilli arayüz (sadece Türkçe)
- API / mobil uygulama

**Kural:** Bu listedeki bir şey isteniyorsa dur ve kullanıcıya sor. "Sadece küçük bir alan ekleyeyim" yapma.

### 3.3 Gelecek genişlemeye hazırlık

Mimari; PMS, rezervasyon, oda yönetimi ve KBS'nin **sonradan modül olarak eklenmesini engellememelidir**. Bunun için tek yapılan şey:

- Konaklama kavramı `Stays` modülünde izole tutulur; oda/rezervasyon mantığı başka modüle sızmaz.
- `ledger_entries.source_type/source_id` polimorfiktir → ileride yeni belge tipleri (fatura, ekstra harcama) aynı deftere yazabilir.
- Modüller arası iletişim Service katmanından geçer.

Bunun ötesinde **hiçbir hazırlık yapılmaz**. Boş tablo, kullanılmayan kolon, kullanılmayan interface açmak yasaktır.

---

## 4. Klasör Yapısı

```
app/
├── Http/
│   ├── Controllers/Controller.php
│   ├── Middleware/
│   └── Kernel.php
├── Models/User.php                 # sadece User burada
├── Providers/
└── Modules/
    ├── Core/                       # paylaşılan altyapı (aşağıda)
    ├── Auth/
    ├── Users/
    ├── Authorization/
    ├── Companies/
    ├── Stays/
    ├── Payments/
    ├── Ledger/
    ├── Dashboard/
    ├── CashBook/
    └── AuditLog/
config/
├── hotel.php                       # uygulamaya özel ayarlar
└── permission.php
database/migrations/                # sadece framework + spatie tabloları
docs/
lang/tr/
public/                             # build/, manifest.json, sw.js, icons/
resources/
├── css/app.css
├── js/app.js                       # Alpine bootstrap + pwa kaydı
└── views/
    ├── layouts/{app,guest,print}.blade.php
    ├── components/                 # <x-ui.*>, <x-form.*>
    └── partials/
routes/web.php                      # BOŞ: tüm route'lar modüllerdedir (kök dahil)
tests/{Feature,Unit}/
```

### 4.1 Modül iç yapısı

Her modül aynı iskeleti kullanır; **ihtiyaç duyulmayan klasör açılmaz**:

```
app/Modules/Payments/
├── Database/Migrations/
├── Database/Seeders/
├── Enums/                  PaymentMethod
├── Http/Controllers/
├── Http/Requests/
├── Models/                 Payment
├── Policies/               PaymentPolicy
├── Services/               PaymentService     ← dışa açık tek kapı
├── Actions/                CreatePayment, CancelPayment
├── Resources/views/
├── Routes/web.php
├── Providers/PaymentsServiceProvider.php
└── Tests/
```

Autoload: `composer.json`'daki mevcut `"App\\": "app/"` eşlemesi `App\Modules\*` sınıflarını zaten kapsar. Ayrıca `"App\\Modules\\"` girdisi **eklenmez** — gereksizdir ve Composer'da "ambiguous class resolution" uyarısı üretir.
Her modülün ServiceProvider'ı kendi route, view namespace, migration ve policy kayıtlarını yapar; `config/app.php` (veya `bootstrap/providers.php`) içinde kayıtlıdır.

### 4.2 `Core` modülü

Paylaşılan, modüle ait olmayan her şey burada:

- `Concerns/HasCreator` — `created_by` / `updated_by` otomatik doldurma
- `Concerns/Auditable` — audit log kaydı
- `Filters/` — liste filtreleme altyapısı (bkz. §9)
- `Exports/` — Excel export temel sınıfları (bkz. §10)
- `Support/Money.php` — para biçimleme, güvenli toplama ve kullanıcı girdisi normalleştirme
- `Enums/Currency.php`
- `View/Components/` — ortak Blade bileşenleri

### 4.3 Modüler monolit kuralları (bağlayıcı)

1. Bir modül **başka modülün Model'ini doğrudan kullanamaz**. Sadece o modülün `Services/` sınıfını çağırır.
   - Tek istisna: Eloquent ilişki tanımları (`Stay::company()` → `Company::class`). İlişki tanımı serbest, iş mantığı değil.
2. Bir modülün Controller'ı başka modülün Controller'ını çağıramaz.
3. `Ledger` modülü **hiçbir modülü tanımaz**; `Stays` ve `Payments` ona yazar.
   Bu yüzden cari ekstre EKRANLARI (controller, route, view) `Companies` modülündedir:
   ekstre firma adını göstermek zorundadır ve Ledger bu bağımlılığı taşıyamaz.
   Hesabın tamamı yine `Ledger\Services\LedgerStatementService`'tedir.
4. Ortak bir şey iki modülde tekrar ediyorsa `Core`'a taşınır, kopyalanmaz.
5. Yeni modül ancak kullanıcı onayıyla açılır.

---

## 5. Veritabanı Şeması (V1'in tamamı)

Genel kurallar:
- Motor InnoDB, collation `utf8mb4_turkish_ci`.
- Para: **`DECIMAL(15,2)`**. `FLOAT`/`DOUBLE` **yasak**.
- `currency`: `CHAR(3)`, varsayılan `TRY`.
- Foreign key'ler `RESTRICT` — finansal kayıt cascade silinmez.
- Aşağıdaki tabloların dışında V1'de tablo yoktur.

### 5.1 Framework & yetki tabloları
`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs` (Laravel varsayılan) + Spatie: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`.

**`users`** ek alanlar: `is_active` (bool, default true), `last_login_at` (nullable), `created_by` (nullable FK), `deleted_at` (soft delete).

### 5.2 `companies`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| name | varchar(200) | Firma ünvanı, index |
| tax_office | varchar(100) null | |
| tax_number | varchar(20) null | index |
| phone | varchar(30) null | |
| email | varchar(150) null | |
| address | text null | |
| city | varchar(100) null | |
| contact_person | varchar(150) null | |
| notes | text null | |
| is_active | boolean | default true |
| created_by / updated_by | FK users null | |
| created_at / updated_at | | |
| deleted_at | soft delete | |

**Kural:** Cari hareketi olan firma silinemez (soft delete dahil) — sadece `is_active = false` yapılır.

### 5.3 `stays`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | zorunlu, index |
| guest_name | varchar(150) | serbest metin, misafir tablosu **yok** |
| room_no | varchar(20) null | serbest metin |
| check_in_date | date | index |
| check_out_date | date | `>= check_in_date` |
| nights | unsigned int | sunucuda hesaplanır, doğrulanır, `>= 1` |
| amount | decimal(15,2) | **nihai toplam** konaklama tutarı (her iki modda da) |
| pricing_mode | varchar(10) | `total` \| `daily` — ücretin nasıl girildiği |
| daily_rate | decimal(15,2) null | yalnızca `daily` modunda dolu |
| currency | char(3) | default TRY |
| notes | text null | |
| status | varchar(20) | `active` \| `cancelled` — V1'de PMS durumu YOK |
| created_by / updated_by | FK users | |
| created_at / updated_at | | |
| deleted_at | soft delete | kazara `delete()` çağrısının fiziksel silme olmasını engeller |

> **İptal `status` alanı üzerinden yapılır**, soft delete ile değil: iptal edilmiş konaklama
> listede görünmeye devam eder ve "Durum" filtresiyle ayrılır. `deleted_at` kolonu yalnızca
> kazara yapılacak bir `delete()` çağrısının fiziksel silme olmasını engeller. **V1'de iptali
> geri alma akışı YOKTUR.**
>
> Cariye işlenmiş bir konaklamanın iptali, aynı `DB::transaction()` içinde ters ledger kaydı
> üretmek **zorundadır**.

Index: `(company_id, check_in_date)`, `(check_in_date, check_out_date)`, `(check_out_date)`,
`(status)`, `(created_by)`, `(currency)`.

**Veritabanı düzeyinde değişmezler:**
- `CHECK (amount > 0)`
- `CHECK (check_out_date > check_in_date)` — asgari 1 gece
- `CHECK (nights >= 1)`
- `CHECK (pricing_mode IN ('total','daily'))`
- `CHECK ((pricing_mode='total' AND daily_rate IS NULL) OR (pricing_mode='daily' AND daily_rate IS NOT NULL AND daily_rate > 0))`

### 5.4 `payments`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | zorunlu, index |
| payment_date | date | index |
| amount | decimal(15,2) | > 0 |
| currency | char(3) | default TRY |
| payment_method | varchar(30) null | enum `PaymentMethod`: `cash`, `bank_transfer`, `credit_card`, `cheque`, `other` |
| reference_no | varchar(50) null | dekont/slip no, index |
| description | varchar(255) null | |
| status | varchar(20) | `active` \| `cancelled` — enum `PaymentStatus` |
| created_by / updated_by | FK users | |
| created_at / updated_at | | |
| cancelled_at | timestamp null | |
| cancelled_by | FK users null | |
| cancellation_reason | varchar(255) null | iptal gerekçesi, zorunlu girdi |

**Silme yoktur** — ne hard delete ne soft delete: tabloda `deleted_at` kolonu YOKTUR.
Yanlış tahsilat `status` üzerinden iptal edilir; iptal ters cari kaydı üretir.

Index: `(company_id, payment_date)`, `(payment_date)`, `(status)`, `(currency)`,
`(payment_method)`, `(created_by)`, `(reference_no)`.

**Veritabanı düzeyinde değişmezler:**
- `CHECK (amount > 0)`
- `CHECK (status IN ('active','cancelled'))`
- `CHECK (payment_method IS NULL OR payment_method IN ('cash','bank_transfer','credit_card','cheque','other'))`
- `CHECK ((status='active' AND cancelled_at IS NULL AND cancelled_by IS NULL) OR (status='cancelled' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL))`

### 5.5 `ledger_entries`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | index |
| source_type | varchar(30) | morph map: `stay`, `payment` |
| source_id | bigint | |
| entry_date | date | index |
| description | varchar(255) | |
| debit | decimal(15,2) | default 0 |
| credit | decimal(15,2) | default 0 |
| currency | char(3) | |
| is_reversal | boolean | default false |
| reverses_entry_id | FK ledger_entries null | **unique** — bir hareket yalnızca bir kez ters kaydedilebilir |
| created_by | FK users | zorunlu |
| created_at | timestamp | **`updated_at` ve `deleted_at` YOK** |

Index: `(company_id, entry_date)`, `(company_id, currency)`, `(source_type, source_id)`, `(entry_date)`.

**Veritabanı düzeyinde uygulanan değişmezler** (uygulama katmanı atlansa bile geçerli):
- `CHECK (debit >= 0 AND credit >= 0)` — negatif tutar yok
- `CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))` — tam olarak bir yön dolu
- `UNIQUE (reverses_entry_id)` — ikinci ters kayıt imkânsız
- `BEFORE UPDATE` / `BEFORE DELETE` tetikleyicileri — ham SQL dahil her yazma reddedilir

### 5.6 `audit_logs`

| Alan | Tip |
|---|---|
| id | bigint PK |
| user_id | FK users null |
| user_name | varchar(150) — snapshot |
| event | varchar(30) — `created`, `updated`, `deleted`, `restored`, `login`, `logout`, `login_failed`, `cancelled`, `reversed`, `exported`, `permission_changed` |
| auditable_type | varchar(50) — morph map |
| auditable_id | bigint null |
| old_values | json null |
| new_values | json null |
| ip_address | varchar(45) null |
| user_agent | varchar(255) null |
| url | varchar(255) null |
| created_at | timestamp, index |

Kayıt **değiştirilemez ve silinemez**. `updated_at` yoktur. Hiçbir role delete yetkisi verilmez.

### 5.8 Kasa defteri tabloları (Gün 10)

Kasa defteri **cari hesaptan ayrı bir defterdir**: `ledger_entries` firma borcunu,
kasa tabloları FİZİKSEL parayı izler. Aralarında yazma bağı yoktur.

**`cash_sessions`** — vardiya: `opened_by`, `opened_at`, `closed_at`, `closed_by`,
`status` (`open`|`closed`), `is_open`, `opening_note`, `closing_note`.

> **İşletmede aynı anda yalnızca BİR açık vardiya olabilir.** Kısıt kullanıcı
> başına değil işletme genelindedir: fiziksel kasa tektir. `is_open` kolonu
> açıkken `1`, kapalıyken `NULL`'dur ve `UNIQUE` index taşır; MySQL tekil
> indexte NULL'ları tekrarlanabilir saydığı için bu "en fazla bir açık vardiya"
> anlamına gelir ve eşzamanlı ikinci açılışı veritabanı reddeder.

**`cash_movements`** — hareket: `cash_session_id`, `type`, `direction`, `amount`,
`currency`, `entry_at`, `description`, `counterparty`, `reference_type/id`,
`is_reversal`, `reverses_movement_id` (**unique**), `created_by`, `created_at`.

- Tutar **her zaman pozitiftir**; işaret `direction` (`in`|`out`) kolonundan gelir.
  Yön tipin doğal yönüdür, **ters kayıtta tersine döner** — ledger'daki
  debit/credit ayrımının kasa karşılığıdır.
- **Açılış bakiyesi ayrı tabloda değil, `opening` tipli bir harekettir**: beklenen
  kasa tek formülle hesaplanır ve para için iki kaynak olmaz.
- `reference_type/reference_id` ileride PMS/Payments bağlanabilsin diye açıktır;
  V1'de uygulama tarafından DOLDURULMAZ (§3.3'teki kaynak izleme deseni).
- Tutar `DECIMAL(15,2)`'dir (§5 genel kuralı) — `amount_minor` DEĞİL. Tamsayı
  kuruş aritmetiği PHP tarafında `Core\Support\Money` ile yapılır.

**`cash_transfers`** — personelden personele devir: `from_session_id`,
`to_session_id`, `from_user_id`, `to_user_id`, `status`
(`pending`|`accepted`|`cancelled`), `initiated_at`, `responded_at`, `note`.
Ara kasa/patron teslimi burada TUTULMAZ; o bir `cash_out` **hareketidir**.

**`cash_transfer_amounts`** — devirde teslim edilen tutarlar, para birimi başına
bir satır. Unique `(cash_transfer_id, currency)`. "Toplam" kolonu yoktur (§6/L5).

**`cash_counts`** — kapanış sayımı, para birimi başına bir satır:
`expected_amount` (kapanış anındaki hesaplanan tutarın anlık görüntüsü),
`counted_amount`. **Fark saklanmaz, hesaplanır**: iki kaynak da değişmez olduğu
için fark her zaman aynı çıkar; saklamak üçüncü bir doğruluk kaynağı yaratırdı.
Unique `(cash_session_id, currency)`.

**Veritabanı düzeyinde uygulanan değişmezler:**
- `UNIQUE(cash_sessions.is_open)` — en fazla bir açık vardiya
- `CHECK` — `status`/`is_open`/`closed_at` tutarlılığı
- `CHECK (cash_movements.amount > 0)`, tip ve yön beyaz listesi
- `CHECK` — **yön tiple uyumlu olmalı** (ters kayıtta tersi): "gelir ama kasadan
  çıkan" gibi anlamsız satır yazılamaz
- `UNIQUE(reverses_movement_id)` — bir hareket yalnızca bir kez ters kaydedilir
- `CHECK (cash_transfers.from_user_id <> to_user_id)` — kendine devir yasak
- `UNIQUE(pending_from_session_id)` (generated column) — bir vardiyanın aynı anda
  yalnızca bir bekleyen devri olabilir
- **Tetikleyiciler:** `cash_movements` üzerinde UPDATE ve DELETE reddedilir;
  **KAPALI vardiyaya INSERT reddedilir** (ham SQL dahil her yol kapalıdır)

### 5.7 Morph map

`AppServiceProvider::boot()` içinde `Relation::enforceMorphMap([...])` zorunlu:
`'stay' => Stay::class`, `'payment' => Payment::class`, `'company' => Company::class`,
`'user' => User::class`, `'role' => Role::class`, `'ledger_entry' => LedgerEntry::class`,
`'cash_session' => CashSession::class`, `'cash_movement' => CashMovement::class`,
`'cash_transfer' => CashTransfer::class`.
Sınıf adı değişse bile veri bozulmaz.

---

## 6. Cari Hesap Kuralları (sistemin kalbi — ihlal edilemez)

**L1.** `ledger_entries` **append-only**'dir. UPDATE ve DELETE yasaktır. Model `update()`/`delete()` çağrılarını exception ile engeller.

**L2.** Her satırda `debit` ve `credit`'ten **tam olarak biri** sıfırdan büyüktür, diğeri `0.00`'dır.

**L3.** Yön:
- **Konaklama → `debit`** (firma bize borçlanır)
- **Tahsilat → `credit`** (borç azalır)
- **İptal → ters kayıt** (konaklama iptali `credit`, tahsilat iptali `debit`)

**L4.** Bakiye **her zaman** deften hesaplanır:
```sql
SELECT SUM(debit) - SUM(credit) FROM ledger_entries
WHERE company_id = ? AND currency = ?
```
Pozitif = firma bize borçlu. **Önbelleklenmiş `balance` kolonu V1'de yoktur.**

**L5.** Bakiye **para birimi bazında** hesaplanır. **Desteklenen hiçbir para birimi bakiyesi bir başkasıyla kesinlikle tek toplamda birleştirilmez.** V1'de otomatik kur servisi ve kur çevrimi yoktur; çok para birimli firmanın ekstresi para birimine göre gruplanır ve her grup kendi bakiyesini gösterir.

> **Para birimi kümesi (Gün 9.6):** `Currency` enum'u (`app/Modules/Core/Enums/Currency.php`)
> TEKNİK geçerlilik kümesidir — V1 itibarıyla `TRY, EUR, GBP, USD, RUB, GEL`. Yeni bir ISO 4217
> kodu eklemek yalnızca bu enum'a bir `case` eklemeyi gerektirir; `currency` kolonları zaten
> `CHAR(3)` olup değeri kısıtlayan bir CHECK taşımaz, bu yüzden migration gerekmez.
> `config('hotel.currencies')` ise İŞ KARARIdır: enum'un bir alt kümesini, formlarda gösterilme
> sırasıyla birlikte tanımlar (`Currency::active()`). **Yeni kayıt formları** (Konaklama/Tahsilat
> oluşturma) `Currency::active()` kullanır; **raporlama/filtre/geçmiş** ekranları (liste filtreleri,
> ekstre, dashboard) her zaman `Currency::cases()` kullanır — bir para birimi "aktif" listeden
> çıkarılsa bile geçmiş borç/alacak asla gizlenmez. Hiçbir otomatik kur çevrimi yoktur (§18);
> `Money` hâlâ sabit 2 ondalıklı (kuruş) varsayımıyla çalışır — bu varsayım
> `Currency::decimalPlaces()` ile adlandırılmıştır, farklı ondalık basamaklı bir ISO kodu
> (ör. JPY, BHD) eklenmeden önce `Money`'nin gözden geçirilmesi gerekir.

**L6.** Cariye yazan her işlem, kaynak kaydıyla **aynı `DB::transaction()`** içinde olur. Konaklama kaydedilip cari kaydı düşmemesi mümkün olmamalıdır.

**L7.** Düzeltme = ters kayıt. Var olan satır asla düzenlenmez.
- Konaklama `amount`/`currency`/`entry_date` değişirse: eski kayıt için ters satır + yeni satır (2 satır).
- Konaklama silinirse (soft delete): ters satır.
- Tahsilat iptalinde: ters satır. Tahsilat tutarı **hiçbir rol tarafından düzenlenemez**; düzeltme = iptal + yeniden giriş.

**L8.** Ters kayıt, `is_reversal = true` ve `reverses_entry_id = <orijinal id>` kolonlarıyla
tanımlanır — açıklama metnine bakılarak değil. Varsayılan açıklama `[İPTAL]` ön ekiyle başlar
ama bu yalnızca okunabilirlik içindir.

Ters kayıt orijinalin **tam tersidir**: aynı firma, aynı tutar, aynı para birimi, aynı
`entry_date`, aynı `source_type/source_id`; yalnızca borç/alacak yönü değişir. Böylece iki
kayıt her tarih aralığında birbirini götürür.

- **Aynı hareket ikinci kez ters kaydedilemez** — `reverses_entry_id` üzerindeki unique
  kısıt bunu eşzamanlı isteklerde bile garanti eder.
- **Ters kaydın tersi alınamaz** (`is_reversal = true` olan kayıt reddedilir); zincir oluşmaz.
- Ters kayıt ve denetim kaydı **aynı transaction** içindedir; biri başarısız olursa ikisi de yazılmaz.

> Ters kaydın orijinalle aynı tarihi taşıması, geçmiş bir dönemin bakiyesini geriye dönük
> değiştirir. V1'de dönem kilidi yoktur; ekstre/dönem kapatma geldiğinde bu davranış
> yeniden değerlendirilmelidir.

**L9.** Cariye yazma **sadece** `Ledger\Services\LedgerService` üzerinden yapılır. Controller,
Blade veya başka modül doğrudan `LedgerEntry::create()` çağıramaz. Servisin dışa açık API'si:

| Metot | İşi |
|---|---|
| `debit()` | Firma borçlanır |
| `credit()` | Firmanın borcu azalır |
| `reverse()` | Bir hareketin tam tersini üretir |
| `balanceForCompany()` | Tek para biriminde bakiye |
| `balancesForCompanyByCurrency()` | Para birimi bazında bakiyeler |
| `hasEntriesForCompany()` | Firmanın hareketi var mı |
| `isReversed()` | Hareket ters kaydedilmiş mi |

`LedgerService` firma kimliğini `int $companyId` olarak alır; Companies modülünün modeline
bağımlı **değildir** (§4.3/3). Tutarlar `Core\Support\Money` ile geçirilir.

**L10.** Eşzamanlılık: bir firmanın bakiyesini okuyup ona göre yazan hiçbir mantık yoktur (limit kontrolü yok), bu yüzden kilit gerekmez. Bu değişirse `lockForUpdate` eklenir.

**Test zorunluluğu:** §12'deki cari testleri geçmeden Ledger, Stays veya Payments üzerinde değişiklik merge edilmez.

---

## 7. Yetki Modeli

### 7.1 Katmanlar (hepsi birlikte)

1. **Route middleware** — `->middleware('can:payments.create')`
2. **Policy** — kayıt bazlı karar; `Route Model Binding` + policy ile IDOR kapatılır
3. **Query kapsamı** — Personel listelerinde `created_by` daraltması
4. **Blade `@can`** — finansal alan/kolonların gizlenmesi

`Gate::before` ile `admin` rolü yetki kontrollerini geçer — ancak **yalnızca argümansız
yetki kontrolleri için** (ör. `can:users.manage` middleware'i).

**Model/kayıt argümanı taşıyan Policy yetenekleri kısa devre YAPILMAZ.** Bu kritik bir
ayrımdır: blanket `Gate::before`, admin için tüm Policy'leri devre dışı bırakır ve
"kullanıcı kendi hesabını silemez", "sistem rolü silinemez" gibi kayıt bazlı kurallar
admin'de hiç çalışmaz. Bu davranış testle doğrulanmıştır.

Buradan çıkan kural: **erişimi kaybettirebilecek iş kuralları Policy'ye ek olarak
Service katmanında da uygulanır.** Policy kullanıcı arayüzünü ve HTTP'yi korur;
Service son savunmadır.

İki mutlak istisna:
- **Denetim kaydı hiç kimse tarafından değiştirilemez/silinemez** — admin dahil.
  `Gate::before` bu yeteneklerde kesin `false` döner.
- Son aktif admin pasifleştirilemez/silinemez/rolü alınamaz (§7.5).

### 7.2 Yetki adları

`{modul}.{eylem}` formatı:

```
users.view, users.manage
roles.manage
companies.view, companies.create, companies.update, companies.delete, companies.restore
stays.view, stays.create, stays.update, stays.delete
payments.view, payments.create, payments.cancel
ledger.view            # tek firmanın ekstresi
ledger.view_all        # tüm firmaların bakiyesi / toplam alacak
cashbook.view          # kasa defteri ve açık vardiya
cashbook.operate       # hareket girme, vardiya açma/kapatma, devir
cashbook.view_all      # tüm vardiyalar, günlük defter, kasa farkları
dashboard.financial    # finansal dashboard kartları
export.excel
audit.view
```

Yetkiler seeder ile oluşturulur; kod içinde string olarak değil, mümkünse sabit/enum üzerinden referans verilir.

**Yeni yetki açılmadan çözülen iki durum:** konaklama iptali `stays.delete`, tahsilat
metadata düzeltmesi `payments.create` yetkisine bağlıdır. Gerekçe: tahsilat girebilen
herkes kendi girdiği kaydın referans numarasını düzeltebilmelidir; kayıt bazlı kısıt
(kendi kaydı + süre penceresi) `PaymentPolicy::update`'tedir. Yetki matrisi bu şekilde
birebir karşılanır ve yetki sayısı gereksiz büyümez.

### 7.3 Rol matrisi

| Yetki | Admin | Patron | Personel |
|---|:--:|:--:|:--:|
| users.view / users.manage | ✅ | ❌ | ❌ |
| roles.manage | ✅ | ❌ | ❌ |
| companies.view | ✅ | ✅ | ✅ |
| companies.create | ✅ | ✅ | ✅ |
| companies.update | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| companies.delete | ✅ | ✅ | ❌ |
| companies.restore | ✅ | ❌ | ❌ |
| stays.view | ✅ | ✅ | ✅ |
| stays.create | ✅ | ✅ | ✅ |
| stays.update | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| stays.delete | ✅ | ✅ | ❌ |
| payments.view | ✅ (tümü) | ✅ (tümü) | ✅ (tümü) |
| payments.create | ✅ | ✅ | ✅ |
| payments.cancel | ✅ | ✅ | ❌ |
| payments — metadata düzenleme | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| payments — finansal alan düzenleme | ❌ | ❌ | ❌ |
| cashbook.view | ✅ | ✅ | ✅ |
| cashbook.operate | ✅ | ✅ | ✅ |
| cashbook.view_all | ✅ | ✅ | ❌ |
| kasa — başkasının vardiyasına hareket | ❌ | ❌ | ❌ |
| ledger.view | ✅ | ✅ | ❌ |
| ledger.view_all | ✅ | ✅ | ❌ |
| dashboard.financial | ✅ | ✅ | ❌ |
| export.excel | ✅ | ✅ | ❌ |
| audit.view | ✅ | ✅ (salt okunur) | ❌ |
| audit — silme | ❌ | ❌ | ❌ |

⚠️ = policy içinde koşullu

### 7.4 Personel koşulları

```php
// config/hotel.php
'personnel_edit_window_hours' => 24,
```

Personel bir kaydı düzenleyebilir ancak:
- `record.created_by === auth()->id()` **ve**
- `record.created_at >= now()->subHours(config('hotel.personnel_edit_window_hours'))`

Ek olarak:
- Personel hiçbir kaydı silemez, hiçbir tahsilatı iptal edemez.
- Personel tahsilat listesinin tamamını görür; yalnızca KENDİ kaydının `reference_no` ve
  `description` alanlarını düzenleme penceresi içinde düzeltebilir.
- Personel firma cari bakiyesini, toplam alacağı ve finansal dashboard kartlarını göremez — **liste ekranlarında bakiye kolonu render edilmez** (gizlenmez, hiç sorgulanmaz).
- Personel Excel export alamaz.

### 7.5 Rol yönetimi

- 3 rol sistem rolüdür (`is_system`): `admin`, `patron`, `personel`. Silinemez, slug'ları değişmez.
- Admin yeni rol tanımlayıp mevcut yetkilerden atayabilir.
- Son aktif admin pasifleştirilemez veya rolü alınamaz.
- Her rol/yetki değişikliği audit log'a yazılır.

---

## 8. Modül Davranış Kuralları

### Authentication
E-posta + şifre. Rate limit (5 deneme / dakika / IP+email). Başarısız giriş `login_failed` olarak loglanır. `is_active = false` kullanıcı giriş yapamaz. Oturum zaman aşımı 8 saat. Şifre sıfırlama e-posta ile.

### Companies
Liste + arama + detay. Detay ekranı sekmeleri: Bilgiler / Konaklamalar / Tahsilatlar / Cari Ekstre (yetkiye göre). Firma silinmeden önce ilişkili kayıt kontrolü.

### Stays
- `nights` sunucuda `check_out_date - check_in_date` olarak hesaplanır; formdan gelen değere
  **asla** güvenilmez. `check_out_date >= check_in_date` zorunludur.
- **Asgari gece sayısı 1'dir**: `check_out_date > check_in_date`. Aynı gün giriş/çıkış
  kabul edilmez. Günübirlik (day-use) kullanım ileride ayrı bir iş kuralı olarak
  tasarlanacaktır; V1'de PMS kapsamına girilmez (§3.2).
- **Ücretlendirme iki modludur** (`pricing_mode`):
  - `total` — kullanıcı nihai toplamı (`amount`) girer, `daily_rate` boştur.
  - `daily` — kullanıcı gecelik ücreti (`daily_rate`) girer; `amount` **sunucuda**
    `nights × daily_rate` olarak hesaplanır. Formdan gelen `amount` yok sayılır
    (`exclude_if` ile doğrulanmış veriden düşer, servis ayrıca yeniden hesaplar).
  Çarpım `Core\Support\Money` ile tamsayı kuruş üzerinden yapılır; float kullanılmaz.
  `amount` her iki modda da nihai toplamdır ve cariye yazılan değer odur.
- Kayıt oluşturulunca `LedgerService` ile **debit** kaydı (`entry_date = check_out_date`,
  açıklama: `Konaklama: {guest_name} (dd.mm.yyyy - dd.mm.yyyy)`).
- **Finansal alanlar:** `company_id`, `check_in_date`, `check_out_date`, `pricing_mode`,
  `daily_rate`, `amount`, `currency`. Mod değişimi (`daily` ↔ `total`) de finansal
  değişikliktir.
  Bunlardan biri değişirse akış tek transaction içinde şudur:
  1. mevcut geçerli cari hareketi ters kaydedilir
  2. konaklama güncellenir
  3. yeni borç hareketi oluşturulur

  Finansal olmayan alanlar (`guest_name`, `room_no`, `notes`) cari hareketi üretmez.
- **İptal:** `status = cancelled` + geçerli cari hareketinin ters kaydı, aynı transaction'da.
  Aynı konaklama ikinci kez iptal edilemez; iptal edilmiş konaklama düzenlenemez.
- **Değişmez:** bir konaklamanın her an **tam olarak bir geçerli borç hareketi** vardır
  (iptal edilmişse hiç yoktur). Düzeltilen hareketler ters kayıtla net sıfıra gelir.
- Denetim kaydı `Auditable` trait'i ile değil, `StayService` tarafından açıkça yazılır —
  finansal düzeltmenin kaydı ters kayıt ve yeni kayıt kimliklerini de taşımak zorundadır.

### Payments
- Kayıt oluşturulunca **credit** kaydı (`entry_date = payment_date`), aynı transaction içinde.
- Tahsilat–konaklama eşleştirmesi (allocation) **V1'de yok**; tahsilat firmaya yazılır, bakiye netleşir.
- **Değişmez finansal alanlar:** `company_id`, `payment_date`, `amount`, `currency`,
  `payment_method`. Bunlar oluşturulduktan sonra **hiçbir rol tarafından** değiştirilemez.
  Düzeltme yolu tektir: iptal + yeniden giriş.
  Koruma üç katmandadır ve HTTP'yi gizlemekle yetinilmez:
  1. `UpdatePaymentRequest` bu alanları hiç tanımaz — `validated()` onları döndürmez.
  2. `PaymentService::updateMetadata()` gelen veride farklı bir finansal değer görürse
     `PaymentFinancialFieldsAreImmutable` fırlatır.
  3. `Payment` modeli `updating` olayında aynı kuralı zorlar — servis atlansa bile geçerli.
- **Düzeltilebilen tek alanlar:** `reference_no`, `description`. Metadata düzeltmesi
  cari hareketi ÜRETMEZ.
- **İptal:** `status = cancelled` + `cancelled_at` + `cancelled_by` + `cancellation_reason`
  (gerekçe zorunludur) ve geçerli cari hareketinin ters kaydı, aynı transaction'da.
  İptal edilmiş tahsilat tekrar iptal edilemez ve düzenlenemez. **Geri alma akışı YOKTUR.**
- **Değişmez:** aktif bir tahsilatın her an **tam olarak bir geçerli alacak hareketi** vardır;
  iptal edilmişse hiç yoktur.
- Denetim kaydı `Auditable` trait'i ile değil, `PaymentService` tarafından açıkça yazılır.

### Ledger / Current Account

Ekstre hesabının tamamı `Ledger\Services\LedgerStatementService`'tedir; controller'da
finansal hesap yazılmaz. Servis firmayı yalnızca `int $companyId` olarak bilir (§4.3/3).

- **Yürüyen bakiye** SQL'de değil serviste, kronolojik sırada (`entry_date ASC, id ASC`)
  ve **para birimi bazında** hesaplanır. Her satır YALNIZCA kendi para biriminin
  bakiyesini taşır; farklı para birimleri tek bakiyede birleştirilmez (§6/L5).
- **Devreden bakiye (carry-forward):** tarih filtresi uygulandığında, aralıktan önceki
  net bakiye para birimi bazında hesaplanır ve ekstre ondan devam eder. Tarih filtresi
  yoksa devreden yoktur.
- **İki filtre türü ayrılır:**
  - `date_from`/`date_to`/`currency` bakiyenin EVRENİNİ belirler; SQL'de uygulanır ve
    devreden bakiye bunlara göre hesaplanır.
  - `type`/`q` yalnızca GÖSTERİMİ daraltır; yürüyen bakiye hesaplandıktan SONRA uygulanır.
    Aksi hâlde "yalnızca tahsilatlar" seçen kullanıcıya gerçek bakiyesi olmayan bir kolon
    gösterilirdi. Ekranda bu durum bir bilgi kutusuyla açıkça belirtilir.
- **İşlem türü** kullanıcıya Türkçe gösterilir (`LedgerEntryType`): Konaklama / Tahsilat /
  İptal / Ters Kayıt / Açılış Bakiyesi / Diğer. Ham `source_type` ana tabloda gösterilmez;
  tanınmayan bir kaynak tipi "Diğer" olur — enum veri yolunu kısıtlamaz, yalnızca etiketler.
- **Ters kayıt gizlenmez:** ekstrede ayrı satır olarak, rozetle ve `[İPTAL]` açıklamasıyla görünür.
- **Kaynak kayda bağlantı** verilir (`stay`/`payment`). Ledger'ın doğruluğu kaynak kayda
  bağlı DEĞİLDİR: kayıt yoksa satır geçerli kalır ve ekran "Kaynak kayıt bulunamadı" der,
  hata vermez. Varlık kontrolü kaynak tipi başına tek sorgudur (N+1 yok).
- **Firma bakiye listesi** (`ledger.view_all`): tüm firmalar, para birimi bazında bakiye,
  son hareket tarihi. Bakiyeler ve tarihler firma başına değil, iki toplu sorguda alınır.
- **Yazdırma** (`layouts/print`): koyu tema UYGULANMAZ. Beyaz zemin, siyah metin, kenar
  çubuğu yok, A4. Sayfa kendi kendine yeten sade CSS taşır ve Vite çıktısını yüklemez.
  PDF kütüphanesi kullanılmaz; tarayıcının `window.print()` işlevi yeterlidir.
- **Ekstre görüntüleme denetim kaydı ÜRETMEZ** — her sayfa açılışını loglamak gürültü olur.
  `exported` olayı Excel export geldiğinde kullanılacaktır.
- Ekstre sayfalanmaz: bir ekstre bütün olarak okunur ve yazdırılır. Tarih filtresi
  büyük dönemleri bölmek için yeterlidir (bkz. `docs/kararlar.md` K-052).
- Excel export §10'da; V1'de Gün 10'da gelir.

### Dashboard

Uygulama kökü (`/`, route adı `home`) Dashboard modülündedir. Controller ince:
yetki → `DashboardService` → view. Ekranda ve controller'da hiçbir finansal hesap
yapılmaz.

**Sorgu bütçesi sabittir ve kayıt sayısıyla BÜYÜMEZ** — ölçülen değer 11 sorgudur;
`DashboardTest` hem N+1 yokluğunu hem de 15 sorguluk üst sınırı doğrular.

**Ekran üç adlandırılmış bilgi katmanına ayrılır (Gün 9.6)** — "Şu anda bilmem
gereken ne?" sorusuna aynı sırayla cevap verir. Bu yalnızca `financial.blade.php`'nin
GÖRÜNÜM sırasıdır; veri katmanı (`DashboardService`/`FinancialSummary`) ve
sorgu bütçesi DEĞİŞMEDİ:

- **A) Bugün** — yetkiye uygun hızlı aksiyonlar (Yeni konaklama/tahsilat/firma,
  `@can` ile gösterilir) + devam eden ve yaklaşan konaklamalar + son cari
  hareketler + son tahsilatlar.
- **B) Finansal durum** — para birimi bazında **açık alacak, açık borç, net
  bakiye**, bu ay konaklama ve bu ay tahsilat; aktif firma sayısı.
- **C) Dikkat gerekenler** — para birimi bazında en yüksek bakiyeli firmalar.

Admin/Patron (`dashboard.financial`) üçünü de görür. **Personel:** bugün/bu hafta
girdiği konaklama ve tahsilat **sayısı** ve kendi son kayıtları — tek katmanlı
"Bugün" başlığı altında (B ve C katmanları personele hiç uygulanmaz). **Tutar
gösterilmez ve SORGULANMAZ** — `PersonnelSummary` nesnesinde hiç `Money` alanı
yoktur, yani gizleme değil yapısal engel vardır.

**"Kasada Şu An" (Gün 10 — slot DOLDU):** Bugün ile Finansal durum arasında,
`cashbook.view` yetkisi olana ve AÇIK vardiya varken çizilir: vardiya sahibi,
devralma saati, hareket sayısı, para birimi bazında beklenen fiziksel kasa ve
son devir. Veri `CashBookService::dashboardSnapshot()`'tan hazır gelir; açık
vardiya yoksa tek sorgu döner ve kart hiç render edilmez.

**Personel panosuna kasa TUTARI eklenmemiştir:** `PersonnelSummary`'de hiç
`Money` alanı olmaması yapısal güvencesi Gün 10'da da korunur. Personel kendi
kasasını Kasa ekranından görür.

**"Sistem henüz boş" durumu** artık yalnızca cari defter boşken değil, KASA da
boşken gösterilir: kasa cari hesaptan bağımsızdır ve hiç firma yokken bile
gerçek para olabilir.

**Açık alacak/borç firma bazında NETLENDİKTEN SONRA toplanır:** bir firmanın
borcu başka firmanın alacağını götürmez. Para birimleri asla birleştirilmez (§6/L5).

"Bu ay" aralığı cari hareketin `entry_date` alanıyla aynı sütun üzerinden uygulanır:
konaklamada `check_out_date`, tahsilatta `payment_date`. Böylece ay toplamı, aynı ay
deftere düşen tutarla birebir tutar. İptal edilmiş kayıtlar sayılmaz.

### Cash Book (Kasa Defteri)

Fiziksel kasanın vardiya/devir defteri. **Cari hesaptan tamamen ayrıdır**:
`CashBookService` Company/Stay/Payment/Ledger modellerine hiç dokunmaz ve
`ledger_entries`'e HİÇBİR ŞEY yazmaz. Oda borcunun doğması ile paranın tahsil
edilmesi ayrı olaylardır: 5.000 TRY'lik konaklama nakit tahsil edilmediyse
kasada +0'dır.

**Vardiya:** aynı anda yalnızca BİR açık vardiya olur (§5.8). Vardiyayı açan
kişi kasadan sorumludur.

**Hareket türleri** (`CashMovementType`) ve gelir-gider raporuna etkisi:

| Tür | Kasaya etkisi | Gelir-gider raporu |
|---|---|---|
| `opening` (açılış bakiyesi) | + | **hayır** |
| `room_income` (oda geliri) | + | gelir |
| `extra_income` (ek gelir) | + | gelir |
| `expense` (gider) | − | gider |
| `cash_out` (ara kasa/patron/banka teslimi) | − | **hayır** |
| `cash_in` (kasaya dışarıdan para) | + | **hayır** |

> **EN KRİTİK KURAL:** ara kasa/patron teslimi **GİDER DEĞİLDİR**. Kasada
> 14.000 TRY varken patrona 12.000 teslim edilirse fiziksel kasa 2.000'e düşer
> ama gelir-gider raporunda 12.000'lik bir gider OLUŞMAZ. Ayrım tek yerde
> tanımlıdır: `CashMovementType::affectsIncomeStatement()`.

**Beklenen kasa** para birimi bazında hesaplanır ve para birimleri ASLA
birleştirilmez (§6/L5):
`açılış + oda geliri + ek gelir + kasa girişi − gider − kasa çıkışı`.
Hesap SQL'de değil serviste, tamsayı kuruş üzerinden yapılır.

**Değişmezlik:** kasa hareketi güncellenemez ve silinemez — ne hard delete ne
soft delete, ne de route. Düzeltme **ters harekettir**: aynı tip, aynı tutar,
ters yön, `is_reversal = true`. Böylece hem beklenen kasa hem gelir-gider
raporu kendiliğinden düzelir. **Açılış bakiyesi ters kaydedilemez** (devredilen
tutarla bağı kopardı); hatalı açılış kapanış sayımındaki FARK olarak görünür.

**Devir İKİ TARAFLIDIR.** Teslim eden devri başlatır (`pending`); teslim alan
KENDİ HESABIYLA kabul edene kadar devir tamamlanmaz ve kasa teslim edenin
vardiyasında görünmeye devam eder. Devir beklerken vardiyaya yeni hareket
girilemez. Kabul anında tek transaction içinde: kapanış sayımı yazılır →
teslim eden vardiya kapanır → teslim alan için yeni vardiya açılır → devredilen
tutarlar yeni vardiyanın `opening` hareketleri olur. Kendi kendine devir
yasaktır (DB CHECK).

**Sayım:** kapanışta personel her para birimi için FİİLEN saydığı tutarı girer;
beklenen tutar SUNUCUDA yeniden hesaplanır (formdan alınmaz). Fark para birimi
bazında ayrı tutulur, birbirine çevrilmez. Beklenen var ama hiç sayılmadıysa
satır yine yazılır — eksik para sessizce kaybolmaz.

**Hesap verebilirlik:** bir vardiyaya hareket girmek, ters kayıt yazmak ve
devretmek YALNIZCA vardiya sahibine açıktır — admin/patron dahil kimse
başkasının vardiyasına hareket yazamaz (kayıt argümanlı Policy yetenekleri
`Gate::before` ile kısa devre yapılmaz, §7.1). Tek istisna KAPATMAdır:
personel kapatmadan giderse kasa kilitli kalmasın diye `cashbook.view_all`
yetkisi olan da kapatabilir; kapatan denetim kaydına yazılır.

**Görünürlük:** açık vardiyanın VARLIĞINI ve sahibini `cashbook.view` olan
herkes görür (personelin kasanın kimde olduğunu bilmesi operasyonel bir
ihtiyaçtır); TUTARLARI yalnızca vardiya sahibi ve `cashbook.view_all` olanlar
görür.

**Denetim kaydı** `Auditable` trait'i ile değil, `CashBookService` tarafından
açıkça yazılır (finansal kayıt deseni, §8/Payments ile aynı gerekçe).

### Basic Audit Log
`Auditable` trait'i ile `Company` ve `User` modellerinde `created/updated/deleted` olayları;
ayrıca `login`, `login_failed`, `logout`, `payment cancelled`, `exported`, rol değişiklikleri.

**İstisna — `Stay`, `Payment` ve `LedgerEntry`:** bu modellerin denetim kayıtları ilgili
servis tarafından **açıkça** yazılır. Gerekçe: finansal kayıtların denetim satırı, ürettiği
ya da ters kaydettiği cari hareketlerin kimliklerini de taşımak zorundadır; genel trait bunu
ifade edemez. `LedgerEntry` ayrıca değiştirilemez olduğu için `updated`/`deleted` olayı hiç yoktur.
`old_values`/`new_values` sadece **değişen alanları** içerir. `password`, `remember_token` asla loglanmaz.
Ekran: tarih/kullanıcı/olay/kayıt tipi filtreli liste, salt okunur.

---

## 9. Arama, Filtre, Sayfalama

- Her liste ekranı aynı desende: `Core/Filters` altındaki filtre sınıfı + query string.
- Standart parametreler: `q`, `date_from`, `date_to`, `company_id`, `created_by`, `sort`, `direction`, `per_page`.
- Varsayılan sayfa boyutu 25, izinli değerler 25/50/100.
- Sıralama alanları **beyaz liste** ile sınırlanır (SQL injection ve rastgele kolon sızıntısına karşı).
- Filtreler sayfa yenilendiğinde korunur (query string).
- Arama: firma adı, vergi no, telefon, yetkili, şehir üzerinde `LIKE`. Fulltext V1'de yok.
- **Türkçe arama kuralı (ölçülerek karar verildi):** Türkçe'de büyük harfe çevirim çift
  yönlüdür (i↔İ, ı↔I) ve firma verisi çoğunlukla tümü büyük harfle girilir. Hiçbir tek
  collation her iki yönü de çözmez: `utf8mb4_turkish_ci` ile "ilgaz" araması `ILGAZ`
  kaydını, `utf8mb4_unicode_ci` ile "çınar" araması `ÇINAR` kaydını bulamaz.
  Bu yüzden arama karşılaştırmasında her iki tarafta noktalı/noktasız i tek karaktere
  indirgenir (`REPLACE(REPLACE(col,'ı','i'),'İ','i') COLLATE utf8mb4_unicode_ci LIKE ?`),
  arama terimi PHP tarafında aynı şekilde normalize edilir.
  **Sıralama bundan etkilenmez**; `ORDER BY` kolonun `utf8mb4_turkish_ci` collation'ını
  kullanmaya devam eder (Ç>C, ı<i, Ö>O, Ş>S, Ü>U).
- Ortak altyapı `Core\Filters\QueryFilter` sınıfındadır; her modül kendi `Filters/`
  klasöründe bu sınıfı genişletir ve `searchable()` / `sortable()` beyaz listelerini tanımlar.
- Tüm liste sorguları `paginate()` kullanır — `get()` ile tüm tablo çekilmez.

---

## 10. Excel Export

- `maatwebsite/excel`, `FromQuery` + `WithChunkReading` (paylaşımlı hostingde bellek limiti).
- Export **ekrandaki aktif filtreleri aynen uygular**.
- Export rotası, listenin kendi yetkisine **ve** `export.excel` yetkisine tabidir. Ekranda görülmeyen kolon export'ta da yer almaz.
- Her export bir `exported` audit kaydı üretir (hangi rapor, hangi filtre).
- Dosya adı: `{rapor}-{Y-m-d-His}.xlsx`.
- Export edilebilecekler: firma listesi, konaklama listesi, tahsilat listesi, firma bakiye listesi, cari ekstre.

---

## 11. Arayüz Standartları

### 11.1 Tasarım dili

Açık, sıcak nötr zemin + beyaz kartlar + **koyu lacivert kenar çubuğu** + sıcak altın
vurgu. Düz renk; **gölge, gradient, glassmorphism ve dekoratif öge yoktur**.
Ayrımlar ince çizgi (1px) ile yapılır. Bu tema uygulamanın **tek** temasıdır —
koyu tema, tema değiştirici ve kullanıcı tema tercihi **yoktur**.

Kenar çubuğu koyu bir yüzeydir ama bu bir "koyu tema" değildir: tek bir bileşenin
kendi paletidir ve kendi token'larını kullanır (`--color-nav*`).

**Vurgu (altın) yalnızca** birincil aksiyon butonu, aktif menü şeridi, marka
monogramı, aktif sıralama göstergesi, odak halkası ve zorunlu alan yıldızında
kullanılır. Silme/iptal butonları altın değildir (`danger` varyantı).

**Altın DOLGU rengidir, metin rengi değildir.** `--color-acc` (#C8A96B) beyaz
üzerinde yalnızca 2.25 kontrast verir. Metin ve odak halkası için
`--color-acc-tx` (#8A6A2F, 5.02) kullanılır. Bu ayrım bağlayıcıdır.

### 11.2 Tasarım token'ları — tek kaynak

Tüm renk ve tipografi token'ları **yalnızca** `resources/css/app.css` içindeki `@theme`
bloğunda tanımlıdır; Tailwind bu bloktan `bg-card`, `border-line`, `text-mut` gibi
sınıfları üretir. **Blade içinde ham hex kodu yazılmaz** — tek muafiyet
`<meta name="theme-color">`'dır (HTML düz değer ister). Bu kural
`DesignSystemTest::blade_icinde_ham_hex_rengi_yazilmaz` ile testle korunur.

| Token | Değer | Kullanım |
|---|---|---|
| `--color-bg` | `#F7F7F5` | sayfa zemini |
| `--color-card` | `#FFFFFF` | kart / tablo zemini |
| `--color-line` | `#E5E4E0` | ayraç ve kart sınırı |
| `--color-well` | `#F1F0EC` | çukur alan, tablo başlık şeridi, bakiye kolonu |
| `--color-tx` | `#1F2937` | ana metin (kart 14.68) |
| `--color-mut` | `#6B7280` | ikincil metin (kart 4.83, sayfa 4.51) |
| `--color-acc` | `#C8A96B` | **dolgu**: birincil buton, aktif şerit |
| `--color-acc-strong` | `#B08F52` | dolgunun hover hâli |
| `--color-acc-tx` | `#8A6A2F` | **metin ve odak halkası** olarak altın |
| `--color-acc-bg` | `#FAF6EE` | altın rozet zemini |
| `--color-on-acc` | `#1F2937` | altın dolgu üzerindeki metin (6.53) |
| `--color-nav` | `#172033` | kenar çubuğu yüzeyi |
| `--color-nav-well` | `#22304A` | aktif menü zemini |
| `--color-nav-line` | `#2B3752` | kenar çubuğu ayracı |
| `--color-nav-tx` / `--color-nav-mut` | `#E8E6E1` / `#9AA3B2` | kenar çubuğu metinleri |
| `--color-field` | `#8A8A85` | **yalnızca** form kontrolü ve ikincil buton sınırı (WCAG 1.4.11 → 3:1) |
| `--color-success` / `--color-danger` | `#15803D` / `#B91C1C` | durum metni |
| `--color-success-bg` / `--color-danger-bg` / `--color-info-bg` | `#ECFDF5` / `#FEF2F2` / `#EFF6FF` | durum kutusu zemini |
| `--color-debit` / `--color-credit` | `#B45309` / `#15803D` | **cari yön**: Borç / Alacak |

**Kullanım kuralı — `--color-well` üzerine `text-mut` YAZILMAZ**: oran 4.24'te
kalır ve 4.5 eşiğini geçmez. Çukur alanlarda `text-tx` kullanılır (12.87).

**Borç kırmızı DEĞİLDİR.** Kırmızı bu uygulamada "iptal / tehlike" demektir;
borç hatalı bir durum değildir. Borç `--color-debit` (amber), alacak
`--color-credit` (yeşil) kullanır ve her ikisi de **her zaman** metin etiketiyle
birlikte gösterilir.

Tipografi: `system-ui` yığını, gövde 15px/1.5, form kontrolleri **16px** (mobil zoom'u
engeller), sayfa başlığı 19px/500. Tüm tutar hücreleri `font-variant-numeric: tabular-nums`
ve sağa hizalı.

### 11.3 Blade bileşenleri

Ortak bileşenler `resources/views/components/ui/` altındadır ve `<x-ui.*>` ile çağrılır:
`card`, `page-header`, `button`, `input`, `select`, `textarea`, `checkbox`, `badge`,
`alert`, `errors`, `empty`, `table`, `sort-header`, `th`, `stat`, `panel`.

`stat` sayısal özet kutusudur (etiket + değer + ipucu); `panel` başlıklı liste
kartıdır (dahili başlığı `<h3>`'tür — sayfa başlığı `<h1>`, `<x-ui.page-header>`
altındaki bölüm başlıkları varsa `<h2>` olduğu için). Pano ve firma detayı aynı
iskeleti bu ikisiyle kurar.

**Mobil sayfa header standardı (Gün 9.6):** her liste ekranı `<x-ui.page-header>`
ile başlık + kısa durum satırı (`subtitle`) + birincil aksiyon (`actions` slotu)
üçlüsünü aynı desende kurar — örn. "Firmalar (6) / 5 aktif firma / [Yeni firma]".
`subtitle` gerçek, ucuz (yeni sorgu gerektirmeyen ya da tek ucuz COUNT sorgusu)
ve dürüst bir cümledir; sayaç rozetinin (`count`) tekrarı DEĞİLDİR — filtrelenmiş
sonuç toplamı ile "aktif" gibi ayrı bir anlam taşıyan sayı birlikte gösterilebilir
(Firmalar: toplam + aktif firma sayısı), ayrı bir anlam yoksa yalnızca `subtitle`
yeterlidir (`count` atlanır).

- Form kontrolleri hata durumunu `$errors` üzerinden kendisi okur; `aria-invalid` ve
  `aria-describedby` bağlantısını kendisi kurar. Her `<label>` `for` ile input `id`'sine bağlıdır.
- Bileşen soyutlaması gereksiz büyütülmez: tek yerde kullanılan bir işaretleme bileşen yapılmaz.
- Sayfalama görünümleri `resources/views/vendor/pagination/` altında Türkçeleştirilmiştir.

### 11.4 Yerleşim

- Masaüstünde sabit **256px** kenar çubuğu (`md` ve üzeri). Kenar çubuğu turuncuya boyanmaz;
  aktif öğe `well` zemin + sol turuncu şerit + `aria-current="page"` ile belirtilir.
- Menü **yetki duyarlıdır**: kullanıcının göremeyeceği bölüm hiç render edilmez.
  Route'u olmayan bir ekran menüye eklenmez (disabled placeholder yazılmaz).
- Kenar çubuğu üstünde **marka alanı** vardır (`partials/brand`): logo dosyası
  (`config('hotel.brand.logo')`, varsayılan `public/images/logo.svg`) varsa
  kullanılır, yoksa altın zeminli monogram çizilir — kırık görsel gösterilmez.
- Menü **gruplu**dur: başlıksız ana grup, "Cari", "Yönetim". İçinde görünür öğe
  kalmayan grup hiç çizilmez.
- Aktif öğe ÜÇ işaretle belirtilir: sol altın şerit, `nav-well` zemin ve
  `aria-current="page"`. Aktiflik desenle bulunur (`companies.*`), böylece alt
  ekranlarda da işaretli kalır.
- Kenar çubuğu altında kullanıcı baş harfi, adı, rol etiketi ve çıkış bağlantısı bulunur.
- Tüm sayfalarda "İçeriğe atla" bağlantısı ve `<main id="icerik">` vardır.

**Mobil (< 768px) gezinme (Gün 9.6 — kenar çubuğu artık BİRİNCİL değil):**

- Birincil gezinme sabit **alt gezinme çubuğudur** (`partials/bottom-nav.blade.php`,
  `[data-bottom-nav]`, `md:hidden`). **Gün 10 sırası: Ana Sayfa, Kasa, Firmalar,
  Tahsilatlar + Daha Fazla.** Konaklamalar "Daha Fazla" çekmecesine taşındı: kasa
  defteri günde onlarca kez, konaklama listesi çok daha seyrek açılır. Sekmeler yetkiye duyarlıdır (aynı `$user->can()` deseni), ikon + kısa metin
  birlikte gösterilir (yalnızca ikon değil), dokunma hedefi ≥ 44px, `aria-current="page"`
  ile aktif sekme işaretlenir, `env(safe-area-inset-bottom)` ile iPhone alt çentiği
  hesaba katılır. Ana içerik (`<main id="icerik">`) alt çubuğun üstüne binmemesi için
  mobilde ekstra alt boşluk taşır (`md`de normale döner).
- **"Daha Fazla" yeni bir menü DEĞİLDİR** — mevcut kenar çubuğu çekmecesini ikinci bir
  tetikleyiciyle açar (aynı `data-sidebar-toggle` işareti, aynı `#kenar-cubugu` paneli).
  Çekmece artık İKİNCİL gezinmedir: Cari Ekstre, Kullanıcılar (yetkiliyse),
  Roller/Yetkiler, Denetim Kayıtları, kullanıcı kimliği ve Çıkış oradadır. Ayrı bir
  Profil/Hesap SAYFASI yoktur (böyle bir route bu turdan önce de yoktu; yeni route
  eklemek bu turun kapsamı dışıdır — CLAUDE.md §18) — kimlik bilgisi çekmecenin alt
  bloğunda zaten görünür.
- Kenar çubuğunu açan HER tetikleyici (üst çubuktaki hamburger + alt çubuktaki
  "Daha Fazla") `[data-sidebar-toggle]` ile işaretlidir; `resources/js/app.js`'teki
  `drawer` nesnesi hepsini birlikte yönetir (`aria-expanded`, odak geri dönüşü).
- **Katman sırası bağlayıcıdır:** alt çubuk `z-20`, çekmece arka planı (overlay) `z-30`,
  çekmece paneli `z-40`. Çekmece açıkken overlay alt çubuğu GÖRSEL olarak örtmelidir;
  aksi hâlde ikisi aynı katmanda olursa alt çubuk overlay'in üstünde kalır ve hem yanlış
  görünür hem de klavye/dokunma etkileşimi çekmeceyi "atlayıp" alttaki sekmelere sızar.
- **Kapalı çekmece VE açıkken alt çubuk `inert`'tir** (`resources/js/app.js`,
  `matchMedia('(min-width: 768px)')` ile senkronize): mobilde ekran dışındayken
  çekmecenin içindeki linkler, çekmece AÇIKKEN de alt çubuğun linkleri `Tab` sırasından
  çıkar — aksi hâlde klavye kullanıcısı görünmeyen/örtülü içeriğe atlar. Masaüstünde
  (`md` ve üzeri, kenar çubuğu her zaman sabit) hiçbiri asla uygulanmaz. Bu davranış
  silinirse gerçek bir klavye tuzağı geri döner (Gün 9.5, `docs/kararlar.md` K-086;
  Gün 9.6 için K-088).
- İkon yolları TEK kaynaktan gelir: `App\Modules\Core\Support\NavIcons::PATHS`. Kenar
  çubuğu ve alt çubuk aynı sabiti kullanır — kopyalanmaz. (Blade `@include` ile
  paylaşmaya çalışmak İŞE YARAMAZ: bir include içinde tanımlanan değişken üst kapsama
  geri sızmaz; bu yüzden sınıf sabiti seçildi.)

### 11.5 Tablo ve mobil liste

- Tablolar **`lg` (1024px) ve üzerinde** gösterilir; altında aynı veri kart listesine düşer.
  Gerekçe: 256px kenar çubuğundan sonra `md` genişlikte ~460px kalır ve tablo yatay kaydırma
  gerektirirdi. **Ana tablolarda yatay scroll yoktur.**
- Zebra stripe kullanılmaz; satır ayrımı ince çizgi iledir.
- **Dokunma hedefi minimum 44px** (`min-h-11`) — mobil kart/panel satırlarında satırın
  TAMAMI (tutar dahil) `<a>` ile sarmalanır, yalnızca başlık metni değil. Padding'e
  güvenilmez: gerçek yükseklik ölçülerek doğrulanır (Gün 9.5, K-086). İstisna: masaüstü
  tablosundaki sıralama başlıkları ve satır aksiyonları (yalnızca `lg`/1024px ve üzerinde
  görünür, fare odaklıdır).

### 11.6 Finansal ve durum gösterimi

- Tutar biçimi: `1.234,56 ₺` (Türkçe binlik/ondalık). Tarih: `d.m.Y`.
- **Borç/alacak, aktif/iptal gibi anlamlar yalnızca renge bağlanmaz** — her durumun
  metin etiketi vardır (renk körlüğü). Rozetler `<x-ui.badge>` ile.
- Durum kutuları (`<x-ui.alert>`) yalnızca gerçekten uyarı/durum gerektiğinde kullanılır.
- Monospace **yalnızca** denetim kaydı gibi teknik ekranlarda; normal formlarda kullanılmaz.
- Silme/iptal işlemleri onay adımı ister. Flash mesajları tek partial'dan gelir.

### 11.7 JavaScript

`resources/js/app.js` sade JavaScript'tir, harici paket yoktur. İçinde üç şey vardır:
kenar çubuğu çekmecesi, **çift gönderim koruması** ve konaklama formunun ücret
önizlemesi.

**Alpine.js kurulu DEĞİLDİR** ve bu bilinçlidir; ihtiyaç doğarsa (modal, tutar
maskesi, dinamik firma arama) kullanıcı onayıyla eklenir. JS ile iş mantığı
yazılmaz, veri hesaplanmaz.

**Çift gönderim koruması:** her form gönderiminde submit butonları kilitlenir.
Kilitleme `setTimeout(0)` ile yapılır — devre dışı bir butonun `name`/`value`
çifti istek gövdesine girmez. Tarayıcı doğrulaması gönderimi durdurursa
(`invalid` olayı) ve bfcache'ten geri dönüldüğünde kilit açılır. Bir formun
muaf olması gerekirse `data-allow-resubmit` konur.

### 11.8 Erişilebilirlik

- Metin kontrastı ≥ 4.5:1, arayüz sınırı/durum göstergesi ≥ 3:1 (WCAG 2.1 1.4.3 / 1.4.11).
- Görünür odak halkası (`:focus-visible` → 2px altın, 2px offset; kenar çubuğunda açık
  altın — koyu zeminde okunabilirlik için, bkz. `.nav-surface :focus-visible`).
- Yalnızca ikon içeren butonlarda `aria-label`. `<html lang="tr">`.
- Kontrol edilen kırılımlar: **360 / 375 / 390 / 430 / 768 / 1366 / 1920** — hiçbirinde
  yatay sayfa kaydırması, kırpılmış tutar veya 40px altı dokunma hedefi yoktur (Gün 9.5
  denetimi: 14 ekran × 6 viewport = 84 kontrol, gerçek Chromium ile).

### PWA (temel)

- `manifest.json` (ad, ikonlar 192/512 + maskable, `display: standalone`, tema rengi)
- Service worker: **sadece statik varlık ve app-shell cache**; sürüm damgalı cache adı, yeni sürümde eski cache temizlenir.
- Çevrimdışı: bilgilendirme sayfası gösterilir. **Çevrimdışı veri girişi, form kuyruğa alma ve arka plan senkronizasyonu yoktur** — kaybolan tahsilat kaydı güveni bitirir.
- `sw.js` site kökünden servis edilir (`/sw.js`).

---

## 12. Test Zorunlulukları

Test yazılmadan merge edilmeyecek alanlar:

**Ledger (Feature + Unit) — pazarlıksız:**
1. Konaklama oluşturma → doğru tutarda tek debit satırı
2. Tahsilat oluşturma → doğru tutarda tek credit satırı
3. Bakiye = SUM(debit) − SUM(credit)
4. Tahsilat iptali → ters debit satırı, bakiye eski haline döner
5. Konaklama tutar güncellemesi → ters + yeni satır, bakiye doğru
6. Konaklama soft delete → ters satır
7. `LedgerEntry` update/delete denemesi exception fırlatır
8. Aynı kaynak için ikinci ters kayıt yazılmaz (idempotans)
9. Farklı para birimleri ayrı bakiye üretir, toplanmaz

**Yetki (Feature):**
- Her rol × her kritik route için 200/403 matrisi
- Personel: başka kullanıcının kaydını düzenleme denemesi → 403
- Personel: süre penceresi dolmuş kendi kaydı → 403
- Personel: `ledger.*`, `dashboard.financial`, `export.excel` route'ları → 403
- Personel: form'a `amount` göndererek başkasının kaydını değiştirme denemesi → engellenir

**Kasa Defteri (Feature) — pazarlıksız:**
1. Beklenen kasa = açılış + gelirler + kasa girişi − gider − kasa çıkışı
2. Ara kasa/patron teslimi kasayı azaltır ama GİDER TOPLAMINI ARTIRMAZ
3. Para birimleri ayrı hesaplanır, birbirine eklenmez (RUB/USD/GEL dahil)
4. Aynı anda ikinci vardiya açılamaz (uygulama + veritabanı)
5. Kapalı vardiyaya hareket eklenemez (servis + tetikleyici)
6. Hareket update/delete denemesi exception fırlatır (model + tetikleyici)
7. Ters kayıt beklenen kasayı ve gelir toplamını düzeltir; ikinci ters kayıt yazılmaz
8. Açılış bakiyesi ters kaydedilemez
9. Devri yalnızca hedef kullanıcı kabul eder; kabul edilmiş devir tekrar kabul edilemez
10. Devirde kapanış sayımı ve fark otomatik yazılır
11. Personel başka personelin vardiyasına hareket ekleyemez (admin dahil)
12. Günlük defter sorgu sayısı vardiya sayısıyla büyümez (N+1)

**Diğer:** login rate limit, pasif kullanıcı girişi, cari hareketi olan firmanın silinememesi.

Komut: `php artisan test`. CI yeşil olmadan push edilmez.

**Testler SQLite'ta değil, gerçek MySQL/MariaDB üzerinde koşar** (`phpunit.xml`, `hotel_account_test`). Gerekçe: SQLite'ta gerçek `DECIMAL` yoktur (NUMERIC affinity → float), `utf8mb4_turkish_ci` collation ve `RESTRICT` foreign key davranışı taklit edilemez. Cari bakiye ve Türkçe arama testleri ancak canlı ortamla aynı motorda anlam taşır.

---

## 13. Kodlama Standartları

- `pint` (Laravel preset) ile formatlama; `larastan/phpstan` **level 5** (kurulu ve CI'da zorunlu).
  Baseline dosyası ve `@phpstan-ignore` yorumu **kullanılmaz** — bulgu gizlenmez, kaynağından düzeltilir.
- Controller **ince**: yetki → validasyon (FormRequest) → Service/Action çağrısı → redirect/view. İş mantığı controller'da olmaz.
- Validasyon **sadece** FormRequest sınıflarında. `$request->all()` ile mass assignment yapılmaz; `$request->validated()` kullanılır.
- Yetki **sadece** Policy sınıflarında. Controller içinde `if ($user->role === 'admin')` yazılmaz.
- Model `$fillable` dar tutulur; `$guarded = []` yasak.
- PHP enum'ları: `PaymentMethod`, `PaymentStatus`, `Currency`, `AuditEvent`, `StayPricingMode`, `StayStatus`, `LedgerEntryType`. Serbest string sabitleri kullanılmaz.
- Para hesabı: SQL'de `SUM()`, PHP'de `Core\Support\Money`. **Float aritmetiği yasak.**
  `Money` tutarı **tamsayı kuruş** olarak tutar; toplama/çıkarma/karşılaştırma tam sayı aritmetiğiyle yapılır. Veritabanında alan `DECIMAL(15,2)` kalır.
  **`bcmath` kullanılmaz** — eklenti her ortamda bulunmayabilir (bu projenin geliştirme ortamında yoktur) ve tamsayı kuruş yaklaşımı aynı kesinliği sıfır bağımlılıkla verir.
- Model cast: `'amount' => 'decimal:2'`, `'check_in_date' => 'date'`.
- N+1 yok: listelerde `with()` zorunlu. `Model::preventLazyLoading(!app()->isProduction())` açık.
- Kuyruk/queue V1'de kullanılmaz.
- Yorumlar Türkçe olabilir; kod adları İngilizce.
- Yeni composer paketi eklemeden önce sor.

### İsimlendirme

| Öğe | Kural | Örnek |
|---|---|---|
| Tablo | çoğul snake_case | `ledger_entries` |
| Model | tekil PascalCase | `LedgerEntry` |
| Route adı | nokta ayrık | `payments.create` |
| Blade dosyası | kebab-case | `ledger-statement.blade.php` |
| Yetki | `modul.eylem` | `stays.update` |
| Migration | açıklayıcı | `create_ledger_entries_table` |

---

## 14. Geliştirme Fazları (7–14 gün)

Sıra bağlayıcıdır; **Ledger, Stays ve Payments'tan önce yapılır.**

**AuditLog Gün 11'den Gün 2'ye alınmıştır.** Denetim kaydını sonradan eklemek geçmiş
veriyi kurtarmaz; giriş/çıkış, kullanıcı ve yetki değişiklikleri ilk günden itibaren
kaydedilmelidir.

| Gün | İş | Bitti sayılma kriteri |
|---|---|---|
| 1 | Laravel iskeleti, modül autoload, Tailwind/Vite, layout, **cPanel'e ilk deploy** | Boş sayfa canlıda açılıyor |
| 2 | Users + Roles/Permissions + **AuditLog altyapısı** + seeder | 3 rolle giriş yapılıyor, menüler farklı, her giriş/CRUD loglanıyor |
| 3 | Companies CRUD + arama | Gerçek firma listesi girilebiliyor |
| 4 | **Ledger çekirdeği + testleri** (ekransız) | §12'deki 9 cari testi yeşil |
| 5–6 | Stays CRUD + cari entegrasyonu | Konaklama → doğru debit |
| 7 | Payments + iptal + cari entegrasyonu | Tahsilat → doğru credit, iptal geri alıyor |
| 8 | Cari ekstre + firma bakiye listesi + yazdırma | Ekstre elle hesapla birebir tutuyor |
| 9 | Dashboard (role duyarlı) + tasarım sistemi | Personel finansal kart görmüyor; pano sorgu sayısı sabit |
| 10 | **Kasa Defteri** (vardiya, hareket, devir, sayım, günlük defter) | Devir zinciri çalışıyor, beklenen kasa elle hesapla tutuyor |
| 10.1 | Search & Filters standardizasyonu + Excel export | Filtreli export doğru satırları veriyor |
| 11 | PWA + denetim kaydı ekranının cilası | Ana ekrana eklenebiliyor |
| 12 | Mevcut Excel geçmişinin aktarımı (§14.1) | Ledger bakiyesi, Excel KALAN BAKİYE ile mutabık |
| 13 | Yetki testleri, güvenlik sertleştirme, yedekleme cron'u | Rol matrisi testleri yeşil |
| 14 | Canlıya alma, kullanıcı eğitimi, paralel kullanım başlangıcı | Patron ve personel gerçek veri giriyor |

Her gün sonu: testler yeşil → `pint` + `phpstan` temiz → commit → staging'e deploy.

### 14.1 Excel Veri Göçü (karar verildi)

Mevcut Excel geçmişi **mümkün olduğunca tam** aktarılır. Yalnızca açılış bakiyesi aktarımı yapılmaz.

**Import prensipleri:**

1. Her **geçerli konaklama satırı** → bir `stay` kaydı oluşturur. Excel'deki
   GÜN / FİYAT / TOPLAM alanları `nights` / `daily_rate` / `amount` ile doğal olarak
   eşleşir: fiyat kolonu doluysa `pricing_mode = daily`, yalnızca toplam varsa `total`.
2. Her `stay` → firma cari defterine **debit** hareketi üretir.
3. **Ödeme tutarı ve ödeme tarihi bulunan her kayıt** → ayrı bir `payment` kaydı oluşturur.
4. Her `payment` → firma cari defterine **credit** hareketi üretir.
5. Tahsilat, V1'de belirli bir konaklama ile **zorunlu olarak eşleştirilmez** (allocation yok).
6. Excel'deki **KALAN BAKİYE alanı import EDİLMEZ.** Yalnızca mutabakat/kontrol amacıyla okunur.
7. Yeni bakiye **ledger üzerinden yeniden hesaplanır** (`SUM(debit) - SUM(credit)`).
8. **Açılış/devir bakiyesi satırı üretilmez.** `source_type = 'opening'` gibi bir tip yoktur.

**Sayfa (sheet) özel kuralları:**

- **`ALACAKLAR` sayfası bir şirket sayfası değildir.** İçinde farklı şirketler bulunur;
  ayrı mapping ve ayrı import kuralı gerektirir. Diğer sayfalarla aynı mantıkla işlenemez.
- **`BIRDIE HOLIDAY` / `BIRDIE HOLIDAY YENİ` ve `YALIN DENTAL`** gibi sayfalarda
  farklı para birimi kolonları vardır; her biri özel mapping gerektirir.
  Para birimi kolonundan gelen değer `stays.currency` / `payments.currency` alanına
  doğrudan yazılır — **çevrim yapılmaz** (§6/L5).

**Kod yerleşimi:**

- Import kodu, uygulamanın normal domain servislerinden **ayrı tutulur**.
  `StayService`, `PaymentService` ve `LedgerService` içinde **hiçbir import'a özel dal bulunmaz**.
- Import yalnızca **CLI üzerinden, kontrollü biçimde** çalıştırılır; HTTP route'u yoktur.
- Import, cari kayıtlarını yine `LedgerService` üzerinden yazar — böylece §6'daki
  değişmezler (append-only, tek yönlü debit/credit, transaction bütünlüğü) import için de geçerlidir.
- Bu kod kalıcı bir modül değildir; V1 sonrası kaldırılabilir.

**Mutabakat zorunluluğu:**

Import tamamlandığında, her firma için hesaplanan ledger bakiyesi ile Excel'deki
KALAN BAKİYE karşılaştırılır ve **fark raporu** üretilir. Fark varsa import kabul
edilmez; önce fark açıklanır. Bu rapor olmadan Excel bırakılmaz.


---

## 15. Dağıtım (cPanel)

Canlı dizin yerleşimi — **Mimari A** (`docs/deploy-lemon.md` §2):

```
/home/kullanici/
├── lemon-app/            # Laravel kökü — web'den ERİŞİLEMEZ (.env, storage, vendor burada)
│   └── public/           # ← DocumentRoot BURAYI gösterir (cPanel'den elle ayarlanır)
│       └── index.php     # Laravel'in KENDİ giriş dosyası — uyarlanmaz, hacklenmez
├── public_html/          # BAŞKA sitenin DocumentRoot'u — deploy hedefi DEĞİLDİR
└── backups/
```

Hesapta birden fazla canlı site vardır. Deploy'un **tek** hedefi Laravel köküdür;
workflow'un `guard` işi başka her hedefi fail-closed reddeder.

**Deploy yöntemi: GitHub Actions + FTPS. SSH erişimi zorunlu kabul edilmez.**

- Genel ilkeler: `docs/deploy-github-actions.md`
- **Hedefe özel kurulum, dizin mimarisi kararı ve blocker'lar: `docs/deploy-lemon.md`**
- Workflow: `.github/workflows/deploy.yml` — yalnızca elle tetiklenir, varsayılanı
  `dry-run`'dır ve `DEPLOY_ENABLED` variable'ı olmadan gerçek aktarım yapamaz.

`develop` → Lemon (kabul/staging), `main` → production; ayrı hedefler, ayrı veritabanı.
**Otomatik push deployment hiçbir dal için etkin değildir.**

> **PHP sürümü blocker'ı ÇÖZÜLDÜ** — bkz. §2 ve `docs/kararlar.md` K-060.
> Bağımlılıklar `config.platform.php = "8.3.0"` ile PHP 8.3'e kilitlendi;
> Symfony bileşenleri 7.4 LTS'e indi. `composer why-not php 8.3` temiz.
>
> **TRIGGER blocker'ı da kaldırıldı** — bkz. `docs/deploy-lemon.md` §8 ve
> `docs/kararlar.md` K-068. cPanel'de `derspros_sebahattin` → `derspros_lemon`
> TÜM AYRICALIKLAR atanmış durumdadır. Kesin doğrulama **ilk gerçek production
> migration'da** yapılır: `CREATE TRIGGER` başarısız olursa `migrate --force`
> fail eder ve **DocumentRoot değiştirilmez**. Ledger trigger koruması hiçbir
> koşulda bypass edilmez veya "yetki yoksa atla" hâline getirilmez (§6/L1).
>
> **Dry-run kilitsizdir** (`docs/deploy-lemon.md` §9, K-069): `mode: dry-run`
> `DEPLOY_ENABLED` ve `confirm_target` gerektirmez, sunucuya hiçbir dosya
> yazmaz/silmez. Kilitler yalnızca `mode: deploy` yolundadır.

Kurallar:
- `.env` repoda tutulmaz ve deploy sırasında **asla üzerine yazılmaz**.
- `storage/app/` (kullanıcı yüklemeleri) ve `bootstrap/cache/` deploy tarafından silinmez.
- `.env`, `storage/`, `vendor/` **asla** `public_html` altında olmaz. Deploy sonrası `https://site/.env` isteğinin 403/404 döndüğü doğrulanır.
- `vendor/` sunucuda `composer install` ile değil, CI'da build edilip yüklenir (bellek limiti).
- Vite build çıktısı (`public/build`) CI'da üretilir, repoya commit edilmez.
- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS zorunlu.
- Cron: günlük `mysqldump` + şifreli yedek kopyası (offsite). `schedule:run` V1'de sadece yedek için.
- Deploy sonrası: `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
- **Migration'lar geri alınabilir olmalı** (`down()` yazılır) ama canlıda `migrate:rollback` yerine ileri düzeltme migration'ı tercih edilir.

---

## 16. Git Akışı

Dal yapısı:

| Dal | Rolü |
|---|---|
| `main` | production — yalnızca yayına çıkan sürüm |
| `develop` | staging / entegrasyon dalı |
| `feature/*` | geliştirme dalları; `develop`'tan açılır, `develop`'a döner |

- **Yeni geliştirme doğrudan `main`'e commit edilmez.**
- `main`'e yalnızca `develop` üzerinden gelinir.
- Commit mesajları Türkçe, kısa ve emir kipinde; tip ön ekli:
  `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
  Örnek: `feat: tahsilat iptalinde ters cari kaydı`
- Bir commit bir iş yapar; migration + model + test aynı commit'te gider.
- Kullanıcı açıkça istemedikçe **pull request açılmaz**.
- `.env`, `storage/`, `vendor/`, `node_modules/`, `public/build/`, `*.sql` commit edilmez.

---

## 17. Komutlar

```bash
composer install
npm install && npm run dev          # geliştirme
npm run build                       # üretim derlemesi
php artisan migrate:fresh --seed    # yerel sıfırlama
php artisan test                    # tüm testler
php artisan test --filter=Ledger    # cari testleri
./vendor/bin/pint                   # formatlama
./vendor/bin/phpstan analyse         # statik analiz
php artisan serve
```

---

## 18. Claude İçin Davranış Kuralları

**Yapma:**
- §3.2'deki listeden hiçbir şeyi "hazırlık olsun diye" ekleme — tablo, kolon, servis, config anahtarı dahil.
- İstenmeyen yeni modül, paket veya soyutlama katmanı ekleme.
- `ledger_entries` üzerinde UPDATE/DELETE yazma.
- Finansal kayıt için hard delete yazma.
- Para hesabında float kullanma.
- Yetki kontrolünü controller içinde rol adı karşılaştırarak yapma.
- Kapsamı genişletirken "küçük bir ekleme" diye geçiştirme.
- Testleri atlama veya geçsin diye zayıflatma.

**Yap:**
- Belirsizlik varsa **sor**; özellikle cari mantığını etkileyen kararlarda.
- Cari etkileyen her değişiklikte §12'deki testleri çalıştır ve sonucu bildir.
- Bir kural bu dosyayla çelişiyorsa önce bu dosyayı güncellemeyi öner.
- V1'i bitirmeye hizmet etmeyen bir iş öneriliyorsa "bu V1 sonrası" diyerek not düş.
- Kararlarını `docs/` altına kısa notlar olarak yaz (mimari kararlar, veri göçü notları).

**Bu dosya güncel tutulur.** Şema, rol matrisi veya kapsam değişirse aynı commit'te CLAUDE.md da güncellenir.
