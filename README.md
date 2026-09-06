# Otel Cari Takip Sistemi

Otelin **firma (cari) alacak ve tahsilat takibi**. Excel ile yürütülen süreçlerin yerini alır.

> Bu depoda çalışmadan önce **[CLAUDE.md](./CLAUDE.md)** okunmalıdır. Kapsam, mimari
> kurallar, cari hesap değişmezleri ve test zorunlulukları orada tanımlıdır.

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
- [docs/deploy-cpanel.md](./docs/deploy-cpanel.md) — cPanel dağıtım rehberi
- [docs/kararlar.md](./docs/kararlar.md) — mimari kararlar ve gerekçeleri
