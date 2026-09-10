<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KURTARMA GÜVENLİĞİ: `rooms` bu migration'ın ürettiği YENİ bir tablodur ve
 * bir sonraki migration (`add_room_id_to_stays_table`) ona FK ile referans
 * verir. Motor açıkça `InnoDB` olarak zorlanır — aksi hâlde sunucunun
 * varsayılan depolama motoruna güvenilirdi ve `stays` (zaten InnoDB) ile
 * `rooms` arasında sessiz bir motor uyuşmazlığı, ADD CONSTRAINT adımında
 * "Foreign key constraint is incorrectly formed" (errno 150) hatasına yol
 * açabilirdi — production'da GERÇEKTEN yaşanan hata budur (bkz.
 * add_room_id_to_stays_table, docs/kararlar.md K-101).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms')) {
            return;
        }

        Schema::create('rooms', function (Blueprint $table) {
            $table->engine = 'InnoDB';

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
