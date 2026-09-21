<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use PHPUnit\Framework\Attributes\Test;

class MonthlyAccountPrintReportTest extends MonthlyAccountTestCase
{
    #[Test]
    public function yetkili_kullanici_aylik_print_raporunu_ozet_ve_dinamik_banka_bilgileriyle_gorur(): void
    {
        $this->createDay($this->admin, [
            'account_date' => '2026-09-14',
            'daily_total' => '1.887.094,80',
            'items' => [
                $this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'amount' => '11.418,00', 'currency' => 'GBP', 'conversion_mode' => 'total', 'try_equivalent' => '740.755,02']),
                $this->item(['method' => 'link', 'bank_name' => 'Yapı Kredi', 'amount' => '1.975,00', 'currency' => 'EUR', 'conversion_mode' => 'total', 'try_equivalent' => '109.268,52']),
            ],
        ]);

        $this->actingAs($this->admin)->get(route('monthly-account.print', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]))->assertOk()
            ->assertSee('AYLIK HESAP RAPORU')
            ->assertSee('Eylül 2026')
            ->assertSee('Kayıtlı gün')
            ->assertSee('1.887.094,80')
            ->assertSee('850.023,54')
            ->assertSee('-1.037.071,26')
            ->assertSee('Garanti BBVA')
            ->assertSee('Yapı Kredi')
            ->assertSee('11.418,00')
            ->assertSee('≈ 740.755,02')
            ->assertSee('AY TOPLAMI')
            ->assertDontSee('sidebar', false)
            ->assertDontSee('bottom-nav', false)
            ->assertDontSee('+ Günlük kayıt');
    }

    #[Test]
    public function index_yazdir_baglantisi_secili_owner_ve_ayi_korur(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $this->actingAs($this->admin)->get(route('monthly-account.index', [
            'owner_id' => $owner->id,
            'month' => '2026-09',
        ]))->assertOk()
            ->assertSee(route('monthly-account.print', ['owner_id' => $owner->id, 'month' => '2026-09']), false)
            ->assertSee('target="_blank"', false);
    }

    #[Test]
    public function kullanici_baska_ownerin_print_endpointinden_veri_goremez(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $intruder = $this->userWithRole(RoleName::Personel);
        $this->createDay($owner, ['daily_total' => '9000']);

        $this->actingAs($intruder)->get(route('monthly-account.print', [
            'owner_id' => $owner->id,
            'month' => '2026-09',
        ]))->assertForbidden();
    }
}
