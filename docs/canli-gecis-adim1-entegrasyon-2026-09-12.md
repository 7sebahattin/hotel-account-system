# Canlı geçiş — ilk adım: güvenli entegrasyon

12 Eylül 2026. Kullanıcı adım adım canlı geçiş hazırlığına başlanmasını istedi.

## Yapılan işlem

- GitHub `origin/main` yeniden okundu; son commit hâlâ `ea6c438` (v2.1.1-E).
- Mevcut 63 değişmiş/yeni dosyanın listesi ve SHA-256 özeti alındı. İzlenen
  dosyalar binary Git patch olarak, yeni dosyalar birebir kopya olarak
  `.local/integration-snapshot-20260912` altında korundu.
- `origin/main` tabanlı `codex/canli-entegrasyon` dalı ve
  `.local/live-integration` çalışma ağacı oluşturuldu.
- Önceki geliştirmeler üç yönlü karşılaştırmayla bu kopyaya uygulandı.
  Çakışma çıkmadı. Başlangıç çalışma dosyalarının 63'ü de hash kontrolünde
  değişmeden kaldı; asıl geliştirme dalı değiştirilmedi.
- Claude'un filtre bileşeni, CSS'i, QueryFilter değişikliği ve test
  düzeltmeleri güncel ana daldaki haliyle korundu. Ortak değişen ekranlarda
  bireysel hesap filtresi, ödeme durumları ve ekstre verileri ayrıca incelendi.

## Önizleme ve doğrulama

Birleşmiş sürüm ayrı yerel sunucuda: `http://127.0.0.1:8188`.
Önceki önizleme `8187` üzerindedir; iki kod kopyası farklıdır.
8188 yalnız yerel `hotel_account_dev` örnek veritabanını kullanır.

Tarayıcıda doğrulandı:

- Konaklama filtresi kapalı başlıyor, kullanıcı açabiliyor.
- Aktif durum filtresi uygulandığında panel açık ve rozet “1 aktif”.
- Bireysel hesap filtresi yeni panel içinde doğru örnek hesabı getiriyor.
- Konaklama ödeme durumu: 750 TL ödenen, 1.250 TL kalan.
- Cari hesap kartı ve ekstre: 2.000 TL borç, 750 TL alacak, 1.250 TL bakiye.
- Ekstrede bakiyenin kapsamı / yalnız görünümü daraltan filtre ayrımı korunuyor.

Kod kontrolleri: Pint başarılı, PHPStan 0 hata, PHP platform gereksinimleri
başarılı, Vite derlemesi başarılı. Birleşmiş kodun tam test paketi gerçek
yerel MariaDB üzerinde **847 test / 2.688 doğrulama ile tamamen başarılı**.
Loglar `.local/integration-full-tests.log`, `integration-phpstan.log`,
`integration-pint.log` ve `integration-platform.log` dosyalarındadır.

## Sınırlar ve sıradaki adım

Bu çalışma bütün geliştirmelerin entegrasyon kopyasıdır; **ilk canlı yayın
paketi değildir**. Döviz geliştirmesi de korunmuştur. Yol haritasındaki
iki yayın için kapsam ayrımı yapılmalı; ilk pakete döviz işi kendiliğinden
dahil edilmemelidir.

Gerçek yedeği geri yükleme, canlı sunucunun migration/commit teyidi, geçmiş
ödeme önizlemesi, paket ayrımı ve tam mobil kabul aşamaları henüz yapılmadı.
Yayın, push veya ana dal birleştirmesi yapılmadı.

Yedek için kullanıcıdan dosyanın konumu istendi. İndirilenler'deki tarihli
bir arşivin yalnız tablo adları kontrol edildi; Lemon şemasıyla eşleşmediği
görüldü ve geçiş için kullanılmadı. Doğru yedek tahmin edilmemeli.

Sonraki adım: doğru ve güncel Lemon yedeğinin doğrulanması, ayrı veritabanına
güvenli geri yükleme ve kayıt/bakiye başlangıç raporu.
