<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\CannotUpdateCancelledStay;
use App\Modules\Stays\Exceptions\StayAlreadyCancelled;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private StayService $stays;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create();
        $this->stays = app(StayService::class);
    }

    private function makeStay(): Stay
    {
        return $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->userWithRole(RoleName::Admin));
    }

    private function balance(): string
    {
        return app(LedgerService::class)->balanceForCompany($this->company->id, Currency::TRY)->toDecimal();
    }

    #[Test]
    public function iptal_ters_kayit_uretir_ve_bakiyeyi_sifirlar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->actingAs($admin)
            ->patch(route('stays.cancel', $stay))
            ->assertRedirect(route('stays.show', $stay));

        $stay->refresh();

        $this->assertSame(StayStatus::Cancelled, $stay->status);
        $this->assertSame('0.00', $this->balance());
        $this->assertSame(2, LedgerEntry::query()->count(), 'Orijinal hareket silinmez, ters kayıt eklenir');

        $reversal = LedgerEntry::query()->where('is_reversal', true)->firstOrFail();
        $this->assertSame('9000.00', $reversal->credit);
        $this->assertSame('stay', $reversal->source_type);
        $this->assertSame($stay->id, $reversal->source_id);
    }

    #[Test]
    public function iptal_sonrasi_gecerli_hareket_kalmaz(): void
    {
        $stay = $this->makeStay();

        $this->stays->cancel($stay, $this->userWithRole(RoleName::Admin));

        $active = app(LedgerService::class)
            ->activeEntriesForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());

        $this->assertCount(0, $active, 'İptal edilmiş konaklamanın geçerli hareketi olmaz');
    }

    #[Test]
    public function ayni_konaklama_ikinci_kez_iptal_edilemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->stays->cancel($stay, $admin);

        $this->assertThrows(
            fn () => $this->stays->cancel($stay->fresh(), $admin),
            StayAlreadyCancelled::class,
        );

        $this->assertSame(2, LedgerEntry::query()->count());
    }

    #[Test]
    public function ikinci_iptal_http_uzerinden_de_engellenir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->actingAs($admin)->patch(route('stays.cancel', $stay));

        $this->actingAs($admin)
            ->patch(route('stays.cancel', $stay->fresh()))
            ->assertForbidden();

        $this->assertSame(2, LedgerEntry::query()->count());
    }

    #[Test]
    public function iptal_edilmis_konaklama_duzenlenemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);

        $this->assertThrows(
            fn () => $this->stays->update($stay->fresh(), [
                'company_id' => $this->company->id,
                'guest_name' => 'Değişti',
                'check_in_date' => '2026-09-07',
                'check_out_date' => '2026-09-10',
                'amount' => '9500.00',
                'currency' => 'TRY',
            ], $admin),
            CannotUpdateCancelledStay::class,
        );

        $this->assertSame('John Smith', $stay->fresh()->guest_name);
    }

    #[Test]
    public function iptal_edilmis_konaklamanin_duzenleme_ekrani_acilmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);

        $this->actingAs($admin)->get(route('stays.edit', $stay))->assertForbidden();

        $this->actingAs($admin)->put(route('stays.update', $stay), [
            'company_id' => $this->company->id,
            'guest_name' => 'Değişti',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9500.00',
            'currency' => 'TRY',
        ])->assertForbidden();
    }

    #[Test]
    public function iptal_edilmis_konaklama_listede_ve_detayda_gorunur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);

        $this->actingAs($admin)->get(route('stays.index'))->assertOk()->assertSee('John Smith');
        $this->actingAs($admin)->get(route('stays.show', $stay))->assertOk()->assertSee('iptal edilmiştir');
    }

    #[Test]
    public function iptali_geri_alma_ucu_yoktur(): void
    {
        $restoreRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'stays')
                && str_contains((string) $route->getName(), 'restore'));

        $this->assertCount(0, $restoreRoutes, 'V1de iptal geri alınamaz');
    }

    #[Test]
    public function iptal_edilmis_konaklama_yeni_hareket_uretmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->stays->cancel($stay, $admin);
        $countAfterCancel = LedgerEntry::query()->count();

        // İkinci iptal denemesi hiçbir hareket üretmemeli.
        try {
            $this->stays->cancel($stay->fresh(), $admin);
        } catch (StayAlreadyCancelled) {
            // beklenen
        }

        $this->assertSame($countAfterCancel, LedgerEntry::query()->count());
        $this->assertSame('0.00', $this->balance());
    }
}
