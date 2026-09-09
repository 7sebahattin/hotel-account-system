<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->string('room_no', 20)->unique();
            $table->string('room_name', 150)->nullable();
            $table->string('room_type', 50)->nullable();

            // Kullanılmış bir oda asla hard-delete edilmez, yalnızca pasifleştirilir
            // (companies/cash_movement_categories ile AYNI ilke). Pasif oda yeni
            // konaklama için seçilemez ama geçmişte görünmeye devam eder.
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
