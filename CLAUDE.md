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
- `Support/Money.php` — para biçimleme ve güvenli toplama
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
| nights | unsigned int | sunucuda hesaplanır, doğrulanır |
| amount | decimal(15,2) | toplam konaklama tutarı |
| currency | char(3) | default TRY |
| notes | text null | |
| created_by / updated_by | FK users | |
| created_at / updated_at | | |
| deleted_at | soft delete | **eklendi** — hard delete yasağı için gerekli |

> `deleted_at` **onaylandı**. Cariye işlenmiş bir konaklamanın silinmesi/iptali, aynı `DB::transaction()` içinde ters ledger kaydı üretmek **zorundadır** — soft delete tek başına yeterli değildir.

Index: `(company_id, check_in_date)`, `(check_in_date, check_out_date)`.

### 5.4 `payments`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | zorunlu, index |
| payment_date | date | index |
| amount | decimal(15,2) | > 0 |
| currency | char(3) | default TRY |
| method | varchar(30) | enum destekli: `nakit`, `kredi_karti`, `havale_eft`, `cek`, `diger` |
| reference_no | varchar(50) null | dekont/slip no |
| description | varchar(255) null | |
| created_by | FK users | |
| created_at / updated_at | | |
| cancelled_at | timestamp null | |
| cancelled_by | FK users null | |

**Silme yoktur.** Yanlış tahsilat iptal edilir; iptal ters cari kaydı üretir.

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
| created_by | FK users | |
| created_at | timestamp | **`updated_at` YOK** |

Index: `(company_id, entry_date)`, `(source_type, source_id)`.

### 5.6 `audit_logs`

| Alan | Tip |
|---|---|
| id | bigint PK |
| user_id | FK users null |
| user_name | varchar(150) — snapshot |
| event | varchar(30) — `created`, `updated`, `deleted`, `login`, `login_failed`, `logout`, `cancelled`, `exported` |
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
`'stay' => Stay::class`, `'payment' => Payment::class`, `'company' => Company::class`, `'user' => User::class`.
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

**L8.** Ters kayıt `description` alanı `[İPTAL]` ön ekiyle başlar ve aynı `source_type/source_id`'yi taşır. Servis, aynı kaynak için ikinci kez ters kayıt yazmadan önce mevcut olup olmadığını kontrol eder (idempotans).

**L9.** Cariye yazma **sadece** `Ledger\Services\LedgerService` üzerinden yapılır. Controller, Blade veya başka modül doğrudan `LedgerEntry::create()` çağıramaz.

**L10.** Eşzamanlılık: bir firmanın bakiyesini okuyup ona göre yazan hiçbir mantık yoktur (limit kontrolü yok), bu yüzden kilit gerekmez. Bu değişirse `lockForUpdate` eklenir.

**Test zorunluluğu:** §12'deki cari testleri geçmeden Ledger, Stays veya Payments üzerinde değişiklik merge edilmez.

---

## 7. Yetki Modeli

### 7.1 Katmanlar (hepsi birlikte)

1. **Route middleware** — `->middleware('can:payments.create')`
2. **Policy** — kayıt bazlı karar; `Route Model Binding` + policy ile IDOR kapatılır
3. **Query kapsamı** — Personel listelerinde `created_by` daraltması
4. **Blade `@can`** — finansal alan/kolonların gizlenmesi

`Gate::before` ile `admin` rolü her yetkiyi geçer. Tek istisna: **audit log silme yetkisi hiç kimsede yoktur.**

### 7.2 Yetki adları

`{modul}.{eylem}` formatı:

```
users.view, users.manage
roles.manage
companies.view, companies.create, companies.update, companies.delete
stays.view, stays.create, stays.update, stays.delete
payments.view, payments.create, payments.cancel
ledger.view            # tek firmanın ekstresi
ledger.view_all        # tüm firmaların bakiyesi / toplam alacak
dashboard.financial    # finansal dashboard kartları
export.excel
audit.view
```

Yetkiler seeder ile oluşturulur; kod içinde string olarak değil, mümkünse sabit/enum üzerinden referans verilir.

### 7.3 Rol matrisi

