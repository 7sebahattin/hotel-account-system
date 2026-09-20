# AI Çalışma Akışı ve Proje Bağlamı

Bu belge, `hotel-account-system` deposunda **Claude, Codex ve ChatGPT'nin aynı proje üzerinde güvenli biçimde sırayla veya paralel çalışabilmesi** için ortak operasyon standardıdır.

> Bağlayıcı ürün/mimari kuralların kaynağı **`CLAUDE.md`** dosyasıdır. Bu belge onu tekrar etmez; ajanların aynı bağlamla başlayıp işi güvenli biçimde devretmesini standardize eder.

## 1. Proje kimliği

- **Repo:** `7sebahattin/hotel-account-system`
- **Canlı alan adı:** `https://lemon.derspros.com.tr`
- **Production branch:** `main`
- **Staging / entegrasyon branch:** `develop`
- **Teknoloji:** PHP 8.3+, Laravel 13, MySQL 8 / MariaDB 10.6+, Blade, Tailwind CSS 4, Alpine.js, Vite
- **CI:** `.github/workflows/ci.yml`
- **Production deploy:** `.github/workflows/deploy.yml`
- **Deploy hedefi:** Lemon uygulaması; aynı hosting hesabındaki diğer siteler deploy hedefi değildir.

## 2. Kaynakların öncelik sırası

Bir ajan göreve başlarken aşağıdaki sırayı izler:

1. Kullanıcının o görev için verdiği açık talimat.
2. `CLAUDE.md` içindeki bağlayıcı mimari, finansal bütünlük, test ve davranış kuralları.
3. `AGENTS.md` ve bu dosyadaki operasyon akışı.
4. İlgili güncel teknik belge: örn. `docs/deploy-lemon.md`, `docs/kararlar.md`, ilgili özellik belgesi.
5. Tarihsel notlar ve eski geçiş belgeleri yalnız bağlam içindir.

**Önemli:** Eski belgelerde “henüz deploy yapılmadı”, eski SHA, eski branch veya eski ortam durumu yazabilir. Güncel gerçeklik için her zaman **GitHub branch/commit durumu, Actions çalışmaları ve gerekiyorsa canlı sistem** doğrulanır.

## 3. Her oturumun zorunlu başlangıç protokolü

Kod değiştirmeden önce ajan şu kontrolleri yapar:

1. Doğru repo mu: `7sebahattin/hotel-account-system`.
2. `CLAUDE.md`, `AGENTS.md` ve bu dosya okunmuş mu.
3. Güncel `main` HEAD SHA nedir.
4. Çalışılan branch adı ve HEAD nedir.
5. Branch, güncel `main`'in önünde/gerisinde mi.
6. Aynı işi yapan açık bir PR veya başka aktif branch var mı.
7. Görev hangi modül/dosyaları etkiliyor.
8. Migration, finansal kayıt, yetki, deploy veya veri bütünlüğü etkisi var mı.
9. Görev için gereken ilgili testler hangileri.
10. Stale worktree / eski branch üzerinden çalışılmıyor mu.

Başlangıç özeti, mümkünse şu formatta kaydedilir:

```text
REPO: 7sebahattin/hotel-account-system
BASE: main
STARTING_MAIN: <sha>
BRANCH: <branch>
STARTING_HEAD: <sha>
SCOPE: <kısa görev>
RISK: ui-only | backend | financial | migration | deploy
```

## 4. Ajan başına branch standardı

Yeni görevlerde önerilen branch adları:

- Claude: `claude/<kisa-gorev>`
- Codex: `codex/<kisa-gorev>`
- ChatGPT: `chatgpt/<kisa-gorev>`
- Genel düzeltme: `fix/<kisa-gorev>`
- Dokümantasyon: `docs/<kisa-gorev>`

Mevcut branch isimleri geriye dönük olarak değiştirilmez.

### Aynı branch'i iki ajan aynı anda değiştirme

**Yapmayın.** Bir görev başka ajana devredilecekse önce mevcut ajanın:

