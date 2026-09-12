# Lemon — Onaya sunulan geliştirme planı

Durum: Tasarım yönü ve uygulama geliştirmesi 11 Eylül 2026 tarihinde kullanıcı tarafından onaylandı. Canlı veri geçişi ve eski ödeme dağılımlarını uygulama ayrı kontrol aşamasıdır.

## 1. Hedefimiz

Personel ve admin bilgisayarda konaklama ve tahsilatı kolayca kaydedecek. Patron telefondan borçları, tahsilatları ve hesap geçmişini takip edecek; gerektiğinde ikincil “İşlem yap” menüsünden yetkili olduğu kayıt işlemlerine ulaşacak.

İlk teslimatın konusu cari hesap, konaklama, ödeme ve raporlama. Kasa uygulamasının kapsamlı geliştirilmesi ikinci ana aşama; PMS, rezervasyon kanalları ve otomatik kur sonraki aşamalar.

Mevcut kayıtlar test amacıyla girilmiş GERÇEK kayıtlardır. Korunacak ve yeni kayıtlar bunların üzerine eklenecek. Sıfırlama yapılmayacak.

## 2. Kararlaştırılan işleyiş

| Konu | Kabul edilen davranış |
|---|---|
| Firma konaklaması | Firma seçilirse konaklayan kişinin adı kaydedilir, borç firma hesabına yazılır. Misafir için ikinci borç oluşturulmaz. |
| Bireysel konaklama | Firma boşsa borç bireysel hesaba yazılır. Mevcut kişi seçilir veya aynı akışta yeni kişi oluşturulur. |
| Tekrar gelen kişi | Aynı hesabı kullanır. Aynı adlı kişiler otomatik birleştirilmez; seçime yardımcı iletişim bilgisi gösterilir. |
| Ödeyen kişi | Borcun sahibi ve ödeyen kişi ayrı tutulur. Misafir, firmanın borcunu kendi ödemesiyle kapatabilir. |
| Ödeme yöntemi | Nakit, kart, havale gibi yöntemler ayrı alandır. “Şahsi ödeme” bu yöntemlerle karıştırılmaz. |
| Toplu ödeme | Aynı hesaptaki uygun borçlara en eskiden başlayarak dağıtılır. |
| Belirli konaklama | Personel bir veya birkaç konaklama seçebilir; diğer borçlar açık kalır. |
| Kısmi ödeme | Her konaklamada tutar, ödenen ve kalan görünür; Ödendi / Kısmen ödendi / Ödenmedi durumu hesaplanır. |
| Fazla ödeme | Alınan para biriminde avans kalır. İlgili işlem kuruyla TL karşılığı gösterilir. |
| Avansın sahibi | Firma ödemesinde firma; misafirin firma hesabına yaptığı fazla ödemede “Firmada bırak / Misafirin hesabında tut” seçimi yapılır. |
| Döviz | Alınan gerçek para birimi ve tutarı saklanır; kur personelce elle girilir, TL karşılığı ayrıca gösterilir. İşlemin kuru sonradan piyasa kuruyla değişmez. |
| TL borca EUR tahsilat | EUR alınır, anlaşılmış işlem kuruyla TL borç kapanır. Tek tahsilattır; EUR ve TL iki ayrı gelir sayılmaz. |
| Konaklama iptali | Borç etkisi geri alınır. Gerçek tahsilat korunur; bağlı ödeme tutarı hesabın kullanılabilir avansına döner. |
| Ödeme iptali | Hatalı tahsilat ve ona ait dağılımlar birlikte geri alınır; gerçek para iadesiyle karıştırılmaz. |
| Para iadesi | Patron/admin ayrı iade işlemi yapar. Avans ve para hareketi birlikte izlenir; çift iade engellenir. |
| Silme | Yalnız admin, bağlantısız kayıtları kalıcı silebilir. Finansal/başka kayıtlara bağlı nesnelerde silme engellenir, sebep açıklanır. |
| İptallerin görünümü | Normal muhasebe dökümlerinde listelenmez; yetkili işlem geçmişinde korunur. Bakiye hesabında iptalin etkisi doğru kalır. |

