# Lemon Deploy Hazırlığı — lemon.derspros.com.tr

> **12 Eylül 2026 güncellik notu:** Bu belgenin aşağıdaki ilk kurulum durum
> bilgileri tarihseldir. Sistem canlıdır ve gerçek kayıt içerir; kullanıcı
> Claude ile yeni sürüm yayımladığını bildirdi. Sonraki geçişte
> [güncel yol haritası](guvenli-canli-gecis-yol-haritasi-2026-09-12.md) esas
> alınır. “Boş production” veya DocumentRoot'un henüz değişmediği varsayılmaz.

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
bir daha hiçbir deploy onlara dokunmaz.

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
       işaret var mı? → config:clear / route:clear / view:clear / event:clear
                          (MIGRATE'TEN ÖNCE — K-102: bayat cache yeni bir
                          modülün Service Provider'ını taşımazsa migrate
                          o modülün migration'larını hiç GÖRMEZ)
                        → php artisan migrate --force
                        → php artisan db:seed --class="App\Modules\Authorization\Database\Seeders\RolePermissionSeeder" --force
                        → config:cache / route:cache / view:cache / event:cache
                        ↓
        başarılı → günlüğe yaz + işaret dosyasını SİL
        başarısız → işaret dosyası KALIR + hata günlüğe yazılır
                    (sonraki cron tekrar dener, sorun görünür kalır)
```

Planlanan cron satırı:

```bash
* * * * * /usr/local/bin/lsphp /home/derspros/lemon-app/deploy/finalize.php \
          >> /home/derspros/lemon-deploy.log 2>&1
```

**`finalize.php` yazıldı** (12 Eylül 2026 devir notu, `deploy/finalize.php`) —
yukarıdaki beş özelliğin hepsini taşır (marker yoksa no-op, `flock()` ile
eşzamanlılık kilidi, her adımın çıktı/çıkış kodu günlüğe yazılır, başarısızlıkta
marker SİLİNMEZ, HTTP isteği bağlamında çağrılırsa 404 ile reddeder). Gerçek bir deploy
sırasında `.github/workflows/deploy.yml`, ana FTPS aktarımından SONRA marker'ı
AYRI ve tek dosyalık bir FTP yüklemesiyle bırakır (storage/ normal aktarımdan
hariç tutulduğu için).

**Cron kaydı 13 Eylül 2026'da kullanıcı tarafından eklendi ve mekanizma
CANLIDA ÇALIŞIR DURUMDADIR.** Bunu depodan/CI'dan oluşturmak mümkün
değildir; cPanel arayüzünden (Cron Jobs) elle eklenen satır şudur:

```bash
* * * * * /usr/local/bin/lsphp /home/derspros/lemon-app/deploy/finalize.php \
          >> /home/derspros/lemon-deploy.log 2>&1
```

İlk başarılı otomatik finalize: **13 Eylül 2026, 20:30:01–20:30:02 (+03:00)**.
Günlük yedi adımı da sırayla gösterdi — `optimize:clear` (altı alt adım) →
`migrate --force` (**Nothing to migrate**: bu paket yeni bir migration
İÇERMİYOR, `stays.force_delete` bir YETKİdir ve seeder'dan gelir) →
`db:seed RolePermissionSeeder` (181 ms) → `config/route/view/event:cache` →
işaret dosyası silindi. Bir sonraki dakikadan itibaren cron sessiz no-op'a
döner.

> **PHP çalıştırma ortamı (13 Eylül 2026, canlıda üç turda çözüldü):** bu
> sunucu **LiteSpeed + CloudLinux Alt-PHP** kullanıyor. Cron satırındaki
> binary `/usr/local/bin/lsphp`tir (gerçekte `/opt/alt/php83/usr/bin/lsphp`e
> çözülür, PHP 8.3.33). Daha önce burada yazılı olan cPanel/EasyApache
> adlandırması (`/usr/local/bin/ea-php83`) BU SUNUCUDA YOKTUR ve cron'da
> `jailshell: /usr/local/bin/ea-php83: No such file or directory` verir.
>
> **`lsphp` bir CLI binary'si DEĞİLDİR** (SAPI `litespeed`). Canlıda sırasıyla
> üç hata çıktı ve `finalize.php` üçüne karşı da sertleştirildi:
>
> 1. `STDOUT`/`STDERR` SABİTLERİ yalnızca CLI SAPI'de tanımlıdır —
>    "Undefined constant STDERR" fatal hatası alındı. Çıktı artık HER ZAMAN
>    `php://stdout` / `php://stderr` akışlarıyla yazılır.
> 2. "CLI değilse çık" kontrolü meşru cron çalışmasını da reddediyordu.
>    Ayırt edici ölçüt SAPI değil, GERÇEK bir HTTP isteği bağlamının varlığıdır
>    (`REQUEST_METHOD`/`HTTP_HOST`) — kontrol buna çevrildi.
> 3. **Asıl neden:** bu `lsphp` yapılandırmasında `proc_open` VE `exec`
>    BİRLİKTE devre dışıdır (`disable_functions`). Yani script harici bir
>    komut HİÇ çalıştıramaz — "doğru binary'yi bulma" sorunu değildi, hiçbir
>    binary çağrılamıyordu. (Aday tarayan ara sürüm bunu günlükte açıkça
>    raporladığı için teşhis edilebildi; sunucuda terminal erişimi yok.)
>
> **Çözüm: artisan AYNI SÜREÇTE çalıştırılır** — alt süreç yok.
> `finalize.php` `vendor/autoload.php` + `bootstrap/app.php` ile uygulamayı
> başlatır ve her adımı `Illuminate\Contracts\Console\Kernel::call()` ile
> koşturur; çıktı `BufferedOutput` ile alınıp günlüğe yazılır, çıkış kodu ve
> fırlatılan her `Throwable` yakalanır. Bu yüzden:
>
> - `APP_RUNNING_IN_CONSOLE=true` bootstrap'TAN ÖNCE yazılır (putenv +
>   `$_ENV` + `$_SERVER`) — Laravel konsolda olduğunu normalde PHP_SAPI'den
>   anlar; bu, framework'ün KENDİ desteklediği açık kapıdır
>   (`Application::runningInConsole()`). `Env::get()` ile doğrulandı.
> - `set_time_limit(0)` ve `memory_limit=512M` — web SAPI limitleri uzun bir
>   migrate'i ortasında kesebilirdi.
> - **Sürüm kapısı** artık doğrudan BU sürecin sürümüne bakar: 8.3 dışında
>   bir sürümle koşulursa hiçbir şey yapmadan durur ve işaret dosyasını KORUR
>   (CLAUDE.md §2 — sürüm kilidi).
>
> Tüm zincir yerelde gerçek MySQL'e karşı doğrulandı: yedi adım (temizleme →
> migrate → yetki seed'i → dört cache) sırayla koştu, marker silindi; bir
> adım hata verdiğinde marker KORUNUP exit 1 verildiği de ayrıca sınandı.

