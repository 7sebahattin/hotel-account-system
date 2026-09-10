<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.1.1-B: elle girilen kasa hareketleri artık bir KATEGORİYE (admin
 * tanımlı ad/sebep) bağlanır.
 *
 * `category_label` (tarihsel anlık görüntü): kategori adı SONRADAN
 * değiştirilebilir olduğu için (§5 admin UI), geçmiş hareketin ekranda
 * gösterdiği ad kategori güncellendiğinde SESSİZCE değişmemelidir — ad
 * kayıt anında buraya kopyalanır. `category_id` hâlâ tutulur (raporlama/
 * filtre için) ama EKRANDA gösterilen metin her zaman bu anlık görüntüdür.
 *
 * Her ikisi de NULLABLE: mevcut hareketler (bu migration'dan önce) ve
 * sistem tarafından üretilen türler (`opening`, `company_collection`)
 * hiçbir zaman bir kategoriden geçmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('type')
                ->constrained('cash_movement_categories')->restrictOnDelete();
            $table->string('category_label', 100)->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('category_label');
        });
    }
};
