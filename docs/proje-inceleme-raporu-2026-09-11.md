# Hotel Account System / Lemon — Proje İnceleme Raporu

İnceleme tarihi: 11 Eylül 2026

Amaç: sürüm güncelleme yol haritası için mevcut durum ve önceliklerin belirlenmesi.

İncelenen commit: `a426b3e823b008f820cd2d1bbc111cecd2eb1337` — yerel `main`.
Kod geçmişindeki son özellik paketi: **v2.1.1-D**. Bu bilgi, canlıda çalışan sürümün doğrulaması değildir.

## 1. Yönetici değerlendirmesi

Proje, temel bir prototipin ötesinde; firma cari hesabı, konaklama, tahsilat, vardiya kasası ve denetim kayıtlarını kapsayan, belirgin iş kuralları olan bir uygulama. Finansal çekirdeğin mevcut tasarımını koruyarak geliştirmeye devam etmek uygun görünüyor. Bu inceleme, yeniden yazım veya teknoloji değişikliği gerektiren bir bulgu ortaya koymadı.

Bir sonraki sürümde öncelik **oturum güvenliği, eşzamanlı işlem korumaları ve tekrarlanabilir yayın süreci** olmalı. Bunların ardından Excel dışa aktarımı ve geçmiş veri göçü, projenin “Excel'i bırakma” hedefini tamamlayacak en doğrudan işlerdir.

Uygulamanın üretime hazır olduğu bu incelemeyle kesinleştirilemez: arka uç testleri, canlı veritabanı, mevcut yedekler ve gerçek kullanıcı akışları burada çalıştırılarak doğrulanmadı.

## 2. İncelemenin kapsamı ve kaynak konumu

Açık çalışma alanı `C:/Users/AsyaFresh/Documents/ChatGPT/hotel-account-system` yalnızca başlangıç durumundaki boş bir Git deposu içeriyordu. Codex proje kaydının etiketinde bulunan aşağıdaki konumda asıl kaynaklar tespit edildi:

`C:/Users/AsyaFresh/Documents/Codex/2026-09-11/mer/hotel-account-system`

Bu rapor açık çalışma alanının `docs` klasörüne yazıldı. İnceleme asıl kaynak konumunda yapıldı; uygulama kaynak kodu değiştirilmedi. Ön yüz doğrulaması için burada `npm ci --no-audit --no-fund` çalıştırıldı; `node_modules` ve `public/build` çıktıları oluştu. Commit, push veya dağıtım yapılmadı.

Yöntem: `AGENTS.md` ve bağlayıcı `CLAUDE.md`, paket kilitleri, Git geçmişi, modüller, kritik servis/model/policy akışları, migration'lar, test kaynakları, PWA kodu ve CI/deploy dosyaları incelendi. Bu çalışma tam kapsamlı sızma testi veya her satırın denetimi değildir.

| Ölçüm | Yerel kaynakta gözlenen |
|---|---|
| Git tarafından izlenen dosya | 444 |
| Modül klasörü | 14 |
| Migration dosyası | 30 |
| `*Test.php` dosyası | 62 |
| `#[Test]` bildirimi | 706; çalıştırılmış test veya veri sağlayıcılı toplam senaryo sayısı değildir |
| Yerel sürüm etiketi | `git tag --list` boş |
| Kaynak çalışma ağacı | İnceleme başlangıcında temiz |

## 3. Ürün ve teknoloji envanteri

| Alan | Mevcut işlev / sınır |
|---|---|
| Kimlik ve yetki | Giriş/çıkış, başarısız giriş sınırı, aktif/pasif kullanıcı, rol/yetki yönetimi |
| Firmalar | Cari kart, arama/filtre, bakiye ve ekstre; hareketi olan firmayı silme koruması |
| Konaklama | Firma ve oda bağlantısı, toplam/günlük fiyat, tarih/gece hesabı, güncelleme ve iptal |
| Cari defter | Borç/alacak, ters kayıt, para birimi bazında bakiye |
| Tahsilat | Nakit, kart, havale, çek ve diğer kanallar; iptal; makbuz; vardiya ataması |
| Kasa | Açılış/kapanış, hareketler, kategoriler, sayım farkı, personel devri |
| Panolar | Finans, operasyon ve personel özetleri |
| Odalar | Oda tanımı ve konaklama bağlantısı; rezervasyon/housekeeping sistemi değil |
| Döviz | Elle girilen işlem kuru ve TL karşılığı; eksik geçmiş kurlarını doldurma ekranı |
| Makbuz defteri | Ayrı belge oluşturma/yazdırma; cari, tahsilat veya fiziksel kasa hareketi üretmez |
| Denetim | Kullanıcı/işlem değişiklik kayıtları ve görüntüleme |
| Mobil | Responsive Blade arayüzü ve temel kurulabilir PWA; çevrimdışı finansal giriş yok |
| Excel | Planlanmış; import/export uygulaması ve ilgili paket mevcut kaynakta bulunmadı |

