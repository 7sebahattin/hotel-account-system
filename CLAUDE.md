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
| Dil | PHP 8.3+ (geliştirme ortamı: 8.4) |
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

### 3.2 ŞİMDİ YAPILMAYACAKLAR (kesin)

Aşağıdakiler için **tablo, migration, model, servis, route, view, config anahtarı veya "ileride lazım olur" placeholder'ı üretme:**

- Guest CRM / misafir kartı (konaklamada sadece `guest_name` metni var)
- TC kimlik / pasaport saklama, KVKK altyapısı, şifreleme
- KBS entegrasyonu
- Rezervasyon
- Oda yönetimi (`room_no` sadece serbest metin)
- Housekeeping
- Stay charges / ekstra harcamalar
- Kasa & banka hesap yönetimi
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
routes/web.php                      # modül route dosyalarını include eder
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
| payment_method | varchar(30) null | enum `PaymentMethod`: `cash`, `bank_transfer`, `credit_card`, `other` |
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
- `CHECK (payment_method IS NULL OR payment_method IN ('cash','bank_transfer','credit_card','other'))`
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

### 5.7 Morph map

`AppServiceProvider::boot()` içinde `Relation::enforceMorphMap([...])` zorunlu:
`'stay' => Stay::class`, `'payment' => Payment::class`, `'company' => Company::class`,
`'user' => User::class`, `'role' => Role::class`, `'ledger_entry' => LedgerEntry::class`.
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

