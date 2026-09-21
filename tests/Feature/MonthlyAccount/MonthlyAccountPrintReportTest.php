<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use DOMDocument;
use DOMXPath;
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
            ->assertSee(route('monthly-account.print', ['owner_id' => $owner->id, 'month' => '2026-09']))
            ->assertSee('target="_blank"', false);
    }

    #[Test]
    public function print_raporu_gunleri_artarak_tek_tabloda_ve_ay_toplamini_sonda_gosterir(): void
    {
        $providers = ['Garanti BBVA', 'İş Bankası', 'Yapı Kredi', 'Ziraat Bankası'];
        $items = [
            $this->item(['method' => 'cash', 'amount' => '100,00']),
            $this->item(['method' => 'extra', 'amount' => '100,00']),
        ];

        foreach (['credit_card', 'link', 'bank_transfer'] as $method) {
            foreach ($providers as $provider) {
                $items[] = $this->item([
                    'method' => $method,
                    'bank_name' => $provider,
                    'amount' => '100,00',
                ]);
            }
        }

        $this->createDay($this->admin, [
            'account_date' => '2026-09-01',
            'daily_total' => '1.000,00',
            'items' => $items,
        ]);

        foreach ([2, 3] as $day) {
            $this->createDay($this->admin, [
                'account_date' => sprintf('2026-09-%02d', $day),
                'daily_total' => '1.000,00',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('monthly-account.print', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]));

        $response->assertOk()
            ->assertDontSee('Bölüm 1 /', false)
            ->assertDontSee('Bölüm 2 /', false);

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//main[contains(@class, "report")]//table');
        $rowHeaders = $xpath->query('//main[contains(@class, "report")]//table/tbody/tr/th[@scope="row"]');

        $this->assertNotFalse($tables);
        $this->assertSame(1, $tables->length);
        $this->assertNotFalse($rowHeaders);
        $this->assertSame(4, $rowHeaders->length);
        $this->assertStringStartsWith('01 Eylül', trim($rowHeaders->item(0)->textContent));
        $this->assertStringStartsWith('02 Eylül', trim($rowHeaders->item(1)->textContent));
        $this->assertStringStartsWith('03 Eylül', trim($rowHeaders->item(2)->textContent));
        $this->assertSame('AY TOPLAMI', trim($rowHeaders->item(3)->textContent));
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
