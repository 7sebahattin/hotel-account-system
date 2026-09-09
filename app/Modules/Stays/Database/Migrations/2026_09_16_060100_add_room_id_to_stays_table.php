<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room Registry (K-100): `room_no` serbest metin alanı GERİYE DÖNÜK UYUMLULUK
 * için kalır — mevcut geçmiş veri hiç değiştirilmez, hiç yeniden yazılmaz.
 *
 * `room_id` NULLABLE'dır: yeni konaklamalar oda seçer, eski kayıtlarda değer
 * NULL kalır ve ekranlarda `room_no` metnine düşülür. Bu yüzden mevcut
 * satırları geriye doldurma (backfill) YAPILMAZ — kullanıcı talimatı: "do not
 * rewrite old data blindly".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('room_no')
                ->constrained('rooms')->restrictOnDelete();

            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
