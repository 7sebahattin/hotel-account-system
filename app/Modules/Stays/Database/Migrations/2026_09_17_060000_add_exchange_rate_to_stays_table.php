<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v2.1.1-C §2/§3 — yabancı para birimli konaklamalar için kur ANLIK GÖRÜNTÜSÜ.
 *
 * `exchange_rate_to_try` ve `try_equivalent` işlem ANINDA sabitlenen bir
 * momentodur — daha sonra canlı bir kurla YENİDEN HESAPLANMAZ (kullanıcı
 * talimatı: "never use a live FX rate later to recalculate historical
 * records"). Bu yüzden `Currency`/`Money` gibi TEKNİK bir servis DEĞİL, her
 * kaydın kendi iki DECIMAL kolonudur.
 *
 * NULLABLE: bu migration'dan ÖNCEKİ yabancı para birimli kayıtlar kur
 * bilgisi TAŞIMAZ — geçmiş veri tahmin/icat edilmez (K-094/K-095/K-096 ile
 * aynı ilke). Bu boşluk §3'teki "Geçmiş Döviz Düzeltme" aracıyla, yalnızca
 * eksik olan kayıtlar için, tek seferlik ve denetimli biçimde kapatılır.
 *
 * CHECK kısıtları veritabanı düzeyinde üç şeyi garanti eder:
 *   1. TRY konaklaması ASLA kur/karşılık taşımaz (§2 — "TRY işlemleri kur
 *      gerektirmez", çevrim yapılmaz, CLAUDE.md §6/L5).
 *   2. Kur ve karşılık HER ZAMAN birlikte dolu ya da birlikte boştur — yarım
 *      bir anlık görüntü (kur var, karşılık yok) oluşamaz.
 *   3. Dolu değerler her zaman pozitiftir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->decimal('exchange_rate_to_try', 10, 4)->nullable()->after('currency');
            $table->decimal('try_equivalent', 15, 2)->nullable()->after('exchange_rate_to_try');
        });

        DB::statement(
            "ALTER TABLE stays ADD CONSTRAINT stays_try_has_no_exchange_rate
             CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))"
        );

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_exchange_rate_pair_complete
             CHECK ((exchange_rate_to_try IS NULL AND try_equivalent IS NULL)
                 OR (exchange_rate_to_try IS NOT NULL AND try_equivalent IS NOT NULL))'
        );

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_exchange_rate_positive
             CHECK (exchange_rate_to_try IS NULL OR exchange_rate_to_try > 0)'
        );

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_try_equivalent_positive
             CHECK (try_equivalent IS NULL OR try_equivalent > 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_try_equivalent_positive');
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_exchange_rate_positive');
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_exchange_rate_pair_complete');
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_try_has_no_exchange_rate');

        Schema::table('stays', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate_to_try', 'try_equivalent']);
        });
    }
};