Mimari: modüler monolit, Laravel/Blade ve servis katmanları. Kilit dosyasında Laravel **13.30.1**, Spatie Permission **8.3.0**, Larastan **3.11.0**, PHPUnit **12.5.34** bulunuyor. Composer platform hedefi PHP **8.3.0**. Ön yüz Tailwind 4 ve Vite kullanıyor; bu incelemede çalışan Vite sürümü **8.2.2** idi. CI Node 22 ve MySQL 8.4 ile tanımlı.

Bunlar yerel bağımlılık envanteridir; güncel sürüm veya güvenlik duyurusu karşılaştırması yapılmadı. PHP 8.3 hedefi belgelerde paylaşımlı hosting kısıtıyla açıklanıyor. Runtime yükseltmesi, uygulama sürüm planından ayrı bir uyumluluk çalışması olmalı.

## 4. Korunması gereken güçlü yönler

- `Core/Support/Money.php`, para işlemlerini tamsayı kuruş ve kontrollü ondalık ayrıştırma üzerinden yürütüyor. Döviz çarpımında taşma kontrolü bulunuyor.
- `LedgerService` ve ilgili migration'lar, güncelleme/silme yerine ters kayıt kullanıyor. Defterde ham SQL UPDATE/DELETE işlemlerini engelleyen veritabanı tetikleyicileri var; ikinci ters kayıt benzersiz kısıtla korunuyor.
- Tahsilat ve konaklamanın cari etkileri transaction içinde ele alınıyor. Nakit tahsilatın kasa etkisi de aynı iş akışına dahil; kart/havale fiziksel kasayı artırmıyor.
- Kasa servisinde vardiya kilitleri mevcut. `CashBookConcurrencyTest` yalnızca sahte nesnelerle değil, ikinci PDO bağlantısıyla veritabanı kilitlerini sınamayı amaçlıyor.
- Yetki, bakiye, iptal, denetim, döviz ve para hesabına yönelik geniş test kaynakları mevcut. CI format, statik analiz, build ve MySQL test adımlarını içeriyor.
- PWA yalnızca statik varlıkları önbellekliyor; finansal HTML navigasyonları ağdan alınıyor, yazma istekleri çevrimdışı kuyruğa eklenmiyor.

Bu yapıların varlığı olumlu bir tasarım göstergesidir; bu oturumda arka uç testlerinin geçtiği anlamına gelmez.

## 5. Öncelikli bulgular

Öncelikler: **P1**, bir sonraki yayın öncesi ele alınması gereken yüksek risk; **P2**, takip eden sürümde planlanacak eksik veya bakım işi. Aşağıdaki çalışma zamanı etkileri, aksi belirtilmedikçe statik kod incelemesine dayalıdır.

### B01 — P1: Kullanıcı pasifleştirme mevcut oturumları sonlandırmıyor

**Kanıt:** `Auth/Http/Requests/LoginRequest.php:68` aktifliği giriş sırasında kontrol ediyor. `Users/Services/UserService.php:73` içindeki `setActive` kullanıcı alanını değiştiriyor; oturum iptali veya remember-token yenilemesi yapmıyor. `bootstrap/app.php:15` içinde aktif kullanıcı middleware'i yok. İncelenen policy'ler yetkilere bakıyor.

**Etki:** Önceden giriş yapmış bir kullanıcı pasifleştirildiğinde mevcut oturumuyla erişmeye devam edebilir. Şifre değişikliği sonrası diğer oturumların iptali de ayrıca doğrulanmalı.

**Öneri:** Her doğrulanmış istekte aktiflik kontrolü; pasifleştirmede mevcut oturumlar ve kalıcı girişlerin iptali.

**Kabul:** Kullanıcı A giriş yapar, yönetici A'yı pasifleştirir; A'nın sonraki okuma ve yazma istekleri reddedilir. Kalıcı giriş çereziyle yeniden erişim de engellenir. Gerçek session driver'ıyla test eklenir.

### B02 — P1: Tek seferlik geçmiş kur düzeltmesi eşzamanlı yazmaya açık

