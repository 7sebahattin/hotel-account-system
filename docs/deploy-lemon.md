# Lemon Deploy Hazırlığı — lemon.derspros.com.tr

> **Durum: hazırlık. Hiçbir deploy yapılmadı.**
> Bu depodan/CI'dan hosting'e bağlanılmadı, hiçbir dosya yüklenmedi, silinmedi
> veya değiştirilmedi; production veritabanı oluşturulmadı, cPanel'de hiçbir
> ayar değiştirilmedi. FTP kökü ve FTPS desteği kullanıcı tarafından bir FTP
> istemcisiyle **yalnızca dizin listeleyerek** doğrulanmıştır (§12).
> `DEPLOY_ENABLED` oluşturulmadı; **dry-run dahil hiçbir workflow çalıştırılmadı.**
> `.github/workflows/deploy.yml` bu hâliyle **gerçek aktarım yapamaz**; buna karşılık
> **dry-run her zaman güvenle çalıştırılabilir** — kilitler yalnızca gerçek deploy
> yolundadır (§9).

## 0. Hedef ortam

| | |
|---|---|
| Alan adı | `lemon.derspros.com.tr` |
| cPanel hesap kökü | `/home/derspros` |
| Lemon DocumentRoot (**mevcut**) | `/home/derspros/lemon.derspros.com.tr` — henüz değiştirilmedi |
| Lemon DocumentRoot (**hedef**) | `/home/derspros/lemon-app/public` — cPanel'den ELLE ayarlanacak (§11 Aşama 4) |
| Laravel kökü (sunucu açısından) | `/home/derspros/lemon-app/` |
| Laravel kökü (**FTP açısından**) | `/lemon-app/` ← workflow'daki `server-dir` bu değerdir |
| FTP kökü (**doğrulandı**) | `/` = `/home/derspros` — ana FTP hesabı buraya chroot'lu (§12) |
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

## 2. Dizin mimarisi — MİMARİ A (KESİNLEŞTİ)

cPanel'de `lemon.derspros.com.tr` alan adının **Document Root alanının
değiştirilebildiği doğrulanmıştır**. Bu yüzden Mimari A seçilmiştir.
Mimari B (ayrık public dizini) **kullanılmayacaktır** ve ona ait şablonlar
(`deploy/public_html-index.php`, `deploy/public_html-htaccess`) depodan
kaldırılmıştır.

```
/home/derspros/
├── lemon-app/                 ← Laravel kökü, web'den ERİŞİLEMEZ
│   ├── .env                   (sunucuda elle oluşturulur, deploy dokunmaz)
│   ├── vendor/  bootstrap/  app/  config/  routes/  database/  lang/
│   ├── storage/               (sunucuda bir kez kurulur, deploy dokunmaz)
│   └── public/                ← YENİ DocumentRoot
│       ├── index.php          Laravel'in KENDİ dosyası — değiştirilmez
│       ├── .htaccess          HTTPS zorunluluğu + güvenlik başlıkları burada
│       ├── build/             Vite çıktısı
│       ├── favicon.ico, robots.txt
│       └── storage -> ../storage/app/public   (storage:link)
│
├── public_html/               ← ana site (derspros.com.tr) — DOKUNULMAZ
├── lemon.derspros.com.tr/     ← ESKİ DocumentRoot; DocumentRoot taşındıktan
│                                 sonra kullanılmaz. Deploy hedefi DEĞİLDİR.
├── nuverna.derspros.com.tr/   ← DOKUNULMAZ
├── nuvernatrade.com/          ← DOKUNULMAZ
└── nymagro.com/               ← DOKUNULMAZ
```

**cPanel ayarı (elle yapılacak, bkz. §11):**
Domains → `lemon.derspros.com.tr` → Document Root:
`/home/derspros/lemon-app/public`

### Mimari A'nın getirdikleri

- `public/index.php` **hiç değiştirilmez** — Laravel'in standart giriş dosyası.
  Workflow'un build adımı bu dosyanın standart kaldığını ayrıca doğrular.
- `public_path()`, `asset()`, `storage:link` doğal çalışır.
- Deploy **tek hedefe** yazar; ikinci bir DocumentRoot aktarımı yoktur.
- `.env`, `vendor/` ve `storage/` DocumentRoot'un **dışındadır** — dizin
  yerleşiminin kendisi asıl korumadır.

### DocumentRoot'u kim değiştirir

**GitHub Actions DEĞİL.** Workflow'un cPanel'e erişimi yoktur ve bu değişikliği
yapamaz. Ayar §11'deki sırayla **elle** yapılır.

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

| Variable | Değer |
|---|---|
| `LEMON_APP_DIR` | **`/lemon-app/`** — FTP kökü `/home/derspros` olduğu için köke göre yol (§12). **Sonunda `/` zorunlu.** Guard başka hiçbir değeri kabul etmez (§4.1). |
| `TARGET_PHP_VERSION` | `8.3` |
| `DEPLOY_ENABLED` | **Şimdi oluşturulmaz.** Gerçek deploy açılana kadar yok. |

`LEMON_DOCROOT` **artık yoktur** — Mimari A'da tek hedef vardır.

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
bir daha hiçbir deploy onlara dokunmaz. İlk gerçek deploy da onları **silmez**:
araç ilk yayında sunucu durumunu boş kabul eder ve yalnızca upload üretir, delete
üretmez (§9); ayrıca üç katmanın üçü de bu iki ağacı dışarıda tutar.

`composer.json`/`composer.lock`'un aktarılmaması **çalışmayı bozmaz**: Laravel
runtime'da bu dosyaları okumaz; paket keşfi `vendor/composer/installed.json` ve
`bootstrap/cache/packages.php` üzerinden yürür. `public/build/` (Vite çıktısı)
`exclude` listesinde **yoktur** ve aktarılır — repoda tutulmayıp CI'da üretildiği
için tek kaynağı budur.

### 4.1 Hedef dizin doğrulaması — FAIL-CLOSED

