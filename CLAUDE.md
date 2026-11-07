# CLAUDE.md

## KURAL: Roller/yetkiler deploy ile sıfırlanmaz — 7 Kasım 2026

Kullanıcı talimatı: "roller yetkiler alanında bir güncelleme yapılmadıkça burası
değişmesin". `RolePermissionSeeder` her deploy'da koşar (§15) ama **yıkıcı değildir**:
mevcut rolün yetkilerine `syncPermissions` UYGULANMAZ; varsayılan matris yalnızca
(1) rol ilk kez kurulurken ve (2) kodla yeni eklenen (DB'de henüz olmayan) yetki için,
o yetkiyi varsayılan olarak taşıyan rollere uygulanır. "Roller / Yetkiler" ekranından
yapılan değişiklikler korunur. Seeder'a `syncPermissions` GERİ EKLENMEZ.
Test: `SeederPreservesRoleChangesTest`.

## Onaylı ortak Çöp Kutusu ve Konaklama fatura işareti — 4–6 Kasım 2026

Kullanıcı açık sorulara şu yanıtları vererek onayladı: **tek ve ayrı bir "Çöp Kutusu"
alanı** (Windows gibi), silinen kayıtlar **türüne göre kendi grubunda** listelenir,
Admin çöpten silince kayıt **komple** silinir. Kapsam: Tahsilat, Konaklama, Makbuz,
Cari hesap (firma), Oda, Kullanıcı. (Aylık Hesap sürümlü kendi silme modeliyle,
Teklif/Personel silme akışı olmadığı için, Roller ayar olduğu için kapsam dışıdır.)

- **Mimari:** `Core\Trash\TrashProvider` sözleşmesi + `TrashRegistry`; her modül
  sağlayıcısını `ModuleServiceProvider::$trashProviders` ile bağlar. `Trash` modülü
  (`/trash`, `trash.index|restore|destroy`) yalnızca sözleşmeyi bilir. Yetkiler
  `trash.view` (Admin/Patron/Personel, görünen gruplar kendi modül yetkisine bağlı) ve
  `trash.force_delete` (yalnızca Admin; sağlayıcı/servis rolü ayrıca doğrular).
- **"Sil" = çöpe taşı:** Tahsilat/Konaklama için aktif kayıt **önce otomatik iptal edilir**
  (cari ters kayıt + dağılım çözülür), sonra çöpe girer; çöpteki her finansal kayıt net
  sıfırdır. Tahsilatı tahsilat girebilen **herkes** (`payments.create`) siler; diğer
  türlerde silme yetkisi mevcut silme yetkisidir (stays.delete, companies.delete,
  rooms.manage, users.manage, makbuzda Admin). Geri alınan tahsilat/konaklama **iptal
  olarak kalır** (iptal geri alınmaz). İşaret: `payments.trashed_at/by`; stays/companies/
  users/rooms/receipt_book_entries `deleted_at` (SoftDeletes).
- **Kalıcı silme (§18 "finansal hard delete yok" kuralına bilerek açılmış dar istisna):**
  yalnızca Admin, yalnızca çöpteki kayıt. Tahsilat/konaklamada kayıt + ona bağlı
  `payment_allocations` satırları + `ledger_entries` satırları (orijinal+ters) **tek
  transaction'da** silinir; defter satırları net sıfır değilse reddedilir (bakiye
  değişmez). Kapı: `ledger_entries_block_delete` ve `payment_allocations_no_delete`
  tetikleyicileri yalnızca `Core\Trash\FinancialPurgeGate`in bağlantıya bağlı, 60 sn'lik,
  **sayılan satır kimlikleriyle sınırlı**, aktif-Admin kanıtlı oturum değişkenleriyle
  açılır (migration `2026_11_06_100000`); UPDATE korumaları değişmez. Firma: cari
  hareketi varsa silinemez; kullanıcı: kayıt oluşturduysa FK reddeder; oda: konaklama
  geçmişi (çöptekiler dahil) varsa silinemez. Testler: `TrashTest`.
- **Çöpteki veri HİÇBİR yerde görünmez (7 Kasım 2026):** listeler, ekstre, toplamlar ve raporlar
  çöpteki kaydı içermez. Tahsilat `Payment` global kapsamı (`notTrashed`), konaklama/firma/oda/
  makbuz/kullanıcı SoftDeletes ile gizlenir; çöp kutusu `withoutGlobalScopes()` kullanır. Defter
  append-only olduğundan satırlar silinmez: `Ledger\Support\LedgerVisibility` (`LedgerEntry`
  global kapsamı `visible` + ham `DB::table('ledger_entries')` sorguları) çöpteki kaynağın
  satırlarını süzer; kaynak modüller (`StaysServiceProvider`, `PaymentsServiceProvider`)
  `hideSourceWhenSet()` ile kaydeder. Çöpe yalnızca net sıfır kayıt girdiği için bakiye değişmez.
  Fiziksel kontroller (firma silme, kalıcı silme hazırlığı) `withoutGlobalScope('visible')` kullanır.
- **Cari ekstre hızlı dönem (7 Kasım 2026):** `?hizli=bu-ay|gecen-ay` tarih aralığını sunucuda
  belirler (`StatementCriteria::QUICK_RANGES`); bu modda özet (ekran + yazdırma) yalnızca
  **"Cari bakiye"**yi gösterir (devreden/toplam borç/toplam alacak gizlenir). Test: `StatementQuickFilterTest`.
- **Fatura işareti:** `stays.invoiced_at/invoiced_by` (NULL = kesilmedi) salt takip
  bayrağıdır; e-Fatura değildir (§3.2 sürer), finansal alan değildir, cari hareketi
  üretmez. Admin/Patron/Personel (`stays.update`) değiştirir, iptal edilmiş konaklamada
  değişmez; her geçiş onay penceresi ister, denetim kaydına yazılır.
  Test: `StayInvoiceToggleTest`.

## Tamamlanan CARİ temizliğinin geçici ekranını kaldırma — 13 Ekim 2026

Kullanıcı canlıdaki manuel temizliğin tamamlandığını bildirdi ve sayfanın
kaldırılmasını istedi. Tek seferlik fiziksel silme istisnası artık sona erer.
CariCleanup runtime modülü, provider kaydı, menü ve bütün HTTP uçları kaldırılır.
İki finansal DELETE trigger'ı bağımsız geçiş engelleri altında eski koşulsuz
SIGNAL korumasına döndürülür; normal §6/L1 ve §18 değişmezleri yeniden tam geçerlidir.
Tamamlanma kaydı/raporu, state ve UPDATE trigger'ları ve bütün tablo satırları
aynen korunur. Hiçbir veri yeniden silinmez, durum sıfırlanmaz veya sayfa açılmaz.
Eski migration aynı basename/içerikle database/migrations'a taşınır; geçmiş
yeniden çalıştırılmaz. Yeni ileri migration bu istisnayı geri açan down içermez.
Aşağıdaki önceki onaylar tarihsel bağlamdır. Kaldırma notu:
docs/cari-temizlik-ekranini-kaldirma-2026-10-13.md.

## Onaylı CARİ veri girişlerini tek seferlik silme istisnası — 12 Ekim 2026

Kullanıcı önceki blokeli ekranın ardından kapsamı açıkça netleştirdi:
**Cari hesap kartları kalır; konaklamalar, teklifler, tahsilatlar, makbuzlar
ve bağlı cari hareketler fiziksel silinir.** “Finansal kayıtların silinmesine
yalnız bu tek seferlik işlem için istisna” sorusuna “tamam değiştir ve girilen
veriyi silelim” yanıtıyla açık onay verdi. Bu dar istisna aşağıdaki önceki
salt okunur kararın ve §6/L1, §18 finansal hard-delete yasağının yalnız
bu işlem için önündedir; normal finansal UPDATE/DELETE yasağı sürer.

- Hedefler yalnız stays, offers/offer_periods/offer_room_types/offer_prices,
  payments, receipt_book_entries, ledger_entries ve payment_allocations.
- companies ve HESAP/PERSONEL/kullanıcı/yetki/oda/audit/eski kasa dahil
  diğer bütün mevcut tablolar/dosyalar korunur. ID sıraları sıfırlanmaz.
- Geçici CariCleanup modülü aktif Admin'e açık ön kontrol ve parola/açık ifade
  ile manuel POST silme içerir. Deploy veya migration CARİ verisi silmez.
- cari_cleanup_operations tek kullanımlık işlem durumunu ve içerik doğrulama
  raporunu kalıcı tutar; tamamlanmış durum UPDATE/DELETE ile açılamaz.
- İki finansal DELETE trigger'ı yalnız bağlantıya bağlı süreli capability ve
  aktif Admin kanıtıyla bu işlemde izin verir. UPDATE korumaları değişmez;
  FK kapatma, TRUNCATE ve silme transaction'ında DDL yoktur.
- Tüm silmeler, kapsam dışı tam satır içerik karşılaştırması ve başarı kilidi
  tek transaction'dadır. Eşzamanlı/tekrar istekler reddedilir; hatada rollback.
- Uygulama yolu, gerçek MySQL testleri, full CI ve bağımsız güvenlik incelemesi
  zorunludur. Canlı son işlem Admin'in yönetim sayfasındaki manuel onayıdır.
- Ayrıntı: docs/cari-giris-temizleme-2026-10-12.md. Aşağıdaki eski karar
  tarihsel bağlamdır; yeni açık kullanıcı onayıyla yukarıdaki kapsam değişti.

## Önceki CARİ temizlik ön kontrolü kararı — 12 Ekim 2026

Kullanıcının tek seferlik CARİ temizleme isteği, finansal korumalar nedeniyle
tam fiziksel silme güvenli değilse işlemin bloke edilmesini zorunlu kılar.
`CariCleanup` yalnız aktif Admin'e açık geçici, salt okunur envanter/ön kontrol
ekranıdır (`/admin/cari-temizleme`). Tüm gerçek tablolar sayılır; on CARİ aday
tablosu silme izni değildir, diğer tüm tablolar varsayılan korunur. Ledger ve
ödeme dağılımı append-only korumaları değiştirilmez. Silme servisi veya HTTP
ucu yoktur; ön kontrol başarılı silme yetkisi vermez. Şema/yetki seeder'ı ve
tek kullanımlık durum tablosu eklenmez. Bu isteğin yayın onayı yalnız bu güvenli
teşhis ekranını kapsar; canlı veri silme yetkisi içermez. Karar ve geçici kod
kaldırma planı: `docs/cari-temizleme-guvenlik-analizi-2026-10-12.md`.

## Onaylı masaüstü Hızlı Menü istisnası — 3 Ekim 2026

Kullanıcının açık isteğiyle yalnız masaüstünde görünen, kullanıcı tarafından
yönetilen Hızlı Menü kapsama alındı. §5'teki şema listesine dar istisna olarak
`user_quick_menus` (kullanıcı başına tek `shortcuts` JSON kaydı) eklenir;
finansal tablolara ve cari değişmezlerine dokunulmaz. §11.1'deki gölgesiz
tasarım kuralına yalnız bu sabit konumlu buton ve açılır panel için kontrollü
gölge istisnası tanınır. Koyu panel mevcut `nav` token'larını kullanır; ayrı
koyu tema yoktur. Hedefler yalnız mevcut ve yetki kontrollü dahili oluşturma
rotalarının kapalı listesinden seçilir. Ayrıntı: `docs/desktop-quick-menu.md`.

## Onaylı Teklifler modülü — 28 Eylül 2026

Kullanıcının açık talimatıyla konaklama fiyat teklifi hazırlama, listeleme,
düzenleme, detay ve yazdırma için bağımsız `Offers` modülü kapsama alındı.
`offers`, `offer_periods`, `offer_room_types`, `offer_prices` yalnızca belge
verisidir; teklif oluşturma/güncelleme `ledger_entries`, `payments` ve kasa
tablolarına yazmaz. Cari bağlantısı isteğe bağlıdır; müşteri adı, otel/yasal
bilgiler ve banka alanları teklif üzerinde snapshot olarak saklanır. Banka
hesap yönetimi, rezervasyon, fatura veya CRM bu istisnaya dahil değildir.
Admin ve Patron görüntüler, oluşturur ve düzenler; Personel erişmez.
Bu görev yalnızca yerel branch/worktree'de hazırlanır; release artışı,
push, PR, merge ve deploy bu paketin parçası değildir.

## Onaylı Personel Takip kapsamı — 25 Eylül 2026

Kullanıcının açık talimatıyla `Personnel` modülü, personel kartları, USB HID
okutma, Android NFC yardımcı uygulaması, vardiya, puantaj ve bağımsız personel
hakediş/ödeme defteri kapsama alındı. §3.2 API/mobil uygulama yasağının bu
göreve ait istisnası yalnız terminal kimlik doğrulamalı tarama ucudur; genel
mobil API, çevrimdışı işlem kuyruğu ve resmi bordro/vergi entegrasyonu değildir.
Şema, route sözleşmesi ve hesap kuralları `docs/personel-takip-teknik-plan.md`
belgesindedir. `users` giriş hesabı olarak kalır; çalışan ayrı kayıttır.

- Personel finansı `ledger_entries`, `payments`, `stays` ve müşteri cari
  servislerine yazmaz. Para `Money`/tamsayı kuruş ile hesaplanır.
- Ham okutma, vardiya/ücret versiyonları, puantaj düzeltmeleri ve personel
  finansal defteri tarihsel kayıtlardır. Düzeltme yeni kayıt, ödeme iptali ters
  kayıttır; kayıtlı geçmişe destructive migration/rollback uygulanmaz.
- Kart UID'si kimlik etiketidir; kimlik doğrulama sırrı değildir. Android
  terminal sırrı yalnız okutma yetkisi verir, sunucuda hash saklanır.
- `personnel.view`, `personnel.manage`, `personnel.attendance`,
  `personnel.payroll`, `personnel.scan`, `personnel.terminals` yetkileri mevcut
  Spatie matrisine eklenir. Admin/Patron tamamına, Personel varsayılan olarak
  yalnız `personnel.scan` yetkisine sahiptir. Ücret ve ödeme bilgileri ayrı
  payroll yetkisiyle korunur; mevcut kullanıcı/rol yönetimi değişmez.
- Bu görev için güncel doğrulanmış main'den `codex/personnel-tracking-nfc`
  dalı, PR, yeşil CI sonrası main'e merge ve mevcut otomatik production
  deployment kullanıcı tarafından önceden açıkça yetkilendirilmiştir.

## Onaylı Cari Ekstre listesi yeni tasarımı — 23 Eylül 2026

Kullanıcının referans görseline göre `statements.index` yenilendi (Konaklamalar
ile aynı desen: özet kartları, her zaman açık filtre kartı + "Daha fazla filtre",
hızlı filtreler, sıralama menüsü, ⋮ satır menüsü). Bağlayıcı noktalar:
- **Hareketi olan hesaplar HER sıralamada üstte, hareketsizler altta**
  (kullanıcı talimatı). Liste mantığı `Companies\Services\StatementListService`
  içindedir; ekran ve yazdırma görünümü aynı sırayı/filtreyi kullanır. Durum,
  son hareket ve bakiye `companies` kolonu olmadığı için (§6/L4) PHP'de, iki toplu
  sorguyla süzülür; sorgu sayısı firma sayısıyla büyümez.
