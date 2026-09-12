# Döviz kapama — çalışma durumu

Kullanıcı çalışma sırasında önceliği canlı geçiş yol haritasına çevirdi.
Yeni kod eklemeye ara verildi; mevcut değişiklikler yerelde korunuyor.
Canlıya yükleme veya ana dala birleştirme yapılmadı.

Yerelde eklenen akış: döviz tahsilatında TL hedefini seçme; işlem kuruyla
konaklamaya dağılım; fazla dövizi avans tutma; dağılım çözülürse cari kapama
çiftlerini ters kaydetme. Kasa ve tahsilat toplamları orijinal para biriminde.
Raporların yabancı para TL karşılığında dönüştürülen kısım ayrılıyor.

`2026_09_19_100000_add_foreign_currency_allocations` migration'ı yalnız yerel
geliştirme ve test veritabanına uygulandı. Mevcut veriler sıfırlanmadı.

Son doğrulama:

- `ForeignCurrencyAllocationTest` + `PaymentAllocationTest`: 34 test,
  128 doğrulama başarılı. İlk çalışmada yeni alanın fillable listesinde
  olmaması yakalandı, düzeltildi ve testler yeniden geçti.
- PHPStan: 0 hata. Pint ve Vite derlemesi başarılı.
- Tüm test paketi bu döviz değişikliklerinden sonra çalıştırılmadı.
- Tarayıcıda yeni hedef seçimi alanı ve döviz formu görüldü; örnek ödeme
  gönderilmedi, uçtan uca tarayıcı kabulü tamamlanmadı.

Devam ederken: E sürümüyle birleştirme, tam testler, döviz iptal/avans ve
özellikle parçalı kuruş yuvarlamalarının tekrar kullanım/ters kayıt
senaryoları, gerçek veri kopyasında geçiş ve rapor mutabakatı tamamlanmalı.
Bu not yayın hazır bildirimi değildir. Güncel plan:
[Güvenli canlı geçiş](guvenli-canli-gecis-yol-haritasi-2026-09-12.md).
