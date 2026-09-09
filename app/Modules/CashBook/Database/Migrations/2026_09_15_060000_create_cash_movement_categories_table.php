<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin tarafından yönetilen kasa hareketi TÜRÜ (kategori) ana verisi
 * (v2.1.1-B §3).
 *
 * KRİTİK SINIR: admin yeni bir ARİTMETİK/YÖN icat edemez — yalnızca sabit
 * sistem davranışlarından (`CashMovementType::selectable()`) birine
 * eşlenen bir AD/SEBEP tanımlar. `system_type` bu yüzden serbest metin
 * DEĞİL, aşağıdaki CHECK ile seçilebilir türlerle sınırlıdır — `opening` ve
 * `company_collection` bilerek DIŞARIDADIR (yalnızca sistem üretir).
 *
 * Silme YOKTUR: kategori geçmişi varsa asla hard-delete edilmez, yalnızca
 * `is_active = false` yapılır (companies/stays ile AYNI ilke, CLAUDE.md §5.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movement_categories', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)->unique();
            $table->string('system_type', 20);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_recipient')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('system_type');
        });

        DB::statement(
            "ALTER TABLE cash_movement_categories ADD CONSTRAINT cash_movement_categories_system_type_valid
             CHECK (system_type IN ('room_income', 'extra_income', 'expense', 'cash_out', 'cash_in'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movement_categories');
    }
};
