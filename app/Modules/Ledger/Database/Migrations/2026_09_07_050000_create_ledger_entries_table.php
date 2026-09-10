<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();

            // Yalnızca izleme amaçlıdır. Domain doğruluğu kaynak kayıttan değil
            // her zaman ledger'dan okunur (CLAUDE.md §6/L4).
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->date('entry_date');
            $table->string('description', 255);

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->char('currency', 3);

            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reverses_entry_id')->nullable()
                ->constrained('ledger_entries')->restrictOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Değiştirilemez kayıt: updated_at ve deleted_at YOKTUR.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'currency']);
            $table->index(['source_type', 'source_id']);
            $table->index('entry_date');

            // Bir hareket yalnızca BİR kez ters kaydedilebilir. NULL değerler
            // tekrarlanabildiği için bu kısıt yalnızca ters kayıtları bağlar.
            $table->unique('reverses_entry_id');
        });

        // Veritabanı düzeyinde değişmezler (CLAUDE.md §6/L2). Uygulama katmanı
        // atlansa bile bu kısıtlar geçerlidir.
        DB::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amounts_not_negative
             CHECK (debit >= 0 AND credit >= 0)'
        );

        DB::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_exactly_one_side
             CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
