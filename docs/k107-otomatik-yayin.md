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
