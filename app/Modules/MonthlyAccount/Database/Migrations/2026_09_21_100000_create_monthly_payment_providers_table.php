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
        if (Schema::hasTable('monthly_payment_providers')) {
            return;
        }

        Schema::create('monthly_payment_providers', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->collation = 'utf8mb4_turkish_ci';
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Önceden kullanılmış adları tanım listesine alır; sabit/genel bir banka
        // kataloğu eklenmez. Böylece mevcut kayıtlar korunurken gereksiz adlar
        // yeni kurulumlara taşınmaz.
        if (Schema::hasTable('monthly_account_items')) {
            $now = now();
            $rows = DB::table('monthly_account_items')
                ->whereNotNull('bank_name')
                ->where('bank_name', '<>', '')
                ->distinct()
                ->orderBy('bank_name')
                ->pluck('bank_name')
                ->map(fn (string $name): array => [
                    'name' => trim($name),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('monthly_payment_providers')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_payment_providers');
    }
};
