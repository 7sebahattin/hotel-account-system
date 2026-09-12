# Lemon — gerçek veriyi koruyarak canlıya geçiş

Tarih: 12 Eylül 2026. Durum: **yayına hazır değil; birleştirme ve geçiş provası bekleniyor.**
Güncelleme: ayrı yerel kopyada entegrasyon tamamlandı, 847 test geçti.
Gerçek yedek provası ve yayın paketlerinin ayrımı bekleniyor. Ayrıntılar:
[ilk adım sonuçları](canli-gecis-adim1-entegrasyon-2026-09-12.md).
“Bugün doğruladığımız durum” tablosu entegrasyon öncesi başlangıç kaydıdır.
Bu belge mevcut, gerçek kayıt içeren sistemin güncellenmesi içindir.
Eski ilk kurulum belgelerindeki “canlı boş”, “henüz yayın yapılmadı” ve
“DocumentRoot değiştirilecek” ifadeleri güncel durum kanıtı değildir.

## 1. Bugün doğruladığımız durum

| Konu | Bulgumuz | Sonuç |
|---|---|---|
| Son kullanıcı bildirimi | Kullanıcı PR birleştirmesinden sonra ayrıca hosting'e yükleme/Deploy yapıldığını teyit etti | Filtre panelleri korunacak canlı özellik; kesin yayın commit'i ayrıca kaydedilmeli |
| GitHub ana dalı | `ea6c43859255720d34b5918be49d84299acaec5c`, PR #1; v2.1.1-E filtre panelleri | Yeni tasarım korunmalı |
| Bizim geliştirme tabanı | `a426b3e823b008f820cd2d1bbc111cecd2eb1337`, v2.1.1-D | Bu çalışma doğrudan yüklenmemeli |
| Geliştirme dalı | `codex/cari-gelistirme`; değişiklikler henüz commit edilmedi | Önce kayıpsız çalışma kontrol noktası hazırlanmalı |
| Diğer yerel kaynak kopyası | Önceki D sürümünde ve temiz | Güncel yayın kaynağı kabul edilemez |
| Claude değişikliği | 17 dosya; filtre bileşeni, CSS, sorgu yardımcıları ve test düzeltmeleri | İncelenen farkta migration/bağımlılık değişikliği yok |
| Ortak değişen dosyalar | 5 dosya; aşağıda listeli | Çakışma çıkmasa da davranış birlikte incelenmeli |
| Canlı veri | Gerçek kayıtlar var; kullanıcı daha önce yedek indirildiğini bildirdi | Yeni yayından hemen önce güncel yedek gerekli |
| Yedeğin açılması | Bizim tarafımızdan geri yükleme ile doğrulanmadı | İndirildi bilgisi tek başına yeterli değil |
| Döviz geliştirmesi | Son yerel çalışmada 34 test / 128 doğrulama geçti | Birleşmiş sürümün tamamı ve tarayıcı denemesi henüz doğrulanmadı |
| Canlıya müdahale | Bu çalışmadan yayın yapılmadı | GitHub okundu; ana dala birleştirme ve sunucuya yazma yapılmadı |

GitHub ana dalındaki sürümün sunucuda gerçekten çalıştığı henüz yayın kaydıyla
doğrulanmadı. Sunucuya elle aktarılmış ilave dosyalar varsa ayrıca karşılaştırılır.
Kullanıcının paylaştığı Claude çalışma notunda PR öncesi iki CI koşusunun
Pint, PHPStan ve 801 test ile başarılı olduğu bildiriliyor. Bu bilgi Claude
notundan alınmıştır; bizim cari/döviz değişikliklerimizin testi değildir.

Ortak değişen dosyalar:

- `CLAUDE.md`
- `app/Modules/Companies/Http/Controllers/StatementController.php`
- `app/Modules/Companies/Resources/views/index.blade.php`
- `app/Modules/Companies/Resources/views/statements/show.blade.php`
- `app/Modules/Stays/Resources/views/index.blade.php`