Hesapta **birden fazla canlı site** vardır. Yanlış bir `server-dir`, onların
dosyalarını etkileyebilir. FTP kökü doğrulandığı için (§12) workflow'un `guard`
işi artık **tek bir değeri** kabul eder ve başka her şeyde durur:

| Kabul edilen tek değer | Neden |
|---|---|
| `/lemon-app/` | Ana FTP hesabı `/home/derspros` altına chroot'lu; Lemon uygulaması köke göre burada durur |

Daha önce `/home/derspros/lemon-app/` biçimi de kabul ediliyordu; FTP kökü
belirsiz olduğu için iki olasılık açık tutulmuştu. Belirsizlik ortadan
kalktığından ikinci biçim **kaldırıldı** — kabul edilen değer kümesi ne kadar
darsa yanlış hedefe yazma olasılığı o kadar küçüktür. FTP hesabı ileride
değişirse workflow'daki `EXPECTED_DIR` **bilinçli olarak** güncellenir: tek
satır, gözden geçirilebilir bir değişiklik.

Tam eşleşmenin yanı sıra **bağımsız ikinci bir kontrol seti** daha çalışır —
tam eşleşme bir gün gevşetilse bile yasak hedefler yakalanır:

- sonu `/` ile bitmeli
- `..` içeremez
- `public_html` altında olamaz
- `derspros.com.tr`, `nuvernatrade.com`, `nymagro.com` klasörlerini gösteremez
- son dizin adı kesinlikle `lemon-app` olmalı

Değer **boşsa iş hemen durur** — tanımsız bir değişken yüzünden hesap köküne
yazılması mümkün değildir. Hiçbir kontrol değeri loglamaz; yalnızca geçti/kaldı
ve hangi biçimin beklendiği yazılır.

### Aracın tehlikeli davranışları

- `dangerous-clean-slate` **kullanılmaz.** Hedef dizinin tüm içeriğini,
  `exclude` edilenler dahil siler.
- `exclude` verildiğinde aracın **varsayılan listesi ezilir**; bu yüzden
  `**/.git*` ve `**/node_modules/**` kalıpları workflow'da tekrar yazılmıştır.
- Araç, kendi tuttuğu `.ftp-deploy-*.json` durum dosyasıyla senkronizasyon
  yapar. **İlk çalıştırmadaki davranışı artık belirsiz değildir**: durum dosyası
  yoksa sunucu durumu boş kabul edilir ve fark yalnızca upload üretir, delete
  üretmez (kaynak koddan doğrulandı — §9). Yine de ilk çalıştırma **`dry-run`**
  olmalıdır: hedefin ve aktarım listesinin beklenen şey olduğu görülmelidir.

---

## 5. Sunucu tarafı hazırlık

### 5.1 Production `.env` şablonu

Sunucuda `/home/derspros/lemon-app/.env` olarak **elle** oluşturulur (cPanel File
Manager → Edit). **Repoya girmez, deploy tarafından gönderilmez, üzerine yazılmaz.**

```dotenv
APP_NAME="Otel Cari Takip"
APP_ENV=production
APP_KEY=                       # ← §5.2'de üretilip BURAYA yapıştırılır
APP_DEBUG=false
APP_URL=https://lemon.derspros.com.tr
APP_TIMEZONE=Europe/Istanbul

APP_LOCALE=tr
APP_FALLBACK_LOCALE=tr
APP_FAKER_LOCALE=tr_TR

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily                # tek dev dosya yerine günlük dosyalar
LOG_LEVEL=error                # production'da debug DEĞİL
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=localhost              # cPanel'de yerel soket; 127.0.0.1 de çalışır
DB_PORT=3306
DB_DATABASE=derspros_lemon
DB_USERNAME=derspros_sebahattin
DB_PASSWORD=                   # ← cPanel'de üretilen parola, elle yapıştırılır
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_turkish_ci

# Paylaşımlı hosting gerçeği (CLAUDE.md §2): Redis yok, kalıcı worker yok
SESSION_DRIVER=database
SESSION_LIFETIME=480           # 8 saat (CLAUDE.md §8)
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true     # HTTPS zorunlu olduğu için
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

# V1'de e-posta GÖNDERİLMEZ (CLAUDE.md §3.2). `log` sürücüsü, yanlışlıkla
# tetiklenen bir e-postayı ağa çıkarmaz; günlüğe yazar. SMTP bilgisi girilmez.
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@lemon.derspros.com.tr"
MAIL_FROM_NAME="${APP_NAME}"

# İlk yönetici hesabı — db:seed BUNLARI okur (§6.3).
# Boş bırakılırsa seeder admin@example.com / "degistir-beni" oluşturur: AÇIK KAPI.
SEED_ADMIN_EMAIL=
SEED_ADMIN_NAME="Sistem Yöneticisi"
SEED_ADMIN_PASSWORD=
```

`APP_KEY`, `DB_PASSWORD` ve `SEED_ADMIN_PASSWORD` **repoya, GitHub'a, CI çıktısına
veya sohbete yazılmaz**; yalnızca sunucudaki bu dosyada yaşarlar.

### 5.2 `APP_KEY` — SSH olmadan üretim

`php artisan key:generate --force` sunucuda çalıştırılamaz (SSH yok, uygulama henüz
ayakta değil). Anahtar **ortamdan bağımsızdır**: 32 rastgele bayttır, nerede
üretildiğinin önemi yoktur. Bu yüzden yerelde üretilip `.env`'e yapıştırılır.

**Yol A (Laravel varsa) — önerilen:**
```bash
php artisan key:generate --show
```
`--show` anahtarı yalnızca **ekrana yazar**, hiçbir `.env` dosyasına dokunmaz
(yerelde doğrulandı).

**Yol B (Laravel yoksa) — birebir eşdeğer:**
```bash
echo "base64:$(openssl rand -base64 32)"
```
Doğrulandı: her iki yol da `base64:` öneki + **32 ham bayt** üretir; Laravel'in
`aes-256-cbc` için beklediği biçim budur.

Çıktı doğrudan cPanel File Manager'da `.env` içindeki `APP_KEY=` satırına
yapıştırılır. Terminal geçmişini temizlemek iyi olur.

