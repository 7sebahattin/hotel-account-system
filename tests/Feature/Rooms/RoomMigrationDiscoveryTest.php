<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * K-102: production'da `create_rooms_table` migration'ı hiç çalışmadı —
 * `rooms` tablosu yoktu, `migrations` tablosunda sıfır satır vardı — ama
 * AYNI deploy'daki `add_room_id_to_stays_table` çalıştı (kısmen). Kök
 * neden bayat bir `bootstrap/cache/config.php`'nin RoomsServiceProvider'ı
 * hiç taşımamasıydı (yerelde birebir aynı hatayla yeniden üretildi, bkz.
 * docs/kararlar.md K-102) — Laravel/registration KODU değil, deploy SIRASI
 * (cache temizleme migrate'ten SONRA değil ÖNCE gelmeli) hatalıydı.
 *
 * Bu test dosyası, o senaryonun CI'da GÜVENLE otomatikleştirilebilen
 * kısmını kapsar: normal koşullarda (bayat cache YOKKEN) migrator'un iki
 * migration'ı da doğru sırada keşfettiğini, ve production'daki kısmi
 * durumdan (kolon var, FK yok, migration kaydı silinmiş) güvenle
 * kurtarabildiğini doğrular. Gerçek bayat-cache senaryosunun kendisi
 * `bootstrap/providers.php`'yi diskte değiştirmeyi ve gerçek bir
 * `config:cache` çalıştırmayı gerektirir — bu, deploy'a kritik dosyaları
 * mutasyona uğratacağı için otomatik bir CI testine dönüştürülmedi;
 * K-102'de manuel olarak, production hatasıyla birebir aynı mesajla
 * doğrulanmıştır.
 */
class RoomMigrationDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Normal koşullarda (RefreshDatabase'in kendi migrate'i, bayat cache
     * YOK) `rooms` her zaman `stays_room_id_foreign`den ÖNCE var olmalıdır
     * — aksi hâlde FK kurulamazdı. Bu, migrator'un iki modülün de migration
     * dizinini doğru keşfettiğinin ve doğru sırada çalıştırdığının kanıtıdır.
     */
    #[Test]
    public function rooms_ve_stays_room_id_fk_migrator_tarafindan_dogru_sirada_kesfedilir(): void
    {
        $this->assertTrue(Schema::hasTable('rooms'), 'rooms tablosu migrator tarafından oluşturulmalı');
        $this->assertTrue(
            Schema::hasForeignKey('stays', 'stays_room_id_foreign'),
            'stays_room_id_foreign FK\'si kurulmuş olmalı — bu, rooms tablosunun FK eklenmeden ÖNCE var olduğunu ispatlar',
        );

        $roomsBatch = DB::table('migrations')
            ->where('migration', '2026_09_16_060000_create_rooms_table')
            ->value('batch');
        $staysRoomIdBatch = DB::table('migrations')
            ->where('migration', '2026_09_16_060100_add_room_id_to_stays_table')
            ->value('batch');

        $this->assertNotNull($roomsBatch, 'create_rooms_table migrations tablosunda kayıtlı olmalı');
        $this->assertNotNull($staysRoomIdBatch, 'add_room_id_to_stays_table migrations tablosunda kayıtlı olmalı');
    }

    /**
     * Production'ın GERÇEKTEN bulunduğu kısmi durumu simüle eder: `stays.
     * room_id` kolonu zaten var (önceki kısmi denemeden), FK yok, migration
     * kaydı silinmiş (migration'ın kendisi "çalıştı" olarak işaretli değil).
     * `php artisan migrate` bu durumdan HATASIZ kurtarmalı — kolonu tekrar
     * eklemeye ÇALIŞMAMALI (aksi hâlde "Duplicate column" ile patlardı),
     * yalnızca eksik FK'yi eklemeli (K-101).
     */
    #[Test]
    public function kismi_production_durumundan_guvenle_kurtarilir(): void
    {
        // Kısmi durumu üret: FK'yi düşür, migration kaydını sil — kolon yerinde bırakılır.
        Schema::table('stays', function ($table): void {
            $table->dropForeign('stays_room_id_foreign');
        });
        DB::table('migrations')->where('migration', '2026_09_16_060100_add_room_id_to_stays_table')->delete();

        $this->assertTrue(Schema::hasColumn('stays', 'room_id'), 'kolon kısmi denemeden kalmış olmalı');
        $this->assertFalse(Schema::hasForeignKey('stays', 'stays_room_id_foreign'));

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(
            Schema::hasForeignKey('stays', 'stays_room_id_foreign'),
            'kurtarma FK\'yi eklemeli',
        );

        // İkinci çalıştırma tamamen no-op olmalı — hiçbir hata fırlatılmamalı.
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exitCode);
    }
}