## 2. Adımlar ve bitirme ölçütleri

| Sıra | Yapılacak iş | Bitti sayılması için | Kullanıcıdan gereken |
|---|---|---|---|
| 1 | Son canlı sürümü kesinleştir | Yayın zamanı, kaynak commit ve gerçek sunucu migration listesi kaydedilmiş olmalı | Claude güncellemesinin kısa özeti; gerektiğinde son yayın kaydına erişim |
| 2 | İki geliştirmeyi birleştir | E sürümünün filtre panelleri ve bizim cari özellikler birlikte çalışmalı | Yeni özelliklerin görünür kontrolü |
| 3 | Güncel yedeği ayrı ortamda aç | Veritabanı, tetikleyiciler ve uygulama dosyaları geri yüklenebilmeli | Hosting yedeği/erişimi gereken adımda birlikte tamamlanır |
| 4 | Gerçek veri kopyasında geçiş provası | Kayıt sayıları ve her hesabın her dövizde bakiyesi birebir korunmalı | Tanıdığınız birkaç hesabı kontrol etmeniz |
| 5 | Son sürümü test et ve paketi sabitle | Tüm testler, rol kontrolleri, masaüstü/mobil ve rapor denemeleri geçmeli | Personel ve patron kullanım kontrolü |
| 6 | Kısa bakım aralığında yayımla | Güncel son yedek + doğrulanmış aynı paket + geçiş sonrası mutabakat | Uygun bakım zamanı ve somut yayın onayı |
| 7 | İlk iş gününü kontrol et | İlk gerçek işlemler, gün sonu kasa ve cari hesaplar tutarlı olmalı | Personelin karşılaştığı sorunları bildirmesi |

## 3. Birleştirme ve sürüm sınırı

Mevcut çalışma önce commit veya güvenli çalışma kopyasıyla korunur. Canlıdaki
kesin sürümden ayrı bir entegrasyon dalı hazırlanır; ana dal ve çalışan yerel
dosyalar üzerine zorla yazılmaz. Birleşmiş sürümün CLAUDE kararları hem K-106
filtre tasarımını hem onaylı bireysel hesap/ödeme kurallarını içermelidir.

Önerilen yayın ayrımı:

1. **Bireysel cari + aynı para biriminde ödeme dağıtımı:** E sürümünün tasarımı
   korunarak yayın adayı hazırlanır. Eski ödemelerin nasıl gösterileceği ve
   eşleştirme önizlemesi bu aşamanın zorunlu parçasıdır.
2. **Dövizden TL borç kapama:** ilk aşama mutabakatı ve kabulünden sonra ayrı
   aday olarak hazırlanır. İptal, kısmi ödeme, avans ve kuruş sınırları ayrıca
   doğrulanır. Henüz tümü tarayıcı ve birleşmiş kod üzerinde doğrulanmış değil.
3. **Avansın firmaya/kişiye aktarımı ve iade:** sahiplik, yetki ve kasa etkisi
   tamamlanınca sonraki yayın; mevcut sürüme yarım işlev eklenmez.

Bu ayrım için ayrı paketler hazırlanmalıdır. Döviz kodunun aynı çalışma
klasöründe bulunması, birinci pakete otomatik dahil edileceği anlamına gelmez.
Numara, son canlı sürüm ve seçilen kesin kapsam doğrulanınca atanır.

## 4. Yedek ve veri geçişi provası

- Yedek veritabanını, migration geçmişini ve finansal UPDATE/DELETE koruma
  tetikleyicilerini içermeli. Kullanıcı dosyaları, sunucu ayarları ve mevcut
  çalışan uygulama paketi de geri getirilebilir biçimde korunmalı.
- Parolalar ve `.env` içerikleri rapora/Git'e konmamalı. Gerçek veri kopyası
  yalnız yetkili erişime açık ayrı ortamda tutulmalı; canlı DB bağlantısı
  taşınmamalı. Deneme ortamının adı ve veritabanı açıkça ayrı olmalı.
