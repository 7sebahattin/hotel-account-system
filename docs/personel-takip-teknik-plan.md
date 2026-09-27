# Personel, NFC, puantaj ve ücret takibi — uygulama sözleşmesi

Tarih: 25 Eylül 2026. Başlangıç: `main` `f686bbb03a56d49e3b100e37de50119c9e0a8e0c`, release 6. Çalışma dalı: `codex/personnel-tracking-nfc`. Bu belge uygulama planıdır; özelliklerin çalıştığı veya yayımlandığı anlamına gelmez. İnceleme revizyonu 2: otomatik giriş/çıkış ve tam aylık ücret tabanı kabul edildi.

Kullanıcı bağımsız Personel modülünü, dar tarama API'sini, Android yardımcı uygulamasını ve yayını açıkça yetkilendirdi. CLAUDE.md §3.2 API/mobil yasağı ve §4.3 yeni modül sınırı bu kapsam için istisnadır; uygulama commit'inde CLAUDE.md kapsam, şema ve yetki matrisi güncellenir. Bunun dışındaki PMS, banka, otomatik kur, çevrimdışı işlem kuyruğu ve resmi bordro entegrasyonu eklenmez.

## 1. Mimari ve mevcut kodla birleşim

- Yeni kök `app/Modules/Personnel`; view namespace `personnel`, web URL `/personnel`, route prefix `personnel.`.
- `PersonnelServiceProvider`, `Core/Providers/ModuleServiceProvider` sınıfını genişletir; `bootstrap/providers.php` kaydı zorunludur. Taban provider mevcut haliyle migration, view, config, policy ve **yalnız web route** yükler.
- Dar API, Personnel provider tarafından `Routes/api.php` üzerinden `/api/personnel` prefix'iyle açıkça kaydedilir; oturum/web middleware grubuna konmaz. Mevcut `bootstrap/app.php` API kaydı içermez, fakat `api/*` hatalarını JSON üretir. Yeni genel API altyapısı veya Sanctum bağımlılığı gerekmez. Route cache testi zorunludur.
- `User` giriş hesabıdır; çalışan kimliği `PersonnelEmployee` olur. Çalışana otomatik kullanıcı hesabı açılmaz ve kullanıcı rolü istihdam kaydı sayılmaz.
- İş mantığı kendi servislerinde: `EmployeeService`, `ScheduleService`, `CardService`, `TerminalService`, `ScanService`, `AttendanceService`, `PayrollService`, `PersonnelReportService`. İnce controller + FormRequest + policy deseni korunur.
- `Dashboard` yalnız `PersonnelReportService::dashboardSnapshot()` çağırabilir. Başka modülden Personnel model sorgusu yapılmaz. Personnel otel `Ledger`, `Payments`, `MonthlyAccount` tablolarına yazmaz.
- Mevcut `Core/Support/Money`, `Currency`, `QueryFilter`, ortak Blade bileşenleri ve `AuditLog/Services/AuditLogger` kullanılır. Yeni Composer/NPM çalışma zamanı bağımlılığı yoktur.
- Audit morph map'e kullanılan Personnel modelleri eklenir. Sırlar, tam token ve tam kart UID audit/log/URL içine yazılmaz; terminal kimliği ve maskeli kart etiketi yeterlidir.

## 2. Veri modeli

Tüm FK'ler RESTRICT; InnoDB, mevcut Türkçe collation. Para DECIMAL(15,2), PHP hesabı integer kuruş; oranlar integer basis point, süreler integer saniye/dakika. UTC anları DB'de, iş günü `Europe/Istanbul` ile hesaplanır. Tarihler `Y-m-d`, ay `Y-m`, kullanıcıya Türkçe format. Model adları tablo adlarının tekil PascalCase karşılığıdır.

