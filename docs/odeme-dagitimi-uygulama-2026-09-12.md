# Şahsi ödeme ve konaklama dağıtımı

Durum: Yerel geliştirme. Canlıya aktarılmadı; geçmiş gerçek tahsilatlar
eşleştirilmedi. Bu adım aynı cari hesap ve aynı para biriminde çalışır.

## Kullanım

Tahsilatta cari hesabı seçtikten sonra ödeyen hesap sahibi veya başka bir kişi
olarak belirtilebilir. Şahsi ödemede kişinin adı zorunludur; cari borcun sahibi
değişmez. Ödeyen adı işlem anındaki haliyle saklanır ve makbuzda da gösterilir.

Varsayılan dağıtım giriş tarihi, ardından kayıt numarasına göre en eski aktif
konaklamadan başlar. Belirli konaklamalar seçilirse yalnızca bunların arasında
aynı sıra uygulanır. Kısmi ödeme mümkündür. Örneğin beş adet 1.000 TL borca
2.500 TL ödeme iki kaydı kapatır, üçüncü kaydın 500 TL'sini karşılar.

Konaklama listesi ve detayında Ödendi / Kısmen ödendi / Ödenmedi ve ödenen-kalan
tutar görünür. Tahsilat detayında dağılım ve kullanılabilir avans görünür.
Fazla ödeme seçili cari hesapta, alındığı para biriminde kalır. Sonraki yeni
konaklamada aynı hesaptaki aynı para birimli avans, tahsilat tarihine göre kullanılır.

## Düzeltme ve iptal

- Konaklama iptali: dağılımlar ters kayıtla çözülür, ödeme korunur ve avans olur.
- Tahsilat iptali: cari, varsa nakit kasa ve dağılımlar aynı transaction'da geri
  alınır. Hata halinde hiçbir parça tek başına kaydedilmez.
- Konaklama tutarı azalırsa yalnızca fazlalık avansa döner. Hesap veya para
  birimi değişirse eski ödeme eski hesapta kalır, bağlantı çözülür.
- Tutar yükseltilirse mevcut dağılım korunur ve artış açık borç olarak görünür.
- Silme yerine dağılım geçmişine ters satır eklenir. UPDATE/DELETE veritabanı
  tetikleyicileriyle engellenir; her dağılım en fazla bir kez ters kaydedilebilir.

## Geçiş ve teknik notlar

Yeni migration `payments`e nullable `payer_type`, `payer_name`, `allocation_mode`
ekler. Eski satırlar NULL kalır; ödeyen veya geçmiş eşleşme tahmin edilmez.
Geçmiş eşleşmesi olmayan aktif ödeme varsa ilgili konaklamaların belirsiz ödeme
durumu açıkça belirtilir; kesin hesap bakiyesi cari ekstreden okunur.
Geçmiş eşleştirme önizlemesi ve bakiye mutabakatı yapılmadan canlı geçiş yapılmaz.

`payment_allocations` mevcut ödeme ile konaklamayı bağlar; yeni gelir veya gider
üretmez. Hesap satırları önce sıralı kilitlenir; aynı hesaptaki ödeme, konaklama
düzeltme ve iptaller birbirini bekler. Para hesapları tamsayı kuruşla yapılır.
Modüller arası erişim servisler üzerinden yürür; Ledger diğer modülleri tanımaz.

## Kalan onaylı adımlar

- TL borca EUR gibi farklı para birimindeki ödeme ve işlem kuru ile kapama.
- Fazla şahsi ödemenin firma veya kişinin avans hesabında tutulması seçimi,
  kontrollü avans aktarımı ve iade.
- Eski tahsilatları önizleme ve mutabakatla eşleştirme.
- Hızlı işlem panelleri ve raporlama/tasarım planının kalan işleri.

## Kontroller

820 testlik toplu çalışmada 819 test geçti; tek hata eski “firma” boş liste
metnini bekleyen testti. Beklenti yeni “cari hesap” metnine uyarlandı.
Sonrasında ödeme dağıtımı, eşzamanlılık, tahsilat paneli ve bireysel konaklama
testleri yeniden çalıştırıldı: **37 test, 142 doğrulama, tamamı başarılı**.
Bu tekrar, avans kullanımındaki toplu sorgu iyileştirmesini de kapsar.
Tam 820 testlik paket bu metin düzeltmesinden sonra tekrar çalıştırılmadı.

Kontroller gerçek MariaDB test veritabanında yapıldı. Kapsam: gerçek ikinci
veritabanı bağlantısıyla hesap kilidi, nakit tahsilatta tek kasa kaydı, hatalı
dağıtımda tam geri alma, eski modelle ikinci iptalin reddi ve SQL ile dağılım
geçmişini değiştirme/silmenin engellenmesi.
Tarayıcıda yerel 2.000 TL konaklamaya 750 TL şahsi ödeme girildi; kalan 1.250 TL
ve Kısmen ödendi durumu görüldü. PHPStan, Pint ve arayüz derlemesi başarılı;
`git diff --check` temiz. Canlı veritabanında işlem yapılmadı.