- Geri yüklenen eski sürüm önce açılmalı. Ardından yeni migration'lar bu
  kopyada çalıştırılmalı; süre, hata ve beklenen şema değişiklikleri kaydedilmeli.
- Önce/sonra karşılaştırma: firma/kişi, aktif/iptal konaklama, tahsilat, kasa
  hareketi sayıları; cari hesap × para birimi borç/alacak/bakiye; vardiya ×
  para birimi beklenen kasa; son kayıt ve makbuz numaraları.
- Şema geçişi tek başına geçmiş finansal tutarları değiştirmemeli. Eski
  hesaplar mevcut kimlikleriyle firma olarak kalmalı; isimden kişi birleştirilmemeli.
- Geçmiş ödeme dağıtımı önce **yalnız önizleme** üretmeli: tarih sırası,
  seçili eşleşme istisnaları, kısmi ödeme, avans ve eksik kur gösterilmeli.
  Bilinmeyen ödeyen kişi tahmin edilmemeli. Onaylanmadan geçmişe uygulanmamalı.
- Önizleme ve tekrar çalıştırma güvenliği henüz uygulanmış/doğrulanmış değil.
  Geçmiş ödeme bulunan hesapların “eşleştirme bekliyor” durumuyla nasıl
  kullanılacağı netleşmeden finansal geçiş tamamlanmış sayılmaz.

## 5. Yayın öncesi test kapısı

Birleşmiş **aynı commit** üzerinde PHP 8.3, üretimle uyumlu MySQL/MariaDB,
Pint, PHPStan, tüm test paketi, bağımlılık platform kontrolü ve arayüz
derlemesi başarılı olmalı. Eski 820 test çalışması ve son 34 test başarısı,
henüz oluşturulmayan birleşmiş yayın paketinin testi yerine geçmez.

Kabul senaryoları:

- Firma seçilen ve seçilmeyen konaklama; aynı isimli farklı kişilerin ayrılması.
- Firma borcuna şahsi ödeme; en eski borca ve seçilen konaklamalara dağıtım.
- Tam/kısmi ödeme; fazla ödeme ve sonradan konaklamaya avans kullanımı.
- Döviz paketinde: 5.000 TL borç + 60 EUR × 50 TL = 2.000 TL kalan; tek EUR
  tahsilatı; fazla EUR'nun korunması; yanlış kur/hesap ve çok küçük tutarlar.
- Ödeme ve konaklama iptali; tutar azaltma ve hesabı değiştirme; ters kayıtların
  cari ve kasayı birlikte düzeltmesi; eşzamanlı kayıt/iptalde çift işlem olmaması.
- Personel/admin/patron yetkileri; filtreler, sıralama, sayfalama, yazdırma,
  Excel, makbuz ve bağımsız Tahsilat Makbuzları modülünün korunması.
- Masaüstü personel girişi, telefonda patron takibi; PWA'da yeni ekranın
  yüklenmesi, eski açık sayfadan gönderim ve çift tıklama/yeniden deneme.

Sunucu düzeyinde çift gönderim koruması henüz tamamlanmış kabul edilmemeli.
Özellikle yeni finansal kayıt yollarında davranışı ölçülmeli ve gerekiyorsa
yayından önce tamamlanmalı.

## 6. Yayın günü

Mevcut FTPS workflow'u dosya aktarımı yapıyor; test job'una zorunlu bağı
yok ve sunucudaki migration/yetki/cache işlemlerini kendi yürütmüyor.
Dolayısıyla “aktarım başarılı” sonucu, uygulamanın güvenli biçimde açıldığı
anlamına gelmez. Plan uygulamasında bu adımlar aynı sürüm kaydıyla takip edilir.

