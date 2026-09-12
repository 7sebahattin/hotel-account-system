# Mevcut Lemon tasarımını koruyarak geliştirme

Durum: İkinci taslak; plan ve görsel inceleme aşaması. Uygulama geliştirmesine onay verilmedi.

## Kullanıcının netleştirdiği sınır

İlk taslak, özelliklerin tamamını göstermediği için mevcut sistemin kaldırılacağı izlenimi verdi. Bu taslak tam uygulamanın yerine geçmeyecek. Mevcut özelliklerin topluca kaldırılması onaylanmadı; herhangi bir kaldırma ayrıca gerekçelendirilip kullanıcıya sorulacak.

## Bu revizyonun dayanağı

Gerçek kaynak ağacındaki `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `partials/sidebar`, `bottom-nav`, `quick-action`, `brand`, Dashboard Finans/Operasyon/Personel şablonları ve firma liste/detay şablonları okundu.

Taslaklar bu şablonlardan türetilen görsel temsillerdir; çalışan uygulamadan alınmış ekran görüntüsü değildir. PHP ortamı olmadan gerçek ekranlar bu oturumda açılmadı. Tutar ve isimler örnektir. Canlıda seçilmiş özel logo/config varsa gerçek görsel birebir eşleşmeyebilir.

## Korunanlar ve değişiklik önerileri

| Mevcut bölüm | Ne olacak? | Görünümde önerilen değişiklik |
|---|---|---|
| Lemon markası | Korunacak | Lacivert menü, altın vurgu, açık zemin ve sistem yazı tipi devam edecek. |
| Masaüstü kenar menüsü | Korunacak | Ana Sayfa; Kasa, Cari ve Yönetim grupları aynı kalacak. Yetkiye bağlı görünürlük korunacak. |
| Lemon Bar / Hızlı İşlem | Korunacak | Kasa Hareketi, Cari Tahsilat, Yeni Konaklama, hesap ekleme ve Kasa Devri kısayolları kaybolmayacak. |
| Finans panosu | Korunacak | Açık alacak öne alınacak; açık borç, net bakiye, aylık konaklama/tahsilat ve aktif hesap sayısı ayrıntıda erişilebilir kalacak. |
| Operasyon panosu | Korunacak | Kasa durumu, eldeki para, günlük hareketler ve devir bilgileri kaldırılmayacak. |
| Personel panosu | Korunacak | Vardiyam/Kasam ve devir uyarıları korunurken konaklama/tahsilat hızlı girişleri yukarı alınacak. |
| Kasa Defteri | Korunacak | İlk cari geliştirmesinde mevcut işlevler devam edecek. Kapsamlı kasa yenilemesi sonraki aşama. |
| Kasa Hareketleri / Devir Geçmişi / Günlük Defter | Korunacak | Aynı Kasa grubunda erişilebilir kalacak. |
| Firma listesi ve cari kart | Genişletilecek | Menü adı “Hesaplar” öneriliyor. Firma/Bireysel filtreleri gelecek. Eski firma bilgileri ve kayıtları korunacak. |
| Vergi no, telefon, yetkili, şehir, aktif/pasif | Korunacak | Sık kullanılmayan liste sütunları gerektiğinde açılabilir; bilgiler detaydan kaldırılmayacak. |
| Konaklamalar | Korunacak ve genişletilecek | Liste/detay devam edecek; yeni kayıt ve düzenleme için panel öneriliyor. Bireysel hesap ve ödeme durumları eklenecek. |
| Tahsilatlar | Korunacak ve genişletilecek | Liste/detay ve makbuz korunacak; hızlı tahsilat paneli, ödeyen kişi, borç dağıtımı ve avans eklenecek. |
| Tahsilat Makbuzları | Korunacak | Bağımsız belge defteri aynı adla ayrı kalacak; cari tahsilatla birleştirilmeyecek. |
| Cari Ekstre / yazdırma | Korunacak | Hesap ve tarih bazlı raporlar devam edecek; Excel dışa aktarımı eklenecek. |
| Kullanıcılar / Roller ve Yetkiler | Korunacak | Mevcut Yönetim grubunda, izinli kullanıcılara gösterilecek. |
| Odalar / Kasa Hareket Türleri | Korunacak | Mevcut tanımlama ekranları kaldırılmayacak. |
| Geçmiş Döviz Düzeltme | Korunacak | Önceki kayıtları doldurma amacı korunacak; yeni döviz akışı ile çakışması engellenecek. |
| Denetim Kayıtları | Korunacak | İptal/düzeltme geçmişi burada erişilebilir; normal muhasebe dökümüyle karıştırılmayacak. |
| Mobil alt menü | Korunacak | Ana Sayfa / Kasa / Hesaplar / Tahsilatlar / Daha Fazla; yalnız Firmalar adı genişleyen kapsama göre değişecek. |
| Mevcut kasa hareket penceresi | Korunacak | Tasarım deseni konaklama ve tahsilata uygun biçimde genişletilecek. |
| PWA kurulumu | Korunacak | Kurulum ve daha fazla menüsü devam edecek. Yeni taslak ayrı mobil uygulama değildir. |

## İkinci taslağı nasıl değerlendireceğiz?

Masaüstü taslağı başlangıçta admin görünümünü gösterir; bu, tüm mevcut menülerin kaybolmadığını görmeyi sağlar. Rol seçicisinden personel görünümüne geçilebilir. Kasa ve Yönetim grupları açılabilir. Tam tasarlanmayan mevcut ekranlar yalnız bölüm adı ve korunan işlev listesiyle temsil edilir; uygulamanın yerine geçen eksik ekranlar değildir.

Mobil taslak mevcut beşli alt menüyü, lacivert marka şeridini, Finans/Operasyon ayrımını ve Daha Fazla erişimini korur. Kasa ve diğer bölümler örnek düzeyinde temsil edilir; yeni kasa kapsamı eklenmez.

## Geliştirme için koruma kabulü

- Mevcut route/özellik envanteri çıkarılır; her birine yeni düzende ulaşılabildiği doğrulanır.
- Önemli mevcut filtreler, kolon bilgileri, yazdırma, makbuz ve işlem geçmişi korunur.
- Yetkisi olmayan kullanıcıya menü veya finans özeti açılmaz.
- Panel açılamazsa mevcut sayfa ile işlem yapılabilmesi tasarımda değerlendirilir.
- Sayfa/panel değişimi kayıtları, tahsilatları veya kasa sonuçlarını değiştirmez.
- İptallerin normal rapordan gizlenmesi ve bağlantısız kalıcı silme, kullanıcının açıkça istediği işlev değişiklikleridir; sırf tasarım sadeleştirmesi gerekçesiyle ilave kaldırma yapılmaz.

Kullanıcı şu aşamada görsel yönü inceliyor. Açık kodlama onayı gelmeden uygulama dosyaları veya veritabanı değiştirilmeyecek.
