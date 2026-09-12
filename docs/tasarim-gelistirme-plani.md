# Lemon — Mobil kullanım ve tasarım geliştirme planı

Durum: Onaya sunulacak taslak. Uygulama kodlaması başlamadı.

## Amaç

Otel personelinin konaklama ve ödeme girişini hızlı yapması; patronun firma ve bireysel hesapların kalan borcunu kolayca görmesi. Kasa ve PMS sonraki aşamalardır. Mevcut gerçek kayıtlar korunacak; tasarım çalışması veri sıfırlama gerektirmez.

## Tasarım yaklaşımı

- Personel/admin için bilgisayar, patron için telefon öncelikli tasarlanacak; her rol diğer cihazdan da kullanılabilecek.
- Mevcut marka ve tasarım sistemi başlangıç noktası olacak. Renk ve bileşen tutarlılığı korunarak gereksiz yoğunluk azaltılacak.
- Personelde hesap arama, Konaklama Ekle ve Ödeme Al öne çıkacak. Patronda kalan alacak ve borçlu hesaplar önde olacak.
- Her ekranda belirgin ana işlem olacak. Seyrek işlemler Diğer İşlemler altında bulunacak. Yetkisiz işlemler gösterilmeyecek.
- İkonlar tek başına kullanılmayacak; anlamlı metinlerle desteklenecek.
- Borç, ödenen ve kalan tutarlar aynı sırada gösterilecek. Durumlar Ödendi / Kısmen ödendi / Ödenmedi olacak; renk tek bilgi taşıyıcısı olmayacak.
- Döviz tutarı esas gösterilecek, altında küçük fakat okunabilir TL karşılığı ve ilgili kur bilgisi bulunacak. Farklı para birimleri belirsiz bir toplamda birleştirilmeyecek.

## Sayfa ve pencere kullanımı

| İş | Masaüstü | Telefon |
|---|---|---|
| Hesap arama/seçme | Aranabilir seçim alanı | Büyük arama ve sonuç listesi |
| Hızlı kişi/firma ekleme | Kısa pencere veya mevcut panel içinde adım | Mevcut akış içinde kısa form |
| Konaklama ekleme/düzenleme | Geniş yan panel | Tam ekran işlem paneli |
| Ödeme alma | Özet içeren yan panel | Tam ekran işlem paneli |
| Ödemenin konaklamalara dağıtımı | Ödeme panelinde genişleyen bölüm | Aynı akışta seçim adımı |
| Kısa kayıt özeti | Yan panel | Alttan açılan panel |
| Filtreleme | Açılır filtre alanı | Alttan açılan panel |
| İptal/silme/iade onayı | Etkiyi anlatan kısa onay penceresi | Aynı anlaşılır onay yaklaşımı |
| Hesap geçmişi ve ekstre | Ayrı, bağlantı verilebilir sayfa | Aynı sayfanın mobil düzeni |
| Raporlama | Ayrı sayfa | Özet ve ayrıntıları açılabilen mobil sayfa |

Kısa işlemler mevcut ekranın üzerinde tamamlanacak. Çok alanlı işlemler küçük pencereye sıkıştırılmayacak. İç içe pencereler yerine tek panelde adımlar kullanılacak; önceki girdiler korunacak. Geri tuşu önce açık panel/adımı kapatacak. Kaydedilmemiş bilgi varsa çıkışta uyarılacak; her basit işlem için gereksiz onay istenmeyecek.

## Öncelikli kullanım örnekleri