Cron kurulana kadar marker dosyası sunucuda bırakılır ama hiç kimse onu
işlemez — bu durumda migrate/seed/cache adımları GEÇİCİ olarak elle
çalıştırılabilir (§15'teki sırayla); cron kurulduktan sonra bir daha hiç elle
müdahale gerekmez.

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

**Üçüncü biçim — OTOMATİK (13 Eylül 2026, kullanıcı talimatı):** `main` dalına
her push/merge gerçek aktarımı kendiliğinden başlatır. Kilitlerden
`DEPLOY_ENABLED` ve `LEMON_APP_DIR` aynen aranır; `confirm_target` ARANMAZ
(insan onayıdır, otomatik akışta yazacak insan yoktur). Tek başına `docs/`,
`tests/`, `.claude/` veya `*.md` değişen bir push aktarım yapmaz
(`paths-ignore`). `develop` ve `pull_request` tetiklemesi **hâlâ yoktur**.

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

**GÜNCELLENDİ (13 Eylül 2026):** kullanıcı talimatıyla `main` dalı için otomatik
push deployment ETKİNLEŞTİRİLDİ — "canlıya al = main'e push". Gerekçe: her yayın
için elle tetikleme + onay metni + sunucuda elle komut zinciri, geliştirme hızını
belirgin biçimde düşürüyordu. `develop` için otomatik deployment HÂLÂ YOKTUR ve
önerim orada `workflow_dispatch`'te kalmaktır.

Bilinen ödünç: `main`e giren hatalı bir commit doğrudan canlıya çıkar. Geri
alma yolu, düzeltmeyi `main`e push etmektir (ileri düzeltme) — paylaşımlı
hostingde dosya düzeyinde geri alma maliyetlidir, bkz. §13.

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
2. **`.env`** dosyasını `/home/derspros/lemon-app/.env` olarak §5'teki şablonla
   oluşturun. Gerçek DB bilgilerini girin, `APP_KEY` satırını boş bırakın.
3. **Veritabanı:** Lemon için ayrı veritabanı + kullanıcı, collation
   **`utf8mb4_turkish_ci`** (Türkçe arama davranışı buna bağlı — CLAUDE.md §9).
4. ~~**TRIGGER yetkisi**~~ — **blocker değil** (§8). cPanel Manage Databases'te
   `derspros_sebahattin` → `derspros_lemon` → TÜM AYRICALIKLAR atanmış durumda.
   Kesin doğrulama ilk gerçek migration'da yapılacak: `CREATE TRIGGER`
   başarısız olursa migration fail eder ve **DocumentRoot değiştirilmez**.
5. **PHP eklentileri:** `mbstring, pdo_mysql, intl, zip, gd, xml, ctype,
   fileinfo, openssl, tokenizer` etkin mi?
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

10. Dry-run temizse `DEPLOY_ENABLED=true` oluşturulur ve workflow
    `mode: deploy` + `confirm_target: lemon.derspros.com.tr` ile çalıştırılır.
11. Deploy sonrası sunucuda (cron/marker mekanizması henüz yok — §6), TAM
    SIRAYLA — **`docs/release-checklist-v2.1.1.md`**'nin bağlayıcı sırasıdır:
    `php artisan key:generate --force`, sonra **cache ÖNCE temizlenir**
    (`config:clear route:clear view:clear event:clear` — MIGRATE'TEN ÖNCE,
    K-102: bayat cache bu deploy'da yeni eklenen bir modülün Service
    Provider'ını taşımıyorsa `migrate` o modülün migration'larını hiç
    GÖRMEZ, sessizce atlar), sonra `migrate --force`, sonra
    **`db:seed --class="App\Modules\Authorization\Database\Seeders\RolePermissionSeeder" --force`** (HER deploy'da,
    migrate'ten hemen sonra — atlanırsa production "no permission named X"
    hatası verir, gerçek üretim olayı), `storage:link`, sonra üretim
    cache'i KURULUR: `config:cache route:cache view:cache event:cache`.
    **`migrate --force` burada TRIGGER yetkisinin gerçek sınavıdır (§8).**
    Hata verirse durun ve Aşama 4'e **geçmeyin**.
    Ardından `docs/release-checklist-v2.1.1.md` §4'teki finansal duman
    testinin TAMAMI çalıştırılır.

### Aşama 4 — DocumentRoot değişikliği (EN SON)

12. **Ancak dosyalar sunucuda, migration'lar HATASIZ tamamlanmış ve uygulama
    hazır olduğunda:**
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

## 13. Rollback — analiz (uygulanmadı)

**Bu turda rollback sistemi KURULMADI**; yalnızca değerlendirildi.

FTPS senkronizasyonu **yerinde güncelleme** yapar: eski sürümün üzerine yazar.
Bu, hata hâlinde geri dönüşü kendiliğinden mümkün kılmaz. Değerlendirilen üç yol:

| Yaklaşım | Artı | Eksi |
|---|---|---|
| **A. Deploy öncesi elle yedek** — `lemon-app/` klasörünün cPanel File Manager ile sıkıştırılmış kopyası | Basit, ek altyapı yok, bugün uygulanabilir | Elle; unutulursa yok. Büyük dosyada yavaş |
| **B. Sürümlü dizin + symlink** (`releases/<sha>/` + `current` symlink) | Anında geri dönüş, sıfır kesinti | FTPS ile symlink oluşturmak güvenilir değil; SSH ister — bu projede yok |
| **C. Git ile ileri düzeltme** — hatalı commit'i düzeltip yeniden deploy | Mevcut akışa uyar, ek şey gerektirmez | Geri dönüş süresi bir CI koşusu kadar (~3 dk) |

**Önerim: A + C birlikte.** Her gerçek deploy öncesi `lemon-app/` klasörünün
yedeği alınır (dosya tarafı), veritabanı için ayrıca `mysqldump` alınır
(CLAUDE.md §15'te zaten günlük yedek cron'u planlı). Kod hatasında C ile ileri
düzeltme yapılır; ciddi bozulmada A geri yüklenir.

**Kritik ayrım — veritabanı rollback'i ayrı bir konudur:** migration'lar
ileri alınmıştır ve `ledger_entries` **append-only**'dir (CLAUDE.md §6/L1).
Dosya yedeğini geri yüklemek veritabanını geri almaz. Şema değişikliği içeren
bir deploy'da **önce `mysqldump`** alınmalıdır. Bu yüzden CLAUDE.md §15
"canlıda `migrate:rollback` yerine ileri düzeltme migration'ı" der.

**Uygulama zamanı:** ilk gerçek deploy öncesi, ayrı bir turda.

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
- `finalize.php` yazıldı ve production cron kaydı 13 Eylül 2026'da eklendi (§6);
  mekanizma canlıda uçtan uca çalıştı — bu madde ARTIK AÇIK DEĞİLDİR
- Rollback mekanizması kurulmadı; yalnızca analiz edildi (§13)
- **Ledger trigger koruması bypass edilmedi, gevşetilmedi, "yetki yoksa atla" hâline getirilmedi** (§8)
- Hiçbir secret değeri okunmadı, yazdırılmadı veya depoya yazıldı
# Local staged fallback

The local staged release design and recovery procedure are in
[`deploy-local-staged.md`](deploy-local-staged.md). The existing Actions
workflow, cron, maintenance trigger, and legacy marker remain supported.
