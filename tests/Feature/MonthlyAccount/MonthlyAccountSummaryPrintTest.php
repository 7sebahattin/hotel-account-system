<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class MonthlyAccountSummaryPrintTest extends MonthlyAccountTestCase
{
    #[Test]
    public function ozet_rapor_oturum_ve_aylik_hesap_yetkisi_ister(): void
    {
        $url = route('monthly-account.print-summary', ['owner_id' => $this->admin->id, 'month' => '2026-09']);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $owner = $this->userWithRole(RoleName::Personel);
        $intruder = $this->userWithRole(RoleName::Personel);
        $this->createDay($owner);
        $this->actingAs($intruder)->get(route('monthly-account.print-summary', [
            'owner_id' => $owner->id,
            'month' => '2026-09',
        ]))->assertForbidden();
    }

    #[Test]
    public function tam_rapordaki_buton_secili_owner_ve_ayi_ozet_rapora_tasir(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $params = ['owner_id' => $owner->id, 'month' => '2026-09'];
        $this->createDay($owner);

        $full = $this->actingAs($this->admin)->get(route('monthly-account.print', $params))->assertOk();
        $full->assertSee('Özet Yazdır')
            ->assertSee(route('monthly-account.print-summary', $params));
        $full->assertSee('AY TOPLAMI')->assertSee('14 Eylül');

        $summary = $this->get(route('monthly-account.print-summary', $params))->assertOk();
        $summary->assertSee('AYLIK HESAP ÖZETİ')->assertSee($owner->name)->assertSee('Eylül 2026')
            ->assertSee(route('monthly-account.print', $params))
            ->assertSee('window.print()', false);
    }

    #[Test]
    public function ozet_yalniz_secili_owner_ve_ayin_toplamlarini_gosterir_gunluk_satir_icermez(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $other = $this->userWithRole(RoleName::Personel);
        $this->createDay($owner, [
            'account_date' => '2026-09-01', 'daily_total' => '111,11',
            'items' => [$this->item(['amount' => '111,11'])],
        ]);
        $this->createDay($owner, [
            'account_date' => '2026-09-02', 'daily_total' => '222,22',
            'items' => [$this->item(['amount' => '222,22'])],
        ]);
        $this->createDay($owner, [
            'account_date' => '2026-10-01', 'daily_total' => '999,99',
            'items' => [$this->item(['amount' => '999,99'])],
        ]);
        $this->createDay($other, [
            'account_date' => '2026-09-01', 'daily_total' => '444,44',
            'items' => [$this->item(['amount' => '444,44'])],
        ]);

        $response = $this->actingAs($this->admin)->get(route('monthly-account.print-summary', [
            'owner_id' => $owner->id, 'month' => '2026-09',
        ]))->assertOk();

        $this->assertSame($owner->id, $response->viewData('owner')->id);
        $this->assertSame('2026-09', $response->viewData('month')->format('Y-m'));
        $this->assertSame('333.33', $response->viewData('summary')['daily_total']->toDecimal());
        $response->assertSee('333,33 ₺')->assertDontSee('111,11 ₺')->assertDontSee('222,22 ₺')
            ->assertDontSee('444,44 ₺')->assertDontSee('999,99 ₺')->assertDontSee($other->name)
            ->assertDontSee('01 Eylül')->assertDontSee('02 Eylül')->assertDontSee('01 Ekim');
    }

    #[Test]
    public function tam_rapor_ay_toplami_ile_ozet_tum_para_birimi_ve_odeme_kanallarinda_birebir_aynidir(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $this->createDay($owner, [
            'account_date' => '2026-09-01', 'daily_total' => '1.000,00',
            'items' => [
                $this->item(['method' => 'cash', 'amount' => '125,25']),
                $this->item(['method' => 'cash', 'currency' => 'EUR', 'amount' => '10,50', 'conversion_mode' => 'total', 'try_equivalent' => '500,50']),
                $this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'currency' => 'GBP', 'amount' => '3,25', 'conversion_mode' => 'total', 'try_equivalent' => '150,75']),
                $this->item(['method' => 'link', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '7,75', 'conversion_mode' => 'total', 'try_equivalent' => '300,25']),
                $this->item(['method' => 'bank_transfer', 'bank_name' => 'Ziraat Bankası', 'amount' => '80,80']),
                $this->item(['method' => 'extra', 'amount' => '9,90']),
            ],
        ]);
        $this->createDay($owner, [
            'account_date' => '2026-09-02', 'daily_total' => '2.000,00',
            'items' => [
                $this->item(['method' => 'cash', 'amount' => '74,75']),
                $this->item(['method' => 'cash', 'currency' => 'EUR', 'amount' => '4,50', 'conversion_mode' => 'total', 'try_equivalent' => '220,50']),
                $this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'currency' => 'GBP', 'amount' => '1,75', 'conversion_mode' => 'total', 'try_equivalent' => '85,25']),
                $this->item(['method' => 'link', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '2,25', 'conversion_mode' => 'total', 'try_equivalent' => '90,75']),
                $this->item(['method' => 'bank_transfer', 'bank_name' => 'Ziraat Bankası', 'amount' => '19,20']),
                $this->item(['method' => 'extra', 'amount' => '0,10']),
            ],
        ]);

        $params = ['owner_id' => $owner->id, 'month' => '2026-09'];
        $full = $this->actingAs($this->admin)->get(route('monthly-account.print', $params))->assertOk();
        $summary = $this->get(route('monthly-account.print-summary', $params))->assertOk();
        $fullTotals = $full->viewData('summary');
        $summaryTotals = $summary->viewData('summary');

        $this->assertMoneyEqual($fullTotals['daily_total'], $summaryTotals['daily_total']);
        $this->assertMoneyEqual($fullTotals['receipt_total'], $summaryTotals['receipt_total']);
        $this->assertMoneyEqual($fullTotals['difference'], $summaryTotals['difference']);

        foreach ($fullTotals['methods'] as $method => $currencies) {
            $this->assertSame(array_keys($currencies), array_keys($summaryTotals['methods'][$method]));
            foreach ($currencies as $currency => $amounts) {
                $this->assertAmountGroupEqual($amounts, $summaryTotals['methods'][$method][$currency]);
            }
        }
        $this->assertSame(array_keys($fullTotals['banks']), array_keys($summaryTotals['banks']));
        foreach ($fullTotals['banks'] as $method => $banks) {
            $this->assertSame(array_keys($banks), array_keys($summaryTotals['banks'][$method]));
            foreach ($banks as $bank => $currencies) {
                $this->assertSame(array_keys($currencies), array_keys($summaryTotals['banks'][$method][$bank]));
                foreach ($currencies as $currency => $amounts) {
                    $this->assertAmountGroupEqual($amounts, $summaryTotals['banks'][$method][$bank][$currency]);
                }
            }
        }

        $fullTotalRow = $this->fullTotalRow($full);
        foreach (['3.000,00 ₺', '200,00 ₺', '15,00 €', '5,00 £', '10,00 $', '100,00 ₺', '10,00 ₺',
            '≈ 721,00 ₺', '≈ 236,00 ₺', '≈ 391,00 ₺'] as $displayedAmount) {
            $this->assertStringContainsString($displayedAmount, $fullTotalRow);
            $summary->assertSee($displayedAmount);
        }
        foreach (['Nakit', 'Kredi Kartı', 'Link Ödeme', 'Havale', 'Ekstra', 'Garanti BBVA', 'Yapı Kredi', 'Ziraat Bankası'] as $label) {
            $summary->assertSee($label);
        }
        $summaryXPath = $this->xpath($summary);
        $this->assertSame(1, $summaryXPath->query('//*[@data-summary-daily-total]')->length);
        $this->assertSame(0, $summaryXPath->query('//*[@data-summary-method="cash"][@data-summary-bank]')->length);
        foreach ([
            ['cash', null, 'TRY', '200,00 ₺', null],
            ['cash', null, 'EUR', '15,00 €', '≈ 721,00 ₺'],
            ['credit_card', 'Garanti BBVA', 'GBP', '5,00 £', '≈ 236,00 ₺'],
            ['link', 'Yapı Kredi', 'USD', '10,00 $', '≈ 391,00 ₺'],
            ['bank_transfer', 'Ziraat Bankası', 'TRY', '100,00 ₺', null],
            ['extra', null, 'TRY', '10,00 ₺', null],
        ] as [$method, $bank, $currency, $primary, $equivalent]) {
            $query = '//*[@data-summary-method="'.$method.'"][@data-summary-currency="'.$currency.'"]';
            $query .= $bank === null ? '[not(@data-summary-bank)]' : '[@data-summary-bank="'.$bank.'"]';
            $rows = $summaryXPath->query($query);
            $this->assertSame(1, $rows->length, $query);
            $rowText = preg_replace('/\s+/u', ' ', trim($rows->item(0)->textContent)) ?? '';
            $this->assertStringContainsString($primary, $rowText);
            if ($equivalent !== null) {
                $this->assertStringContainsString($equivalent, $rowText);
            }
        }
        $this->assertSame(6, $summaryXPath->query('//*[@data-summary-method][@data-summary-currency]')->length);
        $receiptRows = $summaryXPath->query('//*[@data-summary-receipt-breakdown="month"]');
        $this->assertSame(1, $receiptRows->length);
        $receiptText = preg_replace('/\s+/u', ' ', trim($receiptRows->item(0)->textContent)) ?? '';
        foreach (['Nakit: 921,00 ₺', 'Kredi kartı: 236,00 ₺', 'Link ödeme: 391,00 ₺',
            'Havale: 100,00 ₺', 'Ekstra: 10,00 ₺', 'Toplam: 1.658,00 ₺'] as $channelTotal) {
            $this->assertStringContainsString($channelTotal, $receiptText);
            $this->assertStringContainsString($channelTotal, $fullTotalRow);
        }
        $summary->assertDontSee('01 Eylül')->assertDontSee('02 Eylül');
    }

    #[Test]
    public function bos_ve_sifirlik_sutun_davranisi_tam_raporla_uyumludur(): void
    {
        $this->createDay($this->admin, [
            'items' => [$this->item(['method' => 'cash', 'amount' => '3.000,00'])],
        ]);
        $params = ['owner_id' => $this->admin->id, 'month' => '2026-09'];
        $full = $this->actingAs($this->admin)->get(route('monthly-account.print', $params))->assertOk();
        $summary = $this->get(route('monthly-account.print-summary', $params))->assertOk();

        $this->assertSame(array_keys($full->viewData('summary')['methods']), array_keys($summary->viewData('summary')['methods']));
        foreach (['Kredi Kartı', 'Link Ödeme', 'Havale', 'Ekstra'] as $label) {
            $full->assertSee($label);
            $summary->assertSee($label);
        }
        $summary->assertSee('3.000,00 ₺');
    }

    #[Test]
    public function legacy_banka_adindaki_odeme_yontemi_ve_para_birimi_tekrar_edilmez(): void
    {
        $legacyBank = 'Garanti BBVA - POS - EUR - €';
        MonthlyPaymentProvider::query()->create(['name' => $legacyBank, 'is_active' => true]);
        $this->createDay($this->admin, [
            'items' => [$this->item([
                'method' => 'credit_card', 'bank_name' => $legacyBank, 'currency' => 'EUR',
                'amount' => '25,00', 'conversion_mode' => 'total', 'try_equivalent' => '1.000,00',
            ])],
        ]);
        $params = ['owner_id' => $this->admin->id, 'month' => '2026-09'];
        $full = $this->actingAs($this->admin)->get(route('monthly-account.print', $params))->assertOk();
        $summary = $this->get(route('monthly-account.print-summary', $params))->assertOk();

        $this->assertSame('25.00', $full->viewData('summary')['banks']['credit_card'][$legacyBank]['EUR']['amount']->toDecimal());
        $this->assertSame('25.00', $summary->viewData('summary')['banks']['credit_card'][$legacyBank]['EUR']['amount']->toDecimal());
        $fullHeader = $this->xpath($full)->query('//th[contains(@class, "payment-head")]/span[contains(@class, "provider-name")]')->item(0);
        $this->assertNotNull($fullHeader);
        $this->assertSame('Garanti BBVA - EUR - €', trim($fullHeader->textContent));

        $label = $this->xpath($summary)->query('//*[@data-summary-method="credit_card"][@data-summary-currency="EUR"]/span[contains(@class, "amount-label")]')->item(0);
        $this->assertNotNull($label);
        $this->assertSame('Garanti BBVA - EUR', trim($label->textContent));
        $summary->assertSee('25,00 €')->assertSee('≈ 1.000,00 ₺');
    }

    private function assertMoneyEqual(Money $expected, Money $actual): void
    {
        $this->assertSame($expected->minorUnits, $actual->minorUnits);
    }

    /**
     * @param  array{amount: Money, try_equivalent: Money}  $expected
     * @param  array{amount: Money, try_equivalent: Money}  $actual
     */
    private function assertAmountGroupEqual(array $expected, array $actual): void
    {
        $this->assertMoneyEqual($expected['amount'], $actual['amount']);
        $this->assertMoneyEqual($expected['try_equivalent'], $actual['try_equivalent']);
    }

    private function fullTotalRow(TestResponse $response): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $row = $xpath->query('//tr[contains(@class, "total-row")]')->item(0);
        $this->assertNotNull($row);

        return preg_replace('/\s+/u', ' ', trim($row->textContent)) ?? '';
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());

        return new DOMXPath($dom);
    }
}
