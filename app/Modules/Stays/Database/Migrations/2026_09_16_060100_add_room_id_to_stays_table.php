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
 *
 * KURTARMA GÜVENLİĞİ (K-101): production'da bu migration ADD CONSTRAINT
 * adımında (errno 150) başarısız oldu. Laravel'in MySQL grammar'ı
 * `Schema::table()` içindeki "kolon ekle" ve "FK ekle" adımlarını AYRI ALTER
 * TABLE ifadeleri olarak gönderir; bu yüzden `room_id` kolonu o denemede
 * ZATEN eklenmiş, yalnızca FK kısıtı hiç eklenememiş olabilir. Migration
 * BAŞARISIZ olduğu için Laravel bunu "çalıştı" olarak işaretlemez — bir
 * sonraki `migrate` denemesi bu dosyayı BAŞTAN çalıştırır. Bu yüzden her
 * adım kendi varlığını kontrol eder: hem tertemiz bir veritabanında hem de
 * bu kısmi denemenin üzerine güvenle tekrar çalışır. `room_id` zaten varsa
 * KÖRLEMESİNE tekrar eklenmez.
 */
return new class extends Migration
{
    private const FOREIGN_KEY = 'stays_room_id_foreign';

    public function up(): void
    {
        if (! Schema::hasColumn('stays', 'room_id')) {
            Schema::table('stays', function (Blueprint $table): void {
                $table->foreignId('room_id')->nullable()->after('room_no');
            });
        }

        if (! Schema::hasForeignKey('stays', self::FOREIGN_KEY)) {
            Schema::table('stays', function (Blueprint $table): void {
                $table->foreign('room_id', self::FOREIGN_KEY)
                    ->references('id')->on('rooms')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table): void {
            if (Schema::hasForeignKey('stays', self::FOREIGN_KEY)) {
                $table->dropForeign(self::FOREIGN_KEY);
            }

            if (Schema::hasColumn('stays', 'room_id')) {
                $table->dropColumn('room_id');
            }
        });
    }
};
