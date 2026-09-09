# Yayın kontrol listesi — Lemon v2.1.1 (develop @ 0185b15)

Bu dosya v2.1.1 hattının (A / A.1 / B / B.1) production'a ilk kez taşınması
için **tek kaynaktır**. `docs/deploy-lemon.md` genel FTP/CI mimarisini,
`docs/deploy-cpanel.md` sunucu tarafı komutları anlatır — bu dosya SIRAYI
kesinleştirir ve atlanması EN KOLAY adımı (yetki seed'i) öne çıkarır.

**Kapsam dondurulmuştur** (K-099, `docs/kararlar.md`): bu turda hiçbir yeni
ürün özelliği eklenmedi — yalnızca yayın sertleştirmesi. Room Registry,
Currency Registry, Dashboard V2, Settings modülü, POS/Z, Excel/CSV, otomatik
kur çevrimi bu listenin KAPSAMINDA DEĞİLDİR.

---

## 0. Bugünkü gerçek durum

Production ortamı **bugün boştur** — `docs/deploy-lemon.md` §5'e göre
production veritabanı henüz oluşturulmadı, DocumentRoot henüz Lemon'a
çevrilmedi. Bu, sıradaki gerçek deploy'un **v2.1.1'in TAMAMINI TEK SEFERDE**
taşıyan bir İLK deploy olacağı anlamına gelir — "geriye dönük satır" riski
yoktur, ama sıra (§2) yine de bağlayıcıdır: bir sonraki v2.1.2+ deploy'u da
aynı sırayı izleyecektir.

---

## 1. SIRA — bağlayıcı, atlanamaz

```
UPLOAD (FTP/CI)
   ↓
MIGRATE            php artisan migrate --force
   ↓
ROLE/PERMISSION SEED   php artisan db:seed --class=RolePermissionSeeder --force
   ↓
CACHE (clear, sonra build)
   ↓
SMOKE TEST (§4)
```

**Yetki seed'i MIGRATE'TEN SONRA, cache'ten ÖNCE çalışır — bu bağlayıcıdır.**
Gerekçe (gerçek üretim olayı): `cashbook.operate` için "There is no
permission named cashbook.operate for guard web" hatası, koddaki
`Permission` enum'una yeni bir case eklenip DEPLOY edildiği ama
`RolePermissionSeeder`'ın production'da YENİDEN ÇALIŞTIRILMADIĞI için
oluştu. Kod ile veritabanı arasındaki bu senkronizasyon **insan hafızasına
bırakılamaz** — bu yüzden aşağıdaki komut HER deploy'da, migration'dan
hemen sonra, atlanmaksızın çalıştırılır.

`RolePermissionSeeder` **idempotenttir** (`findOrCreate` + `syncPermissions`):
mevcut kullanıcıların rol atamalarını BOZMAZ, yalnızca rol↔yetki eşlemesini
kod ile senkronlar. Her deploy'da güvenle tekrar çalıştırılabilir.

---

## 2. Sunucu tarafı komut dizisi (tam)

Bilinen çalışan kabuk deseni (bu ortam için):

```bash
/bin/bash -lc 'cd /home/derspros/lemon-app && /usr/local/bin/php artisan <komut>'
```

Sırasıyla:

```bash
# 0) (isteğe bağlı, uzun sürecek bir migration varsa) bakım moduna al
php artisan down --secret="<gizli-jeton>" --render="errors::503"

# 1) MIGRATE — forward-only, migrate:fresh KULLANILMAZ
php artisan migrate --force

# 2) ROLE/PERMISSION SEED — HER DEPLOY'DA, atlanamaz (bkz. §1)
php artisan db:seed --class=RolePermissionSeeder --force

# 3) Eski cache'i temizle (önceki deploy'dan kalmış olabilir)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# 4) Production cache'ini kur
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5) Bakım modundan çık (0. adım uygulandıysa)
php artisan up
```

**Kullanılmaz — hiçbir koşulda:**
- `migrate:fresh` (tüm şemayı SİLİP yeniden kurar)
- `db:seed` bayrak vermeden çağrılmak (tüm seederlar — `DatabaseSeeder`
  içindeki dev-only demo hesap mantığı `app()->environment(['local','testing'])`
  kontrolüyle zaten korunur, ama yalnızca `RolePermissionSeeder`'ı çağırmak
  niyeti açık tutar)
- `storage:link`'in tekrar tekrar koşulması zararsızdır ama gerekmiyorsa
  atlanır (V1'de kullanıcı yüklemesi yok)

**4 numaralı adım gerçek Chromium/CLI ile bu oturumda test edildi** — dördü
de local ortamda hatasız tamamlandı; `config:cache` sonrası uygulamanın
kendisi de route/view çözümlemesiyle doğrulandı, ardından cache'ler test
takımının kendi ortamını bozmaması için TEKRAR temizlendi (§6'daki not).

---

## 3. Cache komutları — neden bu sıra

- `config:clear` ÖNCE gelir: önceki deploy'dan kalan bayat bir
  `bootstrap/cache/config.php`, yeni `.env` değerlerini (ör. yeni bir
  `HOTEL_COMPANY_*` alanı) SESSİZCE görmezden bırakabilirdi — Laravel cache
  dosyası varsa `.env`'i bir daha OKUMAZ.
- `event:cache` bu projede GEREKLİDİR (Laravel 13'te mevcut komut) —
  atlanmadı, dördü de test edildi.
- Hiçbir route closure kullanmaz (tüm route'lar `[Controller::class,
  'method']` dizisidir) — `route:cache` bu yüzden güvenlidir; kod tabanında
  taranmıştır (bkz. `docs/kararlar.md` K-099).
- `config/` içinde `env()` çağrısı **yalnızca** config dosyalarının
  kendisinde yapılır; `app/` ve `resources/` altında (CSS/Blade'deki
  `env(safe-area-inset-bottom)` hariç — bu CSS'in KENDİ `env()`'idir, PHP'nin
  DEĞİL) hiçbir `env()` çağrısı yoktur — `config:cache` sonrası "sessizce
  null dönen ayar" riski taranmış ve KAPATILMIŞTIR.

---

## 4. Finansal duman testi (smoke test) — deploy sonrası zorunlu

Aşağıdaki 12 adımın **hepsi** production'da (veya production'un birebir
aynısı bir staging'de) gerçek tarayıcıyla doğrulanır. Herhangi biri
başarısız olursa deploy'u tamamlanmış SAYMA — önce kök nedeni düzelt.

**A. Giriş** — gerçek admin hesabıyla giriş yapılabiliyor.

**B. Kasa vardiyası açma** — "Kasayı Aç" ile bir vardiya açılıyor.

**C. Elle kasa hareketi** — Hızlı İşlem modalından (v2.1.1-B.1) "Kasa
Hareketi" seçiliyor; **tutar alanı BAŞLANGIÇTA BOŞTUR**; kayıt sonrası
beklenen kasa doğru güncelleniyor.

**D. Cari Tahsilat — NAKİT** — firma tahsilatı nakit olarak giriliyor; firma
bakiyesi **tam olarak bir kez** değişiyor; bağlı fiziksel kasa hareketi
görünüyor; makbuz açılıyor ve yazdırılabiliyor.

**E. Cari Tahsilat — KREDİ KARTI** — firma bakiyesi değişiyor; kart işlemi
kayda geçiyor; **fiziksel beklenen kasa DEĞİŞMİYOR**.

**F. Cari Tahsilat — HAVALE/EFT** — firma bakiyesi değişiyor; banka işlemi
kayda geçiyor; **fiziksel beklenen kasa DEĞİŞMİYOR**.

**G. Çok para birimli** — TRY ve EUR **ayrı** kalıyor; hiçbir ekranda
birleşik toplam görünmüyor.

**H. Personel devri** — sayılan nakit YENİ personele geçiyor; kart/banka
kayıtları yeni vardiyanın açılışına EKLENMİYOR; eski vardiyanın kart/banka
geçmişi eski vardiyada kalıcı olarak duruyor.

**I. Sayım farkı** — beklenen 7.000, sayılan 6.900 girildiğinde: yeni açılış
6.900 oluyor, fark **−100** hesaplanıyor, hiçbir otomatik gider/gelir satırı
OLUŞMUYOR.

**J. Kasadan Teslim** — fiziksel kasayı azaltıyor; gelir-gider raporundaki
GİDER TOPLAMINI ARTIRMIYOR.

**K. Yetkiler** — Personel rolü kategori yönetim ekranına (`/cash/categories`)
erişemiyor (403); Patron/Admin erişebiliyor.

**L. Makbuz** — makbuz numarası benzersiz; iptal edilmiş bir tahsilatın
makbuzu hâlâ açılabiliyor ve iptal durumu makbuzda görünüyor.

---

## 5. Kapsam dışı — bu sürümde YOK, aramayın

- Excel/CSV import veya export (V1'in bir sonraki fazı — §14.1)
- Z-raporu / POS mutabakatı (kanal ayrımı yalnızca cari/kasa doğruluğu içindir)
- Oda kaydı (Room Registry), Currency Registry admin ekranı
- Otomatik kur çevrimi / TCMB entegrasyonu
- Settings modülü (firma başlık bilgisi hâlâ `config/hotel.php` üzerinden,
  GEÇİCİ yerleşim — K-096)

---

## 6. Geri alma notu

**Migration'lar yalnızca İLERİ yönde çalışır.** `migrate:rollback` production'da
KULLANILMAZ (CLAUDE.md §15) — bir sorun bulunursa çözüm YENİ bir ileri-düzeltme
migration'ıdır, dosya/kod geri almak şemayı OTOMATİK OLARAK geri almaz. Kod
geri alınsa bile veritabanı yeni sütunları/tabloları/kısıtları taşımaya
devam eder (hepsi nullable/additive olduğu için bu ZARARSIZDIR — bkz.
`docs/kararlar.md` K-099 migration denetimi).

Cache'i her zaman **önce temizleyip sonra yeniden kurun** — cache'i canlıda
test ettiyseniz (bu turda local'de yapıldığı gibi) ortamı normale döndürmek
için `config:clear`/`route:clear`/`view:clear`/`event:clear` çalıştırın.