| Yetki | Admin | Patron | Personel |
|---|:--:|:--:|:--:|
| users.view / users.manage | ✅ | ❌ | ❌ |
| roles.manage | ✅ | ❌ | ❌ |
| companies.view | ✅ | ✅ | ✅ |
| companies.create | ✅ | ✅ | ✅ |
| companies.update | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| companies.delete | ✅ | ✅ | ❌ |
| stays.view | ✅ | ✅ | ✅ |
| stays.create | ✅ | ✅ | ✅ |
| stays.update | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| stays.delete | ✅ | ✅ | ❌ |
| payments.view | ✅ (tümü) | ✅ (tümü) | ⚠️ kendi tahsilatları |
| payments.create | ✅ | ✅ | ✅ |
| payments.cancel | ✅ | ✅ | ❌ |
| payments — tutar düzenleme | ❌ | ❌ | ❌ |
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
- `nights` sunucuda `check_out_date - check_in_date` olarak hesaplanır; formdan gelen değere güvenilmez. 0 gece kabul edilmez (min 1).
- Kayıt oluşturulunca `LedgerService` ile **debit** kaydı (`entry_date = check_out_date`, açıklama: `Konaklama: {guest_name} ({check_in} - {check_out})`).
- Güncellemede tutar/para birimi/tarih değişirse ters kayıt + yeni kayıt.
- Soft delete → ters kayıt.

### Payments
- Kayıt oluşturulunca **credit** kaydı (`entry_date = payment_date`).
- Tahsilat–konaklama eşleştirmesi (allocation) **V1'de yok**; tahsilat firmaya yazılır, bakiye netleşir.
- İptal: `cancelled_at` + `cancelled_by` doldurulur, ters kayıt yazılır. İptal edilmiş tahsilat tekrar iptal edilemez, düzenlenemez.
- `description` ve `reference_no` dışındaki alanlar oluşturulduktan sonra değiştirilemez.