> **Uyarı:** uygulama canlıya girdikten sonra `APP_KEY` değiştirilirse tüm oturumlar
> ve şifrelenmiş veriler okunamaz hâle gelir. Yedeklenmesi gereken bir sırdır.
> V1'de şifrelenmiş kolon yoktur; yine de oturumlar düşer.

### 5.3 PHP eklentileri — kesin liste

Otorite tahmin değil, `composer.lock`'tur. Kilitteki **üretim** paketlerinin
istediği eklentiler:

`ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`,
`mbstring`, `openssl`, `pcre`, `session`, `tokenizer`

Buna Composer'ın göremediği **runtime** gereksinimi eklenir: `pdo` + `pdo_mysql`.

| Eklenti | Durum | Not |
|---|---|---|
| `pdo_mysql` | **ZORUNLU** | Zaten açıldığı bildirildi ✓ |
| `mbstring` | **ZORUNLU** | Türkçe karakter işleme; polyfill var ama yavaş |
| `openssl` | **ZORUNLU** | `APP_KEY`, HTTPS, hash |
| `tokenizer`, `ctype`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `pcre`, `session` | **ZORUNLU** | Neredeyse tüm cPanel PHP kurulumlarında öntanımlı açık |
| `dom` + `libxml` | **ZORUNLU** | `tijsverkoyen/css-to-inline-styles` (Laravel mail bağımlılığı) yükleniyor |
| `intl` | **gerekmiyor** | Kilitte yok; kodda `NumberFormatter`/`Collator` kullanılmıyor. Türkçe biçimleme elle yapılıyor |
| `curl` | **gerekmiyor** | V1'de dış HTTP çağrısı yok |
| `zip` | **şimdi değil, Gün 10'da GEREKECEK** | `maatwebsite/excel` için. Aynı ziyarette açılması işi ikiye bölmez |
| `gd` | **gerekmiyor** | Görsel işleme yok; Excel'de de zorunlu değil |
| `bcmath` | **GEREKMİYOR** | Açıldığı bildirildi, zararı yok ama **kullanılmıyor**: `Core\Support\Money` tamsayı kuruş aritmetiği yapar, `bcmath` bilinçli olarak reddedilmiştir (CLAUDE.md §13) |
| `imagick` | gerekmiyor | — |

**cPanel'de ayrıca kontrol edilecekler:** `mbstring`, `dom`/`libxml`, `openssl`,
`fileinfo`, `tokenizer` — ve Gün 10 için `zip`.

`build` işi zaten her deploy'da `composer check-platform-reqs --no-dev` çalıştırır;
kilit ile hedef PHP arasında uyumsuzluk olursa aktarımdan **önce** durur.

### 5.4 Dizin izinleri

Sunucuda elle oluşturulan yapı yalnızca şu iki ağaçta yazılabilir olmalıdır:

```
/home/derspros/lemon-app/
├── storage/            ← yazılabilir (log, oturum, önbellek, view)
└── bootstrap/cache/    ← yazılabilir (paket keşfi, config/route/view cache)
```

| Yol | İzin | Neden |
|---|---|---|
| `lemon-app/` ve altındaki kod dizinleri | **755** (dizin) / **644** (dosya) | Yalnızca okuma+çalıştırma yeter |
| `storage/` (özyinelemeli) | **755** | Paylaşımlı hostingde PHP, dosyaların **sahibi** olarak çalışır (suPHP/CGI) — sahibin yazma hakkı yeterlidir |
| `bootstrap/cache/` | **755** | Aynı gerekçe |

**775 gerekli mi?** Yalnızca PHP'nin farklı bir kullanıcı (`nobody`, `apache`) olarak
çalıştığı ve dosya sahibiyle **aynı gruba** düştüğü kurulumlarda. cPanel'de standart
olan suPHP/FCGI modelinde PHP hesabın kendi kullanıcısı olarak çalışır ve 755 yeter.
Deploy sonrası `storage/logs/laravel.log` yazılamıyorsa **o zaman** `storage/` ve
`bootstrap/cache/` için 775 denenir.

**777 KULLANILMAZ.** Gerekçe: 777, sunucudaki *diğer* hesapların da yazabilmesi
demektir. Aynı cPanel hesabında başka canlı siteler bulunduğu için bu, oturum
dosyalarının ve günlüklerin komşu bir siteden değiştirilebilmesi anlamına gelir.
Yazma sorunu 777 ile "çözülüyorsa" gerçek sorun sahiplik/SELinux'tur ve orada
düzeltilir.

**Deploy bu dizinlere dokunmaz:** artifact hazırlığında `storage` ve
`bootstrap/cache` silinir, FTP adımında ayrıca `exclude` edilir (§4).

### 5.5 `storage:link` — V1'de GEREKMİYOR

Karar: **ilk deploy'da atlanır.**

Gerekçe (kod taranarak doğrulandı, varsayım değil): uygulamada `Storage::` çağrısı,
`disk('public')` kullanımı, `->store(...)` ile dosya yükleme veya `asset('storage/...')`
bağlantısı **hiç yok**. V1 kapsamında dosya yükleme özelliği bulunmuyor (CLAUDE.md §3.2).
`public/storage` sembolik bağı olmayan bir kurulumda hiçbir ekran bozulmaz.

Gelecekte gerekirse: `php artisan storage:link` §6.1'deki yöntemle çalıştırılır;
FTP üzerinden sembolik bağ oluşturulamaz, cPanel File Manager de symlink üretmez.

---

## 6. İlk deploy sonrası: artisan komutları ve nasıl çalıştırılacağı

### 6.0 Değişmeyen kural

> **İnternete açık `/migrate`, `/deploy`, `/artisan` benzeri bir HTTP ucu
> KESİNLİKLE oluşturulmaz.** Token korumalı uç da reddedilmiştir: token sızarsa
> veritabanı şeması uzaktan değiştirilebilir.

### 6.1 Çalıştırılacak komutlar — sırayla

