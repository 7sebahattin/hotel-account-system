# cPanel Dağıtım Rehberi (staging)

> **Durum:** Hazırlık aşaması. Bu rehber yazıldığı anda **hiçbir deploy yapılmamıştır.**
> Otomatik deploy iş akışı, aşağıdaki §1 bilgileri netleşince yazılacaktır.

## 1. Deploy öncesi netleşmesi gereken bilgiler

Deploy yöntemi bunlara göre belirlenir:

| Soru | Neden önemli |
|---|---|
| **SSH erişimi var mı?** | Varsa `rsync` + `php artisan migrate` ile temiz deploy. Yoksa FTPS ile dosya aktarımı ve migration için alternatif yol gerekir. |
| Hesabın PHP sürümü (Select PHP Version) | Laravel 13 için **8.3+** zorunlu. |
| Aktif PHP eklentileri | `mbstring, pdo_mysql, intl, zip, gd, xml, ctype, fileinfo, openssl, tokenizer` |
| `memory_limit` | `composer install` sunucuda çalıştırılmayacak; yine de 256M önerilir. |
| MySQL sürümü ve `utf8mb4_turkish_ci` desteği | Türkçe arama davranışı buna bağlı. |
| Staging için ayrı subdomain / ayrı veritabanı | Canlı veriyle test edilmemeli. |
| Cron çalıştırma yetkisi | Günlük yedek ve deploy sonrası migration için gerekli. |
| **Veritabanı kullanıcısının TRIGGER yetkisi** | `ledger_entries` tablosunu UPDATE/DELETE'e karşı koruyan tetikleyiciler bu yetkiyi gerektirir. Yetki yoksa `2026_09_07_050100_protect_ledger_entries_from_writes` migration'ı **hata verir ve deploy durur** — bu bilinçlidir: koruma var sanılıp olmaması daha kötüdür. Yetki alınamıyorsa karar kullanıcıya sorulmalıdır. Lemon hedefi için durum: `docs/deploy-lemon.md` §8 (blocker değil; doğrulama ilk gerçek migration'dadır). |

## 2. Dizin yerleşimi

Laravel kökü **web'den erişilemeyen** bir dizinde durur:

> **Güncel ve bağlayıcı yerleşim `docs/deploy-lemon.md` §2'dedir (Mimari A).**
> Aşağıdaki genel şema onunla çelişirse deploy-lemon.md geçerlidir.

```
/home/<kullanici>/
├── <uygulama>/          # Laravel kökü — .env, storage, vendor BURADA
│   ├── .env
│   ├── artisan
│   └── public/          # ← DocumentRoot BURAYA yönlendirilir
│       ├── index.php    # Laravel'in KENDİ dosyası, değiştirilmez
│       └── .htaccess    # HTTPS zorunluluğu + güvenlik başlıkları
└── backups/
```

DocumentRoot doğrudan Laravel'in `public/` dizinini gösterdiği için ayrı bir
giriş dosyası şablonu **gerekmez**; `deploy/public_html-*.php` şablonları bu
nedenle depodan kaldırılmıştır.

## 3. İlk kurulum adımları

1. Alt alan adı (staging) ve veritabanı cPanel'den oluşturulur.
   Veritabanı collation'ı **`utf8mb4_turkish_ci`** olmalıdır.
2. `app/` dizini oluşturulur, proje dosyaları (vendor ve public/build dahil) yüklenir.
3. `.env` sunucuda elle oluşturulur — **repoya asla girmez**:
   ```
   APP_ENV=staging
   APP_DEBUG=false
   APP_URL=https://staging.<alan-adi>
   APP_TIMEZONE=Europe/Istanbul
   APP_LOCALE=tr
   DB_CONNECTION=mysql
   DB_DATABASE=<cpanel_db>
   DB_USERNAME=<cpanel_user>
   DB_PASSWORD=<parola>
   DB_COLLATION=utf8mb4_turkish_ci
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   ```
4. `php artisan key:generate --force`
5. **Eski cache'i temizle — MIGRATE'TEN ÖNCE, atlanmaz (K-102):**
   `php artisan config:clear route:clear view:clear event:clear`
   Gerekçe: önceki deploy'dan kalan bayat bir `bootstrap/cache/config.php`,
   bu deploy'da YENİ eklenen bir modülün Service Provider'ını taşımaz —
   o modülün migration dizini `migrate`e hiç kaydedilmez ve dosyaları
   sessizce, hatasız atlanır (gerçek üretim olayı — Room Registry modülü,
   bkz. `docs/kararlar.md` K-102). Ayrıca bayat cache yeni `.env`
   değerlerini de görmezden bırakabilir.
6. `php artisan migrate --force`
7. **`php artisan db:seed --class=RolePermissionSeeder --force`** — HER
   deploy'da, migrate'ten hemen sonra, ATLANMAZ. Gerekçe: kodda yeni bir
   `Permission` case'i eklenip deploy edildiğinde, bu adım koşulmazsa
   production'da "There is no permission named X for guard web" hatası
   üretir (gerçek üretim olayı — bkz. `docs/release-checklist-v2.1.1.md`
   §1). Seeder idempotenttir, kullanıcı rol atamalarını bozmaz.
8. `php artisan storage:link`
9. Production cache'ini kur: `php artisan config:cache route:cache
   view:cache event:cache` (her deploy sonrası tekrarlanır)

**Tam sıra ve finansal duman testi için: `docs/release-checklist-v2.1.1.md`.**

## 4. Deploy sonrası güvenlik doğrulaması

Bu kontroller **her deploy sonrası** yapılır:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://staging.<alan>/.env          # 403 veya 404 olmalı
curl -s -o /dev/null -w "%{http_code}\n" https://staging.<alan>/storage/logs/laravel.log
curl -s -o /dev/null -w "%{http_code}\n" https://staging.<alan>/composer.json
curl -s -o /dev/null -w "%{http_code}\n" https://staging.<alan>/vendor/autoload.php
```

Herhangi biri **200 dönerse deploy geri alınır.** Bu dosyaların internete açık
olması, veritabanı parolası ve `APP_KEY` dahil her şeyin sızması demektir.

Ayrıca:
- `APP_DEBUG=false` doğrulanır (hata sayfasında stack trace görünmemeli).
- HTTPS yönlendirmesi çalışıyor olmalı.

## 5. Derleme nerede yapılır

`vendor/` ve `public/build/` **sunucuda üretilmez**:

- `composer install --no-dev --optimize-autoloader` → CI'da
- `npm ci && npm run build` → CI'da

Gerekçe: paylaşımlı hostingde `composer install` bellek limitine takılır ve
Node çoğu cPanel hesabında yoktur. Sunucuya yalnızca hazır çıktı gider.

## 6. Yedekleme

Günlük cron:

```
0 3 * * * mysqldump -u <user> -p<parola> <db> | gzip > /home/<kullanici>/backups/db-$(date +\%F).sql.gz
```

- Yedekler **şifrelenerek** hesap dışına (offsite) kopyalanmalıdır.
- cPanel'in kendi yedeği tek başına yeterli değildir — sunucu kaybında o da gider.
- **Ayda bir geri yükleme tatbikatı** yapılmalıdır; denenmemiş yedek yedek değildir.

## 7. Kural

`main` dalı canlıya, geliştirme dalı staging'e gider.
**Production deploy yalnızca açık onayla yapılır.**
