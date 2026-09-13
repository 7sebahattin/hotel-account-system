# Claude değişiklikleri ve kasa kaldırma entegrasyonu

## Karşılaştırılan sürümler

- İlk cari yayın: `dcb771d`.
- Yerel kasa kaldırma: `4b369d5`, `codex/kasa-kaldirma`.
- Claude güncellemeleri: `origin/main` üzerindeki `6d31e06` dahil.
- Çalışma ayrı worktree'dedir; eski geliştirme çalışma alanı değiştirilmedi.
  Bu inceleme kapsamında canlı yayın yapılmadı.

Claude'un filtreli konaklama toplamları, para birimlerini ayrı gösterme,
aktif/iptal tutar ayrımı, yazdırma ekranı, form iyileştirmeleri ve adminin
iptal edilmiş bağlantısız konaklamayı kalıcı silmesi korundu. Kasa modülü
aktif uygulamadan çıkarıldı; tarihsel kasa tabloları ve migration'ları silinmedi.
Cari tahsilat, ödeme dağılımı ve nakit ödeme kasa oturumu gerektirmez.

## Bulunan ve düzeltilen sorunlar

1. **Kaldırılan modül eski cache'den yüklenebilir.** Finalize, kernel'i
   başlatmadan config/services/packages/routes/events cache'lerini temizler.
   Sonrasında optimize:clear ve mevcut migrate/seed/cache sırası çalışır.
   Sunucunun `lsphp`, kapalı `proc_open/exec` kısıtları korunur; alt süreç yoktur.
2. **Aktarım bittiğinde sunucu hazır olmayabilir.** Zaman aşımında yeşil
   sonuç kaldırıldı. Başarı için işaretin silinmesi VE aynı commit/run/attempt
   kimlikli sonuç dosyası gerekir. FTP hatası boş dizin sayılmaz, sertifika
   doğrulaması devre dışı bırakılmaz.
3. **CI ile Deploy paralel ilerliyordu.** Deploy aynı commit için tekrar
   kullanılabilir CI işini bekler. Test, Pint veya PHPStan başarısızsa üretim
   paketi aktarılmaz. Normal yayın hâlâ `main`e push ile başlar.
4. **FTPS sırasında eski ve yeni dosyalar birlikte kullanılabilir.** Ana
   aktarımdan önce Laravel bakım dosyaları hazırlanır. Bekleyen eski yayın
   veya mevcut bakım varsa yeni aktarım başlamaz. Finalize sadece kendi
   yayın kimliğine ait bakımı kapatır. Kilit dosyası `storage/app` içindedir.
5. **Kalıcı silme yalnız bir yetki adına dayanıyordu.** Policy ve servis
   admin rolünü de kontrol eder. İşlem içinde güncel kayıt kilitlenir ve
   iptal durumu yeniden doğrulanır. İlişkili ödeme engeli korunur; diğer SQL
   hataları yanlışlıkla “bağlı ödeme var” mesajına çevrilmez.

## Yayın ve hata halinde izlenecek yol

Normal yayın için elle workflow, migrate veya seeder çalıştırılmaz. Güncel
yedek ve cari/arşiv kayıt sayıları karşılaştırma için saklanır. Yetkili yayın
kararından sonra test edilmiş dal main'e alınır; Actions otomatik ilerler.
Kalıcı finalize cron'u **silinmez**. İşaret normal FTP aktarımından ayrı gelir;
`.env`, kullanıcı dosyaları ve kasa arşivi aktarımda ezilmez.

Bekleme 24 × 15 saniye ve ağ isteklerinin süresidir. Süre dolarsa otomatik
başarı bildirilmez. Cron sonradan tamamlayabilir; günlük ve başarı dosyasının
yayın kimliği kontrol edilir. Sırf kırmızı koşuyu yeşile çevirmek için yeniden
yayın, migration geri alma veya `up` yapılmaz.

Aktarım/finalize başarısızlığında bakım açık kalabilir. Önce hangi dosya ve
komutun başarısız olduğu belirlenir. Eksik aktarım tamamlanıp aynı yayının
işareti/bakım kimliği tutarlı hale getirilmeden site açılmaz. Başarı dosyasının
yazımı gibi son aşama hataları manuel durum incelemesi gerektirir. Doğrulanmış
sunucu adı/sertifika hatası düzeltilir; `--insecure` ile atlanmaz.

Kasa kaldırıldıktan sonra yeni nakit ödeme girilirse eski sürüm artık bu
ödemeleri kasaya yazılmış varsayamaz. Kör kod rollback'i güvenli değildir;
ileri düzeltme veya farklar değerlendirilmiş geri yükleme planı gerekir.

## Doğrulama sınırı

Eski canlı Deploy #18 günlüğünde `d2c5deb` için finalize tamamlandı mesajı
doğrulandı. Bu, burada eklenen yeni yayın korumalarının canlı testi değildir.
Birleşmiş sürümde PHP 8.3 ve yerel MariaDB ile **667 test / 2282 doğrulama
başarılı**. PHPStan level 5 sıfır hata, Vite üretim derlemesi başarılı.
İki workflow YAML olarak ayrıştırıldı ve 22 kabuk script'i Bash sözdizimi
kontrolünden geçti. Bu kontroller gerçek GitHub/FTPS yürütmesinin yerini tutmaz.
Gerçek üretim verisi üzerinde yazma testi yapılmadı; tarayıcı/mobil kabulü
ayrıca gerekir.

Finalize testleri kaldırılmış provider içeren eski cache, migration hatasında
bakım/işaretin korunması, farklı yayının bakımının açılmaması, bozuk işaret,
HTTP üzerinden çağrı reddi ve aynı işin tekrar çalışmamasını kapsar. Bu
testler gerçek finalize dosyasını izole bir kernel test ortamında çalıştırır.