1. Testten geçmiş paketin commit'i, dosya özeti ve geri dönüş paketi sabitlenir.
   Ayrı hazırlanan başka bir derleme kendiliğinden eşdeğer sayılmaz.
2. Personel kayıt girişi durdurulur; açık işlemler tamamlanır. Kısa bakım
   aralığı **aktarımdan önce** başlatılır; eski açık formlar da yazamamalı.
3. Son güncel yedek ve hesap/kasa kontrol toplamları alınır.
4. FTPS hedefi doğrulanır. Mevcut belgede hedef `/lemon-app/`; gerçek sunucu
   durumu teyit edilir. Çalışan DocumentRoot veya hosting hesabının genel PHP
   sürümü eski kurulum talimatı nedeniyle değiştirilmez.
5. `.env`, `APP_KEY`, kullanıcı dosyaları ve diğer siteler korunarak sabit
   paket aktarılır. Dosyaların bir kısmı eski bir kısmı yeni kalırsa açılmaz.
6. Cache temizliği → migration → yalnız `RolePermissionSeeder` → cache
   kurulumu sırası uygulanır. Her adımın çıkış sonucu denetlenir; hata halinde
   sonraki adıma ve `artisan up` işlemine geçilmez. `migrate:fresh` kullanılmaz.
7. Bakım sürerken uygulama açılışı, rol erişimi ve veri mutabakatı kontrol
   edilir. Gerçek hesaplarda deneme borcu/tahsilatı oluşturulmaz; senaryo
   denemeleri ayrı ortamda tamamlanmış olmalıdır.
8. Sonuçlar uygunsa bakım kapatılır, personel eski sekmeleri yeniler. İlk
   gerçek işlem kullanıcıyla birlikte kontrol edilir.

## 7. Hata olursa geri dönüş

**Yeni özellikler kullanılmaya başladıktan sonra yalnız eski dosyaları
yüklemek güvenli bir geri dönüş değildir.** Eski kod yeni ödeme dağılımlarını
ve döviz kapama çiftlerini yönetmez; yeni şemayı okuyabilmesi yeterli değildir.

| Hatanın zamanı | Uygulanacak yaklaşım |
|---|---|
| Bakımdayken, henüz yeni gerçek işlem yok | Yazmalar kapalı kalır; prova edilmiş eski paket + uyumlu veri durumu geri getirilir. Mutabakat geçmeden açılmaz. |
| Bakım açıldıktan ve yeni gerçek işlem girildikten sonra | Yeni giriş durdurulur; hata sonrası veri ayrıca korunur. Önce veri koruyan ileri düzeltme değerlendirilir. |
| Eski yedeğe dönmek zorunluysa | Yedekten sonra girilen tüm gerçek kayıtların korunması/yeniden uygulanması için karşılaştırmalı plan hazırlanır. Üzerine kör geri yükleme yapılmaz. |

Finansal kayıt içeren yeni migration'ların geri alma koruması aşılmaz;
dağılım/defter geçmişi silinmez. Yedek geri yükleme süresi provada ölçülmeden
kesinti süresi veya sıfır veri kaybı garantisi verilmez.

## 8. Yayını durduran durumlar

- Son çalışan sürüm veya sunucu şeması belirsiz.
- Birleşmiş kodda Claude filtreleri kayboluyor ya da testler başarısız.
- Yedek ayrı ortamda açılamıyor, tetikleyiciler eksik veya geri dönüş denenmemiş.
- Tek bir hesabın/para biriminin veya kasanın açıklanamayan bakiye farkı var.
- Geçmiş ödeme eşleştirmesi, yeni veriyle eski kod uyumu veya kritik çift
  gönderim davranışı çözülmemiş.

**İlk somut iş:** son canlı sürüm teyidi ve iki çalışmanın ayrı entegrasyon
kopyasında birleştirilmesi. Bu belgede yalnız yol haritası hazırlandı;
birleştirme, yeni yedek alma, sunucu geçişi ve yayın henüz yapılmadı.