## 3. Yetkiler

| İşlem | Personel | Patron | Admin |
|---|---|---|---|
| Yeni konaklama / ödeme | Evet | Evet; ikincil işlem menüsü | Evet |
| Kendi konaklamasını ilk 24 saatte düzeltme | Evet | Evet | Evet |
| Eski kayıtları düzeltme / ödeme iptali | Hayır | Evet | Evet |
| Para iadesi | Hayır | Evet | Evet |
| Kayıtlar arası sonradan avans aktarma | Hayır | Evet | Evet |
| Bağlantısız kaydı kalıcı silme | Hayır | Hayır | Evet |
| Kullanıcı ve yetki yönetimi | Hayır | Hayır | Evet |

Yeni ödeme sırasında fazla paranın ilk sahibini seçmek, daha önce kaydedilmiş avansı başka hesaba aktarmaktan ayrıdır. Personel ilk kayıt akışında gerekli seçimi yapabilir; sonradan aktarım patron/admin işlemidir. Bu ayrım arayüzde açıklanacak.

Personel, ödeme işlemi için gerekli hesap/konaklama borcunu görebilmeli; işletmenin bütün alacak toplamı patron/admin görünümünde kalacak. Uygulamadan önce mevcut izin matrisiyle bu ekran ihtiyaçları eşleştirilecek.

## 4. Tasarım kararları

İlk görsel taslağın kapsamı tüm uygulamayı göstermiyordu. Kullanıcının sonraki açıklamasıyla mevcut tasarım ve bütün özellikler temel alınacak; toplu kaldırma yapılmayacak. Ayrıntılı mevcut → önerilen eşleştirme `mevcut-tasarimi-koruma-plani.md` dosyasındadır. Yeni görseller çalışan ekran görüntüsü değil, mevcut Blade/CSS şablonlarına dayalı taslaklardır.

Mevcut Lemon marka dilindeki koyu lacivert ve ölçülü altın vurgusu korunacak. Renkli kart kalabalığı yerine okunaklı tutarlar, boşluk ve açık işlem adları kullanılacak.

**Personel / bilgisayar:** Hesap arama, Konaklama Ekle ve Ödeme Al. Hesaplar ve konaklamalar taranabilir tablolar; girişler yan panel. İşlem bitince filtre/sayfa konumu korunur. Klavyeyle kullanılabilir formlar.

**Patron / telefon:** Para birimi bazında kalan alacak; borçlu hesaplar; son tahsilatlar. Hesap detayı dokunarak açılır. İşlem yap ikincil eylemdir. Büyük dokunma alanları, alt gezinme ve okunaklı kartlar.

**Pencere seçimi:** Kısa işlemler küçük pencere; kapsamlı ödeme ve konaklama masaüstünde yan panel, telefonda tam ekran panel. Hesap geçmişi/raporlar ayrı sayfadır. İç içe pencereler yoktur. Kaydetmeden önce finansal etki formun içinde görünür; basit kayıtlar gereksiz onay adımlarıyla yavaşlatılmaz.

İlk taslaklarda hayali adlar ve örnek tutarlar kullanılır. Taslakların düğmeleri yalnız görsel akışı gösterir; gerçek veriye yazmaz.

## 5. Uygulama sırası ve tamamlanma koşulları

### Aşama 0 — Tasarım ve plan onayı

Teslim: Personel bilgisayar ekranı, konaklama/ödeme panelleri, patron mobil özeti ve hesap detayı taslakları; bu karar belgesi.

Bitti sayılması: Kullanıcı görsel yönü ve işleyişi inceler. Tasarım onayı ile geliştirmeye başlama onayı ayrı anlaşılır. Bu aşamada gerçek sisteme değişiklik yoktur.

### Aşama 1 — Gerçek verinin güvenliği ve temel düzeltmeler

Teslim: Doğru kaynak/canlı sürüm eşlemesi; ayrı geliştirme/test ortamı; yedek ve geri yükleme provası; hesap/para birimi bazında başlangıç bakiye raporu. Oturum kapatma, tekrar gönderim, eşzamanlı işlem ve yayın kontrolleri.