| Tablo | Alan sözleşmesi ve kısıtlar |
|---|---|
| `personnel_employees` | `id`, `employee_code` varchar(30) unique, `first_name` varchar(100), `last_name` varchar(100), `start_date` date, `end_date` nullable date >= start_date, `phone` varchar(30) null, `email` varchar(150) null, `position` varchar(100) null, `department` varchar(100) null, `notes` text null, `is_active` bool, `created_by`, `updated_by`, timestamps. Ad, soyad ve kod ayrı zorunlu alanlar. `full_name` accessor ad + boşluk + soyad üretir; kolon değildir. Silme yerine pasifleştirme. |
| `personnel_shifts` | `id`, `name` varchar(100), `is_active`, creator/updater/timestamps. Tanım kimliği; geçmiş saatlerin doğruluk kaynağı değildir. |
| `personnel_shift_versions` | `id`, `shift_id`, `version` positive integer, `start_time`, `end_time`, `ends_next_day` bool, `break_minutes` unsigned, `workdays` JSON ISO günleri 1..7, `late_grace_minutes`, `early_grace_minutes`, `created_by`, `created_at`; unique(shift_id,version). Tam snapshot, append-only. Süre > 0 ve <= 24 saat; mola net süreden küçük. |
| `personnel_shift_assignments` | `id`, `employee_id`, `shift_version_id`, `effective_from` date, `created_by`, `created_at`; unique(employee_id,effective_from). Bir atama sonraki effective_from'a kadar geçerlidir. Geçmiş satır güncellenmez. |
| `personnel_wage_assignments` | `id`, `employee_id`, `effective_from` date, `wage_type` monthly/daily/hourly, `amount` decimal > 0, `currency`, `standard_daily_minutes` positive integer default 480, `overtime_multiplier_bp` integer default 15000, `overtime_enabled` bool default false, `created_by`, `created_at`; unique(employee_id,effective_from), append-only. Aylık bölende ilgili ayın gerçek takvim gün sayısı kullanılır; sabit /30 alanı yoktur. |
| `personnel_cards` | `id`, `uid` varchar(40) ASCII canonical uppercase hex unique, `label` varchar(100) null, `technology` varchar(100) null (NfcA/MifareClassic/NdefFormatable/Ndef/USB HID), `is_active`, creator/updater/timestamps. UID baştaki sıfırları korur; yalnız çift sayıda hex, 4..20 byte. Teknoloji bilgi amaçlıdır, kimlik doğrulama kanıtı değildir. |
| `personnel_card_assignments` | `id`, `card_id`, `employee_id`, `assigned_at` UTC, `released_at` UTC null, `assigned_by`, `released_by` null, `created_at`. Aktif kart için generated nullable unique card key; aynı kart aynı anda iki kişide olamaz. Kapanış yalnız bir kez; başka alan değişmez. Çalışana birden fazla kart atanabilir. |
| `personnel_terminals` | `id`, `name`, `platform` android/kiosk, `device_uuid` UUID, `token_hash` char(64) unique, `token_prefix` varchar(12), `revoked_at` null, `created_by`, `created_at`, `last_seen_at` null. Plain secret DB'de yok. Revocation geri alınmaz; yeni terminal credential üretilir. Device UUID izleme etiketidir, credential yerine geçmez. |
| `personnel_scan_events` | `id`, `request_id` UUID unique (tüm kanallar), `payload_hash`, `card_id` null, `card_assignment_id` null, `employee_id` null, `terminal_id` null, `actor_user_id` null, `source` android/kiosk, `platform`, `device_uuid` null, `technology` null, `occurred_at` UTC **sunucu zamanı**, `requested_action` auto/in/out, `action` in/out null (çözümlenmiş eylem), `result` accepted/duplicate/unknown_card/inactive_employee/inactive_card/invalid_transition, `duplicate_of_id` null, `created_at`. İstek başına değişmez karar ve kaynak snapshot'ı; update/delete trigger ile yasak. |
| `personnel_attendance_corrections` | `id`, `employee_id`, `work_date`, `revision` positive int, `supersedes_id` null unique, `sessions` JSON [{in_at,out_at}], `reason` varchar(500), `created_by`, `created_at`; unique(employee_id,work_date,revision), append-only. Tam günlük yerine geçme snapshot'ı; ham taramayı değiştirmez. |
| `personnel_payroll_entries` | `id`, `employee_id`, `entry_date`, `period` char(7), `type` earning/bonus/deduction/payment/reversal, `direction` credit/debit, `amount` decimal > 0, `currency`, `payment_method` cash/bank_transfer/other null (payment için zorunlu, ödeme reversal'ında aynen kopyalanır), `description`, `source_key` varchar(120) null unique, `calculation_snapshot` JSON null, `reverses_entry_id` null unique FK self, `created_by`, `created_at`. UPDATE/DELETE trigger ile yasak; ödeme dahil her satır immutable. |

Index'ler: scan(employee_id,occurred_at,id), scan(card_id,occurred_at,id), assignment(card_id,assigned_at,released_at), shift/wage(employee_id,effective_from), correction(employee_id,work_date,revision), payroll(employee_id,currency,entry_date), payroll(period,type), employee(is_active,department). Ham tarama ve ücret defterinde `updated_at`/`deleted_at` bulunmaz.

Atama tarihi geçmiş puantaj/hesaplamayı değiştirmemelidir: vardiya/ücret atamalarında geçmişe tarih verme, aynı gün mevcut atamayı değiştirme ve geçmiş versiyona UPDATE reddedilir. İlk kurulumda çalışanın ilk kaydından ve ilk hesabından önceye atama yapılabilir; sonraki değişiklikler en erken yarın geçerlidir. Hatalı geçmiş için puantaj düzeltmesi ve finansal ters kayıt/yeniden hesap akışı kullanılır. UI mevcut ve gelecek atamaları ayrı gösterir.

## 3. Yetki sözleşmesi

`Authorization/Enums/Permission` case, `group()`/`label()` ve `RoleName::permissions()` birlikte güncellenir; mevcut seeder idempotent çalışır. Controller içinde rol adı karşılaştırılmaz.

| Enum / değer | Admin | Patron | Personel varsayılan |
|---|---|---|---|
| PersonnelView / `personnel.view` | evet | evet | hayır |
| PersonnelManage / `personnel.manage` | evet | evet | hayır |
| PersonnelAttendance / `personnel.attendance` | evet | evet | hayır |
| PersonnelPayroll / `personnel.payroll` | evet | evet | hayır |
| PersonnelScan / `personnel.scan` | evet | evet | evet |
| PersonnelTerminals / `personnel.terminals` | evet | evet | hayır |

`view` çalışanlar, vardiyalar ve puantaj raporunu okur; `manage` çalışan/kart/vardiya atamasını yazar; `attendance` düzeltir; `payroll` ücret/ödeme ve finansal raporu okur/yazar; `scan` yalnız kiosk tarar; `terminals` credential oluşturur/iptal eder. Ücret alanları view yetkili fakat payroll yetkisiz istekte **sorgulanmaz ve view verisine konmaz**. Policy kayıt bağlantısını ve servis iş kurallarını denetler; admin değişmezlik kurallarını aşamaz. API terminali Spatie kullanıcı hesabı değildir; tek endpoint'e bağlı ayrı credential middleware ile doğrulanır.

## 4. Kart, tarama ve eşzamanlılık

Kart atama/release, tarama ve pasifleştirme transaction içinde tutarlı kilit sırasını izler: kart ID artan sıra → çalışan ID artan sıra. Kart atamasında çalışan durumu kilit altında yeniden kontrol edilir. Atama eski çalışanla yeni çalışanı etkiliyorsa ikisi ID sırasıyla kilitlenir. DB unique aktif kart ataması servis kontrolünün son savunmasıdır. Çalışan satırı, farklı kart/terminalden eşzamanlı giriş/çıkışı serileştirir. Ödeme/hesaplama/düzeltme de çalışan satırını kilitler.

Tarama `action=auto|in|out` kabul eder; varsayılan **auto** ve normal kullanım otomatik dönüşümlü giriş/çıkıştır. Çalışan kilidi altında duplicate kontrolünden SONRA etkin açık oturum varsa out, yoksa in çözülür. Son kabul edilmiş olaylar ile en güncel puantaj düzeltmesinin kapattığı/açtığı oturumlar birlikte değerlendirilir; düzeltmeyle kapatılmış eski oturum yeniden açılmaz. Açık oturum 24 saati aşmışsa auto yeni işlem üretmez; yönetici düzeltmesi ister. Düzeltme kapattıktan sonraki güvenli auto okutma in olur. İsteğe bağlı açık in/out modlarında ikinci in veya açık oturumsuz out `invalid_transition` olur. Aynı gün birden çok tamamlanmış oturum desteklenir.

1. Auth, rate limit ve FormRequest geçer; request UUID ve canonical payload hash hesaplanır.
2. Mevcut request UUID aynı payload/actor ise orijinal sonucu yeniden döndürür, **yeni satır yoktur**. Başka payload veya başka actor ile kullanım 409. Aynı request yarışında unique constraint sonrası mevcut satır okunur.
3. Kart ve çalışan kilitlenir; sunucu zamanı transaction'da bir kez alınır; o anda geçerli kart ataması snapshot'lanır. İstemci zamanı kabul edilmez.
4. Aynı kart VEYA aynı çalışan için en son kabul edilmiş taramadan itibaren 10 saniye içindeki yeni UUID, istenen/çözümlenen action'dan bağımsız `duplicate` ham olayı üretir; **toggle çözülmeden önce** durur. Böylece hızlı ikinci auto okutma girişin hemen ardından çıkış yapmaz; farklı kart/terminal de bu korumayı aşamaz. Kart bilinmiyorsa kart UID digest'i ile dar rate limit uygulanabilir; bilinmeyen UID ham olarak loglanmaz.
5. Duplicate değilse etkin oturumdan auto action çözülür. Geçerli durum değişikliği `accepted`, aksi durumda açık bir sonuç kaydedilir. Ham olay + audit gerekli ise aynı transaction'dadır. Başarılı HTTP cevabı commit'ten sonra verilir.

UI debounce yalnız kullanıcı deneyimidir; idempotency ve duplicate kuralı sunucudadır. Uygulama HTTP cevabını kaybederse aynı UUID ile tekrarlar. Yeni kart okutma yeni UUID'dir. Otomatik çevrimdışı toplama yoktur; ağ hatası ekranda açık görünür ve yeni kayıt başarı sayılmaz.

## 5. HTTP ve frontend sözleşmesi

Web mutation standart CSRF/session auth, redirect + flash + error bag. Rota parametreleri integer model binding, nested employee bağlantıları policy ve servisle doğrulanır. Aşağıdaki tüm web adlarının başına `personnel.` gelir.

| HTTP / path (başına `/personnel`) | Route adı | Girdi / yetki |
|---|---|---|
| GET `/` | `index` | date varsayılan bugün; view |
| GET `/employees` | `employees.index` | q, active=all/1/0, department, sort=full_name/employee_code/start_date, direction, per_page=25/50/100; view |
| GET `/employees/create`; POST `/employees` | `employees.create`, `employees.store` | çalışan alanları; manage |
| GET `/employees/{employee}` | `employees.show` | month=Y-m; view |
| GET `/employees/{employee}/edit`; PUT aynı employee | `employees.edit`, `employees.update` | çalışan alanları; manage |
| PATCH `/employees/{employee}/active` | `employees.active` | is_active bool, reason; manage |
| GET `/shifts`; POST `/shifts` | `shifts.index`, `shifts.store` | name ve ilk version alanları; view/manage |
| POST `/shifts/{shift}/versions` | `shifts.versions.store` | saat, mola, workdays[], grace; manage |
| PATCH `/shifts/{shift}/active` | `shifts.active` | is_active bool; manage; pasif vardiya yeni atamaya kapalı, geçmiş atama/versiyon korunur |
| POST `/employees/{employee}/shift-assignments` | `shift-assignments.store` | shift_version_id,effective_from; manage |
| POST `/employees/{employee}/wage-assignments` | `wage-assignments.store` | ücret ataması alanları; payroll |
| POST `/employees/{employee}/cards` | `cards.store` | uid,label,technology?; manage |
| POST `/cards/{card}/release` | `cards.release` | reason; manage |
| PATCH `/cards/{card}/active` | `cards.active` | is_active,reason; manage |
| GET `/kiosk`; POST `/kiosk/scans` | `kiosk`, `kiosk.scan` | request_id,card_uid,action; scan |
| GET `/attendance` | `attendance.index` | date,employee_id?,department?; view |
| POST `/employees/{employee}/attendance-corrections` | `attendance-corrections.store` | work_date,expected_revision,sessions[][in_at/out_at],reason; attendance |
| GET `/reports/daily`; GET `/reports/daily/print` | `reports.daily`, `reports.daily.print` | date,employee_id?,department?; view |
| GET `/reports/monthly`; GET `/reports/monthly/print` | `reports.monthly`, `reports.monthly.print` | month,employee_id?,department?,active=all/1/0 (varsayılan all, tarihsel çalışanlar kaybolmaz); view, ücret kısmı payroll |
| GET `/payroll` | `payroll.index` | month,employee_id?,currency?; payroll |
| POST `/employees/{employee}/payroll/calculate` | `payroll.calculate` | month,request_id; payroll |
| POST `/employees/{employee}/payroll/entries` | `payroll.entries.store` | type=bonus/deduction/payment,entry_date,amount,currency,payment_method (payment için zorunlu),description,request_id; payroll |
| POST `/payroll/entries/{entry}/reverse` | `payroll.entries.reverse` | reason,request_id; payroll |
| GET `/terminals`; POST `/terminals` | `terminals.index`, `terminals.store` | name,platform,device_uuid; terminals |
| POST `/terminals/{terminal}/revoke` | `terminals.revoke` | reason; terminals |

API: `POST /api/personnel/scans`, route `personnel.api.scans.store`. Header `Authorization: Bearer <terminal-id>.<random-secret>`; 32 random byte secret, SHA-256 hash sabit zaman karşılaştırma. Request JSON: `{"request_id":"UUID","card_uid":"04AABBCCDD0011","action":"auto","technology":"NfcA"}`. Action eksikse auto normalize edilir; payload hash normalize edilmiş istek üzerindedir. API platform/device UUID terminalden güvenilir snapshot alınır; kiosk için browser installation UUID yalnız kaynak etiketi, kullanıcı session'ı gerçek yetkidir. Token yalnız ilk üretim response/flash'ında gösterilir; ekran/response `Cache-Control: no-store`; session flash bir sonraki istekte silinir. Secret URL veya JS bundle'a konmaz. Revoke sonrası derhal 401, eski token retry de yetkisizdir.

Başarılı işlenmiş sonuç HTTP 200 JSON: `{"event_id":123,"request_id":"UUID","result":"accepted","action":"in","employee_name":"Ad Soyad","occurred_at":"2026-09-25T08:00:00Z","message":"Giriş kaydedildi.","replayed":false}`. `employee_name` unknown/inactive sonucunda null olabilir; ücret, iletişim veya çalışan listesi dönmez. `duplicate`/`invalid_transition` de 200 işlenmiş sonuçtur ve başarı rengi kullanılmaz. 401 yetkisiz, 409 request conflict, 422 validation, 429 throttle, 503 geçici hata. API auth hatası credential varlığını sızdırmaz. Kiosk JSON aynı response şeklidir, bearer yerine session/CSRF kullanır; `Accept: application/json` gerekir.

Rate limit: terminal başına 60/dakika, IP başına 120/dakika; kiosk kullanıcı başına 60/dakika. Hata mesajları Türkçe; log credential redaction zorunlu. Android credential sadece scan kapsamındadır, web route'larıyla kullanılamaz.

### View veri sözleşmesi

Controller `personnel::...` view'larına servis tarafından hazırlanmış diziler verir. Blade finansal/süre hesabı yapmaz. Ortak `permissions = {view,manage,attendance,payroll,scan,terminals}` bool; para görünen yerde `{decimal:string, formatted:string, currency:string}`. ISO zamanın yanında hazır `*_label` kullanılır. Route URL'lerini Blade `route()` ile üretir.

- `index`: `date`, `summary={active_employees,present_count,currently_inside_count,absent_count,late_count,early_count,overtime_minutes,missing_entries_count}`, `rows` sayfalı günlük satırlar. Currently_inside etkin açık oturumu olanlar; absent planlı vardiyası bitmiş, hiç kabul edilmiş olay/oturumu olmayanlar; missing_entries açık/bozuk/eşleşmemiş kayıtlar, absent ile karıştırılmaz. Yalnız payroll yetkisinde `thisMonthPaymentsByCurrency` sorgulanır/verilir; farklı para birimleri toplanmaz.
- `employees.index`: `employees` paginator (id,employee_code,full_name,department,position,is_active,start_date), `filters`, `departments` distinct sınırlı seçenekler.
- `employees.create/edit`: `employee` nullable model, `formDefaults`, `permissions`; old() her zaman önceliklidir.
- `employees.show`: `employee`, `month`, `cards`, `shiftAssignments` (version snapshot dahil), `attendanceRows`; yalnız payroll izniyle `wageAssignments`, `payrollTotals`, `payrollEntries`.
- `shifts.index`: `shifts` paginator, `versionsByShift` ilgili sayfa için toplu, `workdayOptions` ISO 1..7 Türkçe etiket.
- `attendance.index` ve günlük rapor: `date`, `filters`, `rows`, `totals`; satır `{employee_id,employee_code,full_name,work_date,shift_label,sessions:[{in_at,out_at,in_label,out_label}],first_in_label,last_out_label,worked_minutes,break_minutes,net_minutes,late_minutes,early_minutes,overtime_minutes,status,status_label,revision,has_correction}`.
- Aylık rapor: `month`, `filters`, `employees` paginator, `days` tarih dizisi, `rows` çalışan günlük verileri, `totalsByEmployee`; maaş izinli istekte ek `earningsByCurrency` ve `paymentsByCurrency`. Print aynı normalize filtre/hesabı kullanır, en fazla bir ayı chunk'larla hazırlar. Yazdırma ekranı `layouts.print`.
- `payroll.index`: `month`, `filters`, `rows` paginator; her çalışan+currency satırı `{employee_id,full_name,currency,earned,bonus,deduction,paid,balance,calculation_status}`; `entries` yalnız seçili çalışanın sayfalı defteri. Geçmiş snapshot ve reversal ilişkisi görünür.
- `kiosk`: `scanUrl`, `csrfToken`, `defaultAction=auto`, başka çalışan verisi yok. Otomatik varsayılan seçimi ve isteğe bağlı Giriş/Çıkış düğmeleri, odaklı USB HID input, son sonuç `aria-live`; her submit sonrası input temizlenir ve odak geri gelir. Bekleyen istek varken yeni okutma kilitlenir; çözümlenen eylem sunucu cevabından gösterilir.
- `terminals.index`: `terminals` paginator (id,name,platform,device_uuid,token_prefix,created_at,last_seen_at,revoked_at), `newCredential` yalnız yeni oluşturma sonrası tek gösterim.

Menüde yetkiye göre Personel Takibi, Puantaj, Ücret ve Ödemeler, Kart Okutma. Mevcut global layout/genişlik/boşluk token'ları değiştirilmez. `<x-ui.page-header>`, `card`, `filter-panel`, `table`, `badge`, `tabs`, `row-menu`, `errors` kullanılır. Mobil tablo kartlara dönüşür; formlar JS olmadan da çalışır, kiosk fetch dışında vanilla JS yeterlidir.

## 6. Puantaj hesaplama sözleşmesi

Kabul edilmiş olaylar `(occurred_at,id)` sırasıyla eşlenir; in → out bir oturumdur. Açık oturum süre kazandırmaz; `missing_checkout` durumudur. Çıkışsız ertesi gün giriş eski oturumu kendiliğinden kapatmaz. İş günü oturumun girişinin ait olduğu vardiya başlangıç günüdür; gece vardiyasında önceki günün 22:00–06:00 aralığına düşen 01:00 girişi önceki güne aittir. Önce aday önceki günün gece aralığı, sonra bugünkü vardiya değerlendirilir; aralık dışında takvim günü ve `unscheduled` uyarısı kullanılır. Oturum sonraki güne uzasa da tamamı başlangıç iş gününde gösterilir.

Çalışılan süre tamamlanmış, örtüşmeyen oturumların gerçek saniye toplamıdır; toplamdan sonra dakika aşağı yuvarlanır. Günlük planlı mola **bir kez**, `min(gross_minutes,break_minutes)` kadar düşülür; oturum başına tekrar düşülmez. Bu varsayım ekranda "Planlı mola otomatik düşülür" olarak görünür. Net süre >= 0. Geç kalma `max(0,first_in - shift_start - grace)`, erken çıkma `max(0,shift_end - last_out - grace)` dakika; grace ücret süresini artırmaz. Overtime `max(0,net_minutes - scheduled_net_minutes)`; çalışma günü dışında otomatik yasal katsayı yoktur, plan dışı uyarısı gösterilir.

Düzeltme tam iş gününün session listesini append eder; boş liste günü çalışılmamış olarak düzeltir. Out > in, süre <=24 saat, oturumlar birbirleriyle/komşu iş günüyle örtüşmez, çalışan istihdam aralığı ve tarih yetkisi doğrulanır. `expected_revision` uyuşmazsa 409/validation conflict; sessiz son yazan kazanır yoktur. Rapor en son correction varsa onu kullanır, yoksa ham kabul edilen olayları. Tarih, aktör, neden ve önceki snapshot ayrıntıda görünür. Sonradan gelen ham olay correction'ı gizlice geçersiz kılmaz; "Düzeltme sonrası okutma" uyarısı verir.

## 7. Ücret, ödeme ve ters kayıt

Bu modül işletme içi hakediş hesabıdır; SGK/vergi/resmi bordro veya mevzuata uygunluk hesabı iddiası yoktur. Kullanıcıya hesap yöntemi ve seçili oranlar gösterilir. Aylık hesap önce **önizleme**, ardından açık "Hakedişi kaydet" POST eylemidir; GET deftere yazmaz.

Monthly ücretin tabanı anlaşılmış **tam aylık tutardır**. İlgili ayın takvim gün sayısı D; ücret versiyonunun ve istihdamın ay içinde birlikte geçerli olduğu takvim gün sayısı E için taban `amount × E / D` olur. Tam ay istihdam ve tek ücret versiyonu tam tutarı verir; hafta sonları/planlı dinlenme günleri tabandan düşülmez. Ay içi işe başlama/ayrılma ve ücret değişimi geçerlilik parçalarına ayrılır; end_date dahil kabul edilir. Her parça tek kez half-up kuruşa yuvarlanır; aylık günleri ayrı yuvarlayarak tam aylık tutar bozunmaz.

Monthly için yalnız planlı çalışma günlerinin eksik normal süresi ayrıca hesaplanır: `shortfall = max(0,scheduled_net_minutes - min(net_minutes,scheduled_net_minutes))`. Ücretsiz devamsızlık/eksik süre kesintisi `amount × shortfall / (D × scheduled_net_minutes)`; günün tabanını aşmaz. Planlı dinlenme gününde kesinti sıfırdır; 22 çalışma gününün tamamına gelen çalışan aylık tutarın tamamını kazanır. Eksik okutma devamsızlık sayılıp otomatik kesilmez, önce düzeltilir. Önizleme aylık taban, takvim oranı, devamsız gün, eksik dakika ve kesintiyi ayrı gösterir. Ücretli izin/tatil istisnası gerekçeli bonus ile karşılanabilir; resmi izin mevzuatı hesabı yapılmaz.

Daily normal kazanç `amount × min(net_minutes,standard_daily_minutes)/standard_daily_minutes`; hourly normal kazanç `amount × normal_minutes/60`. Overtime kapalıysa hourly tüm net dakikayı normal kazanır, daily en çok bir günlük tutarı, monthly yukarıdaki tabanı ve kesintiyi kullanır. Overtime açıksa monthly için vardiyanın scheduled_net_minutes üstü, daily/hourly için standard_daily_minutes üstü fazla mesaidir; hourly normal dakika bu sınırda tutulur. Overtime dakika oranı monthly `amount/(D × scheduled_net_minutes)`, daily `amount/standard_daily_minutes`, hourly `amount/60`; bu oran overtime dakikası ve `overtime_multiplier_bp/10000` ile çarpılır. Plan dışı çalışma ve plan/ücret standardı farkı önizlemede görünür; monthly dinlenme gününde çalışma için standard_daily_minutes paydasıyla aynı aylık dakika oranı kullanılır ve overtime açıksa tamamı fazla mesaiye girer. Hiçbir yasal katsayı kendiliğinden uygulanmaz.

Hesap rasyonel integer pay/payda ile; monthly taban geçerlilik parçası başına, günlük kesinti ve fazla mesai ayrı; daily/hourly günlük normal ve fazla mesai ayrı en yakın kuruşa half-up yuvarlanır. Sonuç taban − kesinti + fazla mesaidir. Ara çarpımlarda taşma kontrolü zorunlu, float ve sınırsız çarpım yok. Eksik wage/shift, açık session, gelecek gün veya çözümlenmemiş olay bulunan dönem için kayıt engellenir; önizleme sorun listesini verir. Ay içinde currency değişmişse her currency için ayrı earning satırı oluşur.

Earning snapshot: `formula_version`, tarih aralığı, gün bazında ücret/shift assignment ID, correction revision, kullanılan scan IDs/digest, dakika, rate, currency, günlük sonuç ve toplam. `source_key` employee+period+currency+input digest ile çift hesaplamayı önler. Aynı ayın aktif earning'i varsa yeni hesaplama onu otomatik değiştirmez; önce gerekçeli ters kayıt, sonra yeni earning. Çalışan kilidi altında aynı dönem/currency için en fazla bir terslenmemiş earning kontrol edilir. Önizleme özeti post'ta tekrar hesaplanır; stale input digest conflict olur.

Defter yönü: earning/bonus credit (çalışana borç artar), deduction/payment debit (azalır). Balance = credit − debit; çalışan+currency bazında hesaplanır, balance kolonu tutulmaz. Payment gerçek ödeme kaydıdır; negatif bakiye avans olarak açık gösterilebilir. Reversal aynı çalışan/tarih/currency/tutarın ters yönü, orijinal entry ID unique; reversal'ın reversal'ı ve ikinci reversal yasaktır. Düzeltme eskiyi tersle + yeni kayıt; transaction/audit atomik. Otel cari veya kasa hareketi oluşturulmaz.

## 8. Android yardımcı uygulaması

İzole `android/personnel-scanner` proje; native Java + Android platform API, üçüncü taraf NFC/network kütüphanesi yok. Tek Activity: HTTPS sunucu adresi, terminal credential kurulumu; varsayılan Otomatik ve isteğe bağlı Giriş/Çıkış seçimi; NFC hazır/ağ/son sonuç. Kurulumda üretilen device UUID terminal kaydına bağlanır. NFC permission ve donanım özelliği, reader mode lifecycle enable/disable kullanılır. `Tag.getId()` byte dizisi unsigned, sıfır koruyan uppercase hex'e çevrilir. MifareClassic/NfcA/NdefFormatable/Ndef teknoloji listeleri tanınır ve technology kaynak bilgisi gönderilir; payload NDEF okuma veya karta yazma gerekmez. MIFARE veri bloklarını okuduğu iddia edilmez. UID olmayan tag reddedilir.

HTTPS `HttpURLConnection` arka thread, bounded connect/read timeout; hostname/certificate doğrulaması açık, cleartext kapalı. Sabit payload+UUID request sonucu belirsizken elde tutulur, operatör aynı isteği tekrar deneyebilir; offline kayıt toplama veya istemci saatinden attendance üretme yoktur. Credential Android Keystore anahtarı ile şifreli app-private storage; backup dışı; log/clipboard otomatik kopya yok. Cihaz kaybında web'den revoke.

Tarayıcıda ham NFC UID için Web NFC varmış gibi davranılmaz. Masaüstü kiosk USB HID okuyucunun metin çıktısını alır. Android NFC ve HID cihazlar farklı UID byte-order/decimal çıktı verebilir; otomatik tahmin yoktur. Donanım kabulünde aynı kart iki cihazda canonical UID eşleşmesiyle doğrulanır, gerekirse okuyucu üretici ayarı düzeltilir.

## 9. Sorgu, test ve teslim sırası

Dashboard tek gün + önceki gece penceresinde aggregate/bulk sorgular, son olay listesi en fazla 20; çalışan başına sorgu yok. Günlük rapor en fazla 100 çalışan sayfası; aylık en fazla 31 gün ve çalışan sayfası; ilişkiler yalnız sayfa IDs için toplu alınır. Print chunk'ları kontrollü; tek ay sınırı. Performans testi 5 ve 100 çalışanda aynı sorgu sayısı (başlangıç hedef <=12 domain sorgusu) ve çıktı doğruluğunu doğrular. Tüm geçmiş scan tablosunu belleğe çekmek yasaktır; açık oturum sınırı nedeniyle rapor aralığına en fazla 24 saat öncesi eklenir.

1. Kapsam dokümantasyonu, provider, Permission/RoleName/policy, migration ve model immutable korumaları.
2. Çalışan/vardiya/ücret versiyonları/kart/terminal servisleri, gerçek MySQL kısıt ve yarış testleri.
3. API ve kiosk, varsayılan auto ve explicit action, toggle öncesi replay/double-tap, auth/rate limit testleri.
4. Puantaj calculator, correction/revision, gece ve çoklu oturum testleri; ekranlar ve print.
5. Integer hakediş, snapshot/idempotency/reversal/ödeme, rol sızıntısı ve otel defterine yazmama testleri.
6. Android derleme ve fiziksel cihaz kabulü; responsive tarayıcı kontrolü; tam repo kalite kapıları.
7. Release integer güncel main+1, CLAUDE/kararlar/handoff; yetkili yayın akışı ve canlı doğrulama.

Zorunlu senaryolar: pasif/işten ayrılmış kişi, kart değiştirme geçmişi, aynı kartın eşzamanlı atanması, iki terminal aynı anda, aynı UUID farklı body/actor, revoke/retry, farklı UUID 10 saniye, sınırın iki yanı; auto ilk giriş/sonraki çıkış, hızlı tekrar toggle yapmaz, iki kartla aynı çalışan tekrar koruması, >24 saat stale oturum ve correction sonrası yeni giriş; 22:00–06:00, ay sonu/gece, mola, grace, dört scan iki session, açık/bozuk session; correction revision yarış/ham veri korunması; tam ay 22 iş günüyle tam monthly tutar, Şubat/31 gün, ay ortası işe başlama ve rate/currency değişimi, dinlenme günü kesintisiz, açık okutmanın yanlış devamsızlık sayılmaması, günlük eksik süre, payment_method zorunluluğu, geçmiş ücret değişmeme, kuruş yuvarlama, büyük tutar overflow, iki hesaplama yarışı, tek reversal; ham SQL update/delete engeli ve FK RESTRICT; Admin/Patron/Personel/custom role 200/403; API token web yetkisi vermez, CSRF kiosk, unauthorized JSON, print maaş sızdırmaz, aylık active filtresi, dashboard payroll sorgu izolasyonu. Mevcut Ledger/Payments/Stays ve MonthlyAccount regresyonları tam pakette geçer.

Yerel: gerçek MySQL/MariaDB ayrı test DB, `php artisan test`, Pint, PHPStan level 5, `composer check-platform-reqs`, `npm run build`, mevcut monthly-account JS testi; Android `assembleDebug` ve mümkünse unit instrumentation kontrolleri. NFC donanımı yoksa gerçek okutma doğrulandı denmez; APK derlemesi bunu kanıtlamaz.

Migration yalnız yeni personel tabloları ekler; mevcut gerçek veriye backfill/reset yok. `down()` boş yeni tabloları güvenle geri alır; kayıt varsa destructive rollback'i reddeder. Canlıda ileri migration tercih edilir. Trigger yetkisi yoksa migration başarısız olur, koruma atlanmaz. Yayından önce yedek varlığı doğrulanır; main'e yetkili merge otomatik deploy/CI/finalize çalıştırır. Deploy job başarısı, migration/seed/cache tamamlanması, yeni route auth ve mevcut finans ekranları smoke testi kayıt altına alınır. Geri dönüş veriyi silen rollback değildir: tarama erişimini kapatmak/terminal revoke ve ileri düzeltme. Android dağıtımı web deployment'ın ayrı çıktısıdır; kurulum APK'sı, source ve kısa kurulum notu teslim edilir; imzalama sırrı repoya konmaz.

Başlıca riskler: UID kopyalanabilir (kimliğin güçlü ispatı değildir); terminal credential çalınması; donanım UID format farkı; unutulan çıkışlar; ücretli izin ve resmi bordro varsayımlarının operasyonel hesapla karıştırılması; geçmiş rapora yanlış effective-date uygulanması; token veya ücretin normal kullanıcıya sızması. Yukarıdaki ayrık yetki, immutable kayıt, açık düzeltme ve fiziksel kabul adımları bu risklerin uygulama kontrolleridir.