- commit etmesi,
- push etmesi,
- branch/HEAD/PR bilgisini bırakması

beklenir.

Yeni ajan aynı branch'ten devam edecekse önce remote HEAD'i doğrular. Branch zaten merge edildiyse yeni iş **güncel `main` üzerinden yeni branch** ile başlar.

## 5. Görev sahipliği ve çakışma önleme

Paralel işler yapılabilir; ancak aynı dosya veya aynı iş kuralı üzerinde iki ajan eşzamanlı çalışmamalıdır.

Örnek güvenli paralellik:

- Claude: cari ekranı UI düzeni
- Codex: bağımsız rapor modülü testi
- ChatGPT: dokümantasyon / düşük riskli ayrı görsel düzeltme

Örnek riskli paralellik:

- Claude ve Codex aynı anda `StayService` üzerinde değişiklik
- iki ajan aynı migration setini düzenliyor
- bir ajan finansal kayıt kuralını değiştirirken diğeri aynı testleri yeniden yazıyor

Çakışma varsa önce tek branch'te birleştirme planı çıkarılır; körlemesine cherry-pick yapılmaz.

## 6. Değişiklik sınıfına göre doğrulama

Repo CI'ı tüm branch push'larında ve PR'larda çalışır. CI şu kapıları içerir:

- PHP 8.3 hedef uyumluluğu
- `npm ci`
- `npm run build`
- `npm run test:monthly-account`
- gerçek MySQL 8.4 test veritabanı
- `./vendor/bin/pint --test`
- PHPStan/Larastan level 5
- `php artisan test`

Ajan yerelde/çalışma ortamında yapabildiği kontrolleri ayrıca çalıştırır.

### Sadece dokümantasyon

- Kod testi zorunlu değildir.
- Link/path ve belge tutarlılığı kontrol edilir.
- CI yine branch push'unda çalışabilir.

### UI / Blade / CSS / JS

En az:

- ilgili frontend build
- ilgili hedefli test
- mümkünse tam CI sonucu

İş mantığı değişmediyse bunu PR açıklamasında açıkça belirt.

### PHP / servis / domain mantığı

En az:

- hedefli testler
- Pint
- PHPStan
- tam test paketi veya CI sonucu

### Finansal kayıt / cari / tahsilat / döviz / migration

`CLAUDE.md` değişmezleri esastır. Ek olarak:

- tam test paketi
- migration etkisi
- mevcut kayıtların korunması
- rollback/ters kayıt davranışı
- production deploy etkisi

PR açıklamasında açıkça yazılır.

## 7. PR standardı

Her PR mümkünse şu bilgileri içermelidir:

```text
Amaç:
Base main SHA:
Branch:
Değişen alanlar:
Değişen dosyalar:
Business logic değişti mi:
DB/migration var mı:
Testler:
CI durumu:
Deploy etkisi:
Rollback notu:
Kalan risk / yapılacak:
```

PR sadece “çalışıyor” dememeli; **neyin değişmediğini de** belirtmelidir.

Örnek:

> UI düzenlendi. Cari hesap bakiyesi, ledger yazımı, ödeme dağıtımı, DB şeması ve yetki mantığı değiştirilmedi.

## 8. Merge ve canlıya alma kuralı

`main` production branch'tir.

### Kritik gerçek

`.github/workflows/deploy.yml` güncel olarak:

- `main` üzerine production dosyalarını etkileyen bir push/merge sonrası otomatik deploy başlatır,
- önce CI'ı çalıştırır,
- hedef dizin ve deploy kilitlerini doğrular,
- Lemon canlı hedefini günceller,
- finalize marker üzerinden cPanel cron bakım/migration/seed/cache akışını tamamlar.

Yalnız `docs/**`, `tests/**`, `.claude/**` veya Markdown değişiklikleri production dosya aktarımını tetiklemez.

### Ajan davranışı