1. Hesap listesinden Ödeme Al seçildiğinde hesap hazır seçili gelir. Personel yeniden firma aramaz.
2. Konaklama içinden Ödeme Al seçildiğinde ilgili konaklama hedeflenir. Ödeme dağılımı ve kalan borç aynı panelde görünür.
3. Hesaptan toplu ödeme alınırken en eski borçtan dağıtım varsayılandır. Belirli konaklamaları seçme aynı akışta sunulur.
4. Firma boşsa bireysel konaklama açıkça belirtilir. Aynı adlı kişi otomatik birleştirilmez; mevcut kişi seçimi veya yeni kişi oluşturma sunulur.
5. EUR ödeme ile TL borç kapatılırken alınan EUR, elle girilen kur, borçtan düşülecek TL ve kalacak avans kayıt öncesinde birlikte görünür.
6. Fazla ödemenin hangi hesapta kalacağı gerektiğinde açıkça seçilir; seçimin ikinci bir tahsilat olmadığı özetle anlaşılır.
7. Ödeme sonrası kısa başarı mesajı ve güncel kalan borç gösterilir; kullanıcı başladığı listedeki konumunu kaybetmez.
8. Telefon listelerinde geniş tablo yerine hesap adı, kalan borç, durum ve ana işlem içeren kartlar kullanılır. Ayrıntılar isteğe bağlı açılır.
9. Filtreler kapatıldıktan sonra seçilen koşullar görünür kalır; tek hareketle temizlenir.
10. İptal ve iade ekranında hangi borç/avans etkisinin oluşacağı gösterilir. Finansal etki, yalnızca “Emin misiniz?” sorusuna bırakılmaz.

## Uygulama öncesi tasarım teslimi

Önce kodlamadan ekran taslakları hazırlanıp kullanıcıya gösterilecek: personel ana ekranı, patron özeti, hesap listesi/detayı, konaklama girişi ve ödeme paneli. Her birinin telefon görünümü olacak; masaüstü karşılığı da gösterilecek. Görsel yön ve işlem akışları onaylandıktan sonra uygulama geliştirmesine geçilecek. Tasarım onayı, tek başına kodlama veya canlı yayın onayı sayılmayacak.

## Kabul ölçütleri

- 360, 390 ve 430 px telefon genişlikleri ile tablet/masaüstünde temel akışlarda yatay sayfa kaydırması ve kırpılmış tutar olmayacak.
- Dokunma hedefleri tasarımda en az 44 × 44 CSS px olacak; alan açıklamaları ve odak göstergeleri okunabilir olacak.
- Telefon klavyesi açıkken aktif alan ve kayıt işlemine erişim korunacak. Tutar/telefon alanları uygun klavyeyi açacak.
- Klavye ile kullanım, pencere odak yönetimi, kapatınca odağın geri dönmesi ve ekran okuyucu etiketleri kontrol edilecek.
- Doğrulama hatasında alanlar silinmeyecek; hata ilgili alanın yanında açıklanacak.
- Yavaş bağlantıda işlem durumu anlaşılacak; tekrar gönderim tek finansal işlem üretecek.
- Gerçek personel ve patronla firma/bireysel konaklama, toplu/kısmi ödeme, konaklama seçimi, döviz ve ekstre bulma senaryoları denenecek. Süre, yanlış seçim ve yardım ihtiyacı gözlenecek; ilk sürümle karşılaştırılacak.

## Örnek alınan kaynaklar

Bu kaynaklar “en popüler tasarım” sıralaması değil, yerleşik kullanım ilkeleridir. Lemon için yukarıdaki tercihler bu ilkelerden türetilmiş tasarım önerileridir.

- [IBM Carbon — Create flows](https://carbondesignsystem.com/community/patterns/create-flows/): kısa oluşturma işlemleri ile daha kapsamlı formlar için farklı yüzey seçimi.
- [IBM Carbon — Modal usage](https://carbondesignsystem.com/components/modal/usage/): odak gerektiren kısa işlemler ve geri alınamaz eylem onayları.
- [GOV.UK — Check answers](https://design-system.service.gov.uk/patterns/check-answers/): karmaşık işlemlerde kayıt öncesi bilgilerin kontrol edilebilmesi.

## Kesinleşen cihaz kullanımı

Personel/admin ağırlıkla bilgisayarda işlem yapacak. Patron ağırlıkla telefondan takip edecek; gerektiğinde ikincil “İşlem yap” menüsünden kayıt girebilecek. Birleştirilmiş kararlar ve aşamalar `onaya-sunulan-gelistirme-plani.md` dosyasındadır.