```bash
php artisan migrate --force          # 1. şema (TRIGGER sınavı burada — §8)
php artisan db:seed --force          # 2. roller, yetkiler, TEK admin (§6.3)
php artisan config:cache             # 3.
php artisan route:cache              # 4.
php artisan view:cache               # 5.
php artisan event:cache              # 6.
```

Notlar:

- **`route:cache` bu projede çalışıyor** — yerelde doğrulandı. Route dosyalarındaki
  closure'lar yalnızca `Route::group(...)` sarmalayıcılarıdır; önbelleklemeyi
  engelleyen şey *route eyleminin* closure olmasıdır ve öyle bir route yok.
- `config:cache` sonrası **uygulama kodundan `env()` null döner.** Proje buna zaten
  uyumlu: `env()` çağrıları yalnızca `config/` altındadır (`config/hotel.php` dahil).
- `optimize` = `config:cache` + `route:cache` + `view:cache` + `event:cache`.
  Tek komut tercih edilirse `php artisan optimize` yeterlidir; ayrı ayrı çalıştırmak
  hangi adımın patladığını görmeyi kolaylaştırır.
- `storage:link` **çalıştırılmaz** (§5.5).
- `queue:*` yoktur — `QUEUE_CONNECTION=sync` (CLAUDE.md §2).

**Kod her değiştiğinde** (sonraki deploy'larda) 3–6 tekrarlanır; `migrate --force`
yalnızca yeni migration varsa gerekir ama çalıştırmak zararsızdır.

### 6.2 SSH yokken bu komutlar nasıl çalıştırılır

Seçenekler, **güvenlik ve basitlik sırasıyla**:

| # | Yöntem | Değerlendirme |
|---|---|---|
| **A** | **cPanel → Terminal** | **Önerilen — varsa.** Gerçek bir kabuk; komutlar tek tek çalıştırılır, çıktı anında görülür, kalıcı hiçbir mekanizma kurulmaz. İlk deploy tam olarak bunu ister: **görerek** ilerlemek. Hosting'te bu özellik kapalı olabilir — cPanel ana ekranında "Terminal" kutusu var mı, bakılmalı. |
| **B** | **cPanel → Cron Jobs, tek seferlik** | Terminal yoksa en iyisi. Cron satırı elle eklenir, bir kez çalışır, **çıktı bir dosyaya yazılır**, sonra cron **silinir**. Kalıcı `finalize.php` altyapısı kurulmadan ilk deploy tamamlanır. |
| **C** | Kalıcı cron + işaret dosyası (`finalize.php`) | §6.4'teki tasarım. **İlk deploy için değil**: hata ayıklaması zor, ilk kurulumda görünürlük düşük. Otomatikleşecekse *sonraki* deploy'lar için kurulur. |
| **D** | HTTP ucu | **YASAK.** |

**Yöntem B'nin somut hâli** (Terminal yoksa) — cPanel → Cron Jobs → Add New Cron Job,
"Once Per Five Minutes" seçilip komut olarak:

```bash
cd /home/derspros/lemon-app && /usr/local/bin/ea-php83 artisan migrate --force \
  >> /home/derspros/lemon-setup.log 2>&1
```

Çalıştıktan sonra **cron silinir**, `lemon-setup.log` File Manager'dan okunur,
sonra sıradaki komut için aynı şey tekrarlanır. Yavaş ama tamamen görünür ve
geriye hiçbir kalıcı yürütme yolu bırakmaz.

> PHP CLI yolu hosting'e göre değişir (`ea-php83`, `/opt/cpanel/ea-php83/root/usr/bin/php`).
> İlk cron'da `which php; php -v` çalıştırıp doğru yolu tespit etmek en sağlamıdır.

### 6.3 İlk admin kullanıcısı

**Seeder production'a hazır — ek kod gerekmiyor.** Boş bir veritabanında
`APP_ENV=production` ile prova edildi:

- `RolePermissionSeeder` → **3 rol, 20 yetki** kurar (CLAUDE.md §7.2/§7.3).
- **Tek bir admin** oluşturulur; e-posta/ad/parola `.env`'deki `SEED_ADMIN_*`
  değerlerinden okunur.
- **Demo `patron@example.com` / `personel@example.com` hesapları oluşturulmaz** —
  `DatabaseSeeder` bunları yalnızca `local`/`testing` ortamında yaratır. Provada
  veritabanında yalnızca admin çıktı.
- Parola `User` modelindeki `'password' => 'hashed'` cast'i ile **bcrypt'lenerek**
  yazılır; hiçbir yerde düz metin tutulmaz veya loglanmaz.

> **BLOCKER — varsayılan parola açık kapısı.** `SEED_ADMIN_PASSWORD` `.env`'de
> **boş bırakılırsa** `config/hotel.php` varsayılanı devreye girer ve seeder
> `admin@example.com` / `degistir-beni` hesabı oluşturur. Negatif test yapıldı:
> bu parola gerçekten geçerli. **`db:seed` çalıştırılmadan önce `.env`'de
> `SEED_ADMIN_EMAIL` ve `SEED_ADMIN_PASSWORD` mutlaka doldurulmalıdır.**

Geçici parola için: `openssl rand -base64 24`. Parola `.env`'e yazılır, seeder
çalıştıktan **sonra** `.env`'den silinebilir (seeder yeniden çalıştırılmadıkça
gerekmez).

**İlk girişte parola değiştirme:** Admin, `/users/{id}/edit` ekranından kendi
parolasını değiştirebilir (`UserPolicy::update` yalnızca `users.manage` ister;
admin kendi kaydını düzenleyebilir). **Zorunlu parola değiştirme akışı V1'de
yoktur** ve Patron/Personel kendi parolasını değiştiremez — yalnızca admin
değiştirebilir. Bu bir V1 boşluğudur, deploy blocker'ı değildir; ayrı bir iş
olarak not düşülmüştür.

### 6.4 Kalıcı otomasyon (henüz KURULMADI)