**Kanıt:** `Stays/Services/StayService.php:412` ve `Payments/Services/PaymentService.php:432`, kurun boş olup olmadığını transaction öncesinde eldeki model üzerinde kontrol ediyor. Sonraki yazımda kilitleyerek yeniden okuma veya `WHERE exchange_rate_to_try IS NULL` koşullu güncelleme yok. Kur migration'larındaki CHECK'ler değer çiftinin geçerliliğini sağlıyor; ilk yazımdan sonra değişmezliği sağlamıyor.

**Senaryo:** İki istek aynı boş kur kaydını okur; biri 35, diğeri 36 yazar. İkinci işlem birincinin değerini değiştirebilir; iki denetim kaydı da eski değeri boş gösterebilir.

**Öneri:** Kayıt transaction içinde kilitlenerek tekrar doğrulanmalı veya atomik koşullu yazım yapılmalı. Konaklama tutarıyla kur düzeltmesinin aynı anda değişmesi de aynı test grubuna alınmalı.

**Kabul:** İki bağımsız bağlantıdan düzeltme yapılınca yalnızca biri başarılı olur; diğeri anlaşılır çakışma sonucu alır. Kaydedilmiş ilk kur ve denetim izi korunur.

### B03 — P1: Oluşturma isteklerinde sunucu tarafında tekrar koruması yok

**Kanıt:** `resources/js/app.js:316` gönderim butonlarını kilitliyor. Tahsilat/konaklama oluşturma servisleri her başarılı çağrıda yeni kayıt üretir; aynı kullanıcı işlemini tanımlayan idempotans anahtarı tespit edilmedi. Makbuz numarasının benzersizliği farklı oluşturma çağrılarını tek işleme dönüştürmez.

**Etki:** Aynı geçerli POST isteğinin ağ hatası sonrası yeniden gönderilmesi iki ayrı finansal kayıt oluşturabilir. Defterde aynı hareketi iki kez ters çevirmeme koruması bu sorunu çözmez.

**Öneri:** Finansal oluşturma akışlarına işlem anahtarı ve veritabanı benzersizliği eklemek; kullanıcı tarafından bilerek girilen aynı tutarlı iki farklı işlemi engellememek.

**Kabul:** Aynı anahtarla tekrarlanan/eşzamanlı istekte tek kaynak, tek cari etki ve gerekiyorsa tek kasa etkisi oluşur.

### B04 — P1: Deploy, aynı commit için başarılı CI sonucuna bağlı değil

**Kanıt:** `.github/workflows/deploy.yml:201` build işi yalnızca guard'a; `:270` deploy işi guard ve build'e bağlı. Workflow içinde test sonucu/commit eşleşmesi doğrulaması, dal kısıtı veya GitHub Environment bildirimi görülmedi. Hedef dizin ve elle onay korumaları mevcut.

**Etki:** Yetkili bir operatör testleri geçmemiş bir commit'i dağıtabilir. CI'nin ayrı workflow olarak varlığı bunu kendiliğinden engellemez. GitHub tarafındaki branch protection ve environment ayarları bu incelemede okunmadı.

**Öneri:** Dağıtılacak tam commit için kalite kapısı, izin verilen dal/etiket politikası ve yayımlanan commit'in kayıt altına alınması.

**Kabul:** Başarısız veya eksik CI sonucu olan commit dağıtılamaz; artifact, CI ve yayın kaydındaki commit aynı olur.

### B05 — P1: Yayın tamamlama ve geri dönüş adımları operatöre bağlı

**Kanıt:** Deploy workflow'u FTPS dosya aktarımından sonra bitiyor. Cache temizleme, migration, rol/yetki seed'i ve smoke testler `docs/release-checklist-v2.1.1.md` içinde elle yürütülüyor. Belgelerde geçmiş cache/permission kaynaklı üretim olayları anlatılmış. Kod ağacına yerinde dosya aktarımı uygulanıyor.

**Etki:** Yarım aktarım veya eksik sunucu adımı kod/şema/yetki uyumsuzluğu yaratabilir. Dosya aktarımının tamamlanması, uygulama sürümünün başarıyla açıldığı anlamına gelmiyor.

**Öneri:** Hosting olanaklarına uygun, adım sonucunu kaydeden tekrarlanabilir yayın prosedürü; bakım penceresi, yedek referansı, migration/seed/cache ve sağlık kontrolü. SSH erişimi varsayılmamalı.

**Kabul:** Staging'de tam yayın ve yarıda kalan yayından kurtarma provası tamamlanır. Önceki kod artifact'ı ve veritabanı kurtarma yöntemi doğrulanır. Canlıda otomatik migration rollback yerine belgelerdeki ileri düzeltme ilkesi korunur.

