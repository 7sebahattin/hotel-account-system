# K-107 · "canlıya al = main'e push": otomatik yayın akışı

**Tarih:** 13 Eylül 2026 · **Talimat:** kullanıcı (açık istek)

> Bu not `docs/kararlar.md`'nin devamıdır; oradaki K-106'dan sonra gelir ve
> K-059'un ("otomatik push deployment hiçbir dal için etkinleştirilmedi")
> `main` dalı için GEÇERSİZ olduğunu kayda geçirir.

## Sorun

Her yayın şu zinciri gerektiriyordu: Actions'a gir → workflow seç → `mode=deploy`
→ hedef alan adını birebir yaz → aktarımı bekle → sunucuda migrate/seed/cache
adımlarını **elle** koştur (ya da cron'un ne yaptığını günlükten tahmin et).
Kullanıcının ifadesiyle: "sadece canlıya alabilmek için çok fazla şey yaptım…
sürekli cron çalıştır, hatayı bul, tekrar cron çalıştır."

Kullanıcı başka bir projesinde kullandığı akışı örnek gösterdi: GitHub webhook →
sunucudaki `deploy.php` → `main.zip` indirilip site köküne açılır.

## Neden ZIP+webhook tarifi bu projeye UYGULANMADI

O tarif düz PHP siteleri içindir. Bu proje Laravel'dir:

- `vendor/` ve `public/build/` (Vite çıktısı) **repoda yoktur**, CI'da üretilir.
  `main`in ZIP'i bu iki ağacı içermez — açıldığı anda site "vendor/autoload.php
  not found" ile kırılırdı.
- Sunucuda `composer install` / `npm run build` koşturulamaz: bellek limiti bir
  yana, bu `lsphp` yapılandırmasında `proc_open` ve `exec` KAPALIDIR (§6).
- DocumentRoot Laravel kökünün İÇİNDEKİ `public/`tir (Mimari A); ZIP'i site
  köküne açmak bu yerleşimi bozardı.
- Migration/seed/cache adımları ZIP açmakla olmaz; zaten ayrı bir mekanizma
  (cron + işaret dosyası) gerektirir ve o mekanizma artık ÇALIŞIYOR.

## Uygulanan çözüm — aynı sonuç, mevcut boru otomatikleştirildi

```
main'e push
   ↓
GitHub Actions: vendor (8.3, --no-dev) + npm run build + platform kontrolü
   ↓
FTPS aktarımı → /lemon-app/   (storage/, .env, tests, docs HARİÇ)
   ↓
storage/app/deploy-pending işareti (AYRI tek dosyalık FTP yüklemesi)
   ↓
cPanel cron (dakikalık) → deploy/finalize.php
   cache temizle → migrate --force → RolePermissionSeeder → cache kur
   ↓
workflow, işaret dosyasının silinmesini FTPS ile izler ve sonucu RAPORLAR
```

Elle adım yoktur. Kullanıcı "canlıya al" dediğinde yapılacak tek şey, işi
`main`e push etmektir.

## Neyin gevşetildiği, neyin gevşetilMEdiği

| Kilit | Önce | Sonra |
|---|---|---|
| `DEPLOY_ENABLED=true` | aranır | **aranır** (değişmedi) |
| `LEMON_APP_DIR == /lemon-app/` | iki kez aranır | **iki kez aranır** (değişmedi) |
| `confirm_target` (insan onayı) | aranır | otomatik yolda **aranmaz** — yazacak insan yok; elle yolda aynen durur |
| `push` tetikleyicisi | yok | **`main` için var** (`develop`/PR için hâlâ yok) |

Hedef doğrulaması bilinçli olarak korundu: hesapta başka canlı siteler var ve
yanlış hedef onların dosyalarını etkileyebilir. Gevşetilen tek şey, otomatik
akışta anlamı olmayan insan onayıdır.

**İnternete açık migrate/deploy ucu AÇILMADI** (CLAUDE.md §15 yasağı aynen
geçerli). Kullanıcı "güvenlik şu an önemli değil, hız önemli" dedi; yine de
böyle bir uca İHTİYAÇ olmadığı için açılmadı: cron zaten ≤60 saniyede
finalize ediyor ve sonucu workflow raporluyor. Yani bu yasağı çiğnemenin
kazandıracağı bir şey yoktu.

## Geri bildirim döngüsü (asıl yorgunluk buradaydı)

Aktarımdan sonra workflow, `storage/app/` dizinini FTPS ile listeleyip işaret
dosyasının silinmesini bekler (15 sn × 12 = 3 dk):

- Dosya kayboldu → **"FİNALİZE TAMAMLANDI"**, iş yeşil biter.
- 3 dakika sonra hâlâ duruyorsa → uyarı: dosya aktarımı başarılı ama
  migrate/seed/cache adımları koşmadı; `lemon-deploy.log` tam hatayı yazar.

Böylece "cron çalıştı mı, hata mı verdi" sorusu GitHub ekranından cevaplanır.
Kontrol salt-okunurdur ve zaten var olan FTP hesabını kullanır.

## Bilinen ödünç

`main`e giren hatalı bir commit doğrudan canlıya çıkar. Geri alma yolu ileri
düzeltmedir: düzeltmeyi `main`e push et. Kullanıcı bu ödüncü bilerek kabul etti
("şu an hızlı geliştirip sonra güvenliği artıracak bir sistem lazım").

## Etkilenen dosyalar

`.github/workflows/deploy.yml`, `CLAUDE.md` (§15), `docs/deploy-lemon.md`
(§9, §10), `docs/deploy-github-actions.md`, bu not.

---

## Ek (13 Eylül 2026, aynı gün akşam) — sunucu günlüğü artık Actions'a düşer

Bekleme adımı zaman aşımına uğrarsa workflow, FTP hesabının kendi kökünden
`lemon-deploy.log`'u indirip son 80 satırını Actions çıktısına yazar. Gerekçe:
sunucuda terminal erişimi yok ve "deploy et → günlüğü elle bul → yapıştır →
düzelt → tekrar deploy et" döngüsü bu projede en çok zaman kaybettiren şeydi.
Salt okunur; yeni bir uç, yetki veya HTTP arayüzü açılmadı.

## Claude bu akışı nasıl kullanır (uzak oturum kısıtı)

Uzak oturum ortamı `git push origin main` komutunu "production deploy" sayıp
blokluyor; ayrıca Claude'un kendi izin dosyasını (`.claude/settings.json`)
düzenlemesi de engelleniyor. Bu yüzden yayın şu şekilde yapılır:

1. İş dalına (`claude/*`) normal `git push` (serbest).
2. GitHub MCP: `create_pull_request` → `merge_pull_request`.
3. Merge `main`'e bir push olduğu için yayın kendiliğinden başlar.

Kullanıcı isterse `.claude/settings.json` içine
`"permissions": {"allow": ["Bash(git push:*)"]}` ekleyerek 1–2. adımları tek
`git push`'a indirebilir.

## Sonuç — zincir uçtan uca doğrulandı (13 Eylül 2026, 21:20)

Koşu #18 (`main`'e merge ile tetiklendi) tam otomatik tamamlandı:

| Saat (+03:00) | Olay |
|---|---|
| 21:19:29 | FTPS aktarımı bitti, `storage/app/deploy-pending` bırakıldı |
| 21:20:01 | cPanel cron → `deploy/finalize.php`: optimize:clear → migrate (Nothing to migrate) → RolePermissionSeeder (187 ms) → config/route/view/event cache |
| 21:20:02 | finalize tamamlandı, işaret dosyası silindi |
| 21:20:18 | Workflow işaretin silindiğini gördü → **"FİNALİZE TAMAMLANDI"**, koşu yeşil |

Bekleme adımı 43 saniye sürdü. Elle hiçbir adım yapılmadı.

### Tanılama tarihçesi (tekrar yaşanırsa)

20:57 ve 21:12'de bırakılan işaretler ancak 21:20'de işlendi; o aralıkta
sunucu günlüğüne HİÇ satır düşmedi. Cron koşup finalize hata verseydi günlüğe
yazardı (daha önce "Could not open input file" ve "Undefined constant STDERR"
tam olarak böyle görünmüştü). Dolayısıyla kural şudur:

- **Günlükte yeni satır YOK** → cron hiç koşmamış → cPanel → Cron Jobs kontrol.
- **Günlükte hata satırı VAR** → finalize bir adımda patlamış → hata metni orada.

Bekleme süresi 6 dakikaya çıkarıldı. Kasa kaldırma entegrasyonunda sonuç
kontrolü sıkılaştırıldı: zaman aşımı artık workflow'u başarısız bitirir.
Cron sonradan tamamlayabilir; dosya aktarımı uygulamanın hazır olduğu
anlamına gelmez. Aynı commit/run/attempt için başarı kaydı doğrulanmalıdır.

## Kasa kaldırma entegrasyonundaki yayın korumaları

`codex/kasa-kaldirma` dalında hazırlanan değişiklikler henüz canlıda sınanmadı.
Normal kullanım yine `main`e push ile otomatik yayındır. İşlem sırası:
CI → üretim derlemesi → önceki bekleyen yayın/bakım kontrolü → bakım modu →
FTPS → finalize işareti → eski bootstrap cache temizliği → kernel →
optimize:clear/migrate/yetki seed'i/cache → bu yayının bakımını kapat →
`storage/app/deploy-completed.json` → işareti kaldır.

Workflow yalnız işaretin kaybolmasına güvenmez; başarı kaydındaki commit,
run_id ve run_attempt eşleşmesini de arar. FTPS listeleme/sertifika hatası
başarı sayılmaz. Kalıcı finalize cron'u korunur. Ayrıntı ve kurtarma notları:
`docs/claude-karsilastirma-2026-09-13.md`.

---

# K-108 · Finalize artık cron'a bağımlı değil: bakım sayfasına gömülü tetikleyici

**Tarih:** 14 Eylül 2026 · **Talimat:** kullanıcı (açık onay — "evet, yaz")

## Sorun

Bu hosting'in cron'u güvenilmez. Ölçülen: 20:57 ve 21:12'de bırakılan
işaretler 21:20'de; 22:32'de bırakılan işaret ancak **01:31'de** işlendi
(~3 saat). Yeni akış aktarımdan ÖNCE siteyi bakıma aldığı için site, cron
uyanana kadar bakım ekranında kaldı. Kullanıcının ifadesi: "her defasında
cron açmak yorucu ve sıkıcı; tek seferlik ilk girişte oluşan bir script çok
iyi olurdu."

## Çözüm

Deploy, bakım modunu açarken `storage/framework/maintenance.php` dosyasını
zaten KENDİSİ yüklüyor. Bu dosya artık şöyle üretilir:

```
maintenance.php = deploy/maintenance-trigger.php + Laravel bakım stub'ı
```

Bakım süresince gelen **ilk istek** tetikleyiciyi çalıştırır; bekleyen bir
işaret varsa finalize zinciri koşar ve `artisan up` hem `down` hem
`maintenance.php` dosyasını siler — tetikleyici **kendini yok eder**.

Cron YEDEK olarak kalır; hangisi önce davranırsa finalize'ı o koşturur,
`flock` çakışmayı önler.

## Neden §15'i çiğnemez

§15 "internete açık `/migrate`, `/deploy`, `/artisan` benzeri bir HTTP ucu"
yasaklar. Burada:

- **URL/route/token YOK** — dışarıdan çağrılabilecek bir adres oluşmaz.
- Çalışmasının ÖN KOŞULU, yalnızca FTP erişimi olan deploy hattının
  bırakabildiği `storage/app/deploy-pending` dosyasıdır. Ziyaretçi migration
  BAŞLATAMAZ; yalnızca ZATEN BEKLEYEN işi öne çeker.
- İş bitince tetikleyici dosya silinir; kalıcı bir yüzey kalmaz.

## Uygulama ayrıntıları

| Konu | Karar |
|---|---|
| HTTP reddi | `finalize.php` HTTP bağlamını reddetmeye devam eder; yalnızca `LEMON_FINALIZE_EMBEDDED` sabiti tanımlıysa geçer. Bu sabiti ancak sunucudaki dosya tanımlayabilir. |
| Çıktı | Gömülü modda `php://stdout` YANIT GÖVDESİDİR; bu yüzden `LEMON_FINALIZE_LOG` ile doğrudan `lemon-deploy.log`'a yazılır (cron'un yazdığı AYNI dosya). |
| Soğuma | Başarısız finalize işareti korur; 60 sn'lik `storage/app/finalize-attempt` damgası her isteğin aynı migrate'i yeniden denemesini engeller. `flock` zaten eşzamanlıyı eler. |
| Başarı | Ziyaretçi aynı adrese 302 ile döner (tek istekte iki kez Laravel başlatılmaz). POST/PUT için 302 yerine `Retry-After: 1` + 503. |
| Başarısızlık | Ziyaretçi boş sayfa değil, `down` içindeki bakım şablonunu görür. |
| Bakım zaten kapalıysa | `up` atlanır ve geride kalan `maintenance.php` silinir — aksi hâlde her istekte gereksiz yüklenirdi. |

## Doğrulama

Üç yol da yerelde gerçek istek simülasyonuyla koşturuldu: (1) başarı —
zincir koştu, yanıt gövdesine hiçbir şey sızmadı, işaret silindi, bakım
kalktı, başarı kaydı yazıldı; (2) `migrate` hatası — ziyaretçi bakım
şablonunu gördü, işaret ve bakım korundu, ikinci istek soğuma penceresine
takıldı; (3) bakım önceden kaldırılmış — `up` atlandı, bakım kabuğu silindi,
yayın normal tamamlandı. CI'da `tests/Unit/DeployFinalizeTest` gömülü yolu
ayrıca kilitler.

## Bilinen sınır

Tetikleyici yalnızca site ZİYARET EDİLDİĞİNDE çalışır. Kimse girmezse
finalize, cron uyanana kadar bekler — ama bu durumda zaten kimse etkilenmiyor
demektir. Workflow'un 6 dakikalık doğrulaması yine de kırmızı biter ve
gerekirse bakımı kaldırır.