```
GitHub deploy  →  storage/app/deploy-pending işaret dosyası
                        ↓
              cPanel cron (dakikada bir)
                        ↓
       işaret var mı? → php artisan migrate --force
                        → config:cache / route:cache / view:cache / event:cache
                        ↓
        başarılı → günlüğe yaz + işaret dosyasını SİL
        başarısız → işaret dosyası KALIR + hata günlüğe yazılır
```

`finalize.php` yazıldığında taşıması gerekenler: işaret yoksa hemen çıkmak,
eşzamanlı çalıştırmayı engelleyen kilit, çıkış kodunu günlüğe yazmak,
**başarısızlıkta işaret dosyasını silmemek**, `php_sapi_name() === 'cli'` değilse
çıkmak. **İlk deploy'da kullanılmaz** (§6.2/C).

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

## 8. Ledger TRIGGER yetkisi — dry-run blocker DEĞİL, migration kapısı

`ledger_entries` tablosunun değiştirilemezliği, uygulama katmanına ek olarak
**veritabanı tetikleyicileriyle** korunur
(`2026_09_07_050100_protect_ledger_entries_from_writes`). Bu migration
`CREATE TRIGGER` çalıştırır ve veritabanı kullanıcısının **TRIGGER yetkisi**
olmasını gerektirir.

### Bugüne kadar bilinenler

- cPanel → phpMyAdmin → `SHOW GRANTS FOR CURRENT_USER();` çıktısında
  `derspros_lemon.*` için **ALL PRIVILEGES** görüldü.
- **Ancak** `CURRENT_USER`, phpMyAdmin oturum kullanıcısıdır; Laravel'in
  kullanacağı production DB kullanıcısı olan `derspros_sebahattin` ile
  **birebir aynı kullanıcı değildir.** Bu yüzden bu çıktı tek başına kanıt sayılmaz.
- cPanel → Manage Databases ekranında
  `derspros_sebahattin` → `derspros_lemon` → **TÜM AYRICALIKLAR** atanmış durumda.

Bu iki bulgu birlikte yetkinin **büyük olasılıkla var olduğunu** gösterir.

### Karar: blocker statüsü kaldırıldı

TRIGGER yetkisi artık **dry-run'ın önünde bir engel değildir.** Dry-run zaten
veritabanına hiç dokunmaz (§9); onu bir DB yetkisine bağlamak gereksizdi.

**Kesin doğrulama ilk gerçek production migration sırasında yapılacaktır.**
Bu bilinçli bir seçimdir: `SHOW GRANTS` dolaylı bir kanıttır, `CREATE TRIGGER`
ise doğrudan olanıdır.

### Doğrulama nasıl işler

Migration'da **hiçbir `try/catch`, `IF NOT EXISTS` veya sessiz atlama yoktur.**
`DB::unprepared('CREATE TRIGGER …')` yetki yoksa exception fırlatır, migration
**hata verir ve durur**. Koruma var sanılıp olmaması, hiç olmamasından kötüdür.

**Zorunlu davranış — `CREATE TRIGGER` başarısız olursa:**

1. `php artisan migrate --force` **fail eder** ve orada durulur.
2. **DocumentRoot KESİNLİKLE değiştirilmez** (§11 Aşama 4). Lemon alan adı
   eski hâlinde kalır; yarım migrate edilmiş bir veritabanına kimse yönlenmez.
3. Karar kullanıcıya sorulur: hosting'den TRIGGER yetkisi istemek mi, korumayı
   yeniden tasarlamak mı?

**Ledger trigger koruması hiçbir koşulda bypass edilmez, kaldırılmaz veya
"yetki yoksa atla" hâline getirilmez** (CLAUDE.md §6/L1).

---

## 9. Dry-run ile gerçek deploy arasındaki fark

Workflow'un **iki** çalışma biçimi vardır ve kilitler **yalnızca ikincisine**
uygulanır. Güvenli doğrulamayı engellemek kilidin amacı değildir.

| | `mode: dry-run` (varsayılan) | `mode: deploy` |
|---|---|---|
| `DEPLOY_ENABLED` | **gerekmez, okunmaz** | **`true` olmalı** (henüz yok) |
| `confirm_target` | gerekmez; dolu ve yanlışsa yalnızca uyarı | `lemon.derspros.com.tr` birebir |
| `LEMON_APP_DIR` | `/lemon-app/` (hedef doğrulaması her iki biçimde de çalışır) | `/lemon-app/` — deploy yolunda **ikinci kez** kontrol edilir |
| FTPS bağlantısı | kurulur | kurulur |
| Hedef ve aktarım planı | doğrulanır ve raporlanır | doğrulanır |
| Dosya oluşturma / değiştirme / silme | **yok** | var |

Her iki biçimde de ortak: yalnızca `workflow_dispatch` ile elle tetiklenir —
`push`/`pull_request` tetiklemesi **yoktur**, `main` veya `develop`'a merge
hiçbir şey başlatmaz.

### Karar iki kez, bağımsız hesaplanır

`guard` işi kararı verir; **aktarım işi aynı kararı kendi girdilerinden yeniden
hesaplar** ve iki sonuç uyuşmazsa iş durur. Böylece guard çıktısının kaybolması,
boş gelmesi veya ileride yanlışlıkla değiştirilmesi tek başına gerçek aktarım
açamaz. Fail-closed: `'true'` dışındaki **her** değer dry-run demektir.

### Dry-run'ın "hiçbir şey yazmaz" garantisi nereden geliyor

Tahmin değil; aracın kaynağından doğrulandı (`@samkirkland/ftp-deploy`,
`dist/syncProvider.js`). Sunucuyu değiştiren **beş** işlemin tamamı `dryRun`
bayrağıyla kapatılır:

| İşlem | Kaynak koddaki koruma |
|---|---|
| `createFolder` | `if (this.dryRun === true) return;` |
| `uploadFile` (upload + replace) | `if (this.dryRun === false) { … }` |
| `removeFile` | `if (this.dryRun === false) { … }` |
| `removeFolder` | `if (this.dryRun === false) { … }` |
| Durum dosyası (`.ftp-deploy-*.json`) yazımı | `if (this.dryRun === false) { … }` |

