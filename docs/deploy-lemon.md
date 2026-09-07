# Lemon Deploy Hazırlığı — lemon.derspros.com.tr

> **Durum: hazırlık. Hiçbir deploy yapılmadı.**
> Hosting'e bağlanılmadı, FTP testi yapılmadı, production veritabanı
> oluşturulmadı, cPanel'de hiçbir ayar değiştirilmedi.
> `.github/workflows/deploy.yml` bu hâliyle **gerçek aktarım yapamaz** (§9).

## 0. Hedef ortam

| | |
|---|---|
| Alan adı | `lemon.derspros.com.tr` |
| cPanel hesap kökü | `/home/derspros` |
| Lemon DocumentRoot (mevcut) | `/home/derspros/lemon.derspros.com.tr` |
| Ana site DocumentRoot | `/home/derspros/public_html` |
| PHP | 8.3 |
| Aktarım | GitHub Actions → FTPS |
| SSH | kullanılmıyor |

**Aynı hesapta başka aktif siteler vardır.** `public_html`, diğer alan adı
klasörleri ve `/home/derspros` kökü **deploy hedefi değildir**. Workflow'daki
`guard` işi bu dizinleri kalıp kontrolüyle reddeder (§9).

---

## 1. PHP sürümü — ÇÖZÜLDÜ ✅

> Bu bölüm daha önce bir **blocker** idi: kilitli Symfony 8.1.x bileşenleri
> PHP ≥ 8.4.1 istiyordu, hosting'de 8.3 vardı. **Artık çözülmüştür.**

**Neden 8.4'e yükseltilmedi:** Hosting'de PHP 8.4 kurulu, ancak **alan adı bazlı
PHP izolasyonu kapalı**. Sürüm yalnızca hesabın tamamı için değiştirilebiliyor ve
aynı hesapta başka aktif siteler var; global bir değişiklik onları da etkilerdi.

**Yapılan:** Uygulama PHP 8.3'e kilitlendi.

| Kapı | Nasıl |
|---|---|
| Bağımlılık çözümü | `composer.json` → `config.platform.php = "8.3.0"` |
| Test | CI (`ci.yml`) PHP **8.3** kullanır |
| Statik analiz | `phpstan.neon` → `phpVersion: 80300` |
| Derleme | Deploy workflow'u PHP 8.3 ile derler |
| Doğrulama | CI ve deploy build'i `composer check-platform-reqs` çalıştırır |

**Sonuç:** `composer why-not php 8.3` → *"There is no installed package depending
on 'php' in versions not matching 8.3"*. 16 Symfony paketi 8.1.x'ten **7.4 LTS**'e
indi; Laravel v13.30.1 değişmedi (`^8.3` zaten destekliyordu).
`composer audit` → güvenlik uyarısı yok.

Ayrıntılı paket dökümü: `docs/kararlar.md` K-060.

---

## 2. Dizin mimarisi — karar bekliyor

Laravel'de web'den yalnızca `public/` erişilebilir olmalıdır. `.env`, `vendor/`,
`storage/` ve kaynak kod DocumentRoot'un **dışında** kalmalıdır.

### Mimari A — DocumentRoot'u Laravel'in `public/` dizinine almak (ÖNERİLEN)

```
/home/derspros/
├── lemon-app/                 ← Laravel kökü, web'den ERİŞİLEMEZ
│   ├── .env                   (sunucuda elle oluşturulur)
│   ├── vendor/  storage/  bootstrap/  app/  config/  routes/ …
│   └── public/                ← DocumentRoot buraya yönlendirilir
│       ├── index.php          (Laravel'in kendi dosyası — DEĞİŞTİRİLMEZ)
│       ├── build/             (Vite çıktısı)
│       └── .htaccess
├── public_html/               ← ana site, DOKUNULMAZ
└── lemon.derspros.com.tr/     ← kullanılmaz hâle gelir (silinmez, boş bırakılır)
```

cPanel → Domains → `lemon.derspros.com.tr` → Document Root:
`/home/derspros/lemon-app/public`

- ✅ `index.php` hiç değiştirilmez, standart Laravel yerleşimi
- ✅ `public_path()`, `asset()`, `storage:link` doğal çalışır
- ✅ Deploy tek hedefe yazar; workflow'daki ikinci aktarım adımı kaldırılır
- ⚠️ cPanel'de DocumentRoot değiştirme yetkisi gerekir

