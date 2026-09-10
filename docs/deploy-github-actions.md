# Deploy Mimarisi — GitHub Actions + FTPS (genel ilkeler)

> **Bu dosya genel ilkeleri anlatır.**
> Somut hedef (lemon.derspros.com.tr), gerçek secret adları, dizin mimarisi
> kararı ve kurulum adımları için **`docs/deploy-lemon.md`** esastır.
> Çelişki hâlinde `deploy-lemon.md` geçerlidir.
>
> **Durum: hâlâ hiçbir deploy yapılmadı.** `.github/workflows/deploy.yml`
> mevcuttur ancak üç bağımsız kilit nedeniyle gerçek aktarım yapamaz
> (bkz. `deploy-lemon.md` §9).

## 1. Temel ilkeler

- **SSH erişimi zorunlu kabul edilmez.** Dosya aktarımı FTPS ile yapılır.
- Tüm derleme işleri **GitHub Actions runner'ında** yapılır:
  `composer install --no-dev --optimize-autoloader` ve `npm ci && npm run build`.
  Paylaşımlı hostingde composer bellek limitine takılır, Node çoğu hesapta yoktur.
- **`.env` deponun dışında kalır.** Sunucuda elle oluşturulur ve deploy sırasında
  **asla üzerine yazılmaz**.
- **Kullanıcı yüklemeleri korunur** (`storage/app/`), deploy tarafından silinmez.
- `storage/` ve `bootstrap/cache/` yazma izinleri korunur.
- Staging ve production **ayrı hedeflerdir**; ayrı secret setleri, ayrı veritabanı.
- `develop` → staging, `main` → production.

## 2. GitHub Secrets ve Variables

**Güncel liste `docs/deploy-lemon.md` §3'tedir.** Bu bölümde daha önce yer alan
`FTP_STAGING_*` / `FTP_PRODUCTION_*` taslak adları **kullanılmamaktadır**;
depoda tanımlı gerçek secret'lar `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`,
`FTP_PORT`'tur.

Değişmeyen ilke: **`APP_KEY`, veritabanı parolası ve diğer uygulama ayarları
GitHub'a konmaz**; sunucudaki `.env` dosyasında yaşarlar. GitHub Secrets
yalnızca dosya aktarımı için gereken kimlik bilgilerini tutar.

Dizin yolları secret değil **repository variable** olarak tutulur: gizli bilgi
değillerdir ve doğrulama adımının değeri sızdırmadan kontrol etmesine izin verir.

## 3. Deploy edilen ve edilmeyen dosyalar

**Gönderilir:** uygulama kodu, `vendor/`, `public/build/`, `config/`, `database/migrations/`, `resources/views/`, `lang/`

**Gönderilmez (`exclude`):**
```
.git*, .github/, .claude/, tests/, node_modules/, docs/, deploy/,
.env*, storage/**, bootstrap/cache/**, phpunit.xml, pint.json,
package*.json, composer.json, composer.lock, vite.config.js, README.md, CLAUDE.md
```

`storage/**` ve `.env` hariç tutulması kritiktir: aksi halde kullanıcı
yüklemeleri ve sunucu yapılandırması her deploy'da silinir.

## 4. Workflow

Taslak artık bu dosyada tutulmuyor: **gerçek dosya `.github/workflows/deploy.yml`**.
İki kopya tutmak, birinin diğerinden sessizce ayrışmasına yol açardı.

Workflow'un güvenlik tasarımı (üç kilit, hedef dizin doğrulaması, artifact
temizlik kontrolü, hedef PHP uyumluluk kapısı) `docs/deploy-lemon.md` §4 ve §9'da
açıklanmıştır.

## 5. Deploy sonrası zorunlu doğrulama