- Branch'e commit/push ve PR açmak, canlıya almak değildir.
- **`main` merge ancak kullanıcının açık onayıyla** yapılır.
- Kullanıcı “merge et”, “canlıya al”, “pushla ve canlıya çıkar” gibi açık talimat verdiyse ve görev kapsamı uygunsa merge yapılabilir.
- Merge sonrası Actions sonucu kontrol edilir.
- Production workflow başarısızsa “canlıya alındı” denmez.

## 9. Canlı deploy sonrası doğrulama

Kod değişikliği `main`'e merge edildiğinde:

1. Deploy workflow sonucu kontrol edilir.
2. İlgili commit SHA not edilir.
3. Gerekliyse canlı sayfada temel smoke test yapılır.
4. Migration/finalize gerektiren işte cron/finalize sonucu doğrulanır.
5. Başarısız job varsa yeni değişiklikle üstünü örtmek yerine kök neden incelenir.

## 10. Ajanlar arası devir (handoff) standardı

Claude limiti bittiğinde, Codex'e geçerken veya ChatGPT devralırken aşağıdaki blok bırakılır:

```text
AI HANDOFF

REPO: 7sebahattin/hotel-account-system
TASK: <görev>
BASE_MAIN: <işe başlarkenki main sha>
BRANCH: <branch>
CURRENT_HEAD: <sha>
PR: <numara/url veya yok>

DONE:
- ...

CHANGED_FILES:
- ...

BUSINESS_LOGIC:
- değişmedi / değişti: ...

DB_MIGRATION:
- yok / var: ...

TESTS:
- komut: sonuç
- komut: sonuç
- CI: pending/success/fail

DEPLOY:
- canlıya alınmadı / main'e merge edildi
- production commit: <sha veya yok>
- deploy run: <url veya yok>

OPEN_ISSUES:
- ...

NEXT_STEP:
- ...
```

Yeni ajan bu bilgiye **körü körüne güvenmez**; branch ve SHA'ları GitHub'dan doğrular.

## 11. Yeni ajana verilecek kısa başlangıç prompt'u

Aşağıdaki format Claude, Codex veya ChatGPT için kullanılabilir:

```text
REPO: 7sebahattin/hotel-account-system

Önce güncel main HEAD'i doğrula.
AGENTS.md, CLAUDE.md ve docs/AI_WORKFLOW.md dosyalarını oku.
Mevcut açık branch/PR ile çakışma olmadığını kontrol et.
Güncel main'den kendi branch'ini aç.

GÖREV:
<buraya istek>

KISITLAR:
- mevcut finansal/data integrity kurallarını koru
- istemeden migration oluşturma
- ilgili testleri çalıştır
- PR aç
- açıkça istemedikçe main'e merge etme
```

## 12. Context kaybını önleyen temel prensipler

- **SHA hafızadan kullanılmaz; GitHub'dan doğrulanır.**
- **Production durumu eski dokümandan varsayılmaz; Actions/canlı durumdan doğrulanır.**
- **Ajan özel notları, proje gerçeğinin yerine geçmez.**
- Kritik ürün kararları mümkünse `docs/kararlar.md` veya ilgili güncel belgeye işlenir.
- Geçici konuşma bağlamı kalıcı iş kuralı hâline geldiyse repo dokümantasyonuna taşınır.
- Aynı kuralı üç farklı dokümana kopyalayıp zamanla ayrıştırmak yerine tek kaynak seçilir ve diğer dosyalardan ona referans verilir.

## 13. Bu belgenin rolü

Bu dosya:

- Claude'un `CLAUDE.md` bağlamını değiştirmez,
- Codex'in `AGENTS.md` okuma davranışını tamamlar,
- ChatGPT'nin GitHub üzerinden yaptığı branch/PR çalışmalarına ortak başlangıç standardı verir,
- ajan değiştirirken projenin “nerede kaldığını” kaybetmemeyi amaçlar.

Ürün kuralı değişirse `CLAUDE.md` / `docs/kararlar.md`; çalışma yöntemi değişirse bu dosya güncellenir.
