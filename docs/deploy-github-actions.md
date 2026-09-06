# Deploy Mimarisi — GitHub Actions + FTPS

> **Durum: yalnızca tasarım.** Bu dosya bir plandır. Depoda **çalışan bir deploy
> workflow'u yoktur**, hiçbir deploy yapılmamıştır ve production veritabanı
> oluşturulmamıştır. Aşağıdaki taslak, secret'lar tanımlandıktan ve §6'daki
> migration kararı verildikten sonra `.github/workflows/` altına taşınacaktır.

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

## 2. GitHub Secrets listesi

Repository → Settings → Secrets and variables → Actions.

### Staging (ortam adı: `staging`)

| Secret | Örnek / Açıklama |
|---|---|
| `FTP_STAGING_HOST` | `ftp.alanadi.com` — hosting.com.tr FTP sunucusu |
| `FTP_STAGING_USERNAME` | cPanel FTP kullanıcısı |
| `FTP_STAGING_PASSWORD` | FTP parolası |
| `FTP_STAGING_PORT` | `21` (FTPS explicit). Sağlayıcı farklı port veriyorsa o. |
| `FTP_STAGING_APP_DIR` | `/home/<kullanici>/staging-app/` — Laravel kökü (web'den erişilemez) |
| `FTP_STAGING_PUBLIC_DIR` | `/home/<kullanici>/staging_html/` — DocumentRoot |
| `STAGING_DEPLOY_URL` | Doğrulama isteği atılacak adres, ör. `https://staging.alanadi.com` |

### Production (ortam adı: `production`)

| Secret | Açıklama |
|---|---|
| `FTP_PRODUCTION_HOST` | |
| `FTP_PRODUCTION_USERNAME` | |
| `FTP_PRODUCTION_PASSWORD` | |
| `FTP_PRODUCTION_PORT` | |
| `FTP_PRODUCTION_APP_DIR` | `/home/<kullanici>/app/` |
| `FTP_PRODUCTION_PUBLIC_DIR` | `/home/<kullanici>/public_html/` |
| `PRODUCTION_DEPLOY_URL` | |

**Secret olmayanlar:** `APP_KEY`, veritabanı parolası ve diğer uygulama ayarları
GitHub'a **konmaz**; bunlar sunucudaki `.env` dosyasında yaşar. GitHub Secrets
yalnızca dosya aktarımı için gereken kimlik bilgilerini tutar.

**Environment protection:** `production` ortamı GitHub'da "required reviewers"
ile korunmalıdır; böylece `main`'e her merge otomatik canlıya çıkmaz.

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

## 4. Önerilen workflow taslağı

```yaml
# .github/workflows/deploy.yml  — HENÜZ ETKİN DEĞİL
name: Deploy

on:
  workflow_dispatch:
    inputs:
      target:
        description: Deploy hedefi
        type: choice
        options: [staging, production]
        required: true

concurrency:
  group: deploy-${{ inputs.target }}
  cancel-in-progress: false

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: ${{ inputs.target }}     # production için reviewer koruması
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, pdo_mysql, intl, zip, gd, xml, ctype, fileinfo

      - name: Composer (üretim)
        run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

      - uses: actions/setup-node@v4
        with: { node-version: '22', cache: npm }

      - name: Frontend derlemesi
        run: npm ci && npm run build

      - name: Deploy işareti bırak
        # Sunucudaki cron bu dosyayı görünce migration ve cache adımlarını çalıştırır (§6).
        run: date -u +%FT%TZ > storage/app/deploy-pending

      - name: FTPS ile Laravel kökünü gönder
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          port: ${{ secrets.FTP_PORT }}
          protocol: ftps
          server-dir: ${{ secrets.FTP_APP_DIR }}
          exclude: |
            **/.git*
            **/.git*/**
            **/.github/**
            **/.claude/**
            **/tests/**
            **/node_modules/**
            **/docs/**
            **/deploy/**
            **/storage/**
            **/bootstrap/cache/**
            .env*
            phpunit.xml
            pint.json
            package*.json
            composer.json
            composer.lock
            vite.config.js
            README.md
            CLAUDE.md
            AGENTS.md

      - name: Derlenmiş varlıkları public dizinine gönder
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          port: ${{ secrets.FTP_PORT }}
          protocol: ftps
          local-dir: ./public/build/
          server-dir: ${{ secrets.FTP_PUBLIC_DIR }}build/

      - name: Güvenlik doğrulaması
        run: |
          for path in .env storage/logs/laravel.log composer.json vendor/autoload.php; do
            code=$(curl -s -o /dev/null -w '%{http_code}' "${{ secrets.DEPLOY_URL }}/$path")
            echo "$path -> $code"
            if [ "$code" = "200" ]; then
              echo "::error::$path internete açık — deploy geri alınmalı"
              exit 1
            fi
          done
```

Not: `secrets.FTP_HOST` gibi adlar, ortam (`environment:`) düzeyinde tanımlanan
secret'lara karşılık gelir; staging ve production için aynı adlar farklı
değerlerle kullanılır. §2'deki ayrı adlandırma yerine ortam bazlı secret
kullanmak workflow'u tek kopya tutar — hangi yaklaşımın seçileceği kurulum
sırasında netleşecektir.

## 5. Deploy sonrası zorunlu doğrulama

Workflow'un son adımı bunu otomatik yapar; elle de kontrol edilmelidir:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/.env                    # 403/404
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/storage/logs/laravel.log # 403/404
curl -s -o /dev/null -w "%{http_code}\n" https://<alan>/vendor/autoload.php      # 403/404
```

Herhangi biri 200 dönerse deploy **geri alınır**.

## 6. AÇIK KARAR: SSH olmadan migration nasıl çalıştırılacak?

`php artisan migrate --force`, `config:cache`, `route:cache` ve `view:cache`
sunucuda çalışmak zorundadır. SSH yoksa iki seçenek vardır:

### Seçenek A — cPanel cron + işaret dosyası (önerilen)

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

**Öneri: Seçenek A.** Bu karar verilmeden deploy workflow'u etkinleştirilmemelidir;
`finalize.php` de bu karardan önce yazılmayacaktır.

## 7. İlk kurulumda sunucuda elle yapılacaklar

1. Staging subdomain + veritabanı (collation `utf8mb4_turkish_ci`)
2. `~/staging-app/` ve `~/staging_html/` dizinleri
3. `deploy/public_html-index.php` → `staging_html/index.php` (içindeki `$laravel` yolu güncellenir)
4. `deploy/public_html-htaccess` → `staging_html/.htaccess`
5. `.env` dosyası (bkz. `docs/deploy-cpanel.md` §3)
6. `php artisan key:generate --force`, `migrate --force`, `storage:link`
7. Günlük yedek cron'u