`dangerous-clean-slate` de dry-run'da `clearWorkingDir()` çağırmaz — ama zaten
**hiçbir koşulda kullanılmıyor** (§4.1).

**Bilinen tek istisna:** `getServerFiles()`, dry-run kontrolünden **önce**
`ensureDir(server-dir)` çağırır. Hedef dizin sunucuda yoksa bu çağrı onu
oluşturabilir. Sonuç boş bir dizindir — dosya yazılmaz, hiçbir şey silinmez.
Hedefimiz `/lemon-app/` zaten mevcut olduğu için pratikte no-op'tur; guard tek
değere kilitli olduğu için yanlış bir yola dizin açılması da mümkün değildir.

### İlk yayında silme davranışı — ÇÖZÜLDÜ ✅

Daha önce "belgelenmemiş" diye işaretlenmişti. Kaynak koddan doğrulandı
(`dist/deploy.js`, `getServerFiles`): durum dosyası bulunamazsa araç sunucu
durumunu **boş** kabul eder (`data: []`). Boş duruma göre hesaplanan fark
yalnızca **upload** üretir; **delete üretmez.** Yani ilk gerçek aktarım,
sunucuda önceden bulunan dosyaları silmez.

Buna rağmen **ilk çalıştırmanın `dry-run` olması önerisi geçerlidir** — hedefin
ve aktarım listesinin gerçekten beklenen şey olduğunu görmek için.

### Ek kapılar

Hedef dizin doğrulaması (§4.1), artifact temizlik kontrolü (§4),
`composer check-platform-reqs` (§1) her iki biçimde de çalışır.

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

## 11. Sıra — cPanel ve GitHub'da yapmanız gerekenler

**DocumentRoot'u ne zaman değiştirmelisiniz?** Kritik nokta: `/home/derspros/lemon-app/public`
bugün **boştur**. DocumentRoot'u şimdi değiştirirseniz Lemon alan adı, ilk gerçek
deploy tamamlanana kadar 403/404 verir. Bu yüzden sıra şudur:

### Aşama 1 — Dry-run'dan ÖNCE (DocumentRoot'a DOKUNMAYIN)

1. **Dizinleri oluşturun:** `/home/derspros/lemon-app/` ve içinde bir kez:
   ```
   storage/app/public
   storage/framework/{cache/data,sessions,views}
   storage/logs
   bootstrap/cache
   ```
   İzin `755` (hosting `750` istiyorsa ona uyun).
   **Bu dizinlere hiçbir deploy dokunmayacak.**
2. **`.env`** dosyasını `/home/derspros/lemon-app/.env` olarak §5.1'deki şablonla
   oluşturun. Gerçek DB bilgilerini girin. `APP_KEY` §5.2'ye göre **yerelde
   üretilip yapıştırılır** (sunucuda `key:generate` çalıştırılamaz — SSH yok).
   `SEED_ADMIN_EMAIL` ve `SEED_ADMIN_PASSWORD` **mutlaka doldurulur** (§6.3).
3. **Veritabanı:** Lemon için ayrı veritabanı + kullanıcı, collation
   **`utf8mb4_turkish_ci`** (Türkçe arama davranışı buna bağlı — CLAUDE.md §9).
4. ~~**TRIGGER yetkisi**~~ — **blocker değil** (§8). cPanel Manage Databases'te
   `derspros_sebahattin` → `derspros_lemon` → TÜM AYRICALIKLAR atanmış durumda.
   Kesin doğrulama ilk gerçek migration'da yapılacak: `CREATE TRIGGER`
   başarısız olursa migration fail eder ve **DocumentRoot değiştirilmez**.
5. **PHP eklentileri:** kesin liste artık §5.3'tedir (`composer.lock`'tan
   çıkarıldı). cPanel'de ayrıca bakılacaklar: `mbstring`, `dom`/`libxml`,
   `openssl`, `fileinfo`, `tokenizer` — ve Gün 10 (Excel export) için `zip`.
   `intl`, `gd`, `curl` ve `bcmath` **gerekmiyor**.
6. ~~**FTP kökünü belirleyin**~~ — **TAMAMLANDI** (§12). FileZilla ile ana FTP
   hesabına bağlanıldı; uzak kökte `lemon-app`, `lemon.derspros.com.tr`,
   `nuvernatrade.com` klasörleri doğrudan göründü. Kök = `/home/derspros`,
   dolayısıyla `LEMON_APP_DIR = /lemon-app/`.
7. ~~**FTPS destekleniyor mu?**~~ — **TAMAMLANDI.** Bağlantıda "TLS bağlantısı
   kuruldu" doğrulandı; workflow `protocol: ftps` (explicit AUTH TLS) kullanır.
8. **GitHub variable'ları:** `LEMON_APP_DIR=/lemon-app/` ve
   `TARGET_PHP_VERSION=8.3` oluşturun. **`DEPLOY_ENABLED`'ı OLUŞTURMAYIN.**

### Aşama 2 — Dry-run

9. Workflow'u **`mode: dry-run`** ile çalıştırın. `DEPLOY_ENABLED` **gerekmez**,
   `confirm_target` **boş bırakılır**. Çıktıyı bana gönderin.
   Sunucuda hiçbir dosya oluşturulmaz, değiştirilmez veya silinmez (§9).

### Aşama 3 — İlk gerçek deploy

10. Dry-run temizse `DEPLOY_ENABLED=true` **repository variable** olarak
    oluşturulur ve workflow `mode: deploy` +
    `confirm_target: lemon.derspros.com.tr` + **Branch: `develop`** ile
    çalıştırılır. Run yeşil olunca `DEPLOY_ENABLED` hemen **`false`** yapılır
    (§16 — kilit normalde kapalı durur).
11. Deploy sonrası sunucuda, §6.2'deki yöntemle (cPanel Terminal varsa onunla;
    yoksa tek seferlik cron) §6.1'deki komutlar sırayla:
    `migrate --force` → `db:seed --force` → `config:cache` → `route:cache` →
    `view:cache` → `event:cache`.
    `key:generate` **çalıştırılmaz** (anahtar §5.2'de `.env`'e yazıldı);
    `storage:link` **gerekmiyor** (§5.5).
    **`migrate --force` burada TRIGGER yetkisinin gerçek sınavıdır (§8).**
    Hata verirse durun ve Aşama 4'e **geçmeyin**.

