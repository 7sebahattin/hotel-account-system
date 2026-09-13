# Kasa modülünün kaldırılması

Kullanıcı kasa kaldırmayı Codex'e, yerine gelecek sistemi Claude'a verdi.
Bu çalışma `codex/kasa-kaldirma` dalında, canlıya alınmış `dcb771d` tabanındadır.
Yeni bir kasa/gelir-gider sistemi eklenmez. Bu dal henüz canlıya yayınlanmadı.

## Son davranış

- Kasa defteri, hareketler, kategoriler, günlük defter, vardiya açma/kapama,
  devir, sayım, kasa penceresi ve Operasyon panosu kaldırılır. Eski kasa
  adresleri admin dahil 404 verir; yalnızca menü gizleme yapılmaz.
- Mobil menüde Kasa yerine Konaklamalar vardır. Personel kendi kayıt adetlerini
  ve son kayıtlarını görür. Admin/patron cari panoyu görür. Eski
  `?view=operations` bağlantısı mevcut rol kontrolünden sonra bu panoyu açar.
- Nakit, banka, kart, çek ve diğer tahsilat kanalları korunur. Nakit tahsilat
  için açık vardiya veya vardiya sahipliği gerekmez. Yeni `cash_session_id`
  değeri NULL'dır; istekle gönderilen vardiya kimliği kabul edilmez.
- Tahsilat oluşturma ve iptal; cari defter, ödeme dağılımı ve denetim kaydını
  aynı transaction içinde yönetmeye devam eder. Makbuz numarası korunur.
- Eski kasaya bağlı tahsilatın iptali cari borcu geri getirir ve ödeme
  dağılımını çözer. Eski kasa hareketini değiştirmez veya ters hareket eklemez.
  Kasa artık güncel muhasebe kaynağı değildir: kaldırılma anındaki geçmişi
  temsil eder. Yeni sistem eski kasa toplamını güncel bakiye diye kullanmamalıdır.
- Bireysel/firma hesapları, konaklamalar, cari ekstre ve bakiye hesabı,
  manuel kur bilgisi, makbuz defteri, odalar, yetki sınırları ve filtreler korunur.

## Saklanan geçmiş

Kasa tabloları, mevcut kayıtları, FK ve koruyucu trigger'ları silinmez.
`payments.cash_session_id` eski kayıtta değişmez kalır. Kasa migration dosyaları
aynı ad ve içerikle saklanır; AppServiceProvider yalnız bu tarihsel migration
dizinini yükler. CashBook altında çalışır servis, model, controller, route,
provider, view veya fabrika kalmaz. Yeni kurulumlar da aynı migration zincirini
çalıştırabilir; bu nedenle tarihsel tablolar yeni test veritabanında da oluşur.

Denetim kaydı geçmiş konu türlerini metin/kimlik olarak göstermeye devam eder;
silinmiş kasa modelini çözmeye çalışmaz. Geçmiş denetim satırları değiştirilmez.
Arşiv tabloları uygulama tarafından okunmaz/yazılmaz; bu bir dış yedek veya
veritabanı yöneticisine karşı yazma kilidi değildir. İleride fiziksel tablo
silme ayrı veri koruma ve bağımlılık çalışması gerektirir.

`RolePermissionSeeder` dört eski `cashbook.*` yetkisini, özel rol ve doğrudan
kullanıcı atamaları dahil kaldırır. Diğer özel rol yetkileri ve kullanıcı rol
üyelikleri korunur. Sistem rol matrisi mevcut seeder davranışıyla senkronlanır.
Admin Gate kısayolu da `cashbook.*` yetkilerine erişim vermez.

## Yayına geçiş

Bu görevde canlıya aktarım yapılmaz. Yeni tablo/kolon migration'ı yoktur.
Claude'un yeni otomatik yayın akışıyla birleştirildi. Normal yayın sırasında
elle artisan komutu veya geçici cron kurulmaz:

1. Güncel veritabanı/dosya yedeği ve cari bakiyeler, kayıt sayıları, tarihsel
   kasa sayıları karşılaştırma için saklanır.
2. Test edilmiş dal için yayın kararı verildiğinde main'e alınır. CI geçmeden
   aktarım başlamaz. Workflow önce bakım modunu açar, dosyaları gönderir ve
   finalize işaretini bırakır.