### Mimari B — DocumentRoot değiştirilemiyorsa

```
/home/derspros/
├── lemon-app/                     ← Laravel kökü, web'den ERİŞİLEMEZ
└── lemon.derspros.com.tr/         ← DocumentRoot (değişmedi)
    ├── index.php                  (deploy/public_html-index.php şablonu)
    ├── .htaccess                  (deploy/public_html-htaccess şablonu)
    ├── build/                     (Vite çıktısı — ayrıca aktarılır)
    ├── favicon.ico, robots.txt
    └── storage -> ../lemon-app/storage/app/public
```

`index.php` tek satırlık bir taban yolu tanımı taşır — Laravel'in kendi
dosyasının zaten yaptığı `require __DIR__.'/../vendor/autoload.php'` işleminin
göreli bir varyantıdır. **Mutlak yol yazılmaz**, `bindPath` hack'i yapılmaz:

```php
$laravel = __DIR__.'/../lemon-app';
```

Maliyeti dürüstçe:
- ⚠️ `public_path()` sunucuda **servis edilmeyen** `lemon-app/public` dizinini
  gösterir. `storage:link` bu yüzden DocumentRoot altında elle kurulmalıdır.
- ⚠️ Vite çıktısı iki yere gider (Laravel kökü + DocumentRoot) → ekstra adım
- ⚠️ `public/` içine dosya yazan her yeni özellik ayrı düşünülmelidir

### Neden şimdi seçilemez

Workflow'un `LEMON_DOCROOT` değişkeni ve ikinci aktarım adımı **doğrudan bu
seçime bağlıdır**. cPanel'de DocumentRoot'un değiştirilip değiştirilemediğini
görmeden yazılacak workflow tahmine dayanır ve yanlış dizine yazma riski taşır.

**Bu yüzden §11'deki ilk madde sizde: cPanel'de DocumentRoot değiştirilebiliyor
mu?** Cevaba göre workflow'un son hâli tek bir adım eklenerek/çıkarılarak
kesinleşir.

---

## 3. GitHub yapılandırması

### Secrets (zaten oluşturuldu)

| Secret | İçerik |
|---|---|
| `FTP_SERVER` | FTP sunucu adresi |
| `FTP_USERNAME` | FTP kullanıcısı |
| `FTP_PASSWORD` | FTP parolası |
| `FTP_PORT` | FTP portu |

Secret **değerleri** hiçbir adımda yazdırılmaz.

### Variables (henüz OLUŞTURULMADI — §11)

Dizin yolları secret değil **variable** olarak tutulur: gizli bilgi değillerdir,
ama gözden geçirilebilir ve `guard` işi hata mesajında değeri sızdırmadan
kontrol edebilir.

| Variable | Değer (mimari seçimine göre) |
|---|---|
| `LEMON_APP_DIR` | `/home/derspros/lemon-app/` — **sonunda `/` zorunlu** |
| `LEMON_DOCROOT` | A'da `/home/derspros/lemon-app/public/`, B'de `/home/derspros/lemon.derspros.com.tr/` |
| `TARGET_PHP_VERSION` | `8.3` (veya §1/A seçilirse `8.4`) |
| `DEPLOY_ENABLED` | **Şimdi oluşturulmaz.** Gerçek deploy açılana kadar yok. |

`APP_KEY`, veritabanı parolası ve diğer uygulama ayarları **GitHub'a konmaz**;
sunucudaki `.env` dosyasında yaşarlar.

---

## 4. Aktarılan ve aktarılmayan dosyalar

Build işi artifact'ı hazırlarken geliştirme dosyalarını **siler**; FTP adımı
ayrıca `exclude` listesiyle korur; aktarım öncesi bir kontrol adımı da
artifact'ın temiz olduğunu doğrular. Üç katman aynı şeyi söyler.

**Aktarılır:** `app/`, `bootstrap/app.php`, `config/`, `database/`, `lang/`,
`public/` (build dahil), `resources/views/`, `routes/`, `vendor/`, `artisan`

**Aktarılmaz:**