**L5.** Bakiye **para birimi bazında** hesaplanır. **TRY, EUR ve GBP bakiyeleri kesinlikle tek toplamda birleştirilmez.** V1'de otomatik kur servisi ve kur çevrimi yoktur; çok para birimli firmanın ekstresi para birimine göre gruplanır ve her grup kendi bakiyesini gösterir.

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
- Firma cari ekstresi: tarih aralığı filtresi, açılış bakiyesi (aralık öncesi net), satır satır hareketler, yürüyen bakiye (sorguda değil, view'da hesaplanır), kapanış bakiyesi.
- Firma bakiye listesi (`ledger.view_all`): tüm firmalar, bakiye, son hareket tarihi.
- Ekstre yazdırma görünümü (`layouts/print`) ve Excel export.

### Dashboard
Role duyarlı, tek sorgu setiyle:
- **Admin/Patron:** toplam alacak, bu ay konaklama tutarı, bu ay tahsilat, en yüksek bakiyeli 10 firma, son 10 hareket.
- **Personel:** bugün/bu hafta girdiği konaklama ve tahsilat sayısı, kendi son kayıtları. **Tutar toplamı gösterilmez.**

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

Koyu slate zemin + turuncu vurgu. Düz renk; **gölge, gradient, dekoratif öge yoktur**.
Ayrımlar ince çizgi (1px) ile yapılır. Bu tema uygulamanın **tek** temasıdır —
light mode, tema değiştirici ve kullanıcı tema tercihi **yoktur**.

**Vurgu (accent) yalnızca** birincil aksiyon butonu, seçili menü öğesi, aktif sıralama
göstergesi, odak halkası ve zorunlu alan yıldızında kullanılır. Silme/iptal butonları
turuncu değildir (`danger` varyantı).

### 11.2 Tasarım token'ları — tek kaynak

Tüm renk ve tipografi token'ları **yalnızca** `resources/css/app.css` içindeki `@theme`
bloğunda tanımlıdır; Tailwind bu bloktan `bg-card`, `border-line`, `text-mut` gibi
sınıfları üretir. Blade içinde ham hex kodu yazılmaz.

| Token | Değer | Kullanım |
|---|---|---|
| `--color-bg` | `#0f172a` | sayfa zemini |
| `--color-card` | `#1e293b` | kart / tablo zemini |
| `--color-line` | `#334155` | ayraç ve kart sınırı |
| `--color-field` | `#64748b` | **yalnızca** form kontrolü, ikincil buton ve sayfalama sınırı (WCAG 1.4.11 → 3:1) |
| `--color-tx` | `#e2e8f0` | ana metin |
| `--color-mut` | `#94a3b8` | ikincil metin, placeholder |
| `--color-acc` | `#f97316` | vurgu |
| `--color-well` | `#0b1220` | çukur alan, aktif menü zemini |
| `--color-success` / `--color-danger` | `#4ade80` / `#f87171` | durum metni |
| `--color-success-bg` / `--color-danger-bg` | `#1c2b1f` / `#2b1c1c` | durum kutusu zemini |

Tipografi: `system-ui` yığını, gövde 15px/1.5, form kontrolleri **16px** (mobil zoom'u
engeller), sayfa başlığı 19px/500. Tüm tutar hücreleri `font-variant-numeric: tabular-nums`
ve sağa hizalı.

### 11.3 Blade bileşenleri

Ortak bileşenler `resources/views/components/ui/` altındadır ve `<x-ui.*>` ile çağrılır:
`card`, `page-header`, `button`, `input`, `select`, `textarea`, `checkbox`, `badge`,
`alert`, `errors`, `empty`, `table`, `sort-header`, `th`.

- Form kontrolleri hata durumunu `$errors` üzerinden kendisi okur; `aria-invalid` ve
  `aria-describedby` bağlantısını kendisi kurar. Her `<label>` `for` ile input `id`'sine bağlıdır.
- Bileşen soyutlaması gereksiz büyütülmez: tek yerde kullanılan bir işaretleme bileşen yapılmaz.
- Sayfalama görünümleri `resources/views/vendor/pagination/` altında Türkçeleştirilmiştir.

### 11.4 Yerleşim

- Masaüstünde sabit **256px** kenar çubuğu (`md` ve üzeri). Kenar çubuğu turuncuya boyanmaz;
  aktif öğe `well` zemin + sol turuncu şerit + `aria-current="page"` ile belirtilir.
- Menü **yetki duyarlıdır**: kullanıcının göremeyeceği bölüm hiç render edilmez.
  Route'u olmayan bir ekran menüye eklenmez (disabled placeholder yazılmaz).
- Kenar çubuğu altında kullanıcı adı, rol etiketi ve çıkış bağlantısı bulunur.
- Mobilde hamburger + çekmece (sağdan kayan overlay, `Escape` ile kapanır); içerik tam genişlik.
- Tüm sayfalarda "İçeriğe atla" bağlantısı ve `<main id="icerik">` vardır.

### 11.5 Tablo ve mobil liste

- Tablolar **`lg` (1024px) ve üzerinde** gösterilir; altında aynı veri kart listesine düşer.
  Gerekçe: 256px kenar çubuğundan sonra `md` genişlikte ~460px kalır ve tablo yatay kaydırma
  gerektirirdi. **Ana tablolarda yatay scroll yoktur.**
- Zebra stripe kullanılmaz; satır ayrımı ince çizgi iledir.
- Dokunma hedefi minimum 44px.

### 11.6 Finansal ve durum gösterimi

- Tutar biçimi: `1.234,56 ₺` (Türkçe binlik/ondalık). Tarih: `d.m.Y`.
- **Borç/alacak, aktif/iptal gibi anlamlar yalnızca renge bağlanmaz** — her durumun
  metin etiketi vardır (renk körlüğü). Rozetler `<x-ui.badge>` ile.
- Durum kutuları (`<x-ui.alert>`) yalnızca gerçekten uyarı/durum gerektiğinde kullanılır.
- Monospace **yalnızca** denetim kaydı gibi teknik ekranlarda; normal formlarda kullanılmaz.
- Silme/iptal işlemleri onay adımı ister. Flash mesajları tek partial'dan gelir.

### 11.7 JavaScript

`resources/js/app.js` içinde yalnızca kenar çubuğu çekmecesi vardır — sade JavaScript,
harici paket yok. Alpine.js **kurulu değildir**; ihtiyaç doğarsa (modal, tutar maskesi,
dinamik firma arama) kullanıcı onayıyla eklenir. JS ile iş mantığı yazılmaz, veri hesaplanmaz.

### 11.8 Erişilebilirlik

- Metin kontrastı ≥ 4.5:1, arayüz sınırı/durum göstergesi ≥ 3:1 (WCAG 2.1 1.4.3 / 1.4.11).
- Görünür odak halkası (`:focus-visible` → 2px turuncu, 2px offset).
- Yalnızca ikon içeren butonlarda `aria-label`. `<html lang="tr">`.
- Kontrol edilen kırılımlar: 375 / 768 / 1366 / 1920 — hiçbirinde yatay sayfa kaydırması yoktur.

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
- PHP enum'ları: `PaymentMethod`, `PaymentStatus`, `Currency`, `AuditEvent`, `StayPricingMode`, `StayStatus`. Serbest string sabitleri kullanılmaz.
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
| 9 | Dashboard (role duyarlı) | Personel finansal kart görmüyor |
| 10 | Search & Filters standardizasyonu + Excel export | Filtreli export doğru satırları veriyor |
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

Canlı dizin yerleşimi:

```
/home/kullanici/
├── app/              # Laravel kökü — web'den ERİŞİLEMEZ (.env, storage, vendor burada)
├── public_html/      # DocumentRoot → ../app/public içeriğine yönlenir
└── backups/
```

**Deploy yöntemi: GitHub Actions + FTPS. SSH erişimi zorunlu kabul edilmez.**
Ayrıntı, gerekli GitHub Secrets listesi ve workflow taslağı: `docs/deploy-github-actions.md`.
`develop` → staging, `main` → production; ayrı hedefler, ayrı veritabanı.

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
