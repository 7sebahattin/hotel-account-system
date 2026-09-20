<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

class MonthlyAccountPageTest extends MonthlyAccountTestCase
{
    #[Test]
    public function bos_ayda_gun_satiri_gosterilmez(): void
    {
        $response = $this->actingAs($this->admin)->get(route('monthly-account.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('Ay toplamı')
            ->assertSee('Bu ay için kayıt yok.')
            ->assertDontSee('Hesap sahibi')
            ->assertDontSee('Her satır bir gündür.');
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " monthly-table ")]/tbody/tr[@data-monthly-date]');

        $this->assertNotFalse($rows);
        $this->assertSame(0, $rows->length);
    }

    #[Test]
    public function kayit_araliginin_disindaki_bos_gunler_gizlenir_aradaki_bos_gun_korunur(): void
    {
        $this->createDay($this->admin, ['account_date' => '2026-09-22']);
        $this->createDay($this->admin, ['account_date' => '2026-09-24']);

        $response = $this->get(route('monthly-account.index', ['month' => '2026-09']))->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " monthly-table ")]/tbody/tr[@data-monthly-date]');

        $this->assertNotFalse($rows);
        $this->assertSame(3, $rows->length);
        $firstRow = $rows->item(0);
        $middleRow = $rows->item(1);
        $lastRow = $rows->item(2);
        $this->assertInstanceOf(DOMElement::class, $firstRow);
        $this->assertInstanceOf(DOMElement::class, $middleRow);
        $this->assertInstanceOf(DOMElement::class, $lastRow);
        $this->assertSame('2026-09-24', $firstRow->getAttribute('data-monthly-date'));
        $this->assertSame('2026-09-23', $middleRow->getAttribute('data-monthly-date'));
        $this->assertSame('2026-09-22', $lastRow->getAttribute('data-monthly-date'));
        $this->assertStringContainsString('24 Eylül', $firstRow->textContent);
        $this->assertStringContainsString('Perşembe', $firstRow->textContent);
        $this->assertStringContainsString('+ Ekle', $middleRow->textContent);
    }

    #[Test]
    public function banka_ve_odeme_kuruluslari_aylik_listede_ayri_sutunlarda_gosterilir(): void
    {
        $this->createDay($this->admin, ['items' => [
            $this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'amount' => '3000']),
            $this->item(['method' => 'credit_card', 'bank_name' => 'Garanti BBVA', 'amount' => '2000']),
            $this->item(['method' => 'link', 'bank_name' => 'İş Bankası', 'amount' => '1000']),
        ]]);

        $response = $this->get(route('monthly-account.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertDontSee('Banka ve ödeme kuruluşu toplamları')
            ->assertSeeInOrder(['Kredi kartı', '(Garanti BBVA)', 'Kredi kartı', '(Yapı Kredi)', 'Link ödeme', '(İş Bankası)']);

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);

        $this->assertSame(2, $xpath->query('//table[contains(@class, "monthly-table")]/thead//th[normalize-space(.)="Kredi kartı (Garanti BBVA)" or normalize-space(.)="Kredi kartı (Yapı Kredi)"]')->length);
        $this->assertSame(1, $xpath->query('//table[contains(@class, "monthly-table")]/thead//th[normalize-space(.)="Link ödeme (İş Bankası)"]')->length);
        $this->assertSame(1, $xpath->query('//table[contains(@class, "monthly-table")]/thead/tr[contains(@class, "monthly-total-row") and contains(@class, "bg-nav-well") and contains(@class, "text-nav-tx")]/th[normalize-space(.)="Ay toplamı"]')->length);
        $this->assertSame(0, $xpath->query('//table[contains(@class, "monthly-table")]/tfoot')->length);
    }

    #[Test]
    public function ay_listesi_yalniz_secilen_sahibin_o_aydaki_gunlerini_ve_toplamlarini_getirir(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $other = $this->userWithRole(RoleName::Personel);
        $this->createDay($owner, ['account_date' => '2026-08-31', 'daily_total' => '100']);
        $first = $this->createDay($owner, ['account_date' => '2026-09-01', 'daily_total' => '200']);
        $last = $this->createDay($owner, ['account_date' => '2026-09-30', 'daily_total' => '300']);
        $this->createDay($owner, ['account_date' => '2026-10-01', 'daily_total' => '400']);
        $this->createDay($other, ['account_date' => '2026-09-14', 'daily_total' => '9000']);

        $this->actingAs($owner)->get(route('monthly-account.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertViewHas('days', fn (Collection $days) => $days->keys()->all() === ['2026-09-01', '2026-09-30']
                && $days->get('2026-09-01')->is($first) && $days->get('2026-09-30')->is($last))
            ->assertViewHas('summary', fn (array $summary) => $summary['daily_total']->toDecimal() === '500.00');
    }

    #[Test]
    public function ay_toplami_yalniz_son_aktif_surumu_kullanir_silineni_eklemez(): void
    {
        $updated = $this->createDay($this->admin, ['account_date' => '2026-09-01', 'daily_total' => '1000']);
        $deleted = $this->createDay($this->admin, ['account_date' => '2026-09-02', 'daily_total' => '9000']);
        $this->put(route('monthly-account.update', $updated), $this->payload($this->admin, [
            'account_date' => '2026-09-01', 'version' => 1, 'daily_total' => '1500', 'items' => [$this->item(['amount' => '1400'])],
        ]))->assertSessionDoesntHaveErrors();
        $this->delete(route('monthly-account.destroy', $deleted), ['version' => 1])->assertSessionDoesntHaveErrors();

        $this->get(route('monthly-account.index', ['month' => '2026-09']))
            ->assertOk()->assertViewHas('summary', fn (array $summary) => $summary['daily_total']->toDecimal() === '1500.00'
                && $summary['receipt_total']->toDecimal() === '1400.00')
            ->assertSee('Silindi');
    }

    #[Test]
    public function kayitli_gun_satiri_acilabilir_ve_islem_kolonu_yoktur(): void
    {
        $day = $this->createDay($this->admin, ['account_date' => '2026-09-14']);

        $response = $this->get(route('monthly-account.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertDontSee('>Aç<', false)
            ->assertDontSee('İşlemler');

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(@class, "monthly-table")]/tbody/tr[@data-monthly-row-url]');

        $this->assertNotFalse($rows);
        $this->assertSame(1, $rows->length);
        $row = $rows->item(0);
        $this->assertInstanceOf(DOMElement::class, $row);
        $this->assertSame(route('monthly-account.show', $day), $row->getAttribute('data-monthly-row-url'));
        $this->assertSame('0', $row->getAttribute('tabindex'));
        $this->assertSame('link', $row->getAttribute('role'));
    }

    #[Test]
    public function gunluk_form_tek_liste_tutari_tarih_doviz_ve_tekrarlanabilir_odeme_kontrollerini_gosterir(): void
    {
        $response = $this->actingAs($this->admin)->get(route('monthly-account.create', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('name="account_date"', false)
            ->assertSee('name="daily_total"', false)
            ->assertSee('Günlük Liste Toplamı (TL)')
            ->assertSee('data-money-input', false)
            ->assertSee('data-add-item', false)
            ->assertSee('data-monthly-item-template', false)
            ->assertSee('name="items[0][currency]"', false)
            ->assertSee('name="items[0][conversion_mode]"', false)
            ->assertSee('name="items[0][exchange_rate_to_try]"', false)
            ->assertSee('name="items[0][try_equivalent]"', false)
            ->assertDontSee('name="accommodation_total"', false)
            ->assertDontSee('Konaklama Listesi Toplamı')
            ->assertDontSee('Hesap sahibi')
            ->assertDontSee('Örn. 209 no.lu oda · tek çekim');

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//form[contains(@class, "monthly-account-form")]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-monthly-items]/following-sibling::div//button[@data-add-item]')->length);
        $this->assertSame(0, $xpath->query('//input[@data-item-note and @placeholder]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="daily_total" and contains(concat(" ", normalize-space(@class), " "), " monthly-daily-total ")]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="owner_id"]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="items[0][bank_name]" and @data-item-bank]')->length);
        $this->assertSame(0, $xpath->query('//input[@data-item-bank] | //datalist[@id="monthly-bank-names"]')->length);
    }

    #[Test]
    public function gun_ayrintisi_ayni_bankadaki_tl_ve_dovizi_kendi_tutar_ve_tl_karsiligiyla_gosterir(): void
    {
        $day = $this->createDay($this->admin, ['items' => [
            $this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'amount' => '3000', 'note' => 'Tek çekildi']),
            $this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '300', 'conversion_mode' => 'total', 'try_equivalent' => '10000', 'note' => '209 nolu oda']),
        ]]);

        $this->get(route('monthly-account.show', $day))->assertOk()
            ->assertSee('Yapı Kredi')->assertSee('Kredi kartı')->assertSee('3.000,00')
            ->assertSee('300,00')->assertSee('USD')->assertSee('10.000,00')
            ->assertSee('Tek çekildi')->assertSee('209 nolu oda')->assertSee('Kayıt geçmişi');
    }

    #[Test]
    public function patron_ekleme_silme_gormez_personel_kendi_alaninda_ekleme_gorur(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $patron = $this->userWithRole(RoleName::Patron);
        $day = $this->createDay($personel);
        $patronDay = $this->createDay($patron);

        $this->actingAs($patron)->get(route('monthly-account.index', ['owner_id' => $patron->id]))
            ->assertOk()->assertDontSee('+ Günlük kayıt')->assertDontSee('+ Ekle');
        $this->get(route('monthly-account.show', $patronDay))->assertOk()->assertSee('Düzenle')->assertDontSee('Günü sil');
        $this->actingAs($personel)->get(route('monthly-account.index'))->assertOk()->assertSee('+ Günlük kayıt');
        $this->get(route('monthly-account.show', $day))->assertOk()->assertSee('Düzenle')->assertDontSee('Günü sil');
    }

    #[Test]
    public function notlar_html_olarak_calistirilmaz(): void
    {
        $note = '<script>alert("monthly")</script>';
        $day = $this->createDay($this->admin, ['note' => $note, 'items' => [$this->item(['note' => $note])]]);

        $this->get(route('monthly-account.show', $day))->assertOk()->assertSee($note)->assertDontSee($note, false);
    }
}