- Durumlar: Borçlu (herhangi bir para biriminde bize borçlu) / Alacaklı /
  Kapalı (hareket var, tüm bakiyeler sıfır) / Hareket yok. `?durum=`,
  `?son_hareket=7|30|90` (son hareket tarihi), `?balance_sort=recent|highest|lowest`
  (bakiye sıralaması yalnız para birimi seçiliyken). Eski `has_balance` korunur.
- Para birimi seçilince bakiye, durum ve son hareket YALNIZ o para birimine göre
  hesaplanır (§6/L5). Özet kartları durum/son hareket filtresinden bağımsızdır.
- §9 istisnası: filtre çökertilebilir `<x-ui.filter-panel>` DEĞİL, açık karttır.
- Ortak parçalar: `<x-ui.icon>`, `<x-ui.today>`, `<x-ui.row-menu>`,
  `resources/js/popover.js`, `Core\Support\TurkishSuffix` ("%47'si", "%0'ı").
Testler: `StatementListDesignTest`.

## Onaylı Konaklamalar listesi yeni tasarımı — 23 Eylül 2026

Kullanıcının referans görseline göre `stays.index` yenilendi (pano ile aynı
tint token'ları). Bu ekrana özgü, kullanıcı talimatlı istisnalar:
- **Filtreler çökertilebilir `<x-ui.filter-panel>` DEĞİL, her zaman açık bir
  karttadır** (§9 istisnası); seyrek alanlar (para birimi, sayfa boyutu)
  kapalı başlayan "Daha fazla filtre" açılır panelindedir, seçili değer sayısı
  etikette rozetle görünür.
- **Hızlı filtreler** `?hizli=` ile (`StayFilter::QUICK_FILTERS`): bu ay / geçen
  ay (giriş tarihi), aktif konaklamalar (bu gece otelde: giriş ≤ bugün < çıkış,
  iptal hariç), ödenmeyenler (geçerli ödeme dağılımı toplamı tutardan az olan
  aktif kayıtlar — `PaymentAllocationService::paidAmountSubquery()`), bugün
  giriş, bugün çıkış. Diğer filtrelerle AND'lenir; tanınmayan değer yok sayılır.
- **Tablo xl (1280 px) ve üstündedir** (§11.5'teki lg yerine): 9 kolon +
  ödeme durumu 1024'te yatay kaydırma gerektirirdi; altında kart listesi.
- Satırın tamamı ayrıntıya gider; ⋮ menüsü (native `<details>`) yalnız yetkiye
  bağlı kısayolları taşır: Düzenle, Tahsilat al (iptal değilse), Cari ekstre.
- "Ödenmedi" rozeti kırmızı DEĞİL gül tonudur (§11.2 "borç kırmızı değildir");
  iptal kırmızı rozettir. Giriş/çıkış SAATİ gösterilmez — sistemde saklanmıyor.
- Gün/ay adları `Core\Support\TurkishDate` ile üretilir (yerel ayardan bağımsız).
Testler: `StayListDesignTest`.

## Onaylı Finans panosu yeni tasarımı — 23 Eylül 2026

Kullanıcı, paylaştığı referans görsele göre ana sayfanın (Admin/Patron Finans
panosu) yeniden renklendirilmesini onayladı (`docs/anasayfa-yeni-tasarim-yol-haritasi-2026-09-23.md`).
Kararlar: renkli KPI kartları token üzerinden (§11.1 istisnası, aşağıda), grafik
paketsiz ve JS'siz (HTML + satır içi SVG), **Hızlı İşlemler kartı YOK**,
Notlar/Belgeler sekmesi YOK, "Son Hareketler"de sistem geneli "Genel bakiye"
kolonu VAR (beğenilmezse kaldırılır), sorgu bütçesi büyüyebilir ama kayıt
sayısıyla büyümez. Pano SEÇİLİ para biriminde çalışır (`?currency=`, varsayılan
TRY); para birimleri hiçbir yerde birleşmez (§6/L5). Personel panosu değişmedi.

## Onaylı Aylık Hesap arayüz sadeleştirmesi — 15 Eylül 2026

Kullanıcının açık talimatıyla Aylık Hesap liste, ekleme, düzenleme ve ayrıntı
ekranlarındaki **Hesap sahibi** alanları ile başlıklardaki sahip adı kaldırıldı.
Bu görsel sadeleştirme veri sahipliğini, kullanıcı bazlı yetki kapsamını veya
denetim izini kaldırmaz; `owner_id` mevcut kayıt bütünlüğü için korunur.

## Onaylı kapsam değişikliği — kasa kaldırma

Kullanıcı kasa kaldırma işini Codex’e, diğer geliştirmeleri Claude’a verdi.
Bu dal `codex/kasa-kaldirma`, canlı `dcb771d` tabanındadır. Aşağıdaki eski
kasa zorunlulukları bu çalışma için geçersizdir. Cari, konaklama, tahsilat,
makbuz ve denetim izi korunur. Nakit dahil tüm tahsilatlar vardiyasız
çalışır; oluşturma/iptal kasa tablolarına yazmaz. Eski kasa verisi ve
payments.cash_session_id tarihsel olarak korunur; yeni kayıtta NULL olur.
Kasa route, servis, ekran ve yetkileri kaldırılır. Migration geçmişi
korunur, finansal kayıt silinmez. Yeni sistem geliştirmek ve canlıya
yayınlamak bu görevin parçası değildir. Ayrıntılar docs/kasa-kaldirma.md.

## Onaylı geliştirme kapsamı — 11 Eylül 2026

13 Eylül 2026 yayın paketi: kullanıcı açıkça “yayınla” dedi. Yedek provasını
erteledi; yapılmış sayılmaz. Bu `codex/yayin-cari-1` dalında yalnız bireysel
cari ve aynı para biriminde dağıtım vardır; dövizden TL kapama kodu/migration'ı
bu paketten ayrılmıştır. Aşağıdaki üçüncü adım notları diğer geliştirme
dalının tarihçesidir. Eski ödemeler otomatik eşleştirilmez, bakiye korunur.
Canlı aktarım öncesi sunucuda bakım ve migration çalıştırma erişimi gerekir;
dosya aktarımı tek başına tamamlanmış yayın değildir.

Entegrasyon kopyası durum notu: bu çalışma ağacı `codex/canli-entegrasyon`
dalında, güncel `origin/main` (`ea6c438`) tabanında hazırlanmıştır. K-106
filtreleri ile bireysel cari/ödeme/döviz geliştirmeleri birleştirilmiştir.
Tam yerel MariaDB paketi: 847 test, 2.688 doğrulama başarılı; Pint/PHPStan/
platform kontrolü ve Vite başarılıdır. `8188` birleşmiş yerel önizlemedir.
Bu bütün geliştirmelerin entegrasyonudur, ilk canlı yayın paketi değildir;
iki yayın kapsamı henüz ayrılmamıştır. Gerçek yedek provası, geçmiş ödeme
önizlemesi ve canlı kontrol/onay aşamaları beklenir. Sonuç belgesi:
`docs/canli-gecis-adim1-entegrasyon-2026-09-12.md`.

12 Eylül 2026 durum notu: kullanıcı Claude'un v2.1.1-E filtre güncellemesini
ayrıca sunucuya aktardığını teyit etti. GitHub `origin/main` artık `ea6c438`;
yerel geliştirme hâlâ `a426b3e` tabanındadır. Yeni öncelik güvenli canlı
geçiş yol haritasıdır: `docs/guvenli-canli-gecis-yol-haritasi-2026-09-12.md`.
E sürümündeki filtreler korunarak entegrasyon ve gerçek veri kopyasında prova
yapılmadan yerel geliştirme yüklenmez. Döviz işi yerelde 34 ilgili testi
geçti; birleşmiş yayın sürümü ve tarayıcı kabulü henüz tamamlanmadı.

Kullanıcı mevcut tasarım ve özellikler korunarak geliştirmeye açık onay verdi.
Güncel kararlar `docs/onaya-sunulan-gelistirme-plani.md` ve
`docs/mevcut-tasarimi-koruma-plani.md` içindedir. Aşağıdaki eski V1 sınırlarıyla
çelişen durumlarda bu dar kapsam geçerlidir:

- Firma seçilmeyen konaklama bireysel hesaba yazılır. Aynı kişi tekrar seçilebilir;
  aynı isimler otomatik birleştirilmez. Kimlik/pasaport veya kapsamlı CRM eklenmez.
- Tahsilatın borç sahibi ile ödeyeni ayrı tutulur. Otomatik en eski borçtan veya
  seçili konaklamalara kısmi/tam dağıtım, avans ve kontrollü iade kapsamdadır.
- Elle girilen işlem kuruyla farklı para biriminde ödeme, gerçek tahsilat para
  birimi korunarak borca uygulanabilir. Otomatik kur ve PMS kapsam dışıdır.
- İptal normal muhasebe dökümünde gizlenir; append-only finansal ve denetim izi
  korunur. Yalnız admin bağlantısız kayıtları kalıcı silebilir.
- Personel için masaüstü hızlı giriş, patron için mobil takip önceliklidir.
  Mevcut Kasa/Cari/Yönetim, makbuz, PWA, filtre ve rapor özellikleri kaldırılmaz.
- Mevcut girişler gerçek veridir: sıfırlama yapılmaz. Yerel test verisi ayrıdır.
  Canlı veri geçişi önizleme, bakiye mutabakatı ve kullanıcı kontrolü olmadan yapılmaz.

Yerel geliştirme dalı `codex/cari-gelistirme`, `main`in v2.1.1-D içeren
`a426b3e` durumundan başlatıldı (`source/develop` bu durumun atasıdır).
Geliştirme için tekrar onay istenmez; canlı yayın ve eski ödeme dağılımlarının
uygulanması ayrı kontrol aşamasıdır.

İlk uygulama adımı: `companies.account_type` (`company` / `individual`, varsayılan
`company`) mevcut kimlikleri ve cari bağlantıları korur. `companies` burada cari
hesap tablosudur; eski tablolar yeniden adlandırılmaz. Konaklama formunda firma
boşsa `individual_account_id` ile mevcut kişi seçilir ya da misafir adına yeni
hesap açılır. Hesap + konaklama + borç tek transaction içindedir. Aynı isimler
birleştirilmez; hesap türü sonradan değiştirilemez. Bireysel hesap bulunan veri
tabanında tür kolonunu kaldıran rollback engellenir. `IndividualStayTest` bu
akışı kapsar; ödeme dağıtımı bu ilk adımda henüz uygulanmış değildir.

İkinci uygulama adımı (12 Eylül 2026): yeni tahsilatlar `payer_type` ve
`payer_name` ile ödeyeni borç sahibinden ayırır. `allocation_mode` en eski giriş
tarihi/kimlik sırasını veya seçili konaklamaları belirler. `payment_allocations`
pozitif tutarlı dağılım ve tekil `reverses_allocation_id` ile ters dağılım tutar;
UPDATE/DELETE tetikleyicilerle yasaktır. Dağılım ikinci cari/kasa kaydı üretmez.
Finansal yazmalar önce cari hesap satırını kilitler; eşzamanlı tahsilat ve
konaklama değişiklikleri aynı hesapta sıraya girer. Bu adım aynı para biriminde
çalışır. Fazla ödeme seçili cari hesapta avanstır, yeni konaklamada aynı para
biriminde kullanılır. Konaklama iptali dağılımı avansa döndürür; tahsilat iptali
dağılımı da geri alır. Eski tahsilatların yeni alanları NULL kalır; otomatik
geçmiş eşleştirme yapılmaz, ilgili konaklamalarda belirsizlik açıkça gösterilir.
Dövizle farklı para birimli borcu kapatma ve avans sahipliği aktarımı sonraki
onaylı adımda uygulanacaktır. Geçmiş veri uygulaması ve canlı yayın yapılmadı.

Bu dosya, bu repoda çalışan Claude Code oturumları için bağlayıcı kılavuzdur.
Üçüncü uygulama adımı: döviz tahsilatında kullanıcı TL borç kapamayı seçebilir.
`payments.settlement_currency` hedefi saklar; boş değer tahsilat para birimidir.
Dağılımın `amount` alanı borç tutarıdır; nullable `payment_amount` dövizden
kullanılan tutarı, `debt_currency` TL hedefini saklar. Eski satırlar değişmez.
Her döviz dağılımı `payment_allocation` kaynaklı döviz borç / TL alacak çifti
üretir. Gerçek tahsilat ve kasa kaydı alındığı para biriminde tek kalır.
Dağılım çözülürse bu çift de ters kaydedilir. İşlem kuru değişmez; kuruş
hassasiyetinde borcu aşmayan döviz tutarı kullanılır, fark silinmez.
Bu adım dövizden TL'ye kapamadır; diğer döviz çiftleri ve avans sahipliği
aktarımı henüz uygulanmaz. Canlı geçiş yapılmaz.

`codex/yayin-cari-1` paketi canlıya aktarıldı (12 Eylül 2026 devir notu, detay
`docs/yayin-cari-1-2026-09-13.md`): `main`/`develop` `dcb771d`'ye güncellendi,
GitHub Deploy #11 gerçek FTPS aktarımıyla tamamlandı, sunucuda iki migration
uygulandı, yetki seeder'ı çalıştı, bakım modu kapatıldı. Yedek geri yükleme
provası hâlâ ERTELENMİŞTİR, canlı ekran/bakiye kontrolü kullanıcı tarafından
henüz TEYİT EDİLMEDİ — bu paket bu yüzden tam kapanmış sayılmaz. Bu devir
notu ayrıca deploy komut sırasındaki bir hatayı düzeltti: `db:seed --class=
RolePermissionSeeder` KISA sınıf adıyla sunucuda "class not found" verir —
bu seeder `database/seeders/` içinde DEĞİLDİR, tam adı `App\Modules\
Authorization\Database\Seeders\RolePermissionSeeder`dır. §15 ve tüm
`docs/deploy-*`/`docs/release-checklist-v2.1.1.md` dosyaları bu tam adla
düzeltildi.

Konaklamalar ekranı UI/araç eklentisi (12 Eylül 2026, kullanıcı talimatı,
yukarıdaki yayın paketinin ÜZERİNE — cari/ödeme/döviz mantığına dokunmaz):
listeye her satır için "Görüntüle" bağlantısı, filtreye göre para birimi
bazlı toplam kutusu (aktif/iptal ayrı gösterilir, §6/L5 gereği para birimleri
asla birleşmez) ve sayfalanmayan bir yazdırma görünümü (`stays.print`,
mevcut `stays.view` yetkisiyle) eklendi. Ayrıca yeni, BİLEREK dar bir yetki
açıldı: `stays.force_delete` — yalnızca Admin'e atanır ve
`StayPolicy::forceDelete()` bunu yalnızca ZATEN İPTAL EDİLMİŞ bir kayıtta
kabul eder. Gerekçe: ledger append-only olduğu için (§6/L1) aktif bir
konaklamanın borcu hard-delete ile KALDIRILAMAZ — borç deftere kalıcı yazılır
ve yalnızca ters kayıtla sıfırlanabilir; hard-delete bu yüzden yalnızca borcu
zaten sıfırlanmış (İptal edilmiş) bir kaydın artık gereksiz `stays` satırını
temizler, `ledger_entries` ve `payment_allocations`a HİÇ dokunmaz (ikisi de
append-only kalır). Bu kayda daha önce bir ödeme dağılımı uygulanmışsa (geçmişte
serbest bırakılmış olsa bile) `payment_allocations.stay_id` FK kısıtı
(RESTRICT) silmeyi veritabanı düzeyinde reddeder (`StayHasLinkedAllocations`).
Patron/Personel için mevcut Düzenle/İptal yetki kapsamı DEĞİŞMEDİ. Firma
seçim alanı (yalnızca konaklama formundaki `company_id`) Alpine.js OLMADAN,
native `<select>` üzerine yazan bağımsız bir JS combobox ile aranabilir hale
getirildi (`resources/js/app.js`, `initSearchableSelects()`) — §11.7'deki
"dinamik firma arama" istisnası bu turda kullanıcı talimatıyla devreye girdi;
gerçek form değeri her zaman `<select>`in kendisinden okunur, kullanıcı
yazdığı serbest metinle var olmayan bir kayda asla "bağlanamaz".

Deploy finalize script'i yazıldı (12 Eylül 2026 devir notu, `deploy/
finalize.php`, bkz. `docs/deploy-lemon.md` §6): daha önce yalnızca mimari
karar olarak dokümante edilen cron+işaret dosyası akışı artık gerçek koddur
— marker yoksa no-op, `flock()` ile eşzamanlılık kilidi, her adımın çıktısı
günlüğe yazılır, herhangi bir adım BAŞARISIZ olursa marker SİLİNMEZ (bir
sonraki cron baştan dener). `.github/workflows/deploy.yml`, gerçek bir
aktarımdan SONRA bu marker'ı AYRI ve tek dosyalık bir FTP yüklemesiyle
bırakır (`storage/` normal aktarımdan hariç tutulduğu için). Gerçek MySQL'e
karşı elle doğrulandı: cache temizleme → migrate → yetki seed'i → cache
yeniden kurma sırası ve başarısızlıkta marker'ın korunması beklendiği gibi
çalışıyor. cPanel cron kaydı 13 Eylül 2026'da kullanıcı tarafından eklendi.

Canlı sunucu **LiteSpeed + CloudLinux Alt-PHP**tir ve cron'u çağıran
`lsphp` bir **CLI binary'si DEĞİLDİR** (SAPI `litespeed`). Script buna göre
sertleştirildi (detay `docs/deploy-lemon.md` §6): çıktı `php://stdout`/
`php://stderr` ile yazılır (CLI'a özgü `STDOUT`/`STDERR` sabitleri fatal
verirdi) ve web'den çağrılma kontrolü SAPI adına değil GERÇEK bir HTTP
isteği bağlamına bakar. **Bu `lsphp` yapılandırmasında `proc_open` ve `exec`
BİRLİKTE kapalıdır**, yani script hiçbir harici komut çalıştıramaz; bu
yüzden artisan komutları ALT SÜREÇ olarak değil, AYNI SÜREÇTE
`Illuminate\Contracts\Console\Kernel::call()` ile koşturulur (çıktı
`BufferedOutput` ile günlüğe yazılır, çıkış kodu ve her `Throwable`
yakalanır). Bootstrap'tan ÖNCE `APP_RUNNING_IN_CONSOLE=true` yazılır
(Laravel'in kendi desteklediği açık kapı) ve süre/bellek limiti gevşetilir.
Sürüm kapısı MUTLAKTIR (yalnızca 8.3, §2); uygun değilse marker KORUNUR.
**Mekanizma 13 Eylül 2026 20:30'da canlıda uçtan uca çalıştı:** yedi adım
sırayla koştu (migrate: "Nothing to migrate" — bu paket migration
içermiyor, `stays.force_delete` seeder'dan gelen bir YETKİdir) ve işaret
dosyası silindi. Deploy sonrası elle müdahale artık GEREKMİYOR.

## YAYIN AKIŞI — 13 Eylül 2026'dan itibaren geçerli (yeni oturumlar ÖNCE BUNU OKUSUN)

**"Canlıya al" = `main` dalına push.** Başka hiçbir elle adım YOKTUR: elle
workflow tetikleme, onay metni yazma, sunucuda migrate/seed/cache koşturma
KALDIRILDI. Detay ve gerekçe: `docs/k107-otomatik-yayin.md`, `docs/deploy-lemon.md`
§6/§9, §15 (aşağıda).

```
main'e push → Actions "Deploy (Lemon)": CI testleri + vendor (8.3) + Vite derlemesi
            → önceki yayın/bakım kontrolü → bu yayına ait bakım modu → FTPS aktarımı (/lemon-app/)
            → storage/app/deploy-pending işareti (ayrı tek dosyalık FTP yüklemesi)
            → cPanel cron (dakikalık) → deploy/finalize.php:
              kernel başlamadan eski bootstrap cache'lerini temizle
              → optimize:clear → migrate --force → RolePermissionSeeder → 4× cache
              → yalnız bu yayının bakımını kapat → başarı kaydı → işareti sil
            → workflow, işaretin silinmesini ve aynı commit/run/attempt başarı
              kaydını FTPS ile doğrular (15 sn × 24 + ağ süresi) ve
              SONUCU Actions ekranına yazar; takılırsa sunucudaki
              lemon-deploy.log'un son 80 satırını da oraya döker
```

**Claude bu depoda `main`'e Bash ile push EDEMEZ** — uzak oturum ortamı
`git push origin main` komutunu "production deploy" sayıp blokluyor. Çalışan
yol: iş dalına (`claude/*`) normal `git push`, sonra GitHub MCP araçlarıyla
`create_pull_request` + `merge_pull_request`. Merge'in kendisi `main`'e bir
push olduğu için yayını tetikler. (Kullanıcı isterse `.claude/settings.json`
içine `"permissions": {"allow": ["Bash(git push:*)"]}` ekleyerek doğrudan
push'u açabilir — bu dosyayı Claude KENDİSİ düzenleyemez, ortam engelliyor.)

**FTPS sertifikası FTP host adıyla EŞLEŞMEZ.** Workflow'daki salt-okunur
`curl` kontrolleri (bakım öncesi durum kontrolü, finalize bekleme, sunucu
günlüğü indirme) bu yüzden katı TLS doğrulamasıyla ÇALIŞMAZ — curl 60:
"no alternative certificate subject name matches target host name". Her
birinde önce katı denenir, tutmazsa `--insecure`'a düşülür. **Bu geri düşüş
KALDIRILMAMALIDIR** (14 Eylül 2026'da kaldırıldı ve deploy "Aktarım öncesi
bakım dosyalarını hazırla" adımında kırıldı). Dosyayı aktaran
FTP-Deploy-Action'ın TLS davranışı bundan etkilenmez; kimlik bilgileri zaten
aynı kanaldan gittiği için yeni bir maruziyet doğmaz.

**FİNALİZE ARTIK CRON'A BAĞIMLI DEĞİL (K-108, 14 Eylül 2026 — kullanıcı
onayıyla açılan §15 istisnası).** Deploy, bakım modunu açarken
`storage/framework/maintenance.php` dosyasını ZATEN kendisi yüklüyor; o dosya
artık `deploy/maintenance-trigger.php` + Laravel'in kendi stub'ı olarak
üretilir. Bekleyen bir yayın işareti varsa **bakım süresince gelen ilk istek**
finalize zincirini koşturur; `artisan up` hem `down` hem `maintenance.php`
dosyasını sildiği için tetikleyici **kendini yok eder**. Cron YEDEK olarak
kalır — hangisi önce davranırsa finalize'ı o çalıştırır, `flock` ikisinin
çakışmasını önler.

**Bu, §15'in yasakladığı "internete açık migrate ucu" DEĞİLDİR ve öyle bir
uca dönüştürülmemelidir:** ortada URL/route/token YOKTUR; çalışmasının ön
koşulu yalnızca FTP erişimi olan deploy hattının bırakabildiği işaret
dosyasıdır. Ziyaretçi migration BAŞLATAMAZ, yalnızca zaten bekleyen işi öne
çeker. Gömülü modda çıktı yanıt gövdesine değil `lemon-deploy.log`'a yazılır
(`LEMON_FINALIZE_LOG`), HTTP reddi yalnızca `LEMON_FINALIZE_EMBEDDED` ile
atlanır, 60 saniyelik soğuma penceresi başarısız bir finalize'ın her istekte
yeniden denenmesini engeller. Başarıda ziyaretçi 302 ile aynı adrese döner;
başarısızlıkta boş sayfa değil normal bakım ekranını görür. Üç yol da yerelde
gerçek isteklerle doğrulandı (`tests/Unit/DeployFinalizeTest`).

**CRON GÜVENİLİR DEĞİL — bilinen ve ÖLÇÜLMÜŞ durum (14 Eylül 2026).** Finalize
cron'u dakikada bir koşmuyor: 20:57 ve 21:12'de bırakılan işaretler ancak
21:20'de, 22:32'de bırakılan işaret ise 6 dakikalık bekleme boyunca HİÇ
işlenmedi (koşu #20 — 24 denemenin hepsi "Finalize bekleniyor"; sunucu
günlüğüne tek satır düşmedi). Bunun iki sonucu vardır ve ikisi de koda
işlenmiştir:

1. **Bakım modu riski:** yeni akış aktarımdan ÖNCE siteyi bakıma alır ve
   bakımı finalize kapatır. Cron gecikirse site 503'te kalır. Bu yüzden
   workflow, doğrulama zaman aşımına uğradığında `storage/framework/down`
   dosyasını FTP ile SİLER (site eski sürümle ayakta kalır); koşu yine
   KIRMIZI biter çünkü yayın doğrulanmamıştır.
2. **`finalize.php` bunu hata saymaz:** `down` yoksa "zaten kapalı" deyip
   devam eder; `down` BAŞKA bir yayına aitse sahiplenmez ama yine de işareti
   silip başarı kaydını yazar. Aksi hâlde marker sonsuza dek kalır ve her
   cron turu aynı hatayı tekrarlardı (ChatGPT sürümünde bu tuzak vardı).

**Kalıcı çözüm kullanıcıdadır:** cPanel → Cron Jobs → kaydın var/aktif olduğu
ve sıklığı (`* * * * *`) doğrulanmalı. Depodan/CI'dan cron oluşturulamaz.
Cron dakikalık koşmuyorsa aktarım öncesi bakım modu bu projede zarar verir.

**Sunucu gerçekleri (tekrar keşfetme, üçü de canlıda acı çekilerek bulundu):**
LiteSpeed + CloudLinux Alt-PHP; cron'u çağıran `lsphp` CLI SAPI DEĞİL
(`STDOUT`/`STDERR` sabitleri fatal verir), ve `proc_open` + `exec` BİRLİKTE
kapalı (harici komut çalıştırılamaz → artisan AYNI SÜREÇTE `Kernel::call()`
ile koşar). Sunucuda terminal erişimi YOK; tanılama yalnızca
`lemon-deploy.log` üzerinden yapılır ve artık workflow onu kendisi indirir.

**OPCACHE BAYAT BAYT KOD ÇALIŞTIRABİLİR (27 Eylül 2026, v8 olayı).** Sunucu
opcache'i zaman damgasını `revalidate_freq` aralığıyla kontrol eder; FTP ile
yeni yüklenmiş ama o an opcache'te sıcak olan bir dosya finalize sırasında
ESKİ haliyle çalışabilir. v8'de modül rota dosyaları her istekte yüklendiği
için sıcaktı; `route:cache` eski rotalarla üretildi, `receipt-book.edit`
eksik kaldı ve makbuz listesi 500 verdi. İki kalıcı önlem: finalize,
bootstrap'tan önce `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`vendor/composer/` altındaki PHP dosyalarını `opcache_invalidate(..., true)`
ile geçersiz kılar (sonucu günlüğe yazar); `ModuleServiceProvider` rota
önbelleği varken modül rota dosyalarını hiç yüklemez.

**DURUM: ÇALIŞIYOR — zincir uçtan uca doğrulandı (13 Eylül 2026, 21:20).**
`main`'e merge → koşu #18 → 21:19:29 işaret bırakıldı → 21:20:01 cron finalize'ı
koşturdu (optimize:clear → migrate "Nothing to migrate" → RolePermissionSeeder
187 ms → 4× cache) → 21:20:18 workflow işaretin silindiğini görüp
"FİNALİZE TAMAMLANDI" yazdı. Bekleme adımı 43 saniye. Elle hiçbir adım yok.

Ara notlar (tanılama tarihçesi, tekrar yaşanırsa işe yarar): 20:57 ve 21:12'de
bırakılan işaretler ancak 21:20'de işlendi ve o aralıkta günlüğe HİÇ satır
düşmedi — cron o pencerede tetiklenmemişti. Cron koşup finalize hata verseydi
günlüğe yazardı; bu yüzden **"günlükte yeni satır yok" = cron hiç koşmadı**
teşhisi geçerlidir (cPanel → Cron Jobs'a bakılır). Bekleme süresi bu yüzden
6 dakikaya çıkarıldı. Yeni güvenilirlik düzeltmesinde zaman aşımı Deploy'u
kırmızı bitirir: dosya aktarımı tek başına başarılı yayın sayılmaz. Cron
sonradan tamamlayabilir; bu durumda sunucu günlüğü ve aynı yayının
`storage/app/deploy-completed.json` kaydı kontrol edilir. Körlemesine tekrar
yayın yapılmaz. Kalıcı finalize cron'u silinmez.

**Kasa kaldırma entegrasyonu:** Yukarıdaki CI kapısı, bakım sahipliği ve
başarı kaydı kontrolleri yerel `codex/kasa-kaldirma` dalında hazırlanmıştır;
önceki #18 canlı doğrulaması bu yeni kontrollerin canlı testi değildir.
FTPS sertifika doğrulaması başarısızsa kontrol atlanmaz; doğru sunucu adı ve
sertifika düzeltilir. Ayrıntılar `docs/claude-karsilastirma-2026-09-13.md`.

---

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
13. **Rooms (Odalar)** — oda tanım master data (K-100, kullanıcı onayıyla §3.2 istisnası)
14. **Offers (Teklifler)** — konaklama fiyat teklifi belgesi (28 Eylül 2026 kullanıcı onayıyla dar istisna)

### 3.2 ŞİMDİ YAPILMAYACAKLAR (kesin)

Aşağıdakiler için **tablo, migration, model, servis, route, view, config anahtarı veya "ileride lazım olur" placeholder'ı üretme:**

- Guest CRM / misafir kartı (konaklamada sadece `guest_name` metni var)
- TC kimlik / pasaport saklama, KVKK altyapısı, şifreleme
- KBS entegrasyonu
- Rezervasyon
- Housekeeping
- Stay charges / ekstra harcamalar
- Banka hesap yönetimi, banka entegrasyonu, mutabakat
  (**Fiziksel kasa defteri Gün 10'da kapsama ALINDI** — vardiya/devir/sayım.
  Kapsam dışı olan yalnızca BANKA tarafıdır; bkz. §8/Kasa Defteri.)
- Kur servisi, TCMB entegrasyonu, döviz çevrimi
  (**v2.1.1-C'de DAR bir istisna açıldı** — bkz. aşağıdaki blockquote.
  Otomatik/canlı kur servisi, TCMB entegrasyonu ve otomatik güncelleme
  HÂLÂ kapsam dışıdır; açılan yalnızca elle girilen, tek seferlik bir
  kur ANLIK GÖRÜNTÜSÜdür.)
- Yaşlandırma (aging) raporları
- Fatura / e-Fatura / e-Arşiv
- Channel manager
- Çevrimdışı veri girişi
- Bildirim/e-posta gönderimi (şifre sıfırlama hariç)
- Çok dilli arayüz (sadece Türkçe)
- API / mobil uygulama

> **Oda yönetimi K-100'de kapsama ALINDI** — kullanıcı bu listedeki kuralı
> bilerek AŞMIŞTIR (istisna talep edildi, "dur ve sor" kuralı uygulanıp
> kullanıcıya açıkça soruldu, "evet şimdi yap" yanıtı alındı). Kapsam
> KASITLI OLARAK dar tutulmuştur: yalnızca oda tanım master data'sı
> (`rooms` tablosu) ve `Stays.room_id` bağlantısı — Rezervasyon, Housekeeping,
> Oda Tipi lookup tablosu, fiyatlandırma/oda ilişkisi, PMS durumları HÂLÂ
> kapsam dışıdır ve bu istisnanın parçası DEĞİLDİR. Detay: §5.9, §8/Odalar,
> `docs/kararlar.md` K-100.

> **Döviz kuru anlık görüntüsü v2.1.1-C'de kapsama ALINDI** — kullanıcı bu
> listedeki kuralı bilerek AŞMIŞTIR (istisna talep edildi, "dur ve sor" kuralı
> uygulanıp kullanıcıya açıkça soruldu, "evet ikisini de şimdi yap" yanıtı
> alındı). Kapsam KASITLI OLARAK dar tutulmuştur: yalnızca (1) yeni bir
> Stay/Payment/ReceiptBookEntry TRY DIŞI bir para biriminde oluşturulurken
> operatörün ELLE girdiği bir kurun `exchange_rate_to_try`/`try_equivalent`
> olarak SAKLANMASI ve (2) bu turdan ÖNCEKİ eksik kayıtları tek seferlik
> doldurmak için "Yönetim → Geçmiş Döviz Düzeltme" ekranı. Otomatik/canlı kur
> servisi, TCMB entegrasyonu, periyodik güncelleme, kur çevrimi (farklı para
> birimlerini birbirine dönüştürme) HÂLÂ kapsam dışıdır ve bu istisnanın
> parçası DEĞİLDİR. Detay: §5.3, §5.4, §7.2, §8/Payments, §8/Geçmiş Döviz
> Düzeltme, `docs/kararlar.md` K-104.

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
    ├── Rooms/
    ├── Payments/
    ├── Ledger/
    ├── Dashboard/
    ├── CashBook/
    ├── FxCorrection/               # Geçmiş Döviz Düzeltme (v2.1.1-C) — kendi model/migration'ı yok
    ├── ReceiptBook/                # Tahsilat Makbuzu Defteri (v2.1.1-C) — muhasebeden AYRI, yazdırma amaçlı
    ├── Offers/                     # Teklif belgeleri (28 Eylül 2026 onayı) — finansal kayıt üretmez
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
- Aşağıdaki tabloların dışında V1'de tablo yoktur. 28 Eylül 2026 onayıyla
  `offers`, `offer_periods`, `offer_room_types`, `offer_prices` yalnızca
  teklif belgesi için bu sınıra dar istisnadır.

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
| room_no | varchar(20) null | **eski serbest metin — K-100 SONRASI YAZILMAZ.** Yalnızca Room Registry öncesi kayıtlarda dolu; geriye dönük OKUMA içindir, hiçbir zaman geriye doldurulmaz (backfill yapılmaz) |
| room_id | FK rooms null | K-100. Yeni kayıtlar odayı BUNUNLA seçer; `room_no` ile `room_id` AYNI ANDA anlamlı DEĞİLDİR — `Stay::roomLabel()` önce `room_id`'yi, o yoksa `room_no`'yu gösterir |
| check_in_date | date | index |
| check_out_date | date | `>= check_in_date` |
| nights | unsigned int | sunucuda hesaplanır, doğrulanır, `>= 1` |
| amount | decimal(15,2) | **nihai toplam** konaklama tutarı (her iki modda da) |
| pricing_mode | varchar(10) | `total` \| `daily` — ücretin nasıl girildiği |
| daily_rate | decimal(15,2) null | yalnızca `daily` modunda dolu |
| currency | char(3) | default TRY |
| exchange_rate_to_try | decimal(10,4) null | v2.1.1-C — yalnızca TRY DIŞI kayıtlarda, işlem ANINDA girilen kur ANLIK GÖRÜNTÜSÜ; sonradan yeniden hesaplanmaz (bkz. §8/Payments, K-104) |
| try_equivalent | decimal(15,2) null | v2.1.1-C — `amount × exchange_rate_to_try` (tamsayı kuruş aritmetiğiyle); salt bilgi amaçlı, `ledger_entries`e hiç yazılmaz |
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
- `CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))` — TRY hiç kur taşımaz (v2.1.1-C)
- `CHECK` — kur ve karşılık HER ZAMAN birlikte dolu ya da birlikte boş; ikisi de pozitif

### 5.4 `payments`

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | zorunlu, index |
| payment_date | date | index |
| amount | decimal(15,2) | > 0 |
| currency | char(3) | default TRY |
| exchange_rate_to_try | decimal(10,4) null | v2.1.1-C — `stays` ile AYNI kur anlık görüntüsü deseni; `Payment::IMMUTABLE_FIELDS`e BİLEREK eklenmedi (normal düzenleme yolu zaten bu alanı hiç bilmiyor), tek yazma yolu `create()` ve Geçmiş Döviz Düzeltme |
| try_equivalent | decimal(15,2) null | v2.1.1-C — `stays` ile AYNI, salt bilgi amaçlı |
| payment_method | varchar(30) null | enum `PaymentMethod`: `cash`, `bank_transfer`, `credit_card`, `cheque`, `other` |
| cash_session_id | FK cash_sessions null | v2.1.1-A.1 — oluşturma anında EXPLICIT atanır, bir daha değişmez (bkz. §8/Payments, K-095) |
| receipt_no | varchar(20) null, unique | v2.1.1-B — `Payment::id` üzerinden türetilir (`TM-{Y}-{id:06d}`), bir daha değişmez (bkz. §8/Payments, K-096) |
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
`(payment_method)`, `(created_by)`, `(reference_no)`, `(cash_session_id, payment_method)`.

**Veritabanı düzeyinde değişmezler:**
- `CHECK (amount > 0)`
- `CHECK (status IN ('active','cancelled'))`
- `CHECK (payment_method IS NULL OR payment_method IN ('cash','bank_transfer','credit_card','cheque','other'))`
- `CHECK ((status='active' AND cancelled_at IS NULL AND cancelled_by IS NULL) OR (status='cancelled' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL))`
- `CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))` — TRY hiç kur taşımaz (v2.1.1-C)
- `CHECK` — kur ve karşılık HER ZAMAN birlikte dolu ya da birlikte boş; ikisi de pozitif

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

**`cash_movements`** — hareket: `cash_session_id`, `type`, `category_id`,
`category_label`, `direction`, `amount`, `currency`, `entry_at`, `description`,
`counterparty`, `reference_type/id`, `is_reversal`, `reverses_movement_id`
(**unique**), `created_by`, `created_at`.

- Tutar **her zaman pozitiftir**; işaret `direction` (`in`|`out`) kolonundan gelir.
  Yön tipin doğal yönüdür, **ters kayıtta tersine döner** — ledger'daki
  debit/credit ayrımının kasa karşılığıdır.
- **Açılış bakiyesi ayrı tabloda değil, `opening` tipli bir harekettir**: beklenen
  kasa tek formülle hesaplanır ve para için iki kaynak olmaz.
- `reference_type/reference_id` (v2.1.1-A'dan itibaren DOLUYOR): nakit kanallı
  bir `Payment`e bağlı `company_collection` hareketinde `'payment'` / tahsilat
  kimliği taşır (§3.3'teki kaynak izleme deseni, artık gerçekten kullanımda).
  `CashBookService` bu değerlerin ANLAMINI bilmez, yalnızca saklar — Payments'a
  bağımlılık DOĞMAZ. Bağlı bir hareket genel Kasa Hareketleri ekranından
  DOĞRUDAN ters kaydedilemez (`CashMovementPolicy::reverse()`); düzeltme
  yalnızca kaynak kaydın (`PaymentService::cancel()`) kendi akışından yapılır.
- `category_id`/`category_label` (v2.1.1-B, nullable FK + varchar(100)): kullanıcı
  formdan elle bir `CashMovementCategory` seçtiğinde doldurulur (§8/Kasa Defteri
  "Hareket türleri (kategori)"). `category_label`, yazıldığı ANDAKİ kategori
  adının bir ANLIK GÖRÜNTÜSÜdür — kategori sonradan yeniden adlandırılsa/
  pasifleştirilse bile `CashMovement::label()` bu snapshot'ı gösterir, canlı bir
  JOIN yapmaz. Servis tarafından üretilen hareketlerde (`opening`,
  `company_collection`) ikisi de `NULL` kalır; `label()` bu durumda
  `CashMovementType::label()`'a düşer.
- Tutar `DECIMAL(15,2)`'dir (§5 genel kuralı) — `amount_minor` DEĞİL. Tamsayı
  kuruş aritmetiği PHP tarafında `Core\Support\Money` ile yapılır.

**`cash_movement_categories`** (v2.1.1-B) — admin tanımlı hareket türü ana
verisi: `id`, `name` (unique), `system_type` (varchar, `CashMovementType::
selectable()`'ın 5 değeriyle CHECK kısıtlı — `opening`/`company_collection`
ASLA atanamaz), `is_active`, `requires_recipient`, `sort_order`, `created_by`/
`updated_by`, timestamps. **Silme ucu yoktur** — kullanılmış bir kategori
yalnızca `is_active=false` yapılır (§5.8'in kasa hareketi değişmezlik ilkesiyle
AYNI gerekçe). `system_type` yalnızca oluştururken seçilir, düzenleme ekranı
bu alanı hiç kabul etmez — aynı ada sonradan farklı bir davranış bağlanamaz.

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
- **Tetikleyiciler:** `cash_transfer_amounts` ve `cash_counts` üzerinde de UPDATE
  ve DELETE reddedilir (`cash_movements` ile AYNI ilke) — devir kabul edildiğinde
  tutar zaten yeni vardiyanın açılış bakiyesine kopyalanmıştır, sayım satırı da
  kapanmış bir vardiyanın imzalanmış tanığıdır; ikisi de sonradan değiştirilebilseydi
  imzalanmış kayıt ile gerçek açılış/kapanış durumu sessizce koparırdı (K-091)

**Eşzamanlılık:** `recordMovement()`, `reverseMovement()`, `startHandover()` ve
`closeSession()` vardiyayı YAZMADAN ÖNCE `lockForUpdate()` ile satır kilidiyle
okur ve ancak ondan SONRA kapalı/bekleyen-devir durumunu kontrol eder — kontrol
ile yazma arasında kilitsiz bir pencere bırakmaz. Aksi hâlde tam o anda başlayan
bir devir ya da kapanış, devrin zaten bildirdiği tutarı sessizce stale bırakabilirdi
(K-091, `CashBookConcurrencyTest`'te gerçek InnoDB kilitlenmesiyle kanıtlandı).

### 5.9 `rooms` (K-100)

Oda tanım master data'sı — Company/User ile AYNI basitlikte bir kayıttır,
kendi cari hareketi/hesabı YOKTUR.

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| room_no | varchar(20) | zorunlu, **unique** |
| room_name | varchar(150) null | isteğe bağlı görünen ad ("Deniz Manzaralı") |
| room_type | varchar(50) null | serbest metin — ayrı bir lookup tablosu AÇILMADI (§3.3 ilkesi) |
| is_active | boolean | default true |
| created_by / updated_by | FK users null | |
| created_at / updated_at | | |

**Kural: kullanılmış (bir `stays` satırında referans edilmiş) bir oda asla
hard-delete edilmez** — `companies`/`cash_movement_categories` ile AYNI ilke.
Kontrol `Stays\Services\StayService::roomHasHistory()` üzerinden yapılır;
`Rooms` modülü `Stay` modelini doğrudan KULLANMAZ (§4.3/1). Kullanılmamış bir
oda hard-delete edilebilir (soft delete YOKTUR, `deleted_at` kolonu açılmadı —
gereksiz soyutlama, §3.3).

Pasif (`is_active=false`) bir oda YENİ konaklama için seçilemez ama geçmişte
görünmeye devam eder (`Stay.room_id` değişmez).

### 5.10 `receipt_book_entries` (v2.1.1-C)

Tahsilat Makbuzu Defteri — mevcut muhasebe `payments` tablosundan TAMAMEN
AYRI, yazdırma amaçlı bir kayıt defteri. `ledger_entries`/`payments`/
`cash_movements` ile **hiçbir FK/yazma ilişkisi yoktur**; bkz. §8/Tahsilat
Makbuzu Defteri.

| Alan | Tip | Not |
|---|---|---|
| id | bigint PK | |
| receipt_no | varchar(20) null, unique | `id` üzerinden türetilir (`MKB-{Y}-{id:06d}`) — gerçek muhasebe makbuzunun (`TM-…`) önekiyle KARIŞTIRILMAZ |
| receipt_date | date | index |
| customer_name | varchar(200) | serbest metin — `companies` FK'si YOKTUR (dokümantasyon amaçlı, herhangi bir müşteri için) |
| amount | decimal(15,2) | > 0 |
| currency | char(3) | default TRY |
| exchange_rate_to_try | decimal(10,4) null | `stays`/`payments` ile AYNI kur anlık görüntüsü deseni |
| try_equivalent | decimal(15,2) null | salt bilgi amaçlı |
| payment_method | varchar(30) null | enum `ReceiptPaymentMethod` — `Payments\PaymentMethod`ile PAYLAŞILMAZ (o enum'un `Cash` değeri kasa hareketi tetikler, bu modülde HİÇBİR yan etki yoktur) |
| description | text null | |
| created_by / updated_by | FK users | |
| created_at / updated_at | | |

**Düzenleme ve kalıcı silme yalnızca Admin'dedir** (27 Eylül 2026, kullanıcı
talimatı — önceki "silme/iptal ucu yok" kuralının yerine geçer). Makbuz
muhasebe kaydı olmadığı için düzeltme ters kayıt değil doğrudan düzenlemedir;
`receipt_no` düzenlemede DEĞİŞMEZ. Silme hard delete'tir (`deleted_at`/`status`
kolonu yok); silinen satırın tüm değerleri denetim kaydına açıkça yazılır.

**Veritabanı düzeyinde değişmezler:** `CHECK (amount > 0)`,
`CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))`,
kur/karşılık çifti her zaman birlikte dolu/boş ve pozitif — `stays`/`payments`
ile BİREBİR AYNI üç CHECK deseni.

### 5.7 Morph map

`AppServiceProvider::boot()` içinde `Relation::enforceMorphMap([...])` zorunlu:
`'stay' => Stay::class`, `'payment' => Payment::class`, `'company' => Company::class`,
`'user' => User::class`, `'role' => Role::class`, `'ledger_entry' => LedgerEntry::class`,
`'cash_session' => CashSession::class`, `'cash_movement' => CashMovement::class`,
`'cash_transfer' => CashTransfer::class`, `'cash_movement_category' => CashMovementCategory::class`,
`'room' => Room::class`, `'receipt_book_entry' => ReceiptBookEntry::class`.
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
stays.force_delete     # kalıcı silme (hard delete) — yalnızca Admin, yalnızca iptal edilmiş kayıt
rooms.manage           # oda tanımlarını yönet (K-100)
payments.view, payments.create, payments.cancel
ledger.view            # tek firmanın ekstresi
ledger.view_all        # tüm firmaların bakiyesi / toplam alacak
cashbook.view          # kasa defteri ve açık vardiya
cashbook.operate       # hareket girme, vardiya açma/kapatma, devir
cashbook.view_all      # tüm vardiyalar, günlük defter, kasa farkları
cashbook.manage_categories  # kasa hareket türlerini (kategori) yönet (v2.1.1-B)
dashboard.financial    # finansal dashboard kartları
export.excel
audit.view
fx.correct_historical  # Geçmiş Döviz Düzeltme (v2.1.1-C) — yalnızca Admin/Patron
receipt_book.view      # Tahsilat Makbuzu Defteri'ni görüntüle/yazdır (v2.1.1-C, payments.view ile AYNI birleşik desen)
receipt_book.create    # Tahsilat Makbuzu Defteri'ne yeni makbuz ekle (v2.1.1-C)
receipt_book.update    # makbuzu düzenle — yalnızca Admin (27 Eylül 2026)
receipt_book.delete    # makbuzu kalıcı sil — yalnızca Admin (27 Eylül 2026)
offers.view, offers.create, offers.update  # teklif belgesi; finansal kayıt üretmez
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
| stays.force_delete (kalıcı sil) | ✅ (yalnızca iptal edilmiş) | ❌ | ❌ |
| rooms.manage | ✅ | ✅ | ❌ |
| payments.view | ✅ (tümü) | ✅ (tümü) | ✅ (tümü) |
| payments.create | ✅ | ✅ | ✅ |
| payments.cancel | ✅ | ✅ | ❌ |
| payments — metadata düzenleme | ✅ | ✅ | ⚠️ kendi kaydı + süre |
| payments — finansal alan düzenleme | ❌ | ❌ | ❌ |
| cashbook.view | ✅ | ✅ | ✅ |
| cashbook.operate | ✅ | ✅ | ✅ |
| cashbook.view_all | ✅ | ✅ | ❌ |
| cashbook.manage_categories | ✅ | ✅ | ❌ |
| kasa — başkasının vardiyasına hareket | ❌ | ❌ | ❌ |
| ledger.view | ✅ | ✅ | ❌ |
| ledger.view_all | ✅ | ✅ | ❌ |
| dashboard.financial | ✅ | ✅ | ❌ |
| export.excel | ✅ | ✅ | ❌ |
| audit.view | ✅ | ✅ (salt okunur) | ❌ |
| audit — silme | ❌ | ❌ | ❌ |
| fx.correct_historical | ✅ | ✅ | ❌ |
| receipt_book.view | ✅ | ✅ | ✅ |
| receipt_book.create | ✅ | ✅ | ✅ |
| receipt_book.update / receipt_book.delete | ✅ | ❌ | ❌ |
| offers.view / offers.create / offers.update | ✅ | ✅ | ❌ |

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

  Finansal olmayan alanlar (`guest_name`, `room_no`, `room_id`, `notes`) cari hareketi üretmez.
- **İptal:** `status = cancelled` + geçerli cari hareketinin ters kaydı, aynı transaction'da.
  Aynı konaklama ikinci kez iptal edilemez; iptal edilmiş konaklama düzenlenemez.
- **Değişmez:** bir konaklamanın her an **tam olarak bir geçerli borç hareketi** vardır
  (iptal edilmişse hiç yoktur). Düzeltilen hareketler ters kayıtla net sıfıra gelir.
- Denetim kaydı `Auditable` trait'i ile değil, `StayService` tarafından açıkça yazılır —
  finansal düzeltmenin kaydı ters kayıt ve yeni kayıt kimliklerini de taşımak zorundadır.
- **Oda (K-100):** yeni konaklamalar `room_id` ile bir `Room` seçer; formda serbest metin
  YOKTUR. `room_no` yalnızca Room Registry ÖNCESİ kayıtlarda dolu kalır ve hiç yeniden
  yazılmaz — `Stay::roomLabel()` önce `room_id`'yi, o yoksa eski `room_no` metnini gösterir.
  Pasif bir oda YENİ kayıt için seçilemez (`StayRequest`); düzenlemede kaydın KENDİ odası
  pasifleşmiş olsa bile seçenekten kaybolmaz. `room_id` finansal alan DEĞİLDİR, değişimi
  ters kayıt üretmez.
- **Kur (v2.1.1-C, §3.2 istisnası):** yeni bir TRY DIŞI konaklama oluşturulurken
  `exchange_rate_to_try` ZORUNLUDUR; sunucu `try_equivalent`i `Money::multiplyByRate()`
  ile tamsayı aritmetiğiyle hesaplar ve saklar. `LedgerService`'e HİÇ geçirilmez —
  cari hareketi her zaman orijinal para biriminde kalır (§6/L5). Yalnızca OLUŞTURMADA
  sorulur; düzenleme formunda bu alan hiç YOKTUR. Finansal bir düzenleme (tutar/para
  birimi değişimi) MEVCUT kuru koruyarak karşılığı yeniden hesaplar — yeni bir CANLI
  kur DEĞİLDİR. Eksik geçmiş kayıtlar yalnızca "Geçmiş Döviz Düzeltme" ile doldurulur
  (bkz. aşağı). Detay: K-104.

### Rooms (Odalar) — K-100

Oda tanım master data'sı. `Companies`/`Users` ile AYNI basitlikte bir CRUD'dur —
`StayService` gibi özel bir akışa (ters kayıt, cari hareket) ihtiyacı YOKTUR,
çünkü oda tanımının kendisi finansal bir olay üretmez.

- Yönetim ekranı `rooms.manage` yetkisiyle korunur (Admin/Patron); Personel bu
  ekranı hiç görmez. Konaklama formundaki oda SEÇİMİ ayrı bir yoldur
  (`RoomService::optionsForForm()`, `stays.create`/`stays.update` yetkisine bağlı) —
  seçenek listesini görmek `rooms.manage` GEREKTİRMEZ.
- UI konumu: Yönetim → Odalar. Ayrı bir "Tanımlar Merkezi" AÇILMADI — kapsam
  kasıtlı olarak dar tutuldu (bkz. §3.2 istisna notu).
- **Silme:** kullanılmış (bir `stays` satırında referans edilmiş) oda asla
  hard-delete edilmez — silme isteği reddedilir, kullanıcıya pasifleştirmesi
  önerilir. Kontrol `StayService::roomHasHistory()` üzerinden yapılır (§4.3/1:
  `Rooms` modülü `Stay` modelini doğrudan KULLANMAZ). Kullanılmamış oda hard-delete
  edilebilir.
- **Pasifleştirme:** `is_active=false` yeni konaklama için odayı seçilemez yapar
  ama geçmiş kayıtlarda görünmeye devam eder (`Stay.room_id` değişmez).
- Denetim kaydı `Auditable` trait'i ile otomatik yazılır (Company/User ile AYNI desen).
- **CashBook "Oda Geliri" bağlantısı bilerek YAPILMADI:** bu turun kapsamı Room
  master data + Stays entegrasyonu ile sınırlıdır; CashBook'a dokunmak açık
  talimatla ("DO NOT TOUCH ... CASHBOOK") çelişirdi. İleride istenirse ayrı
  bir onayla ele alınır.

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

**Kanal davranışı (`payment_method`, v2.1.1-A):** `PaymentMethod` artık salt bilgi
amaçlı DEĞİLDİR:

| Kanal | Cariyi etkiler | Fiziksel kasayı etkiler |
|---|:--:|:--:|
| `cash` (Nakit) | ✅ | ✅ — `company_collection` hareketi |
| `credit_card` (Kredi Kartı) | ✅ | ❌ |
| `bank_transfer` (Havale/EFT) | ✅ | ❌ |
| `cheque` (Çek / Senet) | ✅ | ❌ — mevcut davranış korunur, yeniden tasarlanmadı |
| `other` / boş | ✅ | ❌ |

- **Nakit (`cash`):** `PaymentService::create()` aynı transaction'da
  `CashBookService::recordMovement()` ile `CashMovementType::CompanyCollection`
  ("Cari Tahsilat") tipinde bir hareket yazar — `reference_type/reference_id`
  ile tahsilata bağlı, tutar/para birimi tahsilatla birebir aynı. Bu hareket
  **yalnızca açık vardiyayı yöneten kullanıcı** tarafından yazılabilir
  (CLAUDE.md §8/Kasa Defteri "Hesap verebilirlik" ile AYNI kural — admin/patron
  dahil kimse başkasının vardiyasına hareket yazamaz); açık vardiya yoksa veya
  aktör sahibi değilse **tüm işlem** (Payment + Ledger dahil) geri alınır —
  kısmi/yarım tahsilat oluşmaz.
- **Kart/Banka/Çek/Diğer:** yalnızca cariyi etkiler; fiziksel kasaya HİÇ
  dokunmaz — kasa sayımını, beklenen kasayı ve personel devrini etkilemez.
- **İptal:** nakit kanalda, cari ters kaydının yanında bağlı kasa hareketi de
  ters kaydedilir — ama **orijinal hareketin kendi vardiyasına değil, GÜNCEL
  açık vardiyaya** (`CashBookService::reverseMovement(..., intoSession:
  $aktifVardiya)`): 7/24 işletmede vardiya o ana kadar çoktan devredilmiş/
  kapanmış olabilir; para geçmişe gitmeden BUGÜN düzeltilir, tıpkı gerçek bir
  kasada olduğu gibi. Kasa tarafı düzeltilemiyorsa (açık vardiya yok) **tüm
  iptal reddedilir** — cari ile kasa yarım kalmış biçimde ayrışmaz. Kart/
  banka/çek iptalinde kasaya hiç dokunulmaz.
  Bağlı bir kasa hareketi **genel Kasa Hareketleri ekranından doğrudan ters
  kaydedilemez** (`CashMovementPolicy::reverse()` + `CashBookService::
  reverseMovement()`'ın `allowLinkedSource` koruması) — aksi hâlde kasa
  düzelir ama tahsilat/cari düzelmeden yarım kalabilirdi. Düzeltme yalnızca
  `PaymentService::cancel()` üzerinden yapılır.
- **Z-raporu YOKTUR ve v2.1.1'de planlanmıyor.** Kanal ayrımı yalnızca
  cari/kasa doğruluğu içindir, POS mutabakatı değildir.
- **Vardiya atfı EXPLICIT'tir, zaman penceresi çıkarımı DEĞİLDİR
  (v2.1.1-A.1, K-095):** `payments.cash_session_id` (nullable FK,
  `cash_sessions`e) oluşturma anında AÇIKÇA yazılır ve **bir daha ASLA
  değişmez** (`Payment::IMMUTABLE_FIELDS`'ta, model + servis + FormRequest
  üç katmanlı korumayla). Kural TEK ve BASİTTİR: aktör o an açık BİR
  vardiyayı yönetiyorsa (`activeSession()->opened_by === aktör`) o vardiyaya
  atanır, aksi hâlde (açık vardiya yok VEYA başkasının vardiyası açık) **NULL
  kalır — tahmin edilmez.** Nakit kanalda bu vardiya ZORUNLUDUR (yukarıdaki
  madde); kart/banka/çek/diğer'de İSTEĞE BAĞLIDIR — Admin/Patron açık vardiya
  olmadan da kart/banka tahsilatı girebilir, kasa açmaya ZORLANMAZ.
  `Payment.cash_session_id` (kaynak işlemin atfı) ile `CashMovement.
  cash_session_id` (nakit iptalinde düzeltmenin yazıldığı GÜNCEL vardiya, bkz.
  yukarı) **İKİ AYRI referanstır** — biri hiç değişmez, diğeri iptalde bilerek
  güncel vardiyaya kayar; birbirine karıştırılmamalıdır.
- **Vardiya kanal özeti:** `PaymentService::shiftCollectionSummary(CashSession
  $session)` bir vardiyanın nakit/kart/banka tahsilatlarını para birimi
  bazında AYRI döner (§6/L5 — asla birleştirilmez); kart/banka tutarları artık
  `WHERE cash_session_id = ?` ile EXPLICIT sorgulanır, zaman penceresi/
  kullanıcı çıkarımı YOKTUR. Personel devri geçmiş kart/banka kayıtlarını
  SİLMEZ/SIFIRLAMAZ — yeni vardiyanın kanal toplamları boşla başlar, eskiler
  kalıcı, tarihsel kayıt olarak eski vardiyada durur. `cash_session_id = NULL`
  taşıyan (eski/atfedilmemiş) kayıtlar hiçbir vardiya özetine sızmaz ama
  `PaymentService::totalsByMethodAndCurrencyBetween()` gibi tarih aralığı
  bazlı (vardiyadan bağımsız) raporlarda görünür kalır.
- Yeni bir yetki AÇILMADI: kural mevcut `payments.create` + `cashbook.operate`
  (Personel'de zaten ikisi de var) üzerinden yürür.

**Adlandırma (v2.1.1-B):** kullanıcıya gösterilen her yerde "Tahsilat ekle" /
"Yeni tahsilat" metni **"Cari Tahsilat"** olarak birleştirildi (sayfa başlığı,
liste/firma detayı/pano butonları, Hızlı İşlem). Bu yalnızca ARAYÜZ metnidir —
`PaymentService`, `PaymentController`, route adları (`payments.create` vb.)
DEĞİŞMEDİ; §13'teki isimlendirme kuralı ("kod İngilizce") burada da geçerlidir.

**Tahsilat Makbuzu (v2.1.1-B, K-096):** her tahsilat, yazdırılabilir bir
"Tahsilat Makbuzu" (`payments.receipt` route'u, `payments.view` yetkisiyle
korunur — yeni bir yetki AÇILMADI) üretir. **Bu belge fatura veya e-Arşiv
fatura DEĞİLDİR** — operasyonel bir tahsilat kaydıdır ve ekranın kendisinde
bu açıkça yazılıdır. Z-raporu hâlâ YOKTUR (yukarıdaki madde).

- **Makbuz numarası** (`receipt_no`, `TM-{Y}-{id:06d}`) `Payment::id` üzerinden
  TÜRETİLİR — ayrı bir sıra tablosu YOKTUR, `MAX()+1` riski taşımaz (MySQL
  AUTO_INCREMENT zaten eşzamanlılığa karşı güvenlidir). Bilinen sınırlama:
  sayı yıl başına 1'e sıfırlanmaz. `create()` içinde `id` atandıktan HEMEN
  SONRA, `saveQuietly()` ile yazılır (bu, `create()` akışının kendi
  tamamlanmasıdır — dışarıdan bir düzenleme değildir).
- `receipt_no`, `Payment::IMMUTABLE_FIELDS`e eklidir: **iptal DAHİL hiçbir
  işlem sonradan değiştiremez** — `cash_session_id` ile AYNI üç katmanlı
  koruma (model + servis + FormRequest), `$fillable`'da YOK.
- Bu özellikten ÖNCE oluşturulmuş kayıtlarda `receipt_no = NULL`'dır —
  geriye dönük makbuz ÜRETİLMEZ (dürüst sınırlama); makbuz ekranı bu durumda
  404 döner, tahsilatın kendi detay ekranını etkilemez.
- **"Yalnız" (yazıyla tutar) alanı YALNIZCA TRY için üretilir**
  (`Payment::amountInWords()`, `Core\Support\TurkishNumberToWords`). Yabancı
  para birimlerinde UYDURMA/yanlış bir çeviri üretmek yerine `null` döner;
  ekran yalnızca sayısal tutar + ISO kodunu gösterir.
- Firma yasal başlık bilgisi (`config('hotel.company.*')`) **GEÇİCİ bir
  yerleşimdir** — gerçek bir Settings modülü açılana kadar. Sayfa
  `layouts.print`'i genişletir (Vite/tema yüklemez, A4 sade CSS — Cari
  Ekstre/Devir Raporu ile aynı desen).
- İptal edilmiş bir tahsilatın makbuzu erişilebilir kalır; iptal durumu
  makbuzda açıkça (rozet + gerekçe) görünür. Makbuz geçmişi ASLA silinmez.
- İzlenebilirlik: nakit kanalda bağlı kasa hareketinin açıklamasına makbuz
  numarası eklenir ve Kasa Hareketleri ekranından tahsilata bağlantı verilir
  (yalnızca route ADI ile — CashBook Payments sınıflarını import ETMEZ, §4.3/3
  ihlal edilmez); `payments::show` da bağlı vardiyaya (`Payment.cashSession`)
  bağlantı verir.
- **Kur (v2.1.1-C, §3.2 istisnası):** Stays ile AYNI kural — TRY DIŞI tahsilatta
  `exchange_rate_to_try` ZORUNLUDUR, `try_equivalent` sunucuda hesaplanır. Tahsilat
  tamamen değişmez olduğu için (yukarı) bu alanlar `Payment::IMMUTABLE_FIELDS`e
  BİLEREK EKLENMEDİ — normal düzenleme yolu (`UpdatePaymentRequest`) zaten bu
  alanları hiç bilmiyor, tek yazma yolu `create()` ve "Geçmiş Döviz Düzeltme"dir.
  Detay: K-104.

### Geçmiş Döviz Düzeltme (v2.1.1-C, §3.2 istisnası)

Yönetim → Geçmiş Döviz Düzeltme (`fx.correct_historical`, Admin/Patron). Bu
turdan ÖNCE oluşturulmuş, TRY DIŞI ve kuru EKSİK Stay/Payment kayıtlarını
listeler; operatör her satıra yalnızca o günkü kuru girer.

- Kendi migration/model'i YOKTUR — `StayService`/`PaymentService`'in
  `missingExchangeRate()`/`correctHistoricalExchangeRate()` metotlarını çağıran
  ince bir kontrolcüdür (Ledger ekstresinin Companies'te barınma gerekçesiyle
  AYNI desen, §4.3/3).
- **Katı kurallar:** orijinal tutar/para birimi ASLA değişmez; ikinci bir
  finansal işlem OLUŞTURULMAZ; cari hareketi ASLA ters kaydedilmez/yeniden
  işlenmez — yalnızca eksik `exchange_rate_to_try`/`try_equivalent` yazılır.
  Kur ZATEN kayıtlıysa ikinci "düzeltme" REDDEDİLİR (`ExchangeRateAlreadyRecorded`)
  — normal ekranlardan rastgele düzenleme mimari olarak İMKÂNSIZDIR.
  Her düzeltme `AuditEvent::ExchangeRateCorrected` ile kim/ne zaman AYRICA loglanır.

### Tahsilat Makbuzu Defteri (v2.1.1-C, §4.3 kural 5 istisnası)

Mevcut muhasebe **Tahsilatlar** (`Payments`) modülünden TAMAMEN AYRI, yazdırma/
dokümantasyon amaçlı bir modül (`ReceiptBook`) — kullanıcının açık onayıyla,
gerçek muhasebe Tahsilat Makbuzuyla (K-096) karışmasın diye BİLEREK FARKLI
isimlendirildi.

- **KRİTİK:** bir makbuzun kaydı firma cari bakiyesini, fiziksel kasayı veya
  herhangi bir muhasebe kaydını KESİNLİKLE ETKİLEMEZ — `ReceiptBookService`
  `LedgerService`/`PaymentService`/`CashBookService`'i HİÇ import ETMEZ.
- Kendi tablosu, kendi numaralandırması (`MKB-{Y}-{id:06d}` — gerçek `TM-…`
  ile KARIŞTIRILMAZ), kendi ödeme şekli enum'u (`ReceiptPaymentMethod`).
- **Düzenleme/silme yalnızca Admin** (`receipt_book.update`/`receipt_book.delete`,
  27 Eylül 2026). Makbuz no değişmez; silme kalıcıdır ve denetim kaydı
  silinen değerleri taşır (`ReceiptBookService::delete()`).
- Giriş UX'i: "Yeni Makbuz" Kasa Hareketi dialog'unun (K-098) AYNI desenini
  kullanan bir modal/sheet açar; standalone `receipt-book.create` sayfası
  JS kapalıyken/doğrulama hatasında FALLBACK'tir.
- Yazdırma: şirket bilgisi sol / "TAHSİLAT MAKBUZU" başlığı sağ (iki sütun),
  `config('hotel.company.*')` kullanır (K-096 ile AYNI kaynak, hardcode YOK).
- Sidebar: "Cari" grubunda **"TAHSİLAT MAKBUZLARI"** — "Tahsilatlar"dan
  görsel/metinsel olarak AÇIKÇA AYRI.
- Yetkiler `receipt_book.view`/`receipt_book.create` — Personel de sahiptir
  (`payments.create` ile AYNI operasyonel seviye). Detay: K-104.

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
  **Para birimi filtresi, "yalnızca bakiyesi olanlar" ve bakiyeye göre sıralama (Gün 12):**
  `balance` bir `companies` kolonu OLMADIĞI için (§6/L4) bu üç filtre SQL'de değil, filtreye
  uyan firmaların toplu bakiye sorgusu üzerinde PHP tarafında uygulanır — yalnızca
  görüntülenecek sayfa kadarı için firma modeli çekilir. Bakiyeye göre sıralama **para birimi
  seçilmeden anlamsızdır** (§6/L5 — para birimleri asla birleştirilmez); seçilmeden istenirse
  sessizce isim sırasına düşer, hata vermez.
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

> **Güncel Finans panosu (23 Eylül 2026)** — aşağıdaki Gün 11 notlarının
> Operasyon/kasa kısımları kasa kaldırmayla tarihseldir. Finans panosu:
> seçili para biriminde 5 KPI kartı (açık alacak/borç, net, bu ay konaklama
> adet+gece, bu ay tahsilat adedi) · "Son 6/12 Ay Cari Hareketler" grafiği
> (`?months=6|12`; sütun = konaklama `check_out_date` / tahsilat `payment_date`
> ay toplamı, çizgi = ay sonu sistem geneli net bakiye; `MonthlyChart` tamsayı
> geometri) · "Para Birimi Bazında Durum" tablosu (her para birimi ayrı satır)
> · sekmeli alt tablo (`?tab=hareketler|konaklamalar|tahsilatlar|borc-alacak`,
> yalnız açık sekme sorgulanır; "Son Hareketler" `?sayfa=` ile sayfalanır ve
> satırdan SONRAKİ tüm hesapların net bakiyesini "Genel bakiye" olarak gösterir)
> · sağ sütunda Cari Özeti, en yüksek bakiyeli 5 hesap ve (yalnız `audit.view`)
> denetim kaydından "Son Güncellemeler". Geçersiz `currency`/`months`/`tab`
> sessizce varsayılana düşer. Testler: `FinanceDashboardDesignTest`.

**Gün 11 — pano role göre TAMAMEN farklı bir yüzeydir**, klasik tek-şablonlu
admin paneli değildir. `DashboardController` üç dala ayrılır ve dal seçimi
**önce** güvenlik kontrolüdür, ikinci planda görünüm tercihidir:

1. `canSeeFinancials($user)` (`dashboard.financial` yetkisi) **false** ise
   kullanıcı `dashboard::personnel` görür — `?view=` parametresi HİÇ okunmaz.
   Bu sıra bağlayıcıdır: personel `/dashboard?view=finance` yazarak Admin/Patron
   dallarına asla giremez (kayıt bazlı değil, dalın kendisi kapalıdır).
2. `dashboard.financial` yetkisi **true** ise `?view=operations` ⇒
   `dashboard::operations`, aksi hâlde (VARSAYILAN, v2.1.1-D §1 — kullanıcı
   talimatıyla Finans öne alındı) `dashboard::finance`.

**Operasyon ve Finans AYRI sorgulanır** — `DashboardService::operations()` hiç
`ledger_entries`/`companies`/`stays`/`payments` sorgulamaz; `finance()` hiç
`cash_*` tablosu sorgulamaz. Hangisi seçiliyse yalnız o çalışır
(`DashboardTest::operasyon_gorunumu_cari_veya_firma_sorgusu_calistirmaz`,
`finans_gorunumu_kasa_sorgusu_calistirmaz`). İkisi de kayıt sayısıyla BÜYÜMEZ;
sorgu üst sınırı Finans için 17 (v2.1.1-D §3 — TL karşılığı toplu sorguları
dahil), Operasyon için vardiya/devir sayısından bağımsız sabit bir sayıdır
(`CashBookService::summariesFor()` zaten O(1)).

- **Operasyon** ("Otelde şu anda ne oluyor?") — A) Şu an (açık vardiya sahibi +
  saat + hareket sayısı, `CashBookService::dashboardSnapshot()`'tan), B) Kasada
  (beklenen fiziksel kasa, para birimi bazında), C) Bugün (bugünün oda geliri/
  ek gelir/gider/ara kasa çıkışı, `CashSessionTotals` toplanarak — yeni bir
  sorgu YAZILMAZ, `summariesFor()`'un zaten hesapladığı nesneler toplanır),
  D) Dikkat gerekenler (yalnızca gerçek sorun varsa: bekleyen devir veya
  kapanış sayım farkı — sorun yoksa bölüm hiç render edilmez), E) Son
  hareketler (açık vardiyanın son 6 hareketi).
- **Finans** ("Para ve cari hesaplarda durum ne?") — A) Cari durum (para
  birimi bazında açık alacak/borç/net + bu ay konaklama/tahsilat), B) En
  yüksek bakiyeli firmalar, C) Son tahsilatlar, D) Yaklaşan konaklamalar.
  "Son cari hareketler" paneli KALDIRILDI: aynı bilgi ekstre ve tahsilat
  listesinde zaten var, tek panoda iki kez gösterilmez.

Admin/Patron ikisini de `?view=` ile gezer (bookmark/geri tuşu doğal çalışır —
ayrı bir JS state makinesi yoktur, `dashboard::_view-switch` sade bağlantılardır).
**Personel bu geçişi hiç görmez** — `dashboard::personnel` view'ı bu partial'ı
hiç include etmediği için ayrı bir kontrol GEREKMEZ.

**Personel panosu "Vardiyam / Kasam" mantığındadır**, finansal yönetim panosu
DEĞİLDİR. Öncelik sırası: (0) bekleyen bir kasa devri varsa **her şeyden önce**
gösterilir ("Kasa devri sizi bekliyor"); (1) Vardiyam — açık vardiyası
kendisindeyse durum + Kasam (para birimi bazında ELİNDEKİ tutar — bkz. altta)
+ hızlı aksiyonlar + son hareketler + (varsa) bekleyen devir uyarısı; açık
vardiya yoksa "Açık vardiyanız yok" + (yetkiliyse) "Kasayı Aç", vardiya
BAŞKASINDAYSA yalnızca var olduğu ve kimde olduğu bilgisi (CLAUDE.md §8/Kasa
Defteri "Görünürlük" — tutar değil); (2) Bugün girdiğim kayıtlar — bugün/bu
hafta konaklama ve tahsilat SAYISI ve kendi son kayıtları.

**Kasam artık Money taşır — bu BİLİNÇLİ bir istisnadır, gizleme değildir:**
`PersonnelSummary` firma cari bakiyesi/toplam alacak gibi hiçbir LEDGER
alanı taşımaz (§7.4 hâlâ geçerli — o veri hâlâ hiç sorgulanmaz), ama personelin
KENDİ ELİNDEKİ fiziksel kasa (Gün 10'dan beri `cashbook.operate` yetkisiyle
zaten kendi ekranında görünen aynı veri) artık panoda da görünür. Yasak olan,
BAŞKASININ vardiyasının tutarını görmektir; bu, Policy ve Service katmanında
zaten korunan bir kuraldır (§8/Kasa Defteri "Görünürlük"), pano yalnızca aynı
kuralı uygular.

**Lemon Bar** — masaüstünde `layouts/app.blade.php` içinde ayrı bir üst çubuktur
(kenar çubuğu marka/kullanıcı kimliğini zaten taşıdığı için ikisi TEKRARLANMAZ):
yalnızca bağlam (Operasyon/Finans sekmesi — yalnızca panoda dolu), Hızlı İşlem
ve (varsa) kritik durum metni taşır. Kritik durum metni yeni bir sorgu YAZMAZ —
yalnızca zaten pano isteği için yüklenmiş veriden (`@section('lemon-bar-status')`)
doldurulur; bu yüzden yalnızca pano sayfalarında görünür, uygulamanın geri
kalanında boştur (global bir kasa sorgusu her sayfada çalıştırılmaz — performans
kararı, bkz. `docs/kararlar.md`). Mobilde aynı bilgi `?view=` sekmesi OLMADAN
(kullanıcı talimatı §12), yalnızca "+" (Hızlı İşlem) ve gerekiyorsa küçük bir
uyarı ikonuyla üst çubukta, sekmeler ise sayfa içeriğinin başında ayrı satırda
görünür.

**Hızlı İşlem** (`partials/quick-action.blade.php`) rol/yetkiye duyarlı, global
bir "+" menüsüdür ve YENİ bir işlem mantığı YAZMAZ — mevcut create route'larına
yönlendirir.

> **v2.1.1-B (K-096):** Gün 11'in personel/Patron-Admin için AYRI iki liste
> tasarımı KALDIRILDI. Liste artık TEK ve HERKES için AYNIDIR: Kasa Hareketi /
> Cari Tahsilat / Yeni Konaklama / Yeni Firma / Kasa Devri — her satır kendi
> yetkisini kontrol eder, rol farkı yalnızca hangi satırların göründüğüyle
> ortaya çıkar. Gerekçe: kasa hareket TÜRÜ artık admin tanımlı, dinamik bir
> kategoridir (§8/Kasa Defteri "Hareket türleri (kategori)") — aynı sistem
> davranışına (`extra_income`) birden fazla aktif kategori eşlenebileceği için
> `?type=room_income` gibi tek bir kategoriye ÖNCEDEN bağlanan bir kısayol
> artık belirsizdir; bu yüzden genel "Kasa Hareketi" kısayolu kategori
> ÖNCEDEN SEÇMEDEN hareket formuna yönlendirir.

> **v2.1.1-B.1 (K-098):** "Kasa Hareketi" artık tam sayfa gezinmesi YERİNE
> native `<dialog>` ile masaüstünde modal, mobilde alt sayfa (bottom sheet)
> açar (`data-cash-movement-trigger`, `partials/cash-movement-dialog.blade.
> php`, `app.js` `initCashMovementDialog()`). İçerik yalnızca tıklandığında
> `fetch()` ile yüklenir — kabuk her sayfada BOŞTUR, kategori/vardiya verisi
> hiçbir sayfa yüklemesinde eagerly sorgulanmaz (Lemon Bar'ın performans
> ilkesiyle AYNI karar). Form GERÇEK bir `<form>` POST'udur; doğrulama/servis
> hatası Referer'a değil HER ZAMAN standalone "Hareket ekle" sayfasına
> yönlendirilir — dialog artık HERHANGİ bir sayfanın üzerinde açılabildiği
> için `back()` kullanıcıyı formu göstermeyen bir sayfaya götürebilirdi.
> Standalone `cashbook.movements.create` route'u FALLBACK olarak durur (JS
> kapalıysa, doğrudan link, ya da fetch başarısızsa).

**"Sistem henüz boş" durumu** artık yalnızca Finans görünümündedir (cari defter
boşken): kasa artık ayrı bir görünümde olduğu için "ikisi birden boşken" birleşik
kontrolü YOKTUR — Operasyon zaten her durumda (açık/kapalı) kendi "Şu an" kartını
gösterdiği için ayrı bir mega boş-durum bloğuna ihtiyaç duymaz.

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

Bağımlılık TEK YÖNLÜdür (v2.1.1-A): `Payments\Services\PaymentService`,
nakit kanallı tahsilatlar için `CashBookService`'i çağırır (§4.3/1'in izin
verdiği "başka modülün Service'ini çağırma" deseni — `Stays`/`Payments`'ın
zaten `LedgerService`'e yaptığı gibi). `CashBookService` bunun TERSİNİ asla
yapmaz; Payments'ın var olduğunu bilmez, `reference_type`'ın ne anlama
geldiğini bilmez, yalnızca opak bir string olarak saklar.

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
| `company_collection` (nakit kanallı cari tahsilat, v2.1.1-A) | + | gelir |

`company_collection` **yalnızca `PaymentService` tarafından üretilir** —
`CashMovementType::selectable()`'da YOKTUR, personel Kasa Hareketi formundan
elle seçemez (aksi hâlde aynı tahsilatın kasa etkisi iki farklı yoldan
üretilip tek doğruluk kaynağı ilkesi bozulurdu). Gerçek gelirdir: firma borcu
fiilen azalmıştır.

**Hareket türleri (kategori, v2.1.1-B, K-096):** Kasa Hareketi formu artık ham
`CashMovementType` DEĞİL, admin tanımlı bir `CashMovementCategory` sunar
(`cashbook.manage_categories` yetkisi, "Yönetim" grubunda "Kasa Hareket
Türleri" ekranı). Kategori, `CashMovementType`in YERİNE geçmez — ÜZERİNE bir
ETİKET katmanıdır:

- Bir kategorinin `system_type`'ı (sabit muhasebe davranışı) yalnızca
  `CashMovementType::selectable()`'ın 5 değerinden biri olabilir (§5.8) ve
  yalnızca OLUŞTURURKEN seçilir — admin kendi aritmetiğini/yönünü İCAT EDEMEZ,
  yalnızca var olan davranışa yeni bir isim/liste sırası/teslim-alan-zorunluluğu
  tanımlar.
- Kullanılmış bir kategori **asla hard-delete edilmez**, yalnızca pasifleştirilir
  (`is_active=false`) — silme route'u yoktur. Pasif kategori YENİ hareket için
  seçilemez ama GEÇMİŞ hareketlerde görünmeye devam eder.
- Kategori adı düzenlenebilir olduğu için `CashMovement.category_label`
  yazıldığı ANDAKİ adın bir ANLIK GÖRÜNTÜsünü taşır (§5.8) — sonradan yeniden
  adlandırma geçmişi DEĞİŞTİRMEZ.
- Varsayılan 5 kategori (Oda Geliri/Ek Gelir/Gider/Kasa Girişi/Kasadan Teslim)
  idempotent bir seeder (`CashMovementCategorySeeder`) ile oluşturulur.

> **EN KRİTİK KURAL:** ara kasa/patron teslimi **GİDER DEĞİLDİR**. Kasada
> 14.000 TRY varken patrona 12.000 teslim edilirse fiziksel kasa 2.000'e düşer
> ama gelir-gider raporunda 12.000'lik bir gider OLUŞMAZ. Ayrım tek yerde
> tanımlıdır: `CashMovementType::affectsIncomeStatement()`.

**Beklenen kasa** para birimi bazında hesaplanır ve para birimleri ASLA
birleştirilmez (§6/L5):
`açılış + oda geliri + ek gelir + kasa girişi + cari tahsilat − gider − kasa çıkışı`.
Hesap SQL'de değil serviste, tamsayı kuruş üzerinden yapılır.

**Değişmezlik:** kasa hareketi güncellenemez ve silinemez — ne hard delete ne
soft delete, ne de route. Düzeltme **ters harekettir**: aynı tip, aynı tutar,
ters yön, `is_reversal = true`. Böylece hem beklenen kasa hem gelir-gider
raporu kendiliğinden düzelir. **Açılış bakiyesi ters kaydedilemez** (devredilen
tutarla bağı kopardı); hatalı açılış kapanış sayımındaki FARK olarak görünür.
Bir kaynak kayda bağlı hareket (`reference_type` dolu) genel Kasa Hareketleri
ekranından ters kaydedilemez — düzeltme kaynak kaydın kendi akışından yapılır
(yukarı bakınız, §8/Payments "Kanal davranışı").

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

> **P0 GÜVENLİK KURALI (v2.1.1-A):** kapanış sayımı ve devir formlarındaki
> tutar alanı **ASLA sistemin beklenen tutarıyla ÖN-DOLDURULMAZ** —
> `_amount-fields.blade.php` boş gelir, beklenen tutar yalnızca REFERANS
> olarak ipucu metninde ("Beklenen: …") görünür. Aksi hâlde personel gerçekte
> saymadan formu olduğu gibi gönderebilir ve fiziksel sayım kontrolü sessizce
> etkisiz kalırdı — bu, canlı kullanımda tespit edilen bir denetim bulgusuydu.

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
`Auditable` trait'i ile `Company`, `User` ve `Room` modellerinde `created/updated/deleted` olayları;
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
- Arama: firma adı, vergi no, telefon, e-posta, yetkili, şehir üzerinde `LIKE`. Fulltext V1'de yok.
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
  `foldTurkishI()` ve `turkishFoldColumnExpression()` **protected**'tır: bir alt sınıf
  aramaya ilişkili bir tablonun kolonunu (`whereHas` içinde — ör. konaklama aramasında
  firma adı, devir aramasında personel adı) aynı Türkçe katlama mantığıyla ekleyebilir;
  SQL metni kopyalanmaz (`StayFilter`, `CashTransferFilter` örnekleri).
- Tüm liste sorguları `paginate()` kullanır — `get()` ile tüm tablo çekilmez.

**Aktif filtre göstergesi ve "boş sonuç" ayrımı (Gün 12):** `QueryFilter::hasActiveFilters()`/
`activeFilterCount()` sayfalama/sıralama DIŞINDAKİ herhangi bir query param'ın dolu olup
olmadığına bakar — modül başına ayrı bir uygulama GEREKMEZ. `<x-ui.empty :filtered="..."
:clear-href="...">` bu bilgiyi kullanarak "hiç kayıt yok" ile "bu filtrelerle eşleşen kayıt
yok" ekranlarını ayırır ve ikincisine "Filtreleri temizle" bağlantısı ekler.

**Filtre UI deseni bilerek TEK'tir — ayrı bir "drawer" bileşeni AÇILMADI.**
Tüm liste ekranları (Firmalar, Konaklamalar, Tahsilatlar, Cari Ekstre + ekstre detayı,
Kasa Hareketleri, Devir Geçmişi, Denetim Kaydı) aynı `<x-ui.filter-panel>` bileşenini
kullanır: mobilde alanlar üst üste dökülür, masaüstünde yan yana sıralanır. Ayrı bir
mobil çekmece kasıtlı olarak eklenmedi (tutarlılık + gereksiz soyutlama yasağı, §11.3).

**Filtre paneli ÇÖKERTİLEBİLİRDİR ve VARSAYILAN OLARAK KAPALIDIR (v2.1.1-E, K-106).**
Gün 12'nin hep-açık grid formu, kullanıcı talimatıyla bir `<details>` kabuğuna alındı —
kapalıyken tek satır (`min-h-11`: filtre ikonu + "Filtreler" + varsa "N aktif" rozeti +
dönen ok) yer kaplar, açıldığında AYNI `grid gap-3 sm:grid-cols-2 lg:grid-cols-4` düzeni
görünür. Alan yerleşimi, filtre parametreleri ve sorgu mantığı DEĞİŞMEDİ.

- **Açık/kapalı kararı SUNUCUDA verilir:** aktif filtre varsa panel `open` başlar
  (aksi hâlde kullanıcı neyin filtrelendiğini göremez ve "Temizle"ye ulaşamazdı).
  Kenar çubuğu gruplarıyla (§11.4, K-105) AYNI desen: native `<details>/<summary>`,
  ayrı bir JS durum makinesi YOKTUR (Alpine.js kurulu DEĞİLDİR, §11.7).
- Gönder/Temizle butonları ve "Sayfa başına" seçici bileşenin kendi alt satırındadır;
  her ekranda kopyalanmaz (§4.2/4).
- `hidden` slotu filtre dışı query param'ı taşır (Firmalar'daki `trashed=1`).
  `:grid="false"` gövdeyi ham render eder — Cari Ekstre DETAYI bunu kullanır, çünkü
  orada alanlar iki ANLAMLI gruba ayrılır ("bakiyenin kapsamı" / "yalnızca görünümü
  daraltır") ve düz bir grid bu ayrımı yok ederdi.
- **İki form bilerek dışarıda:** `users.index`'teki tek kutuluk arama ve `cashbook.day`'deki
  tarih gezinme formu ZATEN tek satırdır; kabuğa almak yer kazandırmaz, fazladan tıklama ekler.

`QueryFilter::countActiveFilters(Request)` **statiktir**: `QueryFilter` alt sınıfı
KULLANMAYAN ekranlar (Denetim Kaydı, cari ekstre detayı) da rozeti aynı kuralla doldurur;
mantık kopyalanmaz.

**Kasa Hareketleri / Devir Geçmişi (Gün 12) — kasa defterinin en önemli filtre alanı.**
`cash/movements` ve `cash/transfers` çapraz-vardiya, sayfalanan, filtrelenen listelerdir
(`CashMovementFilter`/`CashTransferFilter`); `cashbook.index` (tek açık vardiya) ve
`cashbook.day` (tek gün) bunların YERİNE geçmez, tarih/personel/tür bazında GEÇMİŞE
dönük arama ihtiyacını onlar karşılayamaz. **Yetki kapsaması filtrelemeden ÖNCE
uygulanır:** `cashbook.view_all` yoksa temel sorgu `created_by = kullanıcı` (hareketler)
veya `from_user_id/to_user_id = kullanıcı` (devirler) ile SINIRLANIR, filtre parametreleri
bu kapsamın **içinde** ek bir AND'dir — personel `user_id` param'ını başka bir personelin
kimliğine değiştirerek erişimi genişletemez, en kötü ihtimalle sonuç boş döner.
Hızlı filtreler (Bugün/Giderler/Gelirler/Ara Kasa/Benim Vardiyam) YENİ bir sorgu
mantığı taşımaz — aynı `date_from`/`type`/`user_id` parametrelerine giden sade
bağlantılardır; "Gelirler" ikisini birden döndürür (`type[]=room_income&type[]=extra_income`),
bu yüzden `type` filtresi hem tekil hem dizi değer kabul eder.

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

**Pano renk istisnası (23 Eylül 2026, kullanıcı onaylı):** `--color-tint-{amber,
green,blue,purple,rose}-{bg,line,icon,tx}` ve `--color-chart-{stay,payment,net}`
token'ları YALNIZCA Finans panosunun KPI kartlarında, Cari Özeti/Son Güncellemeler
ikonlarında ve aylık grafikte kullanılır. Uygulamanın geri kalanı nötr zemin +
altın vurgu kuralına bağlıdır. Tint zeminlerde `text-mut` yazılmaz (blue/purple/
rose'da 4.5 altı) — ipucu metni `text-tx`. Gölge yine yoktur. Grafik renkleri
renk körlüğü doğrulayıcısından geçmiştir; turuncu net çizgi beyazda 3:1 altında
kaldığı için gösterge ve "Tablo olarak göster" her zaman vardır.

Tipografi: `system-ui` yığını, gövde 15px/1.5, form kontrolleri **16px** (mobil zoom'u
engeller), sayfa başlığı 19px/500. Tüm tutar hücreleri `font-variant-numeric: tabular-nums`
ve sağa hizalı.

### 11.3 Blade bileşenleri

Ortak bileşenler `resources/views/components/ui/` altındadır ve `<x-ui.*>` ile çağrılır:
`card`, `page-header`, `button`, `input`, `select`, `textarea`, `checkbox`, `badge`,
`alert`, `errors`, `empty`, `table`, `sort-header`, `th`, `stat`, `panel`, `fx-note`,
`filter-panel`, `kpi-card`, `tabs`, `icon`, `today`, `row-menu`.

`kpi-card` (23 Eylül 2026) renkli pano özet kartıdır; tutarın punto boyu kartın
genişliğine göre container query ile seçilir, rakam ortadan bölünmez. `tabs`
bağlantı tabanlı sekmedir (`underline` / `pill`), JS kullanmaz, etkin sekme
`aria-current="page"` taşır.

`filter-panel` (v2.1.1-E) liste ekranlarının çökertilebilir filtre kabuğudur — davranışı
ve bağlayıcı kuralları §9'dadır.

`fx-note` (v2.1.1-C, v2.1.1-D'de genişletildi) yabancı para birimli bir
tutarın altında küçük/soluk TL karşılığını gösterir; kur kayıtlı DEĞİLSE hiç
render edilmez (tabloyu kalabalıklaştırmaz). İKİ MODU vardır: `:rate` DE
verilen TEK işlem modu ("≈ … TL · Kur: …") ve yalnızca `:equivalent` verilen
TOPLAM/bakiye modu ("≈ … TL", kur satırı olmadan — birden çok işlemin NETİ
olduğu için TEK bir kur yoktur). **Bileşenin kendi üst açıklama yorumu
İÇİNE ikinci bir `{{-- --}}` yorumu AÇILMAMALIDIR** — Blade yorumları iç
içe geçmez, ilk `--}}`'de kapanır ve dosyanın gerçek kapanışı HTML'e düz
metin olarak sızar (K-105'te tam olarak bu yaşandı ve gerçek tarayıcı
QA'sında yakalandı).

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
- Menü **gruplu**dur: başlıksız ana grup ("Ana Sayfa"), "Kasa", "Cari",
  "Yönetim". İçinde görünür öğe kalmayan grup hiç çizilmez.
- **Etiketli gruplar (Kasa/Cari/Yönetim) native `<details>`/`<summary>` ile
  çökertilebilir/genişletilebilirdir** (v2.1.1-D §2 — Alpine.js kurulu
  DEĞİLDİR, §11.7): koyu (`bg-nav-well`) başlık satırı tıklanınca alt
  öğeleri açar/kapatır. **Varsayılan KAPALIDIR**; yalnızca aktif sayfanın
  bulunduğu grup sunucu tarafında `open` hesaplanır
  (`$groupActive = collect($group['items'])->contains(...)`) ve o grup
  başka bir gruba geçilene kadar açık kalır. Aktif öğe deseni
  `sidebar.blade.php`'de MERKEZİLEŞTİRİLMİŞTİR — her rotanın KENDİ,
  ÇAKIŞMAYAN bir `routeIs()` deseni vardır (bir kardeş rotanın deseniyle
  ASLA örtüşmez; K-105'te tam olarak bu örtüşme bir bug'a yol açmıştı).
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
- Kenar çubuğunu açan tetikleyici (yalnızca alt çubuktaki "Daha Fazla" —
  K-103'te üst çubuktaki hamburger KALDIRILDI, aşağıya bakınız)
  `[data-sidebar-toggle]` ile işaretlidir; `resources/js/app.js`'teki `drawer`
  nesnesi bunu (ve gelecekte eklenebilecek başka tetikleyicileri) genel olarak
  yönetir (`aria-expanded`, odak geri dönüşü) — sabit bir tetikleyici sayısına
  bağlı DEĞİLDİR.
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

> **Mobil üst çubuk temizliği (K-103):** hamburger ve "+" (Hızlı İşlem)
> KALDIRILDI — birincil gezinme alt çubuktur, "Daha Fazla" ikincil gezinmeyi
> zaten karşılar; üstteki hamburger TEKRARDI. "+" de kaldırıldı: her
> kısayolun kendi index sayfasında zaten aynı "oluştur" butonu var (ör.
> Konaklamalar → "Yeni konaklama"), bu yüzden hiçbir yetenek KAYBOLMADI.
> Mobil üst çubuk artık yalnızca marka taşır (`h-12`, önceki `h-14` +
> butonlardan belirgin şekilde daha kısa/hafif) — `partials/pwa-head.blade.
> php` ile PWA meta'larını da taşır. Masaüstü Lemon Bar'a (aşağıda)
> DOKUNULMADI.

**Lemon Bar (Gün 11):** masaüstünde `md:flex` sabit bir üst çubuktur; mobil
üst çubuk artık ayrıdır (yukarı bakınız, K-103) ve Hızlı İşlem'i TAŞIMAZ.
Kenar çubuğu marka/kullanıcı kimliğini zaten taşıdığı için Lemon Bar bunları
TEKRARLAMAZ — yalnızca bağlam (pano sekmesi), Hızlı İşlem ve kritik durum
metni taşır. İçerik `@yield`/`@section`
ile sayfa tarafından doldurulur; doldurulmazsa çubuk sessizce boş kalır (yeni bir
sorgu tetiklenmez). Hızlı İşlem (`partials/quick-action.blade.php`) `[data-quick-action]`
deseniyle `drawer` nesnesindeki AYNI desende yazılmıştır (sayfada birden fazla
tetikleyici olabilir — mobil + masaüstü aynı anda DOM'dadır, yalnızca biri görünür);
ESC, dışarı tıklama ve `aria-expanded` ile klavye erişilebilirdir.

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

`resources/js/app.js` sade JavaScript'tir, harici paket yoktur. Üç çekirdek
davranışı: kenar çubuğu çekmecesi, **çift gönderim koruması** ve konaklama
formunun ücret önizlemesi. Çökertilebilir yüzeyler (kenar çubuğu grupları,
liste filtre paneli) JS KULLANMAZ — native `<details>/<summary>`dir.

**Alpine.js kurulu DEĞİLDİR** ve bu bilinçlidir; ihtiyaç doğarsa (modal, tutar
maskesi, dinamik firma arama) kullanıcı onayıyla eklenir. JS ile iş mantığı
yazılmaz, veri hesaplanmaz.

**Dinamik firma arama istisnası devreye girdi (12 Eylül 2026, kullanıcı
talimatı):** konaklama formundaki `company_id` seçimi artık yazarak aranabilir
(`initSearchableSelects()`) — ama Alpine.js EKLENMEDİ, native `<select>`in
üzerine yazan bağımsız bir vanilla JS combobox ile yapıldı. Native `<select>`
DOM'dan kaldırılmaz (yalnızca `hidden` olur) ve form değeri HER ZAMAN ondan
okunur/yazılır; JS çalışmazsa normal açılır liste olarak kalır (progresif
geliştirme). Boş değerli seçenek ("Bireysel konaklama — firma yok") kutuda
metin olarak GÖSTERİLMEZ — kutu boş başlar (27 Eylül 2026, kullanıcı
talimatı); odaklanınca tüm seçenekler listelenir, kutu silinirse seçim de
boşa döner.

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

### PWA (temel) — K-103

- `public/manifest.json` — statik dosyadır (Blade/route ile üretilmez): `name`/
  `short_name` "Lemon", `display: standalone`, `start_url`/`scope` `/`,
  `theme_color`/`background_color` mevcut tasarım token'larıyla AYNI
  (`#172033`/`#F7F7F5`), 192/512 "any" + 192/512 "maskable" ikon seti.
  İkonlar (`public/icons/*.png`) `partials/brand.blade.php`'nin monogram
  fallback'ıyla AYNI görsel dili kullanır (altın zemin, koyu "L").
- `public/sw.js`, site kökünden servis edilir. Kapsam bilerek DAR
  tutulur: **yalnızca statik app-shell** (Vite `build/` çıktısı — içerik-
  hash'li olduğu için cache-first güvenlidir —, ikonlar, manifest,
  `offline.html`) cache'lenir; kimlik doğrulanmış/finansal sayfa
  navigasyonu HER ZAMAN ağdan istenir ve ASLA cache'lenmez, yalnızca ağ
  tamamen başarısız olursa statik `public/offline.html`'e düşülür.
  **GET dışındaki hiçbir istek (POST/PUT/PATCH/DELETE) yakalanmaz** —
  çevrimdışı finansal mutasyon kuyruğa alma/tekrar oynatma KESİNLİKLE
  yoktur. Sürüm damgalı cache adı (`CACHE_VERSION`), yeni sürümde eski
  cache `activate` olayında silinir.
- Kurulum eylemi kalıcı bir pano kartı DEĞİLDİR — "Daha Fazla" çekmecesinde
  (`[data-pwa-install]`, `md:hidden`) ikincil bir eylemdir, yalnızca
  `beforeinstallprompt` gerçekten ateşlenirse veya uygulama ZATEN
  standalone modda kuruluysa/DEĞİLSE görünür. iOS/iPadOS Safari bu olayı
  hiç ateşlemez — orada bağlamsal bir "Paylaş → Ana Ekrana Ekle" ipucu
  gösterilir (`[data-pwa-ios-hint]`). Masaüstü kenar çubuğu bu blok
  `md:hidden` olduğu için ETKİLENMEZ.
- `viewport-fit=cover` (her iki layout) olmadan `env(safe-area-inset-*)`
  sıfır döner — standalone modda cihazın durum çubuğu/ev göstergesi alanı
  telafi edilmezdi. `.pwa-safe-top` (mobil üst çubuk) yalnızca
  `@media (display-mode: standalone)` içinde üst boşluk ekler; normal
  tarayıcı sekmesinde tarayıcının kendi arayüzü bu alanı zaten ayırdığı
  için gereksiz büyümez.

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
| 11 | **Dashboard V2 + Lemon Bar** — role duyarlı Operasyon/Finans ayrımı, personel "Vardiyam/Kasam", global Hızlı İşlem | Personel finans/kasa sorgusu koşturmaz; Operasyon/Finans birbirinin sorgusunu koşturmaz |
| 11.1 | PWA + denetim kaydı ekranının cilası | Ana ekrana eklenebiliyor |
| 12 | **Arama + Filtre + Operasyon Hızı** — Kasa Hareketleri/Devir Geçmişi listeleri, cari ekstre bakiye filtresi/sıralaması, hızlı filtreler | Personel filtre param'ıyla erişimi genişletemez; filtreli ekstre bakiyesi bozulmaz |
| 12.1 | Mevcut Excel geçmişinin aktarımı (§14.1) | Ledger bakiyesi, Excel KALAN BAKİYE ile mutabık |
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
- Workflow: `.github/workflows/deploy.yml` — `main` dalına push OTOMATİK canlı
  yayın yapar (13 Eylül 2026, kullanıcı talimatı); elle tetiklemenin varsayılanı
  hâlâ `dry-run`'dır ve `DEPLOY_ENABLED` variable'ı olmadan hiçbir yol gerçek
  aktarım yapamaz.

`develop` → Lemon (kabul/staging), `main` → production; ayrı hedefler, ayrı veritabanı.

**`main` için OTOMATİK push deployment ETKİNDİR (13 Eylül 2026 — kullanıcı
talimatıyla değiştirildi; önceki kural "hiçbir dal için etkin değildir"di).**
Gerekçe: kullanıcı her yayın için elle workflow tetikleyip onay metni yazmaktan
ve sunucuda elle komut koşturmaktan yoruldu; bu turda "canlıya al = main'e
push" akışı istendi. Kural artık şudur:

- `main`e push → derleme (vendor + Vite) → FTPS aktarımı → finalize işareti →
  cPanel cron'u `deploy/finalize.php` ile migrate/seed/cache adımlarını en geç
  1 dakika içinde koşturur. Elle hiçbir adım yoktur.
- **Hedef kilitleri GEVŞETİLMEDİ:** `DEPLOY_ENABLED=true` ve
  `LEMON_APP_DIR == /lemon-app/` her iki yolda da aranır (hesapta başka canlı
  siteler vardır). Otomatik yolda ARANMAYAN tek şey `confirm_target`'tır —
  o bir İNSAN onayıdır ve otomatik akışta yazacak insan yoktur.
- Yalnızca sunucuya aktarılan dosyalar tetikler: tek başına `docs/`, `tests/`,
  `.claude/` veya `*.md` değişen bir push aktarım yapmaz (`paths-ignore`).
- `develop` için otomatik deployment HÂLÂ YOKTUR.
- Aktarımdan sonra workflow, işaret dosyasının silinmesini FTPS ile izleyerek
  finalize'ın gerçekten bittiğini raporlar — sunucu günlüğüne bakmak
  gerekmez. **Yeni bir HTTP ucu AÇILMADI**: internete açık migrate/deploy ucu
  yasağı (aşağıdaki kural) aynen geçerlidir.

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
- Deploy sonrası, bu SIRAYLA (bağlayıcı, K-099/K-102 — tam kontrol listesi
  `docs/release-checklist-v2.1.1.md`):
  `config:clear route:clear view:clear event:clear` — eşdeğeri olarak tek
  komut `php artisan optimize:clear` de kullanılabilir (Laravel'in bu dört
  `:clear` komutunu TEK ÇAĞRIDA topladığı kısayolu; K-104'te kullanıcı
  talimatıyla teyit edildi) (bayat cache temizliği,
  **MIGRATE'TEN ÖNCE** — K-102: önceki deploy'dan kalan bayat
  `bootstrap/cache/config.php`, bu deploy'da yeni eklenen bir modülün
  Service Provider'ını taşımıyorsa `migrate` o modülün migration'larını hiç
  GÖRMEZ, sessizce atlar — gerçek üretim olayı) →
  `php artisan migrate --force` →
  **`php artisan db:seed --class="App\Modules\Authorization\Database\Seeders\RolePermissionSeeder" --force`** (HER
  deploy'da, migrate'ten hemen sonra, ATLANMAZ — koddaki yeni bir
  `Permission` case'i bu adım koşulmadan production'da "no permission
  named X for guard web" hatası üretir, gerçek üretim olayı) →
  `config:cache route:cache view:cache event:cache`.
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
- **Her iş (yayına gidecek değişiklik paketi) sonunda `config/release.php` içindeki `version` değerini 1 artır** (7 Kasım 2026, kullanıcı talimatı): kenar çubuğunda "Cari Takip · vN" olarak görünür ve canlı sitenin güncel olup olmadığını anlamanın tek yoludur.

**Bu dosya güncel tutulur.** Şema, rol matrisi veya kapsam değişirse aynı commit'te CLAUDE.md da güncellenir.