Workflow'un son adımı bunu otomatik yapar; elle de kontrol edilmelidir:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/.env                    # 403/404
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/storage/logs/laravel.log # 403/404
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/vendor/autoload.php      # 403/404
```

Herhangi biri 200 dönerse deploy **geri alınır**.

## 6. KARAR VERİLDİ: cPanel cron + işaret dosyası

`php artisan migrate --force`, `db:seed --class=RolePermissionSeeder --force`,
`config:cache`, `route:cache`, `view:cache` ve `event:cache` sunucuda çalışmak
zorundadır. SSH yoksa iki seçenek vardır:

### Seçilen yöntem — cPanel cron + işaret dosyası

**Karar (Gün 3):** Seçenek A uygulanacaktır. İnternete açık migrate/finalize
HTTP ucu **oluşturulmayacaktır.** Akış:

1. GitHub Actions deploy sonunda `storage/app/deploy-pending` işaret dosyasını bırakır.
2. cPanel cron dosyayı kontrol eder.
3. **Önce eski cache'i temizler** (`config:clear route:clear view:clear
   event:clear`) — MIGRATE'TEN ÖNCE, atlanamaz (K-102). Gerekçe: bu
   deploy'da yeni eklenen bir modülün Service Provider'ı
   `bootstrap/providers.php`'de olsa bile, sunucuda kalan bayat bir
   `bootstrap/cache/config.php` hâlâ ESKİ provider listesini taşıyorsa
   `migrate` o modülün Service Provider'ını hiç ÇALIŞTIRMAZ — migration
   dizini kaydedilmez, dosyaları migrate'e GÖRÜNMEZ (hatasız, sessizce
   atlanır). Bu, gerçek bir üretim olayının BİREBİR kök nedenidir (Room
   Registry modülü — bkz. `docs/kararlar.md` K-102).
4. Gerekiyorsa `php artisan migrate --force` çalıştırır.
5. **Ardından `php artisan db:seed --class=RolePermissionSeeder --force`
   çalıştırır — atlanamaz.** Gerekçe: koddaki yeni bir `Permission` case'i
   deploy edilip bu adım koşulmazsa production "There is no permission
   named X for guard web" hatası verir (gerçek üretim olayı — tam sıra ve
   gerekçe: `docs/release-checklist-v2.1.1.md` §1).
6. Ardından üretim cache'ini kurar (`config:cache route:cache view:cache
   event:cache`).
7. Sonucu günlüğe yazar.
8. **Başarılıysa** işaret dosyası silinir.
9. **Başarısızsa** işaret dosyası korunur ve hata günlüğe yazılır (bir sonraki
   cron tekrar dener; sorun görünür kalır).

Bu bölüm yalnızca mimari karardır — `finalize.php` ve deploy workflow'u
**henüz yazılmamıştır** ve deploy fazına kadar yazılmayacaktır.

<details><summary>Değerlendirilen seçenekler</summary>

### Seçenek A — cPanel cron + işaret dosyası (seçildi)

Deploy, `storage/app/deploy-pending` dosyasını bırakır. cPanel'de dakikada bir
çalışan bir cron bu dosyayı kontrol eder:

```bash
* * * * * /usr/local/bin/php /home/<kullanici>/app/deploy/finalize.php >> /home/<kullanici>/deploy.log 2>&1
```

`finalize.php`, işaret dosyası varsa migration ve cache komutlarını çalıştırır,
sonra dosyayı siler.

- ✅ İnternete açık hiçbir uç yok
- ✅ Ek kimlik doğrulama gerekmiyor
- ⚠️ Deploy ile migration arasında en fazla 1 dakikalık gecikme
- ⚠️ cPanel'de cron yetkisi gerekir

### Seçenek B — Token korumalı HTTP ucu

Deploy sonrası workflow, gizli bir token ile bir route'a istek atar; o route
migration'ları çalıştırır.

- ✅ Anında çalışır, cron gerekmez
- ❌ **İnternete açık, migration çalıştırabilen bir uç** demektir. Token sızarsa
  veritabanı şeması uzaktan değiştirilebilir.
- ❌ Uzun süren migration'da HTTP zaman aşımı riski

</details>

Seçenek B (token korumalı HTTP ucu) **reddedilmiştir**: internete açık, migration
çalıştırabilen bir uç, token sızması hâlinde veritabanı şemasının uzaktan
değiştirilmesine izin verir.

## 7. İlk kurulumda sunucuda elle yapılacaklar

Hedefe özel, güncel ve sıralı liste: **`docs/deploy-lemon.md` §11**.
