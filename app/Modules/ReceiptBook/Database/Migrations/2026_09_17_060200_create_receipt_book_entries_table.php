<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahsilat Makbuzu Defteri (v2.1.1-C §4) — mevcut MUHASEBE `payments` tablosundan
 * TAMAMEN AYRI, yazdırma amaçlı bir kayıt defteridir.
 *
 * KRİTİK: bu tablo `ledger_entries`e HİÇBİR ZAMAN yazmaz, `payments` veya
 * `cash_movements` ile de HİÇBİR İLİŞKİSİ yoktur (FK dahil) — bir satırın
 * buraya kaydedilmesi cari bakiyeyi, fiziksel kasayı ya da herhangi bir
 * muhasebe kaydını ETKİLEMEZ. `App\Modules\ReceiptBook\Services\
 * ReceiptBookService` bunu KODLA da garanti eder: `LedgerService`,
 * `PaymentService`, `CashBookService`'i hiç import ETMEZ.
 *
 * Numaralandırma `payments.receipt_no`nun (`TM-{yıl}-{id}`) desenini TEKRAR
 * ETMEZ — karışıklık riski taşımaması için bilerek farklı bir önek kullanır
 * (`MKB-{yıl}-{id}`, bkz. Model).
 *
 * `receipt_date`/`amount`/`currency`/kur alanları — aynı Türkçe kural ve AYNI
 * üç CHECK deseni: bkz. Stays/Payments'taki 2026_09_17_06xxxx migration'ları.
 * Silme ucu YOKTUR (route/servis/policy hiçbiri delete tanımlamaz) — bu
 * yüzden `deleted_at` veya `status` kolonu AÇILMADI (§3.2 ilkesi: kullanılmayan
 * kolon açılmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_book_entries', function (Blueprint $table) {
            $table->id();
            // `id` atandıktan HEMEN SONRA, ikinci adımda yazılır (bkz.
            // ReceiptBookService::create() — Payment::receipt_no ile AYNI
            // desen) — bu yüzden ilk INSERT anında NULL olmalıdır.
            $table->string('receipt_no', 20)->nullable()->unique();
            $table->date('receipt_date')->index();
            $table->string('customer_name', 200);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('TRY');
            $table->decimal('exchange_rate_to_try', 10, 4)->nullable();
            $table->decimal('try_equivalent', 15, 2)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->engine = 'InnoDB';
        });

        DB::statement('ALTER TABLE receipt_book_entries ADD CONSTRAINT receipt_book_entries_amount_positive CHECK (amount > 0)');

        DB::statement(
            "ALTER TABLE receipt_book_entries ADD CONSTRAINT receipt_book_entries_try_has_no_exchange_rate
             CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))"
        );

        DB::statement(
            'ALTER TABLE receipt_book_entries ADD CONSTRAINT receipt_book_entries_exchange_rate_pair_complete
             CHECK ((exchange_rate_to_try IS NULL AND try_equivalent IS NULL)
                 OR (exchange_rate_to_try IS NOT NULL AND try_equivalent IS NOT NULL))'
        );

        DB::statement(
            'ALTER TABLE receipt_book_entries ADD CONSTRAINT receipt_book_entries_exchange_rate_positive
             CHECK (exchange_rate_to_try IS NULL OR exchange_rate_to_try > 0)'
        );

        DB::statement(
            'ALTER TABLE receipt_book_entries ADD CONSTRAINT receipt_book_entries_try_equivalent_positive
             CHECK (try_equivalent IS NULL OR try_equivalent > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_book_entries');
    }
};
