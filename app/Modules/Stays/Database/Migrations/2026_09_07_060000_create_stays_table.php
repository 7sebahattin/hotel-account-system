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
        Schema::create('stays', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();

            $table->string('guest_name', 150);
            $table->string('room_no', 20)->nullable();

            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('nights');

            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);

            $table->text('notes')->nullable();

            // V1'de yalnızca iki durum vardır: active, cancelled.
            // Rezervasyon/giriş/çıkış gibi PMS durumları KAPSAM DIŞIDIR.
            $table->string('status', 20)->default('active');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();

            // İptal, status alanı üzerinden yapılır. deleted_at yalnızca kazara
            // yapılacak bir delete() çağrısının fiziksel silme olmasını engeller.
            $table->softDeletes();

            $table->index(['company_id', 'check_in_date']);
            $table->index(['check_in_date', 'check_out_date']);
            $table->index('check_out_date');
            $table->index('status');
            $table->index('created_by');
            $table->index('currency');
        });

        // Veritabanı düzeyinde değişmezler.
        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_amount_positive CHECK (amount > 0)'
        );

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_date_range CHECK (check_out_date >= check_in_date)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stays');
    }
};
