# Personel Takip — işletim ve veri bütünlüğü

Teknik kararlar ve tablo/endpoint sözleşmesi için
[teknik plan](personel-takip-teknik-plan.md); Android kurulumu için
[okuyucu belgesi](../android/personnel-scanner/README.md) esas alınır.

## Alan sınırı

`app/Modules/Personnel` mevcut modüler monolite eklenir. Personel kaydı oturum
açan kullanıcı değildir. Kullanıcı oluşturma, roller ve oturum açma mevcut
Authorization modülünde kalır. Personel ücretleri, hakedişleri ve ödemeleri
yalnız `personnel_*` tablolarındadır; müşteri carisine, konaklamaya, tahsilata
ve `ledger_entries` tablosuna personel hareketi yazılmaz.

Personel silinmez, pasife alınır. Kart atama geçmişi, vardiya sürümleri,
ücret atamaları, okutmalar, düzeltmeler ve finansal hareketler korunur.
Ücret/vardiya değişikliği yürürlük tarihiyle yeni kayıt üretir; eski atama
ve vardiya sürümü geçmiş hesapları değiştirmek için düzenlenmez.

Pasife alma kart kullanımını durdurur; otomatik işten çıkış değildir.
İstihdamın bitişi ayrı çıkış tarihiyle kaydedilir. Geçmişi olan çalışanın
işe giriş tarihi ve geçmiş çıkışı değiştirilmez; güvenli ileri tarihli
çıkış, kaydedilmiş çalışma/hakediş dönemlerini kesmediği sürece tanımlanır.

## Yetki ve kişisel veri

| Yetki | İşlem |
| --- | --- |
| `personnel.view` | Personel, vardiya, puantaj ve raporları okuma |
| `personnel.manage` | Personel, kart ve vardiya yönetimi |
| `personnel.attendance` | Gerekçeli puantaj düzeltmesi |
| `personnel.payroll` | Ücret, hakediş, prim, kesinti, ödeme ve ters kayıt |
| `personnel.scan` | Oturum açılmış web kioskunda okutma |
| `personnel.terminals` | Android terminal anahtarı oluşturma/iptal |

Menü görünürlüğü tek başına yetkilendirme değildir; sunucu rotaları da
kontrol eder. Terminal anahtarı kullanıcı oturumu değildir ve yönetim
ekranlarına erişim sağlamaz. Anahtar sadece oluşturulurken gösterilir;
sunucuda özeti saklanır. Kaybolan cihazın terminali derhal iptal edilmelidir.
Kart UID'si kimlik eşleştirmesidir, kriptografik kimlik kanıtı değildir:
kopyalanabilir kartlara karşı fiziksel terminal gözetimi gerekir.

## Okuyucular

- **PC:** UID'yi hexadecimal metin ve Enter olarak gönderen USB HID / keyboard
  wedge. Giriş yalnız özel okuyucu alanında yakalanır; uygulamanın başka
  formlarında klavye kısayolu veya küresel tuş yakalama kurulmaz.
- **Android:** izole native yardımcı uygulama `Tag.getId()` ile kimliği alır;
  `MifareClassic`, `NfcA`, `NdefFormatable`, `Ndef` teknoloji sınıflarını tanır.
  Sektör anahtarı, kart sırrı veya NDEF içerik deposu yoktur.
- **Tarayıcı NFC:** ham MIFARE/UID için Web NFC desteği iddia edilmez.
- **PC/SC / iOS:** bu sürümde PC/SC köprüsü ve iOS uygulaması yoktur. PC/SC
  okuyucu HID moduna geçemiyorsa desteklenmez. MIFARE Classic desteği Android
  cihazının NFC donanımına da bağlıdır.

UID büyük harf hexadecimal olarak normalize edilir. Başlangıç sıfırları
korunur. Decimal UID veya ters bayt sırası tahmin edilmez; USB ve Android
çıktısının aynı kart için eşleşmesi kurulum kabulünün parçasıdır. Rastgele
UID üreten kart kalıcı personel tanımlayıcısı olarak kullanılmamalıdır.

## Okutma ve puantaj

Her kabul/red/duplicate okutma sonucu ayrı, değişmez olaydır. UUID aynı
isteğin ağ hatası sonrası yeniden gönderilmesine imkân verir; aynı UUID ile
farklı istek gönderilmez. Belirsiz ağ sonucunda yeni giriş/çıkış üretmek
yerine aynı istek tekrar denenir. Bu sürüm çevrimdışı toplu okutma kuyruğu
değildir; sunucunun kabul zamanı esas alınır.

Otomatik mod son etkin oturuma göre giriş/çıkış sırasını belirler. Hızlı
tekrarlar önce ayıklanır, yönü ikinci kez değiştirmez. Açık oturumun üzerinden
24 saat geçmesi otomatik tahmin yerine yetkili düzeltme gerektirir. Bir
günde birden çok çalışma oturumu korunur. Gece vardiyası çıkışı girişin iş
günüyle ilişkilendirilir. Saat gösterimi ve iş günü Europe/Istanbul,
okutma anı UTC'dir.

