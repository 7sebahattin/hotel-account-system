# Aylık hesap özet yazdırma

Özet baskı, tam aylık raporla aynı `MonthlyAccountController::monthData()` sonucunu kullanır. Sütunların tutarları `MonthlyAccountService::summarize()` tarafından sağlanan `summary` içindeki `daily_total`, `methods` ve `banks` alanlarından okunur. Döviz tutarlarının altındaki TL karşılığı, aynı alandaki kayıtlı `try_equivalent` değeridir; baskı görünümü kur veya finansal toplam hesaplamaz.

Ödeme yöntemi bazındaki TL dökümü, tam raporda da kullanılan `monthly-receipt-breakdown` bileşenidir. Yeni route mevcut `monthly-account.view` yetkisini ve `MonthlyAccountFilterRequest` filtrelerini kullanır. Şema ve finansal iş kuralları değişmez.

Baskı görünümü A4 dikey yönde, 8 mm kenar boşluğuyla iki sütundur. Sol sütunda günlük liste, nakit ve kredi kartı; sağ sütunda link ödeme, havale, ekstra ve ödeme yöntemlerinin TL karşılığı yer alır. Üst satır yalnız marka ve rapor başlığını, ikinci satır ay ile hesap sahibini gösterir. Gruplar ve tutar satırları sayfa içinde bölünmez; olağanüstü çok sayıda dinamik ödeme sütununda okunabilirliği korumak için doğal ek sayfa oluşabilir. Tarayıcının kendi üstbilgi ve altbilgileri uygulama HTML'inin parçası değildir.
