# Aylık Hesap — 14 Eylül 2026

Kullanıcının paylaştığı tablo referansı günlük satır düzenine uyarlandı.
Sayfa mevcut Lemon tasarımını kullanır. Cari başlığının hemen ardından
**Hesap → Aylık Hesap** menüsü gelir.

## Kullanıcı kararları

- Her kullanıcı ayrı bir hesap alanına sahiptir. Personel kendi alanını,
  Patron kendi alanını, Admin yetkisi kapsamında bütün alanları görebilir.
  15 Eylül 2026 sadeleştirmesiyle kullanıcıya gösterilen **Hesap sahibi**
  seçicileri ve başlıklardaki sahip adı kaldırılmıştır; veri sahipliği ve
  denetim izi arka planda korunur.
- Yalnız **Günlük Liste Toplamı** vardır ve elle TL olarak girilir.
  İlk mesajdaki Konaklama Listesi Toplamı sonraki yanıtla kaldırılmıştır.
- Personel görüntüler/ekler/düzenler. Patron görüntüler/düzenler.
  Admin görüntüler/ekler/düzenler/siler. İzinler rol ekranında ayrı gruptur.
- Banka ve ödeme kuruluşu adlarını yalnız Admin, **Yönetim → Banka / Ödeme
  Kuruluşları** ekranında tanımlar. Formda serbest banka adı yazılmaz.

## Günlük akış

Ay seçilir. Liste en son kayıttan ilk kayda doğru gider. İlk ve son
kayıt dışındaki boş günler gizlenir; iki kayıt arasındaki boş gün, eksik günü
tamamlamak için gösterilir. Günlük kayıt açılır, tarih ve Günlük Liste Toplamı girilir.
Nakit / Kredi kartı / Link / Havale / Ekstra satırları istenen sayıda eklenir.
Kart, link ve havalede banka adı; tüm satırlarda para birimi, tutar ve not
bulunur. Ekstra bu ekranın bağlamında ek tahsilattır; gider kaydı değildir.

Örneğin Yapı Kredi + USD hesabı seçildiğinde ödeme tutarı USD olarak girilir.
Kullanıcı kur veya TL karşılığını seçer. `300,00 USD × 40,0000 = 12.000,00 TL`.
TL toplamından türetilen kur dört basamakla gösterilir; yeniden çarpımda
yuvarlama farkı doğabileceği için doğrudan girilmiş TL toplamı esas kalır.

`3000` kutu içinde `3.000,00` olur; ödeme tutarı ile hesaplanan TL karşılığı
ayrı etiket taşır. Ödeme toplamı = nakit + kart + link + havale + ekstra
satırlarının TL karşılıkları. Günlük Liste Toplamı kontrol değeridir ve bu
toplama tekrar eklenmez. Fark = ödeme TL karşılığı − günlük liste toplamı.
Nominal döviz tutarları kendi para birimlerinde ayrıca gösterilir.

Ana tabloda her kullanılan yöntem + kuruluş ayrı kolondur: örneğin **Kredi kartı
(Yapı Kredi)**, **Kredi kartı (Garanti BBVA)** ve **Link ödeme (İş Bankası)**.
Para birimleri alt alta, TL karşılıkları hemen altında gösterilir. Ay toplamı
tablo başlığının altında koyu zeminli ilk satırdır. Telefonda aynı veriler gün
kartlarıdır.

Tarih/tutar karşılaştırması ve erişilebilir tablo başlıkları için
[GOV.UK tablo örneği](https://design-system.service.gov.uk/components/table/)
incelendi. Görsel temel kullanıcının resmi ve mevcut uygulama bileşenleridir.

## Kayıt bütünlüğü

Bu manuel hesap defteri mevcut cari/kasa/konaklama kayıtlarından bağımsızdır;
kayıt girmek bu defterlerde ikinci bir tahsilat oluşturmaz. Kuruluş tanımı yalnız
ortak ad ve aktiflik durumudur; banka hesabı, IBAN, POS, bakiye, mutabakat ve
canlı bağlantı eklenmez. Migration yalnız geçmiş aylık hesap satırlarında gerçekten
kullanılmış adları tanım listesine alır. Kullanılmış ad silinemez; pasifleştirilir,
yeni ödeme formundan kalkar ve geçmiş kayıtta korunur. Kullanılmış ad yeniden
adlandırılmaz; düzeltme gerekiyorsa pasifleştirilip yeni tanım açılır.

Kullanıcı + tarih tekildir. Her düzenleme yeni değişmez sürüm ve ödeme
satırları ekler. Eski sürümler saklanır. Silme normal aylık toplamdan çıkarır,
geçmişi korur; Admin geri getirebilir. Eşzamanlı eski sürümle kaydetme reddedilir.
Sahiplik ve işlem yapan kullanıcı ayrıdır, işlemler denetim kaydına yazılır.

## Doğrulama

Rol matrisi, başka kullanıcıya erişim, aynı gün tekrar kayıt, sürüm çakışması,
Türkçe tutar girişi, kur/TL toplamı doğruluğu, değişmez geçmiş ve mevcut
muhasebeye yan etki olmaması otomatik test kapsamındadır.

15 Eylül 2026 temiz yayın adayı doğrulaması: Aylık Hesap + yetki + tasarım
kapsamındaki 96 test ve 604 assertion, tüm projedeki 740 test ve 2.802 assertion,
6 JavaScript testi, Pint, PHPStan level 5 ve Vite production build geçti. Ayrıca
12 Aylık Hesap rotasına karşı 0 Kasa/CashBook rotası bulundu. Yerel önizlemede migration
ve rol seeder'ı çalıştırıldı; Yönetim tanım ekranı, yalnız kullanılan adların
taşınması, formdaki açılır kuruluş seçimi ve koyu günlük toplam çerçevesi tarayıcıda
kontrol edildi.

Canlı yayın paketi bu belgedeki entegrasyon sınırıyla hazırlanır. GitHub yayın
akışının test, migration, rol seeder'ı, finalize ve canlı duman kontrolleri
tamamlanmadan aktarım başarılı sayılmaz.

## Canlı taban entegrasyon sınırı

İlk geliştirme çalışma ağacının tabanı `a426b3e` olduğu ve eski Kasa modülünü
içerdiği için o dal yayınlanmaz. İzole yayın dalı `codex/aylik-hesap-canli`, Kasa
kaldırılmış güncel `origin/main` (`16578fc`) üzerinden açılmıştır. Yalnız Aylık
Hesap modülü, zorunlu ortak kayıtlar ve testleri taşınır. `CashBook` dosyası,
`cashbook.*` rota/yetkisi veya Kasa menüsü ekleyen fark bulunursa yayın durur.
