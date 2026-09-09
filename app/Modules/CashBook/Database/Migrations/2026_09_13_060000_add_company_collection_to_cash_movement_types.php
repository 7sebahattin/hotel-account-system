<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasa hareketi türlerine `company_collection` eklenir (v2.1.1-A).
 *
 * Nakit kanallı bir cari tahsilatın fiziksel kasa karşılığıdır; yalnızca
 * `PaymentService` tarafından üretilir (CashMovementType::selectable()'da
 * YOKTUR). Yönü her zaman `in`dir, ters kayıtta `out`a döner — mevcut
 * `cash_in`/`room_income`/`extra_income` ile AYNI kalıp.
 *
 * Oluşturma migration'ı GERİYE DÖNÜK DEĞİŞTİRİLMEZ (CLAUDE.md §15): kısıtlar
 * düşürülüp yeni değer listesiyle yeniden kurulur. Mevcut satırlar etkilenmez;
 * eski değerlerin tamamı yeni listede de geçerlidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT cash_movements_type_valid');
        DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT cash_movements_direction_matches_type');

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_valid
             CHECK (type IN (
                 'opening', 'room_income', 'extra_income', 'expense', 'cash_out', 'cash_in',
                 'company_collection'
             ))"
        );

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_matches_type
             CHECK (
                 (is_reversal = 0 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in', 'company_collection')
                         AND direction = 'in')
                  OR (type IN ('expense', 'cash_out') AND direction = 'out')))
              OR (is_reversal = 1 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in', 'company_collection')
                         AND direction = 'out')
                  OR (type IN ('expense', 'cash_out') AND direction = 'in')))
             )"
        );
    }

    public function down(): void
    {
        // Geri alma, halihazırda 'company_collection' taşıyan satır varsa
        // BAŞARISIZ OLUR. Bu bilinçlidir: veri sessizce kaybedilmez.
        DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT cash_movements_type_valid');
        DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT cash_movements_direction_matches_type');

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_valid
             CHECK (type IN ('opening', 'room_income', 'extra_income', 'expense', 'cash_out', 'cash_in'))"
        );

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_matches_type
             CHECK (
                 (is_reversal = 0 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in') AND direction = 'in')
                  OR (type IN ('expense', 'cash_out') AND direction = 'out')))
              OR (is_reversal = 1 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in') AND direction = 'out')
                  OR (type IN ('expense', 'cash_out') AND direction = 'in')))
             )"
        );
    }
};
