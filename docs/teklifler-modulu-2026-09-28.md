# Teklifler modülü kararı — 28 Eylül 2026

- Teklif yalnızca belge verisidir. Cari, tahsilat, kasa veya konaklama hareketi oluşturmaz.
- İsteğe bağlı `company_id` ilişkisinin yanında müşteri adı kaydedilir. Cari adı sonradan değişse de teklif metni aynı kalır.
- Dönem ve oda tipi ayrı sıralı satırlardır; dolu fiyat hücresi `offer_prices` içinde `DECIMAL(15,2)` olarak saklanır. Boş hücre fiyat verilmediğini gösterir.
- Teklif numarası ilk kayıt sırasında yıl ve benzersiz veritabanı kimliğinden üretilir (`TKF-YYYY-ID`), unique index ile korunur. Tarih daha sonra değiştirilirse numara sabit kalır.
- Durumlar `draft`, `ready`, `sent`, `approved`, `cancelled` değerleridir. Durum finansal etki taşımaz.
- Varsayılan yasal/marka alanları mevcut `hotel.company` yapılandırmasından form açılışında alınır ve teklifte snapshot olur. Banka bilgisi teklif metni olarak girilir; ayrı banka hesap yönetimi kurulmaz.
- Admin ve Patron teklifleri oluşturur, düzenler, görüntüler ve yazdırır. Personel erişmez. Yazdırma da görüntüleme yetkisi ister.
- Migration yalnızca dört yeni teklif tablosu ekler; mevcut finansal tablo yapısını değiştirmez. `down()` yalnızca bu yeni tabloları kaldırır; üretimde geçmiş teklif belgesi silinmemelidir.