### B06 — P2: Son aktif yönetici korumasında yarış koşulu var

**Kanıt:** `Users/Services/UserService.php:131` içindeki `isLastActiveAdmin`, diğer aktif yöneticileri kilitlemeden sorguluyor. Pasifleştirme/silme/rol değiştirme yollarında ortak bir serileştirme koruması görülmedi.

**Etki:** İki yönetici üzerinde eşzamanlı işlemde her işlem diğerini aktif görerek son aktif yönetici kalmamasına yol açabilir.

**Kabul:** İki bağımsız bağlantıdan pasifleştirme veya rol düşürme sonrası en az bir aktif yönetici kalır; işlemlerden biri kontrollü reddedilir.

### B07 — P2: Denetim günlüğünün ham SQL karşısındaki koruması daha zayıf

**Kanıt:** AuditLog model/policy korumaları mevcut; `AuditLog/Database/Migrations/2026_09_07_030000_create_audit_logs_table.php` içinde ledger'daki gibi UPDATE/DELETE tetikleyicileri bulunmuyor.

**Etki:** Uygulama katmanını atlayan query builder veya SQL işlemleri denetim izini değiştirebilir. Bu, dışarıdan doğrulanmış bir açık değil; iç veri bütünlüğü sınırıdır.

**Öneri/kabul:** Koruma düzeyi açıkça kararlaştırılsın. Veritabanı düzeyinde değişmezlik isteniyorsa kullanıcı FK'sının `nullOnDelete` davranışıyla birlikte tasarlansın ve ham SQL testleriyle doğrulansın.

### B08 — P2: Dokümanlar ve sürüm kimliği tek güncel durumu göstermiyor

**Kanıt:** Yayın kontrol listesi `develop @ 0185b15` ve A/A.1/B/B.1 kapsamını anlatıyor; oda kaydını kapsam dışında listeliyor. İncelenen kodda Rooms, FxCorrection ve ReceiptBook mevcut. Aynı belgede production boş denirken sonraki bölümlerde gerçek üretim olayları bulunuyor. Yerel Git etiketi yok.

**Etki:** Güncelleme kapsamı ve canlıda neyin bulunduğu yanlış varsayılabilir.

**Kabul:** Tek sürüm notu; sürüm–commit–şema eşlemesi; güncel dağıtım kontrol listesi; canlı/staging durumları tarih ve kanıtla kaydedilir. README'deki varsayılan admin anlatımı üretim seed korumasıyla uyumlu hale getirilir.

## 6. Ürün eksikleri ve operasyon doğrulamaları

**Excel geçişi:** `CLAUDE.md` §10 ve §14.1 hedefleri kodda tamamlanmış görünmüyor. Dışa aktarımda filtre/yetki eşitliği, para birimlerinin ayrılması ve büyük veri davranışı kabul ölçütü olmalı. Import için sheet bazlı eşleme, önizleme, hatalı satır raporu, tekrar çalıştırmada mükerrer kayıt önleme ve firma/para birimi bazında sıfır açıklanamayan bakiye farkı gerekli. Excel'deki kalan bakiye işlem olarak içeri alınmamalı; kontrol değeri olmalı.

**Şifre işlemleri:** Auth route'ları giriş/çıkışla sınırlı. Yönetici kullanıcı formundan şifre belirleyebiliyor; kullanıcının kendi şifresini değiştirmesi ve sıfırlama akışları ayrı bir teslimat olarak ele alınmalı.

**Yedek ve kurtarma:** `docs/deploy-cpanel.md:119` cron örneği ve geri yükleme tatbikatı öneriyor; gerçek zamanlayıcının kurulumu, offsite kopya ve başarılı restore kanıtı burada doğrulanmadı. “Yedek yok” sonucu çıkarılamaz. Kabul için işletmenin kabul edebileceği veri kaybı süresi ve toparlanma süresi belirlenmeli, ölçülmüş restore raporu sunulmalı.

**Ölçek:** Geçmiş döviz düzeltme listeleri `get()` ile tüm eksik kayıtları alıyor. Ekstre servisinde de tam koleksiyon alan yollar var. Excel geçmişi eklenmeden önce gerçekçi veri büyüklüğünde süre/bellek ölçülmeli; sayfalama veya parçalı işleme ölçüme göre planlanmalı. Mevcut veriyle performans sorunu yaşandığı doğrulanmadı.

**Modül sınırları:** Genel servis ayrımı iyi; PaymentService başka modüllerin model tiplerini de taşıyor. Kılavuzdaki katı “yalnız servis” kuralı ile uygulanan sözleşme netleştirilmeli. Sırf bu fark için geniş refactor gerekmiyor.

