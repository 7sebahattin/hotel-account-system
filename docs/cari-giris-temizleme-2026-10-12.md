# CARİ veri girişlerinin tek seferlik temizlenmesi — 12 Ekim 2026

REPO: 7sebahattin/hotel-account-system
START_MAIN: e49a2f7bdbd80f64ea053955c237f52f38e6097a
BRANCH: codex/cari-entry-cleanup
VERSION: 17 → 18 (tek artış)

## Yeni açık kullanıcı kararı

v17 yalnız engelli ön kontrol ekranıydı. Kullanıcı bunun istediği sonuç olmadığını
belirtti; seçiminde “Cari hesap kartları kalsın; konaklama, teklif, tahsilat,
makbuz ve bağlı cari hareketleri silinsin” dedi. Finansal fiziksel silme yasağına
yalnız bu tek seferlik işlem için istisna sorusunu “tamam değiştir ve girilen
veriyi silelim” diyerek onayladı. Önceki bloke kararı bu dar kapsam için değişti.
Bu onay başka finansal/modül korumalarını veya genel reseti kapsamaz.

Canlı silme Codex/CI/migration/deploy tarafından otomatik yapılmaz. Son işlem
aktif Admin'in yönetim sayfasında ön kontrol, parola, açık ifade ve yedek/kapsam
onayından sonra manuel başlatılır. Geliştirme/test sırasında yalnız ayrılmış
yerel test şemalarında silme yapılır.

## Kapsam

Silinecek dokuz tablo, iptal/soft-delete geçmişi dahil:
payment_allocations, ledger_entries, offer_prices, offer_periods,
offer_room_types, offers, payments, stays, receipt_book_entries.

companies tamamen korunur: tüm cari kartları, bireysel/şirket türleri, adları,
kimlikleri, aktiflik ve soft-delete durumları, notlar, oluşturma/güncelleme
kolonları aynı kalır. Bakiye şirket kolonunda tutulmaz, defterden türetilir;
defter temizlendiğinde eski girişlerin bakiyesi ve grafik/ekstre içeriği kalkar.

Önceki 48 tablo envanterindeki diğer 39 tablo tamamen korunur: HESAP'ın dört
tablosu, Personel'in 11 tablosu, kullanıcı/rol/yetki/oda/tercih, audit, eski kasa
arşivi ve framework tabloları. Runtime'da yeni/bilinmeyen her tablo varsayılan
korunur. Ayrıntılı sahiplik/FK envanteri:
cari-temizleme-guvenlik-analizi-2026-10-12.md (önceki bloke kararı tarihsel).

Yeni cari_cleanup_operations tablosu yalnız bu işlemin tek kullanımlık durumu
ve doğrulama raporudur; iş verisi değildir. Kaynak şeması toplam 49 tablo olur.
Bu yeni singleton, mevcut verileri koruma checksum kümesinden açıkça ayrıdır.
ID ve AUTO_INCREMENT sıraları sıfırlanmaz; TRUNCATE ve genel reset yoktur.
Marka/logo ve diğer sistem dosyalarına temizleme kodu yazmaz.

## Veritabanı koruması ve migration

2026_10_12_100000_add_one_time_cari_cleanup_capability.php:
- Yalnız işlem tablosunu/singleton hazır durumunu ve trigger'ları kurar; CARİ
  veri silmez. Yetki/rol seeder'ı değiştirmez.
- ledger_entries_block_delete ve payment_allocations_no_delete normalde
  SIGNAL 45000 ile reddeder. Yalnız singleton running, aynı CONNECTION_ID,
  SHA-256 ile eşleşen rastgele bağlantı nonce'u, UTC süre sınırı ve veritabanında
  halen aktif/silinmemiş Admin rolü kanıtıyla DELETE'e izin verir.
- Finansal UPDATE korumaları değişmez; normal SQL DELETE yasak kalır.
- Tamamlanmış işlem satırı UPDATE edilemez; işlem satırı DELETE edilemez.
- DDL implicit commit yaptığı için silme transaction'ında DDL yapılmaz.
  Migration, iki bağımsız koşulsuz engeli önce kurar; iki yeni DELETE koruması
  kurulmadan geçici engelleri kaldırmaz. DDL hatasında engeller kalır.
  Yeniden deneme bunları tam tanımıyla doğrular; beklenmeyen guard'da durur.
