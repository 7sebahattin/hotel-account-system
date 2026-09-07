#!/bin/bash
#
# SessionStart hook — geliştirme/test ortamını ayağa kaldırır.
#
# KAPSAM: yalnızca yerel geliştirme ve test. Bu script hiçbir production
# veya hosting işlemi yapmaz: deploy yok, uzak veritabanı yok, dış servis yok.
# Yaptığı her şey bu container ile sınırlıdır.
#
set -euo pipefail

# Yalnızca uzak (Claude Code on the web) ortamda çalışır.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/../..}"

log() { printf '  %s\n' "$1"; }

echo "→ Otel Cari Takip — geliştirme ortamı hazırlanıyor"

# ---------------------------------------------------------------- MariaDB
if ! command -v mariadbd >/dev/null 2>&1; then
    log "MariaDB kuruluyor…"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq mariadb-server mariadb-client
fi

if ! mariadb -uroot -e "SELECT 1" >/dev/null 2>&1; then
    log "MariaDB başlatılıyor…"
    mkdir -p /run/mysqld
    chown mysql:mysql /run/mysqld
    (mariadbd-safe --skip-syslog >/tmp/mariadb.log 2>&1 &)

    for _ in $(seq 1 20); do
        mariadb -uroot -e "SELECT 1" >/dev/null 2>&1 && break
        sleep 2
    done
fi

if ! mariadb -uroot -e "SELECT 1" >/dev/null 2>&1; then
    echo "  ✗ MariaDB başlatılamadı. Günlük: /tmp/mariadb.log" >&2
    tail -20 /tmp/mariadb.log >&2 || true
    exit 1
fi
log "MariaDB çalışıyor ($(mariadb -uroot -NB -e 'SELECT VERSION()'))"

# ------------------------------------------------------------ Veritabanı
# Türkçe collation zorunlu (CLAUDE.md §5): 'ı'='I' ve 'i'='İ' eşleşmesi
# firma adı aramasının doğru çalışması için gerekli.
mariadb -uroot <<'SQL'
CREATE DATABASE IF NOT EXISTS hotel_account      CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
CREATE DATABASE IF NOT EXISTS hotel_account_test CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
CREATE USER IF NOT EXISTS 'hotel'@'localhost' IDENTIFIED BY 'hotel';
CREATE USER IF NOT EXISTS 'hotel'@'127.0.0.1' IDENTIFIED BY 'hotel';
GRANT ALL PRIVILEGES ON hotel_account.*      TO 'hotel'@'localhost';
GRANT ALL PRIVILEGES ON hotel_account_test.* TO 'hotel'@'localhost';
GRANT ALL PRIVILEGES ON hotel_account.*      TO 'hotel'@'127.0.0.1';
GRANT ALL PRIVILEGES ON hotel_account_test.* TO 'hotel'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

for db in hotel_account hotel_account_test; do
    collation=$(mariadb -uroot -NB -e \
        "SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db';")
    if [ "$collation" != "utf8mb4_turkish_ci" ]; then
        echo "  ✗ $db collation'ı '$collation' — utf8mb4_turkish_ci olmalı" >&2
        exit 1
    fi
done
log "Veritabanları hazır (hotel_account, hotel_account_test — utf8mb4_turkish_ci)"

# ----------------------------------------------------------------- .env
if [ ! -f .env ]; then
    log ".env oluşturuluyor…"
    sed 's/^DB_PASSWORD=$/DB_PASSWORD=hotel/' .env.example > .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --ansi --no-interaction >/dev/null
    log "APP_KEY üretildi"
fi

# --------------------------------------------------------- Bağımlılıklar
if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    log "Composer bağımlılıkları kuruluyor…"
    # Bu ortamda GitHub zipball indirmeleri proxy tarafından engellenir; source'u
    # olmayan paketlerde composer install kısmen başarısız olabilir. Aşağıdaki
    # onarım adımı eksik kalanları git ile getirir.
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress --quiet \
        || log "composer install kısmen başarısız — git ile onarılacak"
else
    log "Composer bağımlılıkları güncel"
fi

if php "$(dirname "$0")/fetch-blocked-packages.php"; then
    COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --quiet 2>/dev/null || true
fi

if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then
    log "npm bağımlılıkları kuruluyor…"
    npm install --no-audit --no-fund --silent
else
    log "npm bağımlılıkları güncel"
fi

# Blade görünümleri Vite manifest'i beklediği için derleme çıktısı gerekli.
if [ ! -f public/build/manifest.json ]; then
    log "Frontend derleniyor…"
    npm run build --silent >/dev/null
else
    log "Frontend derlemesi mevcut"
fi

# -------------------------------------------------------------- Migration
php artisan migrate --force --no-interaction >/dev/null
log "Migration'lar uygulandı"

php artisan config:clear >/dev/null 2>&1 || true

cat <<'EOF'

  Hazır. Kullanılabilir komutlar:
    php artisan test                 tüm testler (gerçek MariaDB üzerinde)
    php artisan test --filter=Ledger cari testleri
    ./vendor/bin/pint --test         kod formatı kontrolü
    php artisan migrate:fresh --seed yerel veriyi sıfırla
    php artisan serve                geliştirme sunucusu

EOF