Bitti sayılması: Yedek geri yüklenebilir; PHP/MySQL testleri çalışır; iki kez gönderilen ödeme tek kayıt üretir; pasifleştirilen kullanıcı erişemez. Bakiye referansı saklanır.

### Aşama 2 — Firma ve bireysel hesaplar, hızlı kayıt

Teslim: Firma/kişi hesap yapısı, mevcut firma bağlantılarını koruyan geçiş, firma boşsa bireysel konaklama, mevcut kişi seçimi; sade personel ekranı ve konaklama paneli.

Bitti sayılması: Mevcut firma kimlikleri ve mali geçmiş bozulmaz. Aynı adlı farklı kişiler karışmaz. Firma seçilmiş konaklamada ikinci bireysel borç oluşmaz. Temel kayıt bilgisayarda klavyeyle ve telefonda dokunarak tamamlanabilir.

### Aşama 3 — Ödeme dağıtımı, döviz, avans ve iade

Teslim: Ödeyen kişi, otomatik/seçimli dağıtım, kısmi ödeme, para birimleri arası borç kapatma, avans sahipliği, iptal ve iade. Tek ödeme paneli ve kayıt öncesi etki özeti.

Bitti sayılması için örnekler:

- Beş adet 1.000 TL borca 2.500 TL ödeme → ilk iki kayıt ödendi, üçüncüde 500 TL kaldı, son ikisi ödenmedi.
- Yalnız ikinci/üçüncü konaklama seçilirse ilk konaklama açık kalır.
- 5.000 TL borca 40 TL/EUR işlem kuruyla 100 EUR → 1.000 TL kalan borç; gerçek tahsilat 100 EUR.
- 3.000 TL borca aynı kurla 100 EUR → borç sıfır; 25 EUR avans. EUR nakit girişi yalnız bir kez oluşur.
- Konaklama iptali alınan parayı silmez; dağılım çözülür, avans kullanılabilir olur.
- İade yalnız kullanılabilir tutar kadar yapılır. Bağlı ödeme/dağılım/avans işlemindeki başarısızlık yarım kayıt bırakmaz.

Mevcut kasa entegrasyonu bu aşamada korunur: nakit hangi para biriminde alındıysa o para birimiyle hareket oluşur; kart/havale fiziksel nakit kasasını artırmaz. Kasanın kapsamlı ekran geliştirmesi daha sonraki aşamadır.

### Aşama 4 — Patron mobil takip ve raporlar

Teslim: Mobil özet, borçlu hesaplar, hesap ekstresi, son tahsilatlar, tarih/hesap filtreleri, yazdırma ve Excel dışa aktarımı. Patronun gerektiğinde işlem yapabileceği ikincil menü.

Bitti sayılması: Normal dökümde iptaller yok; toplamlar doğru. Ekran ile aynı filtrede Excel sonucu uyuşur. Patron telefondan bir hesabın borcunu ve geçmişini yardımsız bulur. Farklı para birimleri kendi biriminde gösterilir.

### Aşama 5 — Eski kayıtların kontrollü uyarlanması ve yayın

Teslim: Mevcut tahsilatların konaklamalara dağıtım önizlemesi, istisna listesi, kullanıcı kontrolü ve onaylı geçiş. Mevcut kayıtları koruyarak yayın.

Bitti sayılması: Eski ve yeni hesap/para birimi bakiyelerinde açıklanamayan fark sıfır. Eksik kur, belirsiz kişi veya özel konaklamaya ait ödeme tahmin edilmez; kontrol listesine alınır. Geçiş tekrar çalıştırıldığında ikinci tahsilat/borç oluşmaz. Yayın öncesi son yedek ve geri dönüş adımları hazırdır.

Canlı veri girişi geçiş sırasında devam ediyorsa kısa kayıt duraklatma penceresi veya güvenilir fark aktarımı uygulanır. Eski anlık kopyayla yeni girişlerin üzerine yazılmaz.