- down yalnız hiç kullanılmamış hazır durumda çalışır; eski koşulsuz DELETE
  engellerini geri kurar. Tamamlanmış/çalışan durumu silemez veya yeniden açamaz.
- Migration ve yürütücü aynı advisory lock'u kullanır.

Bu güvence uygulamanın yetkilendirilmiş yoluna ilişkindir. Şema/trigger değiştirme
yetkili bir veritabanı yöneticisinin veya ele geçirilmiş DB kimlik bilgilerinin
kasıtlı müdahalesine karşı değişmezlik iddiası değildir.

## Ön kontrol ve gerçek silme

GET yalnız okur. POST ön kontrol CSRF korumalıdır; tek READ ONLY / REPEATABLE
READ snapshot'ta tablo sayımı ve tam içerik özeti üretir. Ön kontrol tokenı
şifreli/doğrulanmış, ilgili Admin'e ve oturuma bağlı ve 10 dakika geçerlidir.
Token cari hedefler, şirket kartları ve şema envanterinin özetiyle bağlıdır.
Diğer modüllerin ön kontrol sonrasında normal çalışması tokenı gereksiz yere
geçersiz kılmaz; onların güncel satırları gerçek işlemde korunur.

Şema özeti tablo/engine/collation, kolon/tip/default/hesaplanan ifade, indeksler
(MySQL functional expression dahil), CHECK/constraint/FK ve trigger gövdelerini
kapsar; istatistiksel row count/cardinality gibi değişken tahminler kullanılmaz.
Bilinmeyen view/non-InnoDB, eksik gerekli tablo/koruma veya korunacak tablodan
hedefe yeni FK, işlemi kapalı tutar.

POST /admin/cari-temizleme/sil:
1. Aktif Admin policy, CSRF, current_password:web, birebir doğrulama ifadesi
   CARİ GİRİŞLERİNİ SİL, zorunlu yedek/kapsam kutusu ve token denetlenir.
   Deneme sınırı dakikada üç istektir. Parola old input/log/rapora konmaz.
2. Eşzamanlı temizlemeyi advisory lock ve singleton satır kilidi reddeder.
3. Tek READ WRITE / REPEATABLE READ transaction başlar. Tüm InnoDB tablolar
   FOR UPDATE tam taramayla kilitlenir; mevcut satırlar ve boşluklar korunur.
   Güncel Admin yetkisi tekrar okunur. Token içerik/şema ve süresi kilitlerden
   sonra tekrar doğrulanır.
4. Süreli bağlantı capability'si sadece bu transaction'da açılır. Ters kayıt
   FK'leri yapraklardan başlayarak silinir; sonra teklif alt satırları/belgeler,
   tahsilatlar, konaklamalar ve makbuzlar kaldırılır. FK kontrolü açık kalır.
5. Tüm hedeflerin boş olduğu ve mevcut kapsam dışı tabloların tam satır içeriği
   önce/sonra birebir aynı olduğu doğrulanır. Satır özetleri kolon adı, NULL,
   scalar tip, binary bayt ve uzunluk ayrımını korur; duplicate satırlar kaybolmaz.
6. Sayılar/korunan özetler ve tamamlanma kilidi aynı transaction'da yazılır.
   Hata/eksik temizlik/şema veya içerik farkında tamamı ROLLBACK olur.
7. Bağlantı nonce'u ve advisory lock her çıkışta temizlenir. Başarıdan sonra
   tekrar çalıştırma kalıcı reddedilir; geçici menü bağlantısı gizlenir.
   Direkt URL yalnız tamamlanma raporunu gösterir.

Framework session/cache/rate-limit altyapısı normal HTTP işleyişinde kendi
kayıtlarını güncelleyebilir. Temizleme transaction'ı bu tablolara yazmaz; kendi
önce/sonra karşılaştırmasında bunları da korur. “Bütün production satırları her
zamanda değişmez” gibi eşzamanlı normal uygulama faaliyetini dışlayan bir iddia
yapılmaz. İşlem sırasında kısa süreli satır kilitleri normal yazmaları bekletebilir;
kilit/deadlock hatası güvenli rollback ve yeniden ön kontrol gerektirir.