### Aşama 4 — DocumentRoot değişikliği (EN SON)

12. **Ancak §15'teki 11 maddelik checklist'in TAMAMI doğru olduğunda:**
    cPanel → Domains → `lemon.derspros.com.tr` → Document Root:
    `/home/derspros/lemon-app/public` → Güncelle.
13. §7'deki güvenlik doğrulamasını koşun.

**Neden en son:** DocumentRoot'u erken değiştirmek, alan adını hazır olmayan
boş bir dizine yönlendirir. Bu sırayla Lemon, geçiş anına kadar eski hâlinde
kalır ve tek bir anahtar çevirmeyle yeni uygulamaya geçer.

---

## 12. FTP kökü — ÇÖZÜLDÜ ✅

> Bu bölüm daha önce bir **açık soru** idi: `server-dir` değerinin gerçekte
> neye karşılık geldiği bilinmiyordu ve tahmin edilmemişti.

**Doğrulama (kullanıcı tarafından, FileZilla ile):** ana FTP hesabına bağlanıldı,
bağlantı başarılı ve **"TLS bağlantısı kuruldu"**. Uzak sitenin kökünde (`/`)
doğrudan `lemon-app`, `lemon.derspros.com.tr`, `nuvernatrade.com` gibi klasörler
görünüyor.

**Sonuç:**

- Ana FTP hesabı `/home/derspros` altına **chroot'ludur**; mutlak yol
  (`/home/derspros/lemon-app/`) FTP oturumunda geçerli değildir.
- Lemon uygulamasının deploy hedefi köke göre **`/lemon-app/`** olur.
- **FTPS (explicit AUTH TLS) desteklenmektedir**; workflow'daki
  `protocol: ftps` seçimi doğrulanmış oldu.

Buna dayanarak `guard` işi tek değere daraltıldı (§4.1) ve `LEMON_APP_DIR`
variable'ının değeri kesinleşti (§3). Hiçbir dosya yüklenmedi, silinmedi veya
değiştirilmedi; bu doğrulama yalnızca bir dizin listeleme işlemidir.

### Kalan yapısal risk — ayrı FTP hesabı önerisi

Ana FTP hesabı `/home/derspros` altına chroot'lu olduğu için **hesaptaki tüm
sitelere yazabilir.** Guard bunu workflow düzeyinde engeller, ama koruma
yazılımsaldır: parola sızarsa veya guard bir gün gevşetilirse teknik bir engel
kalmaz.

**Yapısal sertleştirme (öneri, uygulanmadı):** cPanel → FTP Accounts üzerinden
yalnızca `/home/derspros/lemon-app` dizinine erişebilen **ayrı bir FTP hesabı**
oluşturmak. O hesapla bağlanıldığında kök doğrudan uygulama dizini olur ve
başka bir siteye ulaşmak protokol düzeyinde imkânsız hâle gelir.

Bunun bir sonucu vardır: yeni hesapta hedef `/lemon-app/` değil **`/`** olur;
`EXPECTED_DIR` ve `LEMON_APP_DIR` bilinçli olarak güncellenmelidir. Guard'daki
"son dizin adı `lemon-app` olmalı" kontrolü de o senaryoda anlamını yitirir ve
yeniden düşünülmelidir. Bu yüzden **bugün değiştirilmedi**; kararı size bırakıyorum.

---

## 13. Rollback — V1 planı (kesinleştirildi)

**Kritik avantaj:** DocumentRoot hâlâ `/home/derspros/lemon.derspros.com.tr`'yi
gösteriyor. Yani ilk gerçek deploy sırasında **Lemon alan adına gelen trafik yeni
uygulamaya hiç yönlenmiyor.** İlk deploy ve migration, canlı kullanıcı olmadan
yapılıyor. Bu, rollback ihtiyacını neredeyse sıfıra indirir.

### Deploy öncesi

| Soru | Cevap |
|---|---|
| `lemon-app/` klasörünün yedeği gerekli mi? | **Hayır.** İçinde yalnızca elle oluşturulmuş boş `storage/`, `bootstrap/cache/` ve `.env` var. **`.env`'in bir kopyası** yine de yerelde/parola kasasında tutulmalı — tek gerçek kayıp riski odur. |
| Migration öncesi `mysqldump` gerekli mi? | **Hayır** — `derspros_lemon` boş. Boş veritabanının dump'ı bilgi taşımaz. Geri dönüş yolu: veritabanını cPanel'den silip yeniden oluşturmak (ya da `migrate:fresh`). |

### Deploy sonrası, DocumentRoot değişmeden önce

Bir şey ters giderse **hiçbir şey geri alınmaz** — sadece durulur. Kimse etkilenmez.
Düzeltilir ve tekrar denenir.

### DocumentRoot değiştikten sonra

**Geri dönüş tek adımdır ve saniyeler sürer:** cPanel → Domains →
`lemon.derspros.com.tr` → Document Root'u eski değerine
(`/home/derspros/lemon.derspros.com.tr`) geri al. Eski dizin **silinmediği için**
olduğu gibi geri gelir.

> **Bu yüzden eski `/home/derspros/lemon.derspros.com.tr` dizini, yeni uygulama
> birkaç gün sorunsuz çalışana kadar SİLİNMEZ.** Rollback mekanizmamız odur.

Veri tarafı: `ledger_entries` append-only'dir (CLAUDE.md §6/L1) ve migration'lar
ileri alınır. Dosya geri alma veritabanını geri almaz; **şema değiştiren** bir
sonraki deploy'dan önce `mysqldump` alınmalıdır (o zaman veri olacaktır).

**Kurulmayan:** sürümlü dizin + symlink (B) elendi — FTPS ile güvenilir symlink
kurulamıyor ve SSH yok.

---

## 14. Bu depodan YAPILMAYANLAR