Günlük özet ham olaylardan tekrar hesaplanır. Brüt süre tamamlanan
oturumların toplamıdır; tanımlı mola en fazla brüt süre kadar düşülür.
Net süre, planlanan net süre, tolerans sonrası geç kalma/erken çıkma ve
fazla mesai ayrı değerlendirilir. Eksik çıkış tamamlanmış gün gibi ücret
kesinleştirmesine sokulmaz.

Düzeltme, seçilen iş gününün tamamlanmış oturum listesinin yeni revizyonudur.
Boş liste günün çalışmalarını kaldırır ama ham okutmayı silmez. Gerekçe,
işlemi yapan kullanıcı ve zaman saklanır. Eşzamanlı düzenlemede beklenen
revizyon değişmişse işlem reddedilir; çakışan oturumlar kabul edilmez.
Düzeltme anındaki kabul edilmiş son okutmanın kayıt kimliği saklanır.
İlgili güne daha sonra okutma eklenirse düzeltme yeniden değerlendirilmeden
hakediş kesinleştirilmez; aynı saniyedeki olay sırası da kayıt kimliğiyle korunur.

## Hakediş ve ödeme

Bu modül işletme içi hakediş takibidir; vergi/SGK veya hukuki bordro motoru
değildir. Ücret aylık/günlük/saatlik, para birimi ve standart çalışma süresi
ile atanır. Para hesapları mevcut `Money` sınıfının tamsayı alt birimleriyle
yapılır; float kullanılmaz. Fazla mesai etkinliği ve katsayısı ücret
atamasındadır, geçmiş atamalara geriye dönük uygulanmaz.

- Aylık ücret, istihdam kapsamındaki takvim günleri üzerinden ilgili ayın
  gerçek gün sayısıyla oranlanır. Ücret ataması segmenti bir kez yuvarlanır.
  Planlı çalışma eksikliği kesinti olarak, fazla çalışma ayrı bileşen olarak
  hesaplanır. Dinlenme günü devamsızlık kesintisi değildir.
- Günlük ücret standart günlük dakikaya, saatlik ücret 60 dakikaya göre
  hesaplanır. Fazla mesai açıksa normal ve fazla çalışma ayrılır.
- Önizleme otomatik finansal kayıt üretmez. Açık/eksik/gelecek hesap günü
  varken kesinleştirme reddedilir. Girdilerin özeti değişmişse yeniden
  önizleme gerekir.
- Prim, kesinti ve ödeme gerekçeli ayrı hareketlerdir. Ödeme yöntemi nakit,
  havale/EFT veya diğer olabilir. Kısmi ödeme mümkündür.
- İptal fiziksel silme değildir: asıl harekete bağlı eşit tutarlı ters kayıt
  oluşturulur. Aynı hareket ikinci kez ters kaydedilmez.

Bakiyeler para birimi bazında ayrıdır; farklı para birimleri toplanmaz.
Kaydedilmiş hakediş/prim eksi kesinti ve net ödeme kalan bakiyeyi verir.
Önizleme ile kaydedilmiş finansal bakiye aynı kavram değildir.

## Kurulum, test ve yayın

Yeni tablolar additive migration ile oluşturulur. Üretimde migration elle
çalıştırılmaz; mevcut deploy finalize/cron mekanizması migration, permission
seed ve cache adımlarını uygular. `main` merge öncesi CI yeşil olmalıdır.
Dosya aktarımı tek başına yayın başarısı değildir: finalize, pending marker
temizliği ve workflow SUCCESS doğrulanır. Android kaynakları web deploy
paketinden hariç tutulur; APK ayrı CI artifact'ıdır.

Yerel otomatik kontroller:

```text
composer validate --strict
composer check-platform-reqs
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test
npm run test:monthly-account
npm run test:personnel
npm run build
```

Migration geri alma yalnız bütün personel tabloları boşken mümkündür; veri
varsa migration bilinçli olarak durur. Üretimde geri alma yerine ileri
düzeltme uygulanır. Geçmişi koruyan bu kontrolü devre dışı bırakmayın.

Finans ekranında **bu ay kalan** yalnız seçilen ayın hareketlerini,
**genel kalan bakiye** ise bütün dönemlerin net hareketlerini gösterir.
Ödeme tarihi farklı aya aitse hareket o tarihin döneminde görünür.

PHP testleri yalnız izole MySQL/MariaDB test veritabanında çalıştırılır.
SQLite ile finansal trigger/constraint davranışı doğrulanmış sayılmaz.
Android derlemesi ve UID testi yardımcı uygulama README'sinde açıklanır.
Otomatik test, gerçek kart/telefon/USB okuyucu testinin yerine geçmez.

Üretimde smoke kontrolü salt okunurdur: login, yetkisiz erişim yönlendirmesi,
release göstergesi ve deploy sonucu incelenir; sahte personel veya ödeme
kaydı oluşturulmaz. Donanım kabulü işletmenin belirlediği test terminali ve
kartıyla yapılır; gerçek personel ücret geçmişi test için değiştirilmez.
