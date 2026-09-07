<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ödeme yöntemlerine `cheque` (Çek) eklenir.
 *
 * Oluşturma migration'ı GERİYE DÖNÜK DEĞİŞTİRİLMEZ (CLAUDE.md §15): kısıt
 * düşürülüp yeni değer listesiyle yeniden kurulur. Mevcut satırlar etkilenmez;
 * eski değerlerin tamamı yeni listede de geçerlidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_valid');

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_method_valid
             CHECK (payment_method IS NULL
                 OR payment_method IN ('cash', 'bank_transfer', 'credit_card', 'cheque', 'other'))"
        );
    }

    public function down(): void
    {
        // Geri alma, halihazırda 'cheque' taşıyan satır varsa BAŞARISIZ OLUR.
        // Bu bilinçlidir: veri sessizce kaybedilmez, önce dönüştürülmesi gerekir.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_valid');

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_method_valid
             CHECK (payment_method IS NULL
                 OR payment_method IN ('cash', 'bank_transfer', 'credit_card', 'other'))"
        );
    }
};
