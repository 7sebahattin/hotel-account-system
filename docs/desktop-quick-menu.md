# Masaüstü Hızlı Menü kararı

- Mevcut kullanıcı tercihi deposu yoktu. Users modülünde `user_quick_menus`
  tablosu kullanıcı başına tek JSON kısayol listesi tutar. Kayıt yoksa yetkili
  Cari Tahsilat, Yeni Konaklama ve Yeni Cari Hesap varsayılanları gösterilir;
  kayıtlı boş liste boş kalır.
- Hedefler sunucudaki `QuickShortcutRegistry` kapalı listesindedir. Her
  gösterimde route varlığı ve güncel kullanıcı izinleri denetlenir. Teklif
  oluşturma için hem `offers.view` hem `offers.create` gerekir. Girilen ad
  yalnız Blade escaped metin olarak basılır; URL veya SVG kullanıcı girdisi yoktur.
- Ayarları yalnız oturumdaki kullanıcı kaydeder. Sıra JSON liste sırasıdır;
  yeni finansal işlem, ledger yazımı veya mevcut domain servisinde değişiklik
  yapılmaz.
- Migration ileri yönde additive'dir. `down()` tabloyu kaldırır; canlıda
  geri alma yerine ileri düzeltme tercih edilir.