**İki makbuz akışı:** Cari tahsilat makbuzu `TM-…`, bağımsız makbuz defteri `MKB-…` kullanıyor. Bunların farklı etkileri kasıtlı. Kullanıcı kabulünde, bağımsız makbuz oluşturmanın tahsilat/kasa oluşturmadığının anlaşıldığı doğrulanmalı; otomatik bağlama talebi ayrı ürün kararıdır.

## 7. Yapılan doğrulamalar ve sınırlar

| Kontrol | Sonuç |
|---|---|
| Kaynak ve Git durumu | Gerçek kaynak konumu ve commit belirlendi |
| Bağımlılık kurulumu | `npm ci --no-audit --no-fund` başarılı; 56 paket kuruldu |
| Ön yüz üretim build'i | `npm run build` başarılı; CSS 28,47 kB, JS 11,15 kB ham çıktı |
| PHP/PHPUnit/Pint/PHPStan | Çalıştırılamadı: PATH'te PHP ve Composer bulunmadı; vendor yoktu |
| Veritabanı testleri | Çalıştırılmadı; MySQL komutu ve hazırlanmış test ortamı bulunmadı |
| Gerçek tarayıcı/mobil kabul | Yapılmadı; çalışan arka uç kurulmadı |
| Canlı sürüm, GitHub CI sonuçları, yedekler | Dış ortamdan doğrulanmadı |
| Güvenlik bağımlılık taraması | Yapılmadı; paketlerde açık var/yok hükmü verilmedi |

Sonuç: Ön yüz build'i doğrulandı. Arka uç bulguları kod kanıtına dayanıyor ve belirtilen regresyon senaryolarıyla doğrulanmalı. Kapsama yüzdesi veya “706 test geçti” iddiası yoktur.

## 8. Sürüm yol haritası için önerilen paketleme

Bu bölüm kesinleşmiş takvim veya sürüm taahhüdü değildir. Canlı başlangıç sürümü doğrulandıktan sonra numaralandırılacak iş gruplarıdır.

| Sıra | İş paketi | Ön koşul | Tamamlanma ölçütü |
|---|---|---|---|
| 0 | Ortam ve sürüm envanteri | Kaynak/canlı erişim bilgisi | Tek doğru proje konumu, canlı commit/şema bilgisi, çalışan PHP 8.3 + MySQL test ortamı, mevcut test sonuçları |
| 1 | Güvenilirlik ve yayın sertleştirme | Paket 0 | B01–B05 kapalı; B06 aynı pakete alınması değerlendirilmiş; yarış/tekrar testleri ve yayın/kurtarma provası başarılı |
| 2 | Excel'den çıkış | Paket 1, örnek Excel ve mutabakat kuralları | Filtreli export; prova import; tekrarsız aktarım; firma/para birimi bazında açıklanamayan fark yok; geri dönüş planı |
| 3 | Kullanım ve bakım iyileştirmeleri | Kullanıcı kabul geri bildirimi | Şifre akışları, büyük listeler, makbuz kullanım testi, güncel dokümanlar ve kararlaştırılmış audit koruması |

Yeni özellik içermeyen düzeltmeler için **yama**, Excel gibi yeni kabiliyetler için **minör sürüm** adaylığı uygundur; kesin numara canlı başlangıç sürümüne göre seçilmeli. Mevcut bulgular zorunlu bir majör sürüm ihtiyacı göstermiyor.

Rezervasyon, housekeeping, KBS, e-fatura, banka entegrasyonu, otomatik kur ve mobil API mevcut kapsam dışında tutulmuş. Bu rapor onları geliştirme kapsamına almıyor; ileride seçilirlerse ayrı ihtiyaç ve kabul çalışması gerekir.

## 9. Planlama toplantısında netleştirilecek bilgiler

1. Canlı sistemde hangi commit çalışıyor, aktif kullanıcı ve veri sayısı nedir?
2. Kullanıcıların en sık yaşadığı üç sorun veya zaman kaybı hangileridir?
3. Excel tamamen bırakıldı mı; aktarılacak dosyalar ve mevcut bakiye kontrolü hazır mı?
4. Yayın için kabul edilebilir kesinti, veri kaybı ve geri yükleme süreleri nedir?
5. Sonraki sürümün hedef tarihi ve geliştirici/test kapasitesi nedir?

Bu bilgiler olmadan tarih vermek yerine yukarıdaki bağımlılık ve kabul ölçütleriyle planlama yapılması daha güvenilir olacaktır.