| Dışlanan | Neden |
|---|---|
| `.env`, `.env.*` | Sunucudaki yapılandırma **asla** ezilmemeli |
| `.git`, `.github/`, `.claude/` | Kaynak geçmişi ve CI yapılandırması web'e çıkmamalı |
| `tests/`, `docs/` | Üretimde işe yaramaz, saldırı yüzeyi büyütür |
| `node_modules/` | Derleme CI'da yapılır |
| `storage/**` | **Kullanıcı verisi, oturumlar, günlükler.** Silinirse geri gelmez |
| `bootstrap/cache/**` | Sunucuda üretilir; ezilirse önbellek bozulur |
| `composer.json/lock`, `package*.json`, `vite.config.js`, `phpunit.xml`, `pint.json`, `phpstan.neon` | Üretimde gereksiz |

**`storage/` ve `bootstrap/cache/` sunucuda BİR KEZ elle oluşturulur** (§11) ve
bir daha hiçbir deploy onlara dokunmaz.

### Aracın tehlikeli davranışları

- `dangerous-clean-slate` **kullanılmaz.** Hedef dizinin tüm içeriğini,
  `exclude` edilenler dahil siler.
- `exclude` verildiğinde aracın **varsayılan listesi ezilir**; bu yüzden
  `**/.git*` ve `**/node_modules/**` kalıpları workflow'da tekrar yazılmıştır.
- Araç, kendi tuttuğu `.ftp-deploy-*.json` durum dosyasıyla senkronizasyon
  yapar. **İlk çalıştırmada, durum dosyası yokken sunucuda halihazırda bulunan
  dosyalara ne yaptığı belgelenmemiştir** — kaynak koda inilerek doğrulanmadı.
  Bu yüzden ilk çalıştırma **zorunlu olarak `dry-run`** olmalıdır (§9).

---

## 5. Production `.env` şablonu

Sunucuda `/home/derspros/lemon-app/.env` olarak **elle** oluşturulur.
**Repoya girmez, deploy tarafından gönderilmez, üzerine yazılmaz.**

```dotenv
APP_NAME="Otel Cari Takip"
APP_ENV=production
APP_KEY=                       # ← sunucuda üretilir, buraya YAZILMAZ (§5.1)
APP_DEBUG=false
APP_URL=https://lemon.derspros.com.tr
APP_TIMEZONE=Europe/Istanbul

APP_LOCALE=tr
APP_FALLBACK_LOCALE=tr
APP_FAKER_LOCALE=tr_TR

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error                # production'da debug DEĞİL
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=derspros_lemon     # cPanel'de oluşturulan gerçek ad
DB_USERNAME=derspros_lemon
DB_PASSWORD=                   # ← cPanel'de üretilen parola
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_turkish_ci

# Paylaşımlı hosting gerçeği (CLAUDE.md §2): Redis yok, kalıcı worker yok
SESSION_DRIVER=database
SESSION_LIFETIME=480           # 8 saat (CLAUDE.md §8)
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true     # HTTPS zorunlu olduğu için
CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=log                # V1'de e-posta gönderimi yok (CLAUDE.md §3.2)
MAIL_FROM_ADDRESS="noreply@lemon.derspros.com.tr"
MAIL_FROM_NAME="${APP_NAME}"
```

### 5.1 `APP_KEY`

Repoda üretilmez, GitHub'a konmaz, bu dosyaya yazılmaz. Sunucuda bir kez:

```bash
php artisan key:generate --force
```

**Uygulama canlıya girdikten sonra `APP_KEY` değiştirilirse tüm oturumlar ve
şifrelenmiş veriler okunamaz hâle gelir.** Yedeklenmesi gereken bir sırdır.

---

## 6. Migration stratejisi — cron + işaret dosyası

Karar korunuyor (Gün 3, `docs/deploy-github-actions.md` §6):

> **İnternete açık `/migrate`, `/deploy`, `/artisan` benzeri bir HTTP ucu
> KESİNLİKLE oluşturulmaz.** Token korumalı uç reddedilmiştir: token sızarsa
> veritabanı şeması uzaktan değiştirilebilir.

Tasarlanan akış:

```
GitHub deploy  →  storage/app/deploy-pending işaret dosyası
                        ↓
              cPanel cron (dakikada bir)
                        ↓
       işaret var mı? → php artisan migrate --force
                        → config:cache / route:cache / view:cache
                        ↓
        başarılı → günlüğe yaz + işaret dosyasını SİL
        başarısız → işaret dosyası KALIR + hata günlüğe yazılır
                    (sonraki cron tekrar dener, sorun görünür kalır)
```

Planlanan cron satırı:

```bash
* * * * * /usr/local/bin/ea-php83 /home/derspros/lemon-app/deploy/finalize.php \
          >> /home/derspros/lemon-deploy.log 2>&1
```

**`finalize.php` bu turda YAZILMADI** ve production cron kurulmadı — talimat
gereği yalnızca tasarım dokümante edildi. Yazıldığında taşıması gerekenler:

- işaret dosyası yoksa hiçbir şey yapmadan çıkmak (cron her dakika koşar)
- eşzamanlı iki çalıştırmayı engelleyen kilit
- `migrate --force` çıktısını ve çıkış kodunu günlüğe yazmak
- **başarısızlıkta işaret dosyasını silmemek**
- yalnızca CLI'dan çalışabilmek (`php_sapi_name() === 'cli'` değilse çıkmak)

---

## 7. Deploy sonrası zorunlu doğrulama

Aşağıdakilerin **hepsi** 403/404 dönmelidir. Herhangi biri 200 dönerse
deploy geri alınır:

```bash
for p in .env storage/logs/laravel.log composer.json vendor/autoload.php \
         artisan config/database.php .git/config; do
  printf '%-28s %s\n' "$p" \
    "$(curl -s -o /dev/null -w '%{http_code}' https://lemon.derspros.com.tr/$p)"
done
```

Ayrıca:
- `https://lemon.derspros.com.tr/` → giriş ekranı açılıyor mu
- HTTP → HTTPS yönlendirmesi çalışıyor mu
- Bir firma kaydı açılıp cari ekstre görüntüleniyor mu (ledger okunuyor mu)

---

## 8. Ledger TRIGGER yetkisi — ikinci blocker

`ledger_entries` tablosunun değiştirilemezliği, uygulama katmanına ek olarak
**veritabanı tetikleyicileriyle** korunur
(`2026_09_07_050100_protect_ledger_entries_from_writes`).

Bu migration `CREATE TRIGGER` çalıştırır ve veritabanı kullanıcısının
**TRIGGER yetkisi** olmasını gerektirir. Paylaşımlı hostinglerde bu yetki
bazen verilmez.

**Yetki doğrulanmadan production migration çalıştırılmamalıdır.**

Kontrol (cPanel → phpMyAdmin → SQL):

```sql
SHOW GRANTS FOR CURRENT_USER();
-- Çıktıda TRIGGER geçmeli veya ALL PRIVILEGES olmalı.
```

