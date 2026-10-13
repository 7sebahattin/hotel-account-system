# Tek seferlik CARİ temizleme ekranının kaldırılması — 13 Ekim 2026

REPO: 7sebahattin/hotel-account-system
START_MAIN: 97605cf448cb3939d616d36ae3d9b28885a0603a
BRANCH: codex/retire-cari-cleanup
VERSION: 18 → 19, tek artış.

## Kullanıcı kararı ve kapsam

Kullanıcı manuel temizliğin tamamlandığını bildirdi: “tamam silindi sayfayı
kaldırabilirsin”. Bu paket geçici ekranı ve silme yeteneğini emekli eder.
Kullanıcı bildirimi, bu ajan tarafından production satırlarının tekrar okunup
mutabakatının yapıldığı anlamına gelmez. Yeni bir temizlik/silme çalıştırılmaz.

- CariCleanup controller, request, policy, provider, route, view ve iki servis
  kaldırılır. Provider kaydı ve sidebar bağlantısı/active pattern silinir.
- Eski GET ve POST /admin/cari-temizleme, /on-kontrol ve /sil uçları yoktur;
  aktif Admin dahil URL'ler 404 döner.
- Şirket kartları, HESAP, PERSONEL, audit, eski kasa ve tüm mevcut iş verileri
  değiştirilmez. Kimlik sıraları sıfırlanmaz.
- cari_cleanup_operations ve tamamlanma/aktör/sayı/checksum raporu aynen kalır;
  state UPDATE/DELETE korumaları korunur. Tamamlanmış işlem yeniden açılamaz.
- Normal finansal UPDATE/DELETE yasağı tekrar koşulsuzdur. Pano/defter netini
  tekrar kullanma optimizasyonu kalır; iş kuralları veya para hesabı değişmez.

## Migration geçmişi ve ileri koruma geçişi

2026_10_12_100000_add_one_time_cari_cleanup_capability.php modül dizininden
database/migrations'a aynı basename ve içerikle taşındı. Git içerik hash'i
bfde21e80daab094f08e3421909cac88099d2e0b olarak korundu. Laravel migration
geçmişi basename ile eşleştiğinden mevcut production'da eski işlem tekrar
çalışmaz. Temiz kurulumlar tarihsel migration'ı, ardından yeni emeklilik
migration'ını çalıştırır; final durumda silme capability'si yoktur.

2026_10_13_100000_retire_one_time_cari_cleanup.php yalnız iki finansal DELETE
trigger'ını eski koşulsuz SIGNAL SQLSTATE 45000 gövdelerine döndürür.
Tablo/kolon/rapor DROP, satır UPDATE/DELETE/INSERT, FK kapatma veya TRUNCATE yoktur.
Laravel'in kendi migration geçmişine normal yeni sürüm kaydı eklenmesi ayrıdır.

DDL implicit commit yaptığı için bir transaction'ın DDL'yi geri alacağı
varsayılmaz. Aynı lemon_cari_cleanup advisory lock'u tutulur; önce iki tabloda
bağımsız koşulsuz engeller kurulur/doğrulanır. Ancak sonra asıl trigger'lar
değiştirilir. Her ikisi tamamlanmadan geçici engeller kaldırılmaz.
Ara hata kapalı durumda kalır; yeniden deneme korumalar altında idempotenttir.
İşlem ready/running/completed olsa da satıra dokunmadan emekli edilebilir.
down() açıklamalı NO-OP'tur: hiçbir DDL/DML çalıştırmaz, eski capability'yi
geri açmaz ve koşulsuz korumaları yerinde bırakır. Böylece mevcut konaklama
eşzamanlılık testinin DatabaseMigrations yaşam döngüsü korunur; test veya
koruma eşiği değiştirilmez. Eski migration'ın down() yolu completed arşivini
assertReady ile korumaya devam eder.

İki finansal UPDATE trigger'ı ve bütün diğer modül/state trigger'ları değişmez.
Çalışan eski temizleme, ortak advisory lock nedeniyle emeklilik migration'ıyla
yarışamaz. Normal deploy bakım/finalize akışı source/cache/provider geçişini
tamamlar; eski provider dosyasına bağımlı önbellek kalmamalıdır.

## Doğrulama

- 8 retirement testi / 307 doğrulama, PHP 8.3.33 ve ayrı MariaDB 11.4.8:
  eski route/method'lar 404, route isimleri/menü yok, normal panel çalışıyor,
  tüm aktif şemanın satır snapshot'ları aynı, normal UPDATE/DELETE45000.
- Doğru eski connection/nonce/hash/expiry/aktif Admin capability'si kurulsa bile
  finansal DELETE reddedilir. Completed raporu ve singleton açılamaz/silinemez.
- Koşulsuz trigger gövdeleri metadata'dan doğrulandı. Testler DDL veya şema
  reseti yapmaz; transaction ile geri alınan temsilî fixture kullanır.
- Ayrı hotel_account_cleanup_smoke veritabanında gerçek completed raporu
  yanında sonradan eklenmiş finansal kayıtlarla dış transaction olmadan DDL
  provası yapıldı. İlk asıl trigger DROP'undan sonra hata enjekte edildi:
  iki guard yerinde kaldı, nonempty finansal DELETE45000 aldı.
  Recovery ve ikinci up geçti; 49 tablonun bütün satır içerikleri, rapor,
  UPDATE/state trigger'ları aynı kaldı. down sonrası bütün satır/trigger'lar
  aynı kaldı; silme istisnası açılmadı.
- Eski migration'ın yeni path'te aynı içerik/hash ile bulunduğu ve migrate
  status'ta tekrar çalışmadığı doğrulandı.
- Runtime app/bootstrap/config/resources/routes içinde CariCleanup veya eski
  temizleme URL/route bağımlılığı kalmadı.

Build, JS, Pint/PHPStan, tam test, CI ve deploy sonuçları final raporda belirtilir.
Önceden main üzerinde de yeniden üretilen Windows finalize.php rename
erişim hatası ve mevcut StagedReleaseTest uyarısı ayrı değerlendirilir;
Linux/MySQL CI yeşil olmadan merge yapılmaz.

Bu paket production'da tamamlanma raporunu indirecek yeni UI/API eklemez.
Arşiv kanıtı mevcut veritabanında korunur. Yeni bir temizlik ancak yeni açık
kapsam ve güvenlik incelemesiyle düşünülebilir; bu paket yeniden açma yolu sunmaz.
