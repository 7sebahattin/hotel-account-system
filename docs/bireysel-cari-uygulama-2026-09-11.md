# Bireysel cari ve konaklama — uygulama notu

Bu adım yerel geliştirme dalında uygulanmıştır; canlıya aktarılmamıştır.
Kullanıcı cPanel yedeğini indirdiğini bildirdi. Yedeğin içeriği ve geri yükleme
başarısı henüz doğrulanmadı.

## Çalışan akış

- Cari hesaplar firma veya bireysel olarak açılabilir; liste türüne göre süzülür.
- Konaklamada firma seçilirse borç firmaya yazılır. Firma boş bırakılırsa mevcut
  bireysel hesap seçilir ya da misafir adına yeni hesap açılır.
- Aynı isim otomatik birleştirilmez. Mevcut kişi seçiminde ad, hesap numarası ve
  varsa telefon birlikte görünür. Telefon cari kartından düzenlenebilir.
- Hesap, konaklama ve cari borç tek veritabanı işlemiyle yazılır; biri hata
  verirse tamamı geri alınır. Düzenlemede hesap değişirse eski borç ters kayıtla
  kapanır ve yeni hesaba yazılır. Normal düzenlemede hesap türü değiştirilemez.
- Mevcut tahsilat, bakiye ve ekstre aynı bireysel hesap üzerinden çalışır.
  Konaklama bazında ödeme dağıtımı, ödeyen/borçlu ayrımı ve çapraz döviz ödeme
  bu adımın kapsamında uygulanmış değildir; onaylı planın sonraki adımlarıdır.

## Veri geçişi

Yeni migration yalnızca `companies.account_type` kolonunu ekler. Varsayılan
`company` mevcut firmaların kimliklerini, tutarlarını ve cari bağlantılarını
korur. Eski konaklamalar veya tahsilatlar dönüştürülmez. Bireysel hesap varken
kolonu kaldıran rollback veri kaybını önlemek için durur; canlıda ileri düzeltme
ve geçiş öncesi yedek/bakiye kontrolü kullanılmalıdır.

## Yerel ortam

Uygulama: `http://127.0.0.1:8187`, MariaDB: `127.0.0.1:33117`.
Geliştirme veritabanı `hotel_account_dev`, otomatik testler `hotel_account_test`.
Taşınabilir araçlar `.local/` altında, git dışında tutulur.
Gerçek kayıtlar bu ortama kopyalanmadı. Örnek Misafir — Yerel Deneme kaydı
yalnızca tarayıcıdan akış kontrolü için oluşturuldu.

## Doğrulama

Yeni `IndividualStayTest`: firmasız giriş, açık kişi seçimi, aynı adla ayrı
hesap, firma akışının korunması, çakışan seçimlerin reddi, pasif/silinmiş kişi,
düzenleme ve borcun taşınması, tür filtresi, personelin bireysel tahsilatı,
cari yazma hatasında tüm işlemin geri alınması.
Sonuçlar:

- Değişiklik öncesi temel: 790 test / 2.475 doğrulama başarılı.
- İlk toplu tarama: 798 testin 792'si başarılı. Altı hata eski menü adını veya
  eski zorunlu firma alanını bekleyen testlerden geldi. Bu beklentiler yeni
  davranışa göre güncellendi; yetki ve bakiye kontrolleri korunarak.
- Son değişikliklerden sonra etkilenen gruplar: 137 test / 491 doğrulama başarılı
  (yeni 10 bireysel hesap testi dahil).
- PHPStan: hata yok. Pint ve `git diff --check`: temiz. Vite build: başarılı.
- Tarayıcı: 2.000 TL bireysel konaklama oluşturuldu, bireysel kart ve ekstrede
  borç görüldü; karttan yeni girişte aynı kişi seçili geldi, EUR seçiminde manuel
  kur alanı açıldı. Masaüstü ekstre görsel olarak kontrol edildi.

Canlı yayın yapılmadı; commit/push yapılmadı. Ödeme dağıtımı ve yeni hızlı giriş
panelleri için bu adımın üzerine devam edilebilir.