Audit ve eski kasa geçmişi fiziksel korunur. Polimorfik işaretçiler silinen
kaynak ID'lerini tarihsel olarak göstermeye devam edebilir; arşiv tutarları
ve işaretçileri yeniden yazılmaz. Bu işlem audit'e normal delete olayları
eklemez; ayrı tamamlanma kaydı aktör, zaman, silinen sayılar ve içerik
doğrulamasını saklar. Kimlikler yeniden kullanılmaz.

## Doğrulama ve sınırlar

27 hedef test / 480 doğrulama PHP 8.3.33 + ayrı MariaDB 11.4.8 üzerinde geçti.
Gerçek şirket/soft-delete kartları, tahsilat/dağılım/ters kayıt ve teklif alt
FK'leri; HESAP revizyon/ödeme satırları; Personel bordro; eski kasa; audit;
tüm diğer tabloların tam satır snapshot'ları karşılaştırıldı. Yetki, gerçek
CSRF, parola/ifade/token, stale/expired token, eksik metadata/koruma,
bilinmeyen korunan FK, yarım DDL guard'ı, tekrar/eşzamanlı istek, ara hata
rollback, checksum farkı rollback ve bağlantı temizliği sınandı.
Yeni testlerde şema reseti veya finansal koruma atlatma yoktur.

Ayrı hotel_account_cleanup_smoke şemasında dış transaction olmadan gerçek
commit provası yapıldı: 3 cari kartı ve tüm korunan tablolar aynı kaldı; ters
kayıtlı ledger/allocation, payment/stay temizlendi. Bağlantı kapatılıp açıldıktan
sonra tamamlanma kilidi halen geçerliydi.

Ayrı hotel_account_cleanup_rollback şemasında gerçek dış transaction'a ilk
DELETE sonrasında hata enjekte edildi. 49 tablonun bütün satır özetleri aynı
kaldı; bağlantı yeniden açılınca işlem hazırdı ve capability temizlenmişti.

Geçici menünün kalıcı durum kontrolü sabit bir okuma ekler. Pano aynı defter
netini iki kez sorgulamak yerine zaten hesaplanan para birimi netini son
hareketlere iletir; mevcut 18 sorgu bütçesi değiştirilmez. Para birimi ve
sayfalama bakiyelerinin aynı kaldığı ek regresyon testiyle doğrulanır.
Dashboard/Ledger/Statements paketinde 178 test ve 716 doğrulama geçti.

Yerel Blade önizlemesinde ilk GET, ön kontrol sonrası parola/onay formu ve
tamamlanma ekranı kontrol edildi. 360/375/390/430/768/1366/1920 CSS pikselde
yatay taşma yoktu; ilk ekranda silme kapalı, tamamlanmada silme formu yoktu.
Bu tarayıcı önizlemesi üretim oturumu veya canlı kayıt sayımı değildir.

Build, JS testleri ve statik analiz sonuçları final raporda belirtilir.
Tam paket/CI/deploy bitmeden başarı iddiası yapılmaz. Önceden main üzerinde de
yeniden üretilen Windows çalışan finalize.php rename erişim hatası ve mevcut
StagedReleaseTest uyarısı yeni CARİ değişikliğinden ayrıdır; Linux CI gereklidir.

Production silme yapılmış veya canlı korunan veri checksum'ı alınmış değildir.
Canlı başarılı silme kanıtı ancak Admin'in son işleminden çıkan tamamlanma
raporu ve korunan veri doğrulamasıyla oluşur.

## Geçici kod kaldırma planı

Başarılı kullanımdan sonra modülün form/route/controller/view ve menü kodları
ayrı release/test/CI paketiyle kaldırılabilir. İki finansal DELETE koruması,
bağımsız DDL engelleri altında eski koşulsuz korumaya döndürülür. Tamamlanma
singleton'u, içerik doğrulama raporu ve onu koruyan state trigger'ları denetim
kanıtı olarak korunur; migration rollback veya state silme ile ikinci kullanım
açılmaz. HESAP/Personel/eski kasa/audit/şirket kartları değiştirilmez.