- Bu depodan/CI'dan hosting'e bağlanılmadı; **dry-run dahil hiçbir workflow çalıştırılmadı**
- Hiçbir dosya yüklenmedi, silinmedi, değiştirilmedi — FTP kökü doğrulaması
  kullanıcının FTP istemcisinde salt okunur dizin listelemesiydi (§12)
- `DEPLOY_ENABLED` oluşturulmadı
- Ayrı (dizine kısıtlı) FTP hesabı oluşturulmadı; yalnızca önerildi (§12)
- Production veritabanı oluşturulmadı, migration çalıştırılmadı
- cPanel'de hiçbir ayar değiştirilmedi
- **DocumentRoot değiştirilmedi** — bu işlem cPanel'den elle yapılacaktır (§11 Aşama 4)
- `finalize.php` ve production cron yazılmadı (§6)
- Rollback mekanizması kurulmadı; yalnızca analiz edildi (§13)
- **Ledger trigger koruması bypass edilmedi, gevşetilmedi, "yetki yoksa atla" hâline getirilmedi** (§8)
- Hiçbir secret değeri okunmadı, yazdırılmadı veya depoya yazıldı

---

## 15. DocumentRoot geçiş kriteri — kesin checklist

DocumentRoot şu an: `/home/derspros/lemon.derspros.com.tr`
Hedef: `/home/derspros/lemon-app/public`

**Aşağıdakilerin HEPSİ doğru olmadan DocumentRoot değiştirilmez.**
Sıra bağlayıcıdır; her madde bir öncekine dayanır.

| # | Kriter | Nasıl doğrulanır |
|---|---|---|
| 1 | Gerçek deploy başarıyla tamamlandı | Actions run yeşil; "Gerçek aktarım tamamlandı" özeti |
| 2 | `.env` sunucuda ve eksiksiz | File Manager'da `/home/derspros/lemon-app/.env` var; §5.1'deki tüm anahtarlar dolu |
| 3 | `APP_KEY` dolu ve `base64:` ile başlıyor | `.env` içinde göz kontrolü (§5.2) |
| 4 | `SEED_ADMIN_EMAIL` ve `SEED_ADMIN_PASSWORD` dolu | §6.3 blocker'ı — varsayılan parola kapısı kapalı |
| 5 | DB bağlantısı doğrulandı | `php artisan migrate --force` hatasız bağlandı (ayrı bir test gerekmez) |
| 6 | `migrate --force` **hatasız** bitti, 13 migration | Çıktıda 13 satır `DONE` |
| 7 | **Tetikleyiciler oluştu** | phpMyAdmin → `SHOW TRIGGERS;` → `ledger_entries_block_update` ve `ledger_entries_block_delete`. **Yoksa DURULUR** (§8) |
| 8 | `db:seed --force` çalıştı; admin hazır | `users` tablosunda **tek** kullanıcı, `roles` = 3, `permissions` = 20; demo hesap YOK |
| 9 | Cache komutları hatasız | `config:cache`, `route:cache`, `view:cache`, `event:cache` (§6.1) |
| 10 | `storage/` ve `bootstrap/cache/` yazılabilir | `storage/logs/` altında dosya oluştu; hata yok (§5.4) |
| 11 | Uygulama `public/index.php` seviyesinde ayağa kalkıyor | Geçici doğrulama: aynı hesaptaki bir alt alan adını **geçici olarak** `lemon-app/public`'e yöneltmek yerine, cron ile `php artisan about` çalıştırıp çıktının hatasız olduğunu görmek yeterlidir |

**11 için not:** DocumentRoot değişmeden HTTP üzerinden test etmenin temiz bir yolu
yok — bu, geçişin kaçınılmaz "tek yönlü kapı" anıdır. Riski düşüren şey §13'teki
geri dönüş: eski dizin duruyor, DocumentRoot saniyeler içinde geri alınabiliyor.

Geçişten hemen sonra: §7'deki güvenlik doğrulaması koşulur
(`https://lemon.derspros.com.tr/.env` → 403/404 olmalı).

---

## 16. `DEPLOY_ENABLED` operasyon modeli

| Soru | Karar | Gerekçe |
|---|---|---|
| **Variable mı secret mı?** | **Repository *variable*** | Gizli bilgi değil, bir *anahtar*. Variable olarak değeri gözden geçirilebilir ve loglarda görünmesi zararsızdır. Secret yapmak yanlış güvenlik hissi verir: sır olmayan bir şeyi gizlemek, kimin ne zaman açtığını izlemeyi zorlaştırır. |
| **Değeri tam olarak ne?** | **`true`** (küçük harf, tırnaksız, boşluksuz) | Guard `[ "$ENABLED" != "true" ]` ile **tam eşleşme** arar. `True`, `TRUE`, `1`, `yes` **çalışmaz** — bu bilinçlidir: yanlışlıkla "doğru gibi görünen" bir değerle açılmasın. |
| **Ne zaman oluşturulmalı?** | §15'teki 1–4. maddeler hazır olduğunda, **gerçek deploy'a basmadan hemen önce** | Daha erken oluşturmak, kilidi aylarca açık bırakır. |
| **Deploy sonrası kaldırılmalı mı?** | **Evet — ilk deploy'dan sonra `false` yapılır veya silinir** | Asıl model: **kilit normalde kapalıdır**, yalnızca bilinçli bir deploy için açılır ve hemen kapatılır. Böylece depoya yazma yetkisi olan biri "Run workflow"a basarak canlıyı ezemez. |

**Önerilen operasyonel akış — her gerçek deploy için:**

1. `DEPLOY_ENABLED` = `true` yap
2. Workflow'u `mode: deploy` + `confirm_target: lemon.derspros.com.tr` +
   **Branch: `develop`** ile çalıştır
3. Run yeşil → `DEPLOY_ENABLED`'ı **`false` yap** (silmek yerine `false` bırakmak,
   değişkenin varlığını ve son kullanımını görünür tutar)
4. §6.1'deki artisan komutlarını çalıştır

Bu, "üç kilitten biri kalıcı olarak açık kalır" hatasını yapısal olarak engeller.