### Ledger / Current Account
- Firma cari ekstresi: tarih aralığı filtresi, açılış bakiyesi (aralık öncesi net), satır satır hareketler, yürüyen bakiye (sorguda değil, view'da hesaplanır), kapanış bakiyesi.
- Firma bakiye listesi (`ledger.view_all`): tüm firmalar, bakiye, son hareket tarihi.
- Ekstre yazdırma görünümü (`layouts/print`) ve Excel export.

### Dashboard
Role duyarlı, tek sorgu setiyle:
- **Admin/Patron:** toplam alacak, bu ay konaklama tutarı, bu ay tahsilat, en yüksek bakiyeli 10 firma, son 10 hareket.
- **Personel:** bugün/bu hafta girdiği konaklama ve tahsilat sayısı, kendi son kayıtları. **Tutar toplamı gösterilmez.**

### Basic Audit Log
`Auditable` trait'i ile `Company`, `Stay`, `Payment`, `User` modellerinde `created/updated/deleted` olayları; ayrıca `login`, `login_failed`, `logout`, `payment cancelled`, `exported`, rol değişiklikleri.
`old_values`/`new_values` sadece **değişen alanları** içerir. `password`, `remember_token` asla loglanmaz.
Ekran: tarih/kullanıcı/olay/kayıt tipi filtreli liste, salt okunur.

---

## 9. Arama, Filtre, Sayfalama

- Her liste ekranı aynı desende: `Core/Filters` altındaki filtre sınıfı + query string.
- Standart parametreler: `q`, `date_from`, `date_to`, `company_id`, `created_by`, `sort`, `direction`, `per_page`.
- Varsayılan sayfa boyutu 25, izinli değerler 25/50/100.
- Sıralama alanları **beyaz liste** ile sınırlanır (SQL injection ve rastgele kolon sızıntısına karşı).
- Filtreler sayfa yenilendiğinde korunur (query string).
- Arama: firma adı, vergi no, misafir adı, referans no üzerinde `LIKE`. Fulltext V1'de yok.
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

- Blade + Tailwind. Ortak bileşenler `resources/views/components` altında: `<x-ui.card>`, `<x-ui.table>`, `<x-ui.badge>`, `<x-ui.empty>`, `<x-form.input>`, `<x-form.select>`, `<x-form.money>`, `<x-form.date>`.
- **Alpine.js sadece şunlar için:** modal/dropdown aç-kapa, form onay diyaloğu, tutar maskesi, dinamik firma arama kutusu. Alpine ile iş mantığı yazılmaz, veri hesaplanmaz.
- Mobil öncelikli: tablolar `md` altında kart görünümüne düşer. Dokunma hedefi min 44px.
- Tutar biçimi: `1.234,56 ₺` (Türkçe binlik/ondalık). Tarih: `d.m.Y`.
- Negatif/pozitif bakiye renkli gösterilir; renk tek başına anlam taşımaz, metin de yazar.
- Silme/iptal işlemleri onay adımı ister.
- Flash mesajları tek bir partial'dan.

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

- `pint` (Laravel preset) ile formatlama; `larastan/phpstan` level 5 minimum.
- Controller **ince**: yetki → validasyon (FormRequest) → Service/Action çağrısı → redirect/view. İş mantığı controller'da olmaz.
- Validasyon **sadece** FormRequest sınıflarında. `$request->all()` ile mass assignment yapılmaz; `$request->validated()` kullanılır.
- Yetki **sadece** Policy sınıflarında. Controller içinde `if ($user->role === 'admin')` yazılmaz.
- Model `$fillable` dar tutulur; `$guarded = []` yasak.
- PHP enum'ları: `PaymentMethod`, `Currency`, `AuditEvent`. Serbest string sabitleri kullanılmaz.
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

| Gün | İş | Bitti sayılma kriteri |
|---|---|---|
| 1 | Laravel iskeleti, modül autoload, Tailwind/Vite, layout, **cPanel'e ilk deploy** | Boş sayfa canlıda açılıyor |
| 2 | Auth + Users + Roles/Permissions + seeder | 3 rolle giriş yapılıyor, menüler farklı |
| 3 | Companies CRUD + arama | Gerçek firma listesi girilebiliyor |
| 4 | **Ledger çekirdeği + testleri** (ekransız) | §12'deki 9 cari testi yeşil |
| 5–6 | Stays CRUD + cari entegrasyonu | Konaklama → doğru debit |
| 7 | Payments + iptal + cari entegrasyonu | Tahsilat → doğru credit, iptal geri alıyor |
| 8 | Cari ekstre + firma bakiye listesi + yazdırma | Ekstre elle hesapla birebir tutuyor |
| 9 | Dashboard (role duyarlı) | Personel finansal kart görmüyor |
| 10 | Search & Filters standardizasyonu + Excel export | Filtreli export doğru satırları veriyor |
| 11 | Basic Audit Log + PWA | Her CRUD loglanıyor, ana ekrana eklenebiliyor |
| 12 | Mevcut Excel geçmişinin aktarımı (§14.1) | Ledger bakiyesi, Excel KALAN BAKİYE ile mutabık |
| 13 | Yetki testleri, güvenlik sertleştirme, yedekleme cron'u | Rol matrisi testleri yeşil |
| 14 | Canlıya alma, kullanıcı eğitimi, paralel kullanım başlangıcı | Patron ve personel gerçek veri giriyor |

Her gün sonu: testler yeşil → `pint` + `phpstan` temiz → commit → staging'e deploy.

### 14.1 Excel Veri Göçü (karar verildi)

Mevcut Excel geçmişi **mümkün olduğunca tam** aktarılır. Yalnızca açılış bakiyesi aktarımı yapılmaz.

**Import prensipleri:**

1. Her **geçerli konaklama satırı** → bir `stay` kaydı oluşturur.
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

Kurallar:
- `.env`, `storage/`, `vendor/` **asla** `public_html` altında olmaz. Deploy sonrası `https://site/.env` isteğinin 403/404 döndüğü doğrulanır.
- `vendor/` sunucuda `composer install` ile değil, CI'da build edilip yüklenir (bellek limiti).
- Vite build çıktısı (`public/build`) CI'da üretilir, repoya commit edilmez.
- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS zorunlu.
- Cron: günlük `mysqldump` + şifreli yedek kopyası (offsite). `schedule:run` V1'de sadece yedek için.
- Deploy sonrası: `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
- **Migration'lar geri alınabilir olmalı** (`down()` yazılır) ama canlıda `migrate:rollback` yerine ileri düzeltme migration'ı tercih edilir.

---

## 16. Git Akışı

- Geliştirme dalı: `claude/hotel-accounting-app-architecture-ud52jk`
- `main`'e doğrudan push yok.
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
