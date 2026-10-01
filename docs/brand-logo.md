# Marka logoları

Marka ekranı `Yönetim → Marka Logoları` altında yalnız Admin ve Patron'a açıktır.
İki ayrı PNG/JPEG/WebP dosyası (en çok 2 MB) yüklenir: açık zemin ve koyu zemin.
SVG kullanıcı yüklemesi kabul edilmez; sanitizasyon altyapısı yoktur.

`BrandLogoService` önce `storage/app/public/branding/logo-on-light.*` veya
`logo-on-dark.*` dosyasını, yoksa `config/hotel.php` içindeki ilgili varsayılan
`public/images/branding/` asset'ini seçer. Eskiden özelleştirilmiş `brand.logo`
değeri, koyu menü logosu için geriye dönük olarak çalışır. Yüklenen dosyalar
sabit isimle öncekinin yerini alır; veritabanı veya finansal kayıt oluşturulmaz.

Yüklenen logo doğrudan `/storage` symlink'ine bırakılmaz. Uygulama yalnız
`on-light` ve `on-dark` varyantlarını kabul eden salt-okunur
`/branding/logo/{variant}` rotasından dosyayı servis eder. Böylece shared
hosting'de `public/storage` symlink'i eksik olsa bile logo görüntülenir; keyfi
storage yolu web'e açılmaz. URL'ye içerik hash'i eklendiği için logo
değiştirildiğinde tarayıcı önbelleği eski görseli tutmaz.

En iyi görünüm için şeffaf, kırpılmış nihai logo dosyaları kullanılmalıdır.
Yazdırma şablonu her zaman `on-light`, koyu gezinti alanları `on-dark`
varyantını kullanır.
