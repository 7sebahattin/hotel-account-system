<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use DOMDocument;
use DOMElement;
use DOMNode;
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
            ->assertSee('Garanti BBVA - GBP - £')
            ->assertSee('Yapı Kredi - EUR - €')
            ->assertSee('11.418,00')
            ->assertSee('≈ 740.755,02')
            ->assertSee('AY TOPLAMI')
            ->assertSee('table-layout: auto', false)
            ->assertDontSee('table-layout: fixed', false)
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
    public function aylik_liste_ve_print_ozet_kartlari_ayni_renkli_vurgulari_kullanir(): void
    {
        $this->createDay($this->admin, [
            'account_date' => '2026-09-14',
            'daily_total' => '1.000,00',
            'items' => [$this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'amount' => '750,00'])],
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('monthly-account.index', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]))->assertOk();

        $indexDom = new DOMDocument;
        @$indexDom->loadHTML('<?xml encoding="UTF-8">'.(string) $indexResponse->getContent());
        $indexXpath = new DOMXPath($indexDom);
        $daily = $indexXpath->query('//*[@data-monthly-summary="daily"]');
        $receipt = $indexXpath->query('//*[@data-monthly-summary="receipt"]');
        $difference = $indexXpath->query('//*[@data-monthly-summary="difference"]');

        $this->assertNotFalse($daily);
        $this->assertNotFalse($receipt);
        $this->assertNotFalse($difference);
        $this->assertSame(1, $daily->length);
        $this->assertSame(1, $receipt->length);
        $this->assertSame(1, $difference->length);
        $this->assertStringContainsString('bg-info-bg', $daily->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString('border-t-nav-well', $daily->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString('bg-success-bg', $receipt->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString('border-t-success', $receipt->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString('bg-acc-bg', $difference->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString('border-t-acc-tx', $difference->item(0)->attributes->getNamedItem('class')?->nodeValue ?? '');

        $printResponse = $this->get(route('monthly-account.print', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]))->assertOk();

        $printDom = new DOMDocument;
        @$printDom->loadHTML('<?xml encoding="UTF-8">'.(string) $printResponse->getContent());
        $printXpath = new DOMXPath($printDom);

        $this->assertSame(1, $printXpath->query('//div[contains(@class, "summary-daily")]')->length);
        $this->assertSame(1, $printXpath->query('//div[contains(@class, "summary-receipt")]')->length);
        $this->assertSame(1, $printXpath->query('//div[contains(@class, "summary-difference")]')->length);
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
        $groupHeaders = $xpath->query('//main[contains(@class, "report")]//table/thead/tr[1]/th[@scope="colgroup"]');
        $paymentHeaders = $xpath->query('//main[contains(@class, "report")]//table/thead/tr[contains(@class, "payment-headers")]/th');
        $columnDefinitions = $xpath->query('//main[contains(@class, "report")]//table/colgroup/col');

        $this->assertNotFalse($tables);
        $this->assertSame(1, $tables->length);
        $table = $tables->item(0);
        $this->assertInstanceOf(DOMElement::class, $table);
        $this->assertStringContainsString('print-cols-very-dense', $table->getAttribute('class'));
        $this->assertSame('17', $table->getAttribute('data-column-count'));
        $this->assertNotFalse($columnDefinitions);
        $this->assertSame(17, $columnDefinitions->length);
        $this->assertNotFalse($rowHeaders);
        $this->assertSame(4, $rowHeaders->length);
        $this->assertStringStartsWith('01 Eylül', trim($rowHeaders->item(0)->textContent));
        $this->assertStringStartsWith('02 Eylül', trim($rowHeaders->item(1)->textContent));
        $this->assertStringStartsWith('03 Eylül', trim($rowHeaders->item(2)->textContent));
        $this->assertSame('AY TOPLAMI', trim($rowHeaders->item(3)->textContent));

        $this->assertNotFalse($groupHeaders);
        $this->assertSame(
            ['Nakit', 'Kredi Kartı', 'Link Ödeme', 'Havale', 'Ekstra'],
            array_map(static fn (DOMNode $header): string => trim($header->textContent), iterator_to_array($groupHeaders)),
        );
        $this->assertNotFalse($paymentHeaders);
        $paymentHeaderText = implode(' ', array_map(
            static fn (DOMNode $header): string => preg_replace('/\s+/u', ' ', trim($header->textContent)) ?? '',
            iterator_to_array($paymentHeaders),
        ));
        $this->assertStringNotContainsString('POS', $paymentHeaderText);
        $this->assertStringNotContainsString('Link Ödeme', $paymentHeaderText);
        $this->assertStringNotContainsString('Havale', $paymentHeaderText);
        $this->assertStringContainsString('Garanti BBVA - TRY - ₺', $paymentHeaderText);
        $this->assertStringContainsString('Ziraat Bankası - TRY - ₺', $paymentHeaderText);
    }

    #[Test]
    public function print_raporu_odeme_tl_karsiliginda_yontem_kirimlarini_renkli_grup_basliklarini_ve_ortali_rapor_basligini_gosterir(): void
    {
        $this->createDay($this->admin, [
            'account_date' => '2026-09-14',
            'daily_total' => '1.000,00',
            'items' => [
                $this->item(['method' => 'cash', 'amount' => '10,00']),
                $this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'amount' => '100,00']),
                $this->item(['method' => 'link', 'bank_name' => 'Yapı Kredi', 'amount' => '200,00']),
                $this->item(['method' => 'bank_transfer', 'bank_name' => 'Garanti BBVA', 'amount' => '300,00']),
                $this->item(['method' => 'extra', 'amount' => '40,00']),
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(route('monthly-account.print', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);

        $titleRows = $xpath->query('//header[contains(@class, "report-head")]/div[contains(@class, "report-title-row")]');
        $title = $xpath->query('//header[contains(@class, "report-head")]//h1');
        $creditCardHeaders = $xpath->query('//th[contains(@class, "group-head-credit-card")]');
        $linkHeaders = $xpath->query('//th[contains(@class, "group-head-link")]');
        $breakdowns = $xpath->query('//td[contains(@class, "receipt-total-cell")]/div[contains(@class, "receipt-breakdown")]');

        $this->assertNotFalse($titleRows);
        $this->assertSame(1, $titleRows->length);
        $this->assertNotFalse($title);
        $this->assertSame('AYLIK HESAP RAPORU', trim($title->item(0)->textContent));
        $this->assertNotFalse($creditCardHeaders);
        $this->assertSame(1, $creditCardHeaders->length);
        $this->assertSame('Kredi Kartı', trim($creditCardHeaders->item(0)->textContent));
        $this->assertNotFalse($linkHeaders);
        $this->assertSame(1, $linkHeaders->length);
        $this->assertSame('Link Ödeme', trim($linkHeaders->item(0)->textContent));
        $this->assertNotFalse($breakdowns);
        $this->assertSame(2, $breakdowns->length);

        foreach ($breakdowns as $breakdown) {
            $text = preg_replace('/\s+/u', ' ', trim($breakdown->textContent)) ?? '';
            $this->assertStringContainsString('Kredi Kartı:100,00 ₺', $text);
            $this->assertStringContainsString('Link Ödeme:200,00 ₺', $text);
            $this->assertStringContainsString('Havale:300,00 ₺', $text);
            $this->assertStringContainsString('Ekstra:40,00 ₺', $text);
        }
    }

    #[Test]
    public function print_raporu_legacy_banka_basliklarindaki_tekrarli_yontem_ve_para_birimi_etiketlerini_temizler(): void
    {
        $legacyProviders = [
            'Garanti BBVA - POS - TRY - ₺',
            'Ziraat Bankası - LİNK ÖDEME - EUR - €',
            'Garanti BBVA - HAVALE - TRY - ₺',
        ];
        MonthlyPaymentProvider::query()->insert(array_map(
            fn (string $name): array => [
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $legacyProviders,
        ));

        $this->createDay($this->admin, [
            'account_date' => '2026-09-14',
            'daily_total' => '1.000,00',
            'items' => [
                $this->item([
                    'method' => 'credit_card',
                    'bank_name' => 'Garanti BBVA - POS - TRY - ₺',
                    'amount' => '100,00',
                ]),
                $this->item([
                    'method' => 'link',
                    'bank_name' => 'Ziraat Bankası - LİNK ÖDEME - EUR - €',
                    'currency' => 'EUR',
                    'amount' => '100,00',
                    'conversion_mode' => 'total',
                    'try_equivalent' => '5.000,00',
                ]),
                $this->item([
                    'method' => 'bank_transfer',
                    'bank_name' => 'Garanti BBVA - HAVALE - TRY - ₺',
                    'amount' => '100,00',
                ]),
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(route('monthly-account.print', [
            'owner_id' => $this->admin->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $paymentHeaders = $xpath->query('//main[contains(@class, "report")]//table/thead/tr[contains(@class, "payment-headers")]/th');

        $this->assertNotFalse($paymentHeaders);
        $headerTexts = array_map(
            static fn (DOMNode $header): string => preg_replace('/\s+/u', ' ', trim($header->textContent)) ?? '',
            iterator_to_array($paymentHeaders),
        );
        $joinedHeaders = implode(' | ', $headerTexts);

        $this->assertContains('Garanti BBVA - TRY - ₺', $headerTexts);
        $this->assertContains('Ziraat Bankası - EUR - €', $headerTexts);
        $this->assertStringNotContainsString('POS', $joinedHeaders);
        $this->assertStringNotContainsString('LİNK ÖDEME', $joinedHeaders);
        $this->assertStringNotContainsString('HAVALE', $joinedHeaders);
        $this->assertStringNotContainsString('TRY - ₺TRY', $joinedHeaders);
        $this->assertStringNotContainsString('EUR - €EUR', $joinedHeaders);
    }

    #[Test]
    public function gunluk_print_raporu_gercek_view_renderinda_tum_rapor_bolumlerini_gosterir(): void
    {
        $day = $this->createDay($this->admin, [
            'account_date' => '2026-09-13',
            'daily_total' => '125.087,98',
            'note' => 'Gün sonu kontrol notu',
            'items' => [
                $this->item(['method' => 'cash', 'amount' => '68.264,19', 'bank_name' => null, 'note' => 'Nakit tahsilat']),
                $this->item(['method' => 'credit_card', 'bank_name' => 'Ziraat Bankası', 'currency' => 'EUR', 'amount' => '260,00', 'conversion_mode' => 'total', 'try_equivalent' => '14.605,50', 'note' => 'Kart tahsilatı']),
            ],
        ]);

        $this->actingAs($this->admin)->get(route('monthly-account.print-day', $day))
            ->assertOk()
            ->assertSee('GÜNLÜK HESAP RAPORU')
            ->assertSee('13 Eylül 2026')
            ->assertSee('Günlük Liste Toplamı')
            ->assertSee('125.087,98')
            ->assertSee('Alınan Ödemeler · TL Karşılığı')
            ->assertSee('82.869,69')
            ->assertSee('Banka ve ödeme kuruluşu toplamları')
            ->assertSee('Ziraat Bankası')
            ->assertSee('260,00')
            ->assertSee('14.605,50')
            ->assertSee('Kur:')
            ->assertSee('Ödeme ayrıntıları (2)')
            ->assertSee('Nakit tahsilat')
            ->assertSee('Kart tahsilatı')
            ->assertSee('Gün sonu kontrol notu')
            ->assertSee('Kayıt geçmişi')
            ->assertSee('Sürüm 1')
            ->assertDontSee('sidebar', false)
            ->assertDontSee('Günü sil')
            ->assertDontSee('Düzenle');
    }

    #[Test]
    public function gunluk_print_nakit_not_yokken_ve_silinmis_gunde_500_uretmeden_render_eder(): void
    {
        $cashDay = $this->createDay($this->admin, [
            'account_date' => '2026-09-13',
            'note' => null,
            'items' => [$this->item(['method' => 'cash', 'bank_name' => null, 'note' => null])],
        ]);
        $deletedDay = $this->createDay($this->admin, ['account_date' => '2026-09-14']);
        $this->delete(route('monthly-account.destroy', $deletedDay), ['version' => 1])->assertRedirect();

        $this->actingAs($this->admin)->get(route('monthly-account.print-day', $cashDay))
            ->assertOk()
            ->assertSee('Ödeme ayrıntıları (1)')
            ->assertDontSee('Banka ve ödeme kuruluşu toplamları')
            ->assertDontSee('Günlük not');
        $this->get(route('monthly-account.print-day', $deletedDay->fresh()))
            ->assertOk()
            ->assertSee('Silinmiş kayıt');
    }

    #[Test]
    public function kullanici_baska_ownerin_gunluk_print_endpointinden_veri_goremez(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $intruder = $this->userWithRole(RoleName::Personel);
        $day = $this->createDay($owner);

        $this->actingAs($intruder)->get(route('monthly-account.print-day', $day))->assertForbidden();
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
