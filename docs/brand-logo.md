# Marka logoları

Marka ekranı `Yönetim → Marka Logoları` altında yalnız Admin ve Patron'a açıktır.
İki ayrı PNG/JPEG/WebP dosyası (en çok 2 MB) yüklenir: açık zemin ve koyu zemin.
SVG kullanıcı yüklemesi kabul edilmez; sanitizasyon altyapısı yoktur.

`BrandLogoService` önce `storage/app/public/branding/logo-on-light.*` veya
`logo-on-dark.*` dosyasını, yoksa `config/hotel.php` içindeki ilgili varsayılan
`public/images/branding/` asset'ini seçer. Eskiden özelleştirilmiş `brand.logo`
değeri, koyu menü logosu için geriye dönük olarak çalışır. Yüklenen dosyalar
sabit isimle öncekinin yerini alır; veritabanı veya finansal kayıt oluşturulmaz.

Production'da mevcut `php artisan storage:link` adımı ve `APP_URL` HTTPS değeri
gereklidir. Link yoksa yüklenen dosyanın URL'si servis edilemez; yayın öncesi
görsel kontrolde bu doğrulanmalıdır. En iyi görünüm için şeffaf, kırpılmış nihai
logo dosyaları kullanılmalıdır. Yazdırma şablonu her zaman `on-light`, koyu
gezinti alanları `on-dark` varyantını kullanır.
