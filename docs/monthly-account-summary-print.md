# Aylık hesap özet yazdırma

Özet baskı, tam aylık raporla aynı `MonthlyAccountController::monthData()` sonucunu kullanır. Sütunların tutarları `MonthlyAccountService::summarize()` tarafından sağlanan `summary` içindeki `daily_total`, `methods` ve `banks` alanlarından okunur. Döviz tutarlarının altındaki TL karşılığı, aynı alandaki kayıtlı `try_equivalent` değeridir; baskı görünümü kur veya finansal toplam hesaplamaz.

Ödeme yöntemi bazındaki TL dökümü, tam raporda da kullanılan `monthly-receipt-breakdown` bileşenidir. Yeni route mevcut `monthly-account.view` yetkisini ve `MonthlyAccountFilterRequest` filtrelerini kullanır. Şema ve finansal iş kuralları değişmez.