3. Kalıcı finalize cron'u kernel başlamadan eski cache'i temizler;
   optimize:clear → migrate → tam sınıf adıyla RolePermissionSeeder → cache
   sırasını çalıştırır. Sonra kendi bakımını kapatıp başarı kaydını yazar.
4. Actions aynı commit/run/attempt başarı kaydını doğrular. Zaman aşımı ve
   FTP hataları başarılı yayın sayılmaz. Kalıcı finalize cron'u silinmez.
5. Cari bakiyeler, kayıt sayıları ve kasa arşivi değişmeden kaldı mı kontrol
   edilir. Admin/patron/personel ekranları gerçek para hareketi yaratmadan
   kontrol edilir. İlk gerçek nakit tahsilat yetkili kullanıcıyla izlenir.

Hata ve kurtarma ayrıntıları: `docs/claude-karsilastirma-2026-09-13.md`.

Yeni sürümde işlem girildikten sonra eski dosyalara dönmek güvenli değildir:
eski sürüm nakit tahsilatları kasaya yazılmış varsayar. Geri dönüş gerekirse
kayıt girişini durdurup farkları incele; ileri düzeltme veya doğrulanmış tam
geri yükleme planı kullan. Kör rollback/migrate:fresh yapılmaz.

## Claude'a devir

- Önce bu belge ve CLAUDE.md kapsam değişikliğini oku.
- Çalışma dalı `codex/kasa-kaldirma`; kendi dalında ilerle. Bu worktree'yi
  değiştirme. Birleştirmeden önce güncel main ile farkları gözden geçir.
- Özellikle PaymentService, PaymentController/Payment modeli, Dashboard,
  menüler, RolePermissionSeeder ve Permission/RoleName ortak değişim alanıdır.
- Yeni sistem nakit tahsilatı tekrar kasa/shift zorunluluğuna bağlamamalıdır.
  Cari bakiyenin tek kaynağı LedgerService'dir. Yeni sistem entegrasyonunu
  kullanıcı kararı olmadan bu dala ekleme.
- Eski kasa migration'larını veya korunmuş vardiya kolonunu silme; tarihsel
  kasa satırlarını yeni sistemin açılış bakiyesi sayma.
- Yayın yapılmadan önce birleştirilmiş son commit için tam test, Pint,
  PHPStan ve frontend build yeniden doğrulansın.

## Doğrulama

Emekli kasa işlevlerinin testleri çıkarıldı; aynı davranışın artık var olmadığını
ve cari akışın bağımsızlığını sınayan `CashBookRemovalTest` eklendi. Mevcut
cari/konaklama/dağılım testleri korunur; yalnız kasa zorunluluğu kuran hazırlıklar
ve eski arayüz beklentileri yeni kapsamla değiştirildi.

Yerel MariaDB doğrulaması:

- Tam paket: 648 test, 647 başarılı; tek hata patron yetki sayısını eski
  kasa yetkileri dahil 24 bekleyen testti. Beklenti yeni matristeki 20'ye
  düzeltildi; diğer hakların varlığı/yokluğu kontrolleri korundu.
- Bu düzeltmeden sonra rol matrisi ve kasa kaldırma regresyonları: 19 test,
  291 doğrulama, tamamı başarılı. Sonradan eklenen dövizli şahsi nakit ödeme
  ve eski kasa denetim kaydı okuma kontrolleri de bu koşuda yer aldı.
- Pint başarılı, PHPStan level 5: sıfır hata, Vite build başarılı.
- İlk kasa kaldırma sürümünde eski kasa migration'ları ve Ledger, StayService,
  CompanyService dosyaları taban sürümle aynıydı; şema geçmişi ve cari bakiye algoritması değiştirilmedi.
- Yeni arayüz HTTP/view testleriyle kontrol edildi; bu dalda ayrıca gerçek
  tarayıcı/mobil görsel kabulü yapılmış sayılmamalıdır.

Claude değişiklikleri 6d31e06 dahil karşılaştırılarak alındı. StayService'e
Claude'un toplam ve kalıcı silme işlevleri geldi; entegrasyonda silme kontrolü
sıkılaştırıldı. Ayrıntı ve son doğrulama: `docs/claude-karsilastirma-2026-09-13.md`.