## 6. Kodlamadan önce teknik tasarımda çözülecek ayrıntılar

Bu bölüm kullanıcıdan teknik karar beklemez. Geliştirme sırasında alternatifler değerlendirilip önemli iş etkisi varsa kısa bir örnekle kullanıcıya sorulur.

- Hesap sahibi, konaklayan ve ödeyen kişi birbirinden ayrı temsil edilmeli; eski firma FK/morph bağlantıları kaybolmamalı.
- Para birimleri arası tahsilatta alınan para, borç para birimindeki kapama ve dönüşüm bağlantısı ayrı izlenmeli. EUR tahsilatı TL borca gelişigüzel kredi olarak eklemek iki para biriminde yanlış bakiye bırakır. Append-only defter ilkesi korunarak dönüşüm kayıtları tasarlanmalı.
- Dağıtımlar tek tahsilata bağlı yardımcı kayıtlardır, yeni gelir değildir. Kaynak ve hedef tutarları, kur yönü, yuvarlama ve avans birlikte tutarlı olmalı.
- Farklı para birimli birden fazla borç varsa kapatılacak para birimi açık seçilmeli. Otomatik dağıtım farkında olmadan para birimleri arasında gezinmemeli. EUR/USD gibi TL dışı çapraz senaryolar için kur kuralı ayrıca somutlaştırılmalı; sessiz varsayım yapılmamalı.
- “En eski” sırası için öneri: konaklama giriş tarihi, eşitlikte kayıt kimliği. Eski tarihli sonradan ekleme, tamamlanmış ödeme dağılımlarını sessizce yeniden yazmamalı.
- Döviz avansı ileride farklı kurla kullanıldığında yeni kullanım kuru ayrı saklanmalı; ilk tahsilat kuru değiştirilmemeli. Kur farkı gösteriminin kapsamı tasarımda netleşmeli.
- İptali normal listeden gizlemek, ters kayıtları bakiye hesabından çıkarmak anlamına gelmez. Normal rapor ve denetim geçmişi ayrılmalı.
- Finansal alanı değişen ve önceden ödeme dağıtılmış konaklamalarda yeniden dağılım/avans sonucu atomik ve görünür olmalı.
- Kalıcı silme öncesi FK dışındaki polimorfik bağlantılar ve işlem geçmişi de kontrol edilmeli. Silme ilişkili veriyi zincirleme silmemeli.
- Yetki, eşzamanlılık, işlem anahtarı, kuruş yuvarlama ve günlük finansal toplamlar gerçek MySQL/MariaDB üzerinde test edilmeli.

Eski CLAUDE.md içindeki “Guest CRM yok / allocation yok / döviz çevrimi yok” sınırları bu konuşmada kabul edilen dar kapsamla değişiyor. Geliştirme onayından sonra kılavuz güncellenecek. Bu izin kapsamlı misafir CRM, kimlik/pasaport saklama, otomatik kur veya PMS ekleme izni değildir.

## 7. Sonraki ana aşamalar

1. Kasa defterini günlük çalışma, vardiya, devir ve patron mobil takibiyle tamamlamak.
2. PMS ve rezervasyon süreçleri için ayrı ihtiyaç çalışması.
3. Rezervasyon kanalları ve otomatik kur için sağlayıcı, erişim, maliyet ve senkronizasyon incelemesi.

Kesin sürüm numarası ve takvim, mevcut canlı sürüm ve test ortamı doğrulandıktan sonra seçilecek. İlk teslimat bir framework yükseltme projesi değil; güvenilir ve sade cari takip geliştirmesidir.

## 8. Onay kapsamı

Kullanıcı “Bu plana göre geliştirmeye başlamamı onaylıyor musun?” sorusuna “onaylıyorum” yanıtını verdi. Yerel geliştirme ve testler yetkilendirildi. Gerçek sistem `https://lemon.derspros.com.tr` adresindedir. Canlı kayıtlar korunacak; önizleme ve bakiye kontrolünden önce geçmiş ödeme dağılımları uygulanmayacak.
