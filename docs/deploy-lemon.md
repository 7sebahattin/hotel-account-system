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
| Lemon DocumentRoot (**mevcut**) | `/home/derspros/lemon.derspros.com.tr` — henüz değiştirilmedi |
| Lemon DocumentRoot (**hedef**) | `/home/derspros/lemon-app/public` — cPanel'den ELLE ayarlanacak (§11 Aşama 4) |
| Laravel kökü | `/home/derspros/lemon-app/` |
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
| `LEMON_APP_DIR` | `/home/derspros/lemon-app/` — FTP kökü chroot'luysa `/lemon-app/`. **Sonunda `/` zorunlu.** Başka hiçbir değer kabul edilmez (§4.1). |
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
dosyalarını etkileyebilir. Workflow'un `guard` işi bu yüzden **yalnızca iki
değeri** kabul eder ve başka her şeyde durur:

| Kabul edilen | Ne zaman |
|---|---|
| `/home/derspros/lemon-app/` | FTP kökü `/` ise (chroot yok) |
| `/lemon-app/` | FTP kullanıcısı `/home/derspros` altına hapsedilmişse |

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

## 8. Ledger TRIGGER yetkisi — deploy öncesi TEK kalan blocker

`ledger_entries` tablosunun değiştirilemezliği, uygulama katmanına ek olarak
**veritabanı tetikleyicileriyle** korunur
(`2026_09_07_050100_protect_ledger_entries_from_writes`).

Bu migration `CREATE TRIGGER` çalıştırır ve veritabanı kullanıcısının
**TRIGGER yetkisi** olmasını gerektirir. Paylaşımlı hostinglerde bu yetki
bazen verilmez.

**Yetki doğrulanmadan production migration çalıştırılmamalıdır.**
PHP sürümü blocker'ı çözüldükten sonra (§1) geriye kalan tek engel budur.

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
4. **TRIGGER yetkisi** (§8): phpMyAdmin → SQL → `SHOW GRANTS FOR CURRENT_USER();`
   Çıktıyı iletin. **Bu doğrulanmadan production migration çalıştırılmaz.**
5. **PHP eklentileri:** `mbstring, pdo_mysql, intl, zip, gd, xml, ctype,
   fileinfo, openssl, tokenizer` etkin mi?
6. **FTP kökünü belirleyin** (§13): FTP istemcisiyle bağlanıp kök dizinde ne
   göründüğüne bakın — `/home/derspros` içeriği mi, yoksa doğrudan
   `public_html`, `lemon-app` gibi klasörler mi? Buna göre `LEMON_APP_DIR`
   değerini seçin (§4.1'deki iki biçimden biri).
7. **FTPS destekleniyor mu?** Hosting explicit FTPS (AUTH TLS) sunuyor mu?
   Sunmuyorsa bildirin — düz FTP'de parola ağ üzerinden açık gider.
8. **GitHub variable'ları:** `LEMON_APP_DIR` ve `TARGET_PHP_VERSION=8.3`
   oluşturun. **`DEPLOY_ENABLED`'ı OLUŞTURMAYIN.**

### Aşama 2 — Dry-run

9. Workflow'u **`mode: dry-run`** ile çalıştırın. Çıktıyı bana gönderin.
   Sunucuda hiçbir dosya değişmez.

### Aşama 3 — İlk gerçek deploy

10. Dry-run temizse `DEPLOY_ENABLED=true` oluşturulur ve workflow
    `mode: deploy` + `confirm_target: lemon.derspros.com.tr` ile çalıştırılır.
11. Deploy sonrası sunucuda (cron/marker mekanizması henüz yok — §6):
    `php artisan key:generate --force`, `migrate --force`, `storage:link`,
    `config:cache route:cache view:cache`.

### Aşama 4 — DocumentRoot değişikliği (EN SON)

12. **Ancak dosyalar sunucuda ve uygulama hazır olduğunda:**
    cPanel → Domains → `lemon.derspros.com.tr` → Document Root:
    `/home/derspros/lemon-app/public` → Güncelle.
13. §7'deki güvenlik doğrulamasını koşun.

**Neden en son:** DocumentRoot'u erken değiştirmek, alan adını hazır olmayan
boş bir dizine yönlendirir. Bu sırayla Lemon, geçiş anına kadar eski hâlinde
kalır ve tek bir anahtar çevirmeyle yeni uygulamaya geçer.

---

## 12. FTP kökü — doğrulanması gereken nokta

`server-dir` değerinin gerçekte neye karşılık geldiği **henüz bilinmiyor**:
FTP kullanıcısı `/home/derspros` altına hapsedilmişse (chroot) mutlak yol
çalışmaz, göreli yol gerekir.

**Bu turda hosting'e bağlanılmadığı için doğrulanamadı ve tahmin edilmedi.**
`/home/derspros/lemon-app/` değeri körlemesine doğru kabul EDİLMEMİŞTİR:
workflow her iki biçimi de tanır (§4.1) ve tanımadığı bir değerde durur.

Doğrulama yolu: bir FTP istemcisiyle bağlanıp kök dizinde ne göründüğüne bakmak
(Aşama 1/6). Dry-run çıktısı da bunu gösterir — bu yüzden ilk çalıştırma
zorunlu olarak dry-run'dır.

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

## 14. Bu turda YAPILMAYANLAR

- Hosting'e bağlanılmadı, FTP testi yapılmadı
- Hiçbir dosya yüklenmedi, silinmedi, değiştirilmedi
- Workflow çalıştırılmadı
- Production veritabanı oluşturulmadı, migration çalıştırılmadı
- cPanel'de hiçbir ayar değiştirilmedi
- `finalize.php` ve production cron yazılmadı (§6)
- Rollback mekanizması kurulmadı; yalnızca analiz edildi (§13)
- **DocumentRoot değiştirilmedi** — bu işlem cPanel'den elle yapılacaktır (§11 Aşama 4)
- Dry-run dahil hiçbir workflow çalıştırılmadı
- Hiçbir secret değeri okunmadı, yazdırılmadı veya depoya yazıldı
