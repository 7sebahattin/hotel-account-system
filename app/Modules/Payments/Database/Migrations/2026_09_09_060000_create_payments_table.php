<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahsilat kaydı (CLAUDE.md §5.4).
 *
 * SİLME YOKTUR: ne hard delete ne soft delete. Yanlış tahsilat `status`
 * üzerinden iptal edilir ve iptal, ters cari kaydı üretir. Bu yüzden tabloda
 * `deleted_at` kolonu YOKTUR — soft delete'in var olması bile kaydı
 * listelerden düşürerek finansal izi bulanıklaştırırdı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();

            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('payment_method', 30)->nullable();

            $table->string('reference_no', 50)->nullable();
            $table->string('description', 255)->nullable();

            $table->string('status', 20)->default('active');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['company_id', 'payment_date']);
            $table->index('payment_date');
            $table->index('status');
            $table->index('currency');
            $table->index('payment_method');
            $table->index('created_by');
            $table->index('reference_no');
        });

        // Veritabanı düzeyinde değişmezler — uygulama katmanı atlansa bile geçerli.
        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)'
        );

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_status_valid
             CHECK (status IN ('active', 'cancelled'))"
        );

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_method_valid
             CHECK (payment_method IS NULL
                 OR payment_method IN ('cash', 'bank_transfer', 'credit_card', 'other'))"
        );

        // İptal edilmiş bir tahsilat kimin, ne zaman iptal ettiğini taşımak
        // zorundadır; aktif tahsilatta bu alanlar boştur.
        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_cancellation_consistent
             CHECK ((status = 'active'    AND cancelled_at IS NULL AND cancelled_by IS NULL)
                 OR (status = 'cancelled' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