Yetki yoksa migration **hata verir ve durur** — bu bilinçlidir: koruma var
sanılıp olmaması, hiç olmamasından kötüdür. O durumda karar kullanıcıya
sorulacaktır (hosting'den yetki istemek / korumayı yeniden tasarlamak).

---

## 9. Workflow neden şimdi deploy edemiyor

`.github/workflows/deploy.yml` üç bağımsız kilit taşır:

1. **Tetikleme:** yalnızca `workflow_dispatch`. `push`/`pull_request` yok —
   `main` veya `develop`'a merge hiçbir şey başlatmaz.
2. **Varsayılan biçim `dry-run`:** araç bağlanır, ne yapacağını raporlar,
   **hiçbir dosya yazmaz/silmez**.
3. **`DEPLOY_ENABLED` variable'ı:** `true` değilse `mode: deploy` seçilse bile
   `guard` işi hatayla durur. Bu değişken **henüz oluşturulmamıştır**.

Gerçek deploy ayrıca `confirm_target` alanına `lemon.derspros.com.tr` yazılmasını
ister — yanlışlıkla tıklamayı engeller.

Ek kapılar: hedef dizin kalıp doğrulaması (§0), artifact temizlik kontrolü (§4),
`composer check-platform-reqs` (§1).

---

## 10. Branch politikası önerisi

Lemon şu anda **ilk kabul/staging ortamı** olarak kullanılacak.

**Öneri: `develop` → Lemon.** Gerekçe:

- `develop` zaten entegrasyon dalıdır (CLAUDE.md §16); Lemon'un rolü de budur.
- `main`'i Lemon'a bağlamak, `main`'in anlamını "production" olmaktan çıkarır;
  gerçek production geldiğinde iki ortam aynı dalı paylaşır ve ayrıştırmak
  geriye dönük iş çıkarır.
- Kabul testleri sırasında Lemon'a sık deploy gerekir; `main`'e sık merge
  etmek dal modelini bozar.

Gerçek production ortamı geldiğinde: `main` → production, `develop` → Lemon.

**Otomatik push deployment hiçbir dal için etkinleştirilmedi ve sizin onayınız
olmadan etkinleştirilmeyecek.** Onay verdiğinizde bile önerim, `develop` için
`push` tetikleyicisi yerine `workflow_dispatch`'te kalmaktır: paylaşımlı
hostingde geri alma maliyeti yüksektir.

---

## 11. Gerçek deploy öncesi cPanel'de yapmanız gerekenler

Sırayla:

1. **DocumentRoot değiştirilebiliyor mu?** (§2) — Domains → `lemon.derspros.com.tr`
   → Document Root alanı düzenlenebiliyor mu? **Mimari kararı buna bağlı.**
2. **PHP sürümü** (§1) — Select PHP Version'da **8.4 sunuluyor mu?**
   Sunuluyorsa 8.4'e alın (Çözüm A). Sunulmuyorsa bana bildirin, Çözüm B'yi
   uygulayayım.
3. **PHP eklentileri:** `mbstring, pdo_mysql, intl, zip, gd, xml, ctype,
   fileinfo, openssl, tokenizer`
4. **Veritabanı:** Lemon için ayrı veritabanı + kullanıcı.
   Collation **`utf8mb4_turkish_ci`** (Türkçe arama davranışı buna bağlı —
   CLAUDE.md §9).
5. **TRIGGER yetkisi** (§8) — `SHOW GRANTS FOR CURRENT_USER();` çıktısını bana iletin.
6. **Dizinler:** `/home/derspros/lemon-app/` oluşturun. İçinde bir kez:
   ```
   storage/app/public
   storage/framework/{cache/data,sessions,views}
   storage/logs
   bootstrap/cache
   ```
   Yazma izni: `755` (hosting `750` istiyorsa ona uyun).
   **Bu dizinlere hiçbir deploy dokunmayacak.**
7. **`.env`** dosyasını §5'teki şablonla oluşturun, gerçek DB bilgilerini girin.
8. **FTP hesabının kök dizini:** FTP kullanıcısı `/home/derspros` altını
   görebiliyor mu, yoksa belirli bir dizine mi hapsedilmiş? Buna göre
   `LEMON_APP_DIR` mutlak yol yerine göreli olmalı olabilir.
   **Bu bilgi olmadan `server-dir` tahmin edilemez.**
9. **FTPS destekleniyor mu?** Hosting explicit FTPS (AUTH TLS) sunuyor mu?
   Sunmuyorsa düz FTP ile parola açık gider — o durumda bana bildirin,
   deploy yöntemi yeniden değerlendirilmelidir.
10. **GitHub'da variable'ları oluşturun** (§3): `LEMON_APP_DIR`, `LEMON_DOCROOT`,
    `TARGET_PHP_VERSION`. `DEPLOY_ENABLED`'ı **henüz oluşturmayın.**
11. Hazır olduğunuzda workflow'u **`mode: dry-run`** ile çalıştırın ve çıktıyı
    bana gönderin. Yalnızca dry-run temiz çıkarsa `DEPLOY_ENABLED=true`
    oluşturulur.

---

## 12. Bu turda YAPILMAYANLAR

- Hosting'e bağlanılmadı, FTP testi yapılmadı
- Hiçbir dosya yüklenmedi, silinmedi, değiştirilmedi
- Workflow çalıştırılmadı
- Production veritabanı oluşturulmadı, migration çalıştırılmadı
- cPanel'de hiçbir ayar değiştirilmedi
- `composer.lock` değiştirilmedi (§1 kararı sizde)
- `finalize.php` ve production cron yazılmadı (§6)
- Hiçbir secret değeri okunmadı, yazdırılmadı veya depoya yazıldı
