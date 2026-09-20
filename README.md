# Otel Cari Takip Sistemi

Otelin **firma (cari) alacak ve tahsilat takibi**. Excel ile yürütülen süreçlerin yerini alır.

> Bu depoda çalışmadan önce **[CLAUDE.md](./CLAUDE.md)** okunmalıdır. Kapsam, mimari
> kurallar, cari hesap değişmezleri ve test zorunlulukları orada tanımlıdır.
> Claude, Codex ve ChatGPT arasında ortak çalışma/devretme standardı için
> **[docs/AI_WORKFLOW.md](./docs/AI_WORKFLOW.md)** kullanılır.

## Teknoloji

PHP 8.3+ · Laravel 13 · MySQL 8 / MariaDB 10.6+ · Blade · Tailwind CSS 4 · Alpine.js (gerektiğinde) · Vite

## Yerel kurulum

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Veritabanı (Türkçe collation zorunlu)
mysql -u root -e "CREATE DATABASE hotel_account      CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;"
mysql -u root -e "CREATE DATABASE hotel_account_test CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;"

php artisan migrate --seed
npm run build
php artisan serve
```

Varsayılan yönetici hesabı `.env` üzerinden ayarlanır
(`SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`); tanımlanmazsa
`admin@example.com` / `degistir-beni` kullanılır. **Canlıda mutlaka değiştirin.**

## Claude Code on the web

`.claude/hooks/session-start.sh` her oturum başında MariaDB'yi ayağa kaldırır,
test veritabanlarını Türkçe collation ile oluşturur ve bağımlılıkları kontrol eder.
Hook yalnızca yerel geliştirme/test ortamına dokunur; hiçbir hosting işlemi yapmaz.

## Çoklu AI geliştirme

Bu repo Claude, Codex ve ChatGPT ile aynı GitHub akışı üzerinden geliştirilebilir.
Her ajan güncel `main` HEAD'i doğrular, kendi branch'inde çalışır, test/CI sonucunu
kaydeder ve devrederken standart handoff bırakır.

Ayrıntılar: **[docs/AI_WORKFLOW.md](./docs/AI_WORKFLOW.md)**.

## Dallar

| Dal | Rolü |
|---|---|
| `main` | production |
| `develop` | staging / entegrasyon |
| `feature/*` | geliştirme |

## Komutlar

```bash
php artisan test              # tüm testler (gerçek MySQL üzerinde koşar)
php artisan test --filter=Ledger
./vendor/bin/pint             # kod formatı
npm run dev                   # geliştirme sunucusu
npm run build                 # üretim derlemesi
```

## Neden testler SQLite'ta koşmuyor

SQLite'ta gerçek `DECIMAL` yoktur (NUMERIC affinity → float), `utf8mb4_turkish_ci`
collation ve `RESTRICT` foreign key davranışı taklit edilemez. Cari bakiye ve
Türkçe arama testleri ancak canlı ortamla aynı motorda anlam taşır.
Bkz. `phpunit.xml` ve CLAUDE.md §12.

## Dokümantasyon

- [CLAUDE.md](./CLAUDE.md) — bağlayıcı proje kılavuzu
- [docs/AI_WORKFLOW.md](./docs/AI_WORKFLOW.md) — Claude/Codex/ChatGPT ortak çalışma ve handoff standardı
- [docs/deploy-cpanel.md](./docs/deploy-cpanel.md) — cPanel dağıtım rehberi
- [docs/deploy-github-actions.md](./docs/deploy-github-actions.md) — FTPS deploy mimarisi ve GitHub Secrets
- [docs/kararlar.md](./docs/kararlar.md) — mimari kararlar ve gerekçeleri
