# Ana sayfa (Finans panosu) yeni tasarım — yol haritası

Tarih: 23 Eylül 2026 · Durum: **onaya sunulan taslak, kodlama başlamadı.**

Kaynak: kullanıcının paylaştığı referans ekran görüntüsü (renkli KPI kartları,
"Son 6 Ay Cari Hareketler" grafiği, Cari Özeti, Hızlı İşlemler, en yüksek
bakiyeli firmalar, para birimi sekmeleri, Son Hareketler tablosu, Son
Güncellemeler).

Hedef ekran: `app/Modules/Dashboard/Resources/views/finance.blade.php`
(Admin/Patron). Personel panosu (`personnel.blade.php`) bu işin dışındadır.

## 1. Kısa cevap

Evet, masaüstünde **büyük ölçüde birebir** benzetilebilir. Ancak birkaç öğe
CLAUDE.md kurallarıyla çeliştiği için ya kullanıcı onayı gerekir ya da
küçük bir uyarlama yapılır (bkz. §3). Veri tarafında yeni tablo/migration
GEREKMEZ; yalnızca mevcut servislere okuma (rapor) metotları eklenir.

## 2. Referans görüntüdeki bloklar ve mevcut durum

| # | Blok (görüntü) | Bugün panoda var mı? | Gereken iş |
|---|---|---|---|
| 1 | 5 renkli KPI kartı (Açık Alacak, Açık Borç, Net Bakiye, Bu Ay Konaklama, Bu Ay Tahsilat) | Var — ama sade `x-ui.stat` ızgarası | Yeni `x-ui.kpi-card` bileşeni (ikon kutusu + pastel zemin) |
| 2 | "4 konaklama · 12 gece", "2 tahsilat" alt satırları | Yok | `StayService`/`PaymentService`'e bu ay ADET + GECE toplamı |
| 3 | Son 6 Ay Cari Hareketler (sütun + çizgi grafik, dönem seçici) | Yok | Aylık toplam sorguları + grafik çizimi (§4) |
| 4 | Cari Özeti (aktif/pasif firma, toplam konaklama + gece, toplam tahsilat, ortalama gecelik) | Yalnız aktif firma sayısı | `CompanyService::passiveCount()`, konaklama/gece/tahsilat genel toplamları, ortalama gecelik (Money ile, float yok) |
| 5 | Hızlı İşlemler (renkli butonlar) | Hızlı İşlem yalnız Lemon Bar "+" menüsünde | Panoya kart; mevcut create route'larına link, yeni işlem mantığı YOK |
| 6 | En yüksek bakiyeli firmalar (sıra numaralı, 1. satır vurgulu) | Var (para birimine göre gruplu) | Görsel yenileme; "Tümünü Gör" → `statements.index` |
| 7 | Para Birimi Bazında Durum (TRY / GBP sekmeleri) | Var (her para birimi ayrı kart) | Sekmeli görünüme dönüştürme (`?currency=` bağlantısı, JS'siz) |
| 8 | Son Hareketler tablosu (Tarih/İşlem/Açıklama/Borç/Alacak/Bakiye, sayfalama) | Yok (Gün 11'de bilerek kaldırılmıştı) | `LedgerStatementService::recentEntries()` zaten var; bakiye kolonu için ek hesap (§3.5) |
| 9 | Tablo sekmeleri: Konaklamalar / Tahsilatlar / Borç-Alacak / Notlar / Belgeler | Konaklama + tahsilat listeleri ayrı panellerde var | Konaklamalar/Tahsilatlar/Borç-Alacak sekme olur; **Notlar/Belgeler** için bkz. §3.4 |
| 10 | Son Güncellemeler (denetim kaydından) | Yok | `AuditLog` modülüne okuma servisi, `@can('audit.view')` ile |

## 3. Kurallarla çelişen noktalar — karar gerekiyor

1. **Renkli/pastel kartlar ve mavi-yeşil-turuncu butonlar (§11.1).** Mevcut
   tasarım dili "sıcak nötr zemin + beyaz kart + yalnızca altın vurgu" der.
   Görüntüdeki mor, mavi, pembe kart zeminleri ve renkli butonlar bu kuralı
   bilerek aşar. **Onay gerekir.** Öneri: yeni renkler yalnızca `@theme`
   bloğuna token olarak eklenir (Blade'de ham hex yasağı sürer,
   `DesignSystemTest` korunur), kontrast 4.5:1 ölçülür, istisna §11.1'e yazılır.
   Görüntüdeki hafif gölge **eklenmez** (gölge yasağı); ayrım 1px çizgiyle
   yapılır — görünüm neredeyse aynıdır.
2. **Grafik (§11.7, §13 "yeni paket ekleme").** İki yol:
   - **Önerilen:** sunucuda üretilen satır içi SVG (Blade bileşeni). Paket yok,
     JS yok, yazdırma/PWA ile uyumlu, testle doğrulanabilir.
   - Alternatif: Chart.js (npm). Daha hızlı ama yeni paket → **açık onay gerekir**.
3. **"Masraf / Ekstre" butonu.** Kasa/masraf modülü kaldırıldı
   (`docs/kasa-kaldirma.md`). Buton yalnızca **"Cari Ekstre"** olarak
   yapılabilir; masraf girişi geri getirilmez.
4. **"Notlar" ve "Belgeler" sekmeleri.** Belge yükleme altyapısı ve genel not
   defteri yoktur (§3.2 ruhu: kullanılmayan placeholder açılmaz). Öneri: bu iki
   sekme **eklenmez**. İstenirse ayrı kapsam kararıdır.
5. **Net Bakiye ve Bakiye kolonu (§6/L5).** Kartlar ve tablo her zaman **tek
   para birimi** gösterir (seçili sekme, varsayılan TRY); TRY ile GBP asla
   aynı toplama girmez. Tablodaki "Bakiye" kolonu tüm firmaların o para
   birimindeki sistem geneli yürüyen neti olur — bu, firma ekstresindeki
   bakiyeden farklı bir anlamdır ve başlıkta açıkça yazılmalıdır. İstenmezse
   kolon kaldırılır (daha sade seçenek).
6. **Sorgu bütçesi.** `DashboardTest` finans görünümü için 17 sorgu üst sınırı
   koyuyor. Yeni bloklarla bu ~24–26'ya çıkar; sınır yükseltilir ama
   **firma/kayıt sayısıyla büyümeme** kuralı korunur (toplu sorgular).

## 4. Aşamalar

Her aşama kendi başına yayınlanabilir; her birinin sonunda testler + Pint +
PHPStan + Vite derlemesi temiz olmalıdır.

### Aşama 0 — Onay (kod yok)
- §3'teki 6 karar kullanıcıya sorulur.
- 1366 px ve 390 px için statik HTML taslağı gösterilir (görsel onay).

### Aşama 1 — Renk token'ları ve bileşenler (≈ yarım gün)
- `resources/css/app.css` `@theme`: kart zeminleri (turuncu/yeşil/mavi/mor/
  pembe `*-bg`), ikon kutusu renkleri, bilgi mavisi metin rengi. Her biri için
  kontrast notu (mevcut yorum deseniyle).
- Yeni bileşenler: `x-ui.kpi-card` (ikon + etiket + tutar + alt satır + ton),
  `x-ui.tabs` (bağlantı tabanlı, `aria-current`), gerekirse `x-ui.rank-list`.
- `NavIcons` benzeri tek kaynaktan ikon yolları (cüzdan, ok, hesap makinesi,
  yatak, kart).
- CLAUDE.md §11.1/§11.2/§11.3 güncellenir.

### Aşama 2 — Veri (servis metotları, ≈ 1 gün)
- `StayService`: bu ay adet + gece; genel konaklama adedi + gece; aylık
  (son N ay) para birimi bazında toplam; ortalama gecelik (tamsayı kuruş).
- `PaymentService`: bu ay adet; genel tahsilat toplamı; aylık toplam.
- `CompanyService::passiveCount()`.
- `LedgerStatementService`: son N hareket + sayfalama; (onaylanırsa) sistem
  geneli yürüyen bakiye.
- `AuditLog`: son N olay okuma servisi.
- `DashboardService::finance()` yeni alanları `FinancialSummary`'e ekler;
  para birimi ayrımı her yerde korunur. Yeni tablo/migration YOK.
- Testler: aylık toplamların iptal edilenleri saymaması, para birimi
  ayrımı, sorgu sayısının firma sayısıyla büyümemesi.

### Aşama 3 — Ekran (≈ 1–1,5 gün)
- `finance.blade.php` yeniden düzen, masaüstü ızgarası:
  - Üst: 5 KPI kartı (`lg:grid-cols-5`).
  - Orta sol (2 sütun): grafik kartı · Cari Özeti · sağ sütun: Hızlı
    İşlemler + En yüksek bakiyeli firmalar.
  - Alt sol: Para birimi sekmeleri + Son Hareketler sekmeli tablosu ·
    sağ: Son Güncellemeler.
- Grafik: SVG sütun (konaklama/tahsilat) + çizgi (net), eksen etiketleri,
  `<title>`/tablo alternatifi (erişilebilirlik). Dönem seçici `?months=6|12`.
- Yetki: Hızlı İşlem satırları `@can`, Son Güncellemeler `@can('audit.view')`.
- Boş sistem durumu korunur.

### Aşama 4 — Mobil ve erişilebilirlik (≈ yarım gün)
- < 1024 px: tablo kart listesine düşer (§11.5), KPI'lar 2 sütun, grafik
  yatay kaydırmasız küçülür, sağ sütun alta iner.
- 360/390/430/768/1366/1920 kırılımlarında Chromium ekran görüntüsü
  kontrolü; 44 px dokunma hedefi; kontrast ölçümü.

### Aşama 5 — Yayın
- İş dalı → PR → `main` merge (otomatik yayın akışı, CLAUDE.md "YAYIN AKIŞI").
- Migration yoktur; yayın riski yalnızca görünüm/sorgu düzeyindedir.

## 5. Tahmini toplam

3–4 iş günü (onay ve taslak turu hariç). Chart.js seçilirse grafik kısmı
yarım gün kısalır.

## 6. Kapsam dışı (bu yol haritasında YAPILMAYACAK)

- Belge yükleme, not defteri, masraf/kasa girişi.
- Otomatik kur çevrimi veya farklı para birimlerinin tek toplamda birleşmesi.
- Personel panosunun yeniden tasarımı (istenirse ayrı iş).
- Koyu tema / tema değiştirici.
