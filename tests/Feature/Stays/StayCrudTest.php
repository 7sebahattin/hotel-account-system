<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Models\Stay;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create(['name' => 'Monarch']);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000,00',
            'currency' => 'TRY',
            'notes' => 'Acente rezervasyonu',
        ], $overrides);
    }

    #[Test]
    public function konaklama_olusturulur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->payload())
            ->assertRedirect();

        $stay = Stay::query()->firstOrFail();

        $this->assertSame('John Smith', $stay->guest_name);
        $this->assertSame('9000.00', $stay->amount);
        $this->assertSame(Currency::TRY, $stay->currency);
        $this->assertSame(StayStatus::Active, $stay->status);
        $this->assertSame($admin->id, $stay->created_by);
        $this->assertSame($this->company->id, $stay->company_id);
    }

    #[Test]
    public function gece_sayisi_sunucuda_hesaplanir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        // Formdan gönderilen nights değeri yok sayılır.
        $this->actingAs($admin)->post(route('stays.store'), $this->payload(['nights' => 99]));

        $this->assertSame(3, Stay::query()->firstOrFail()->nights);
    }

    #[Test]
    #[DataProvider('nightRanges')]
    public function gece_sayisi_tarih_farkina_esittir(string $in, string $out, int $expected): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->post(
            route('stays.store'),
            $this->payload(['check_in_date' => $in, 'check_out_date' => $out]),
        );

        $this->assertSame($expected, Stay::query()->firstOrFail()->nights);
    }

    /** @return array<string, array{string, string, int}> */
    public static function nightRanges(): array
    {
        return [
            'bir gece' => ['2026-09-07', '2026-09-08', 1],
            'üç gece' => ['2026-09-07', '2026-09-10', 3],
            'ay geçişi' => ['2026-09-29', '2026-10-02', 3],
            'yıl geçişi' => ['2026-12-30', '2027-01-02', 3],
        ];
    }

    /**
     * Aynı gün giriş/çıkış artık KABUL EDİLMEZ; asgari gece sayısı 1'dir.
     * Günübirlik kullanım ileride ayrı bir iş kuralı olarak ele alınacaktır.
     */
    #[Test]
    public function ayni_gun_giris_cikis_reddedilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->post(
            route('stays.store'),
            $this->payload(['check_in_date' => '2026-09-07', 'check_out_date' => '2026-09-07']),
        )->assertSessionHasErrors('check_out_date');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function cikis_tarihi_giristen_once_olamaz(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->post(
            route('stays.store'),
            $this->payload(['check_in_date' => '2026-09-10', 'check_out_date' => '2026-09-07']),
        )->assertSessionHasErrors('check_out_date');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function gecersiz_tarih_araligi_veritabani_duzeyinde_de_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stays')->insert([
            'company_id' => $this->company->id,
            'guest_name' => 'Ters Tarih',
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-07',
            'nights' => 0,
            'amount' => '100.00',
            'currency' => 'TRY',
            'status' => 'active',
            'created_by' => $this->userWithRole(RoleName::Admin)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function gecersiz_tutar_reddedilir(string $amount): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('stays.store'), $this->payload(['amount' => $amount]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Stay::query()->count());
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'sıfır' => ['0'],
            'sıfır ondalıklı' => ['0,00'],
            'negatif' => ['-100'],
            'üç ondalık' => ['100,123'],
            'metin' => ['bin lira'],
            'boş' => [''],
        ];
    }

    #[Test]
    public function negatif_tutar_veritabani_duzeyinde_de_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stays')->insert([
            'company_id' => $this->company->id,
            'guest_name' => 'Negatif',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'nights' => 3,
            'amount' => '-100.00',
            'currency' => 'TRY',
            'status' => 'active',
            'created_by' => $this->userWithRole(RoleName::Admin)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function desteklenmeyen_para_birimi_reddedilir(): void
    {
        // 'USD' Gün 9.6'dan itibaren desteklenir (Currency enum); reddedilmesi
        // gereken kod, enum kümesinin dışında kalan gerçek bir örnek olmalı.
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('stays.store'), $this->payload(['currency' => 'CHF']))
            ->assertSessionHasErrors('currency');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function desteklenen_uc_para_birimi_kabul_edilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        foreach (['TRY', 'EUR', 'GBP'] as $currency) {
            // TRY dışındaki para birimlerinde kur zorunludur (v2.1.1-C §2).
            $overrides = ['currency' => $currency];
            if ($currency !== 'TRY') {
                $overrides['exchange_rate_to_try'] = '32,50';
            }

            $this->actingAs($admin)
                ->post(route('stays.store'), $this->payload($overrides))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Stay::query()->count());
    }

    #[Test]
    public function zorunlu_alanlar_eksikse_kayit_olusmaz(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('stays.store'), [])
            ->assertSessionHasErrors(['guest_name', 'check_in_date', 'check_out_date', 'amount', 'currency']);

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function silinmis_firmaya_konaklama_eklenemez(): void
    {
        $this->company->delete();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('stays.store'), $this->payload())
            ->assertSessionHasErrors('company_id');
    }

    #[Test]
    public function durum_kullanicidan_alinmaz(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('stays.store'), $this->payload(['status' => 'cancelled']));

        $this->assertSame(StayStatus::Active, Stay::query()->firstOrFail()->status);
    }

    #[Test]
    public function hard_delete_ucu_yoktur(): void
    {
        $this->assertTrue(Schema::hasColumn('stays', 'deleted_at'));

        $deleteRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'stays')
                && in_array('DELETE', $route->methods(), true));

        $this->assertCount(0, $deleteRoutes, 'Konaklama silinmez, iptal edilir');
    }
}
