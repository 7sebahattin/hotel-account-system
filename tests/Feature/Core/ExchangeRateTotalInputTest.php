<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Support\ExchangeRateSnapshot;
use App\Modules\Core\Support\Money;
use App\Modules\Payments\Models\Payment;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * K-110 — döviz girişinin İKİNCİ modu: operatör kur yerine doğrudan TL
 * TOPLAMINI girer, kur sunucuda TÜRETİLİR.
 *
 * Bağlayıcı kural: girilen TL AYNEN saklanır, kurdan yeniden hesaplanmaz.
 * Kur `DECIMAL(10,4)` olduğu için her toplam dört ondalıkla tam ifade
 * edilemez; yaklaşık olan TÜRETİLMİŞ değerdir (kur), operatörün yazdığı
 * rakam değil.
 *
 * Her iki modda da cari deftere yazılan tutar SEÇİLEN PARA BİRİMİNDEDİR
 * (CLAUDE.md §6/L5) — TL karşılığı salt bilgi amaçlıdır.
 */
class ExchangeRateTotalInputTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function stayPayload(array $overrides = []): array
    {
        return [
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Yılmaz',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '1.000,00',
            'currency' => 'EUR',
            ...$overrides,
        ];
    }

    #[Test]
    public function konaklamada_tl_toplami_girilince_kur_turetilir_ve_toplam_aynen_saklanir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '41.234,57',
            ]))
            ->assertSessionDoesntHaveErrors();

        $stay = Stay::query()->firstOrFail();

        // Türetilen kur 41,2346'dır; geri çarpım 41.234,60 ₺ eder.
        $this->assertSame('41.2346', $stay->exchange_rate_to_try);
        // Ama SAKLANAN karşılık operatörün girdiği rakamdır — kaydırılmaz.
        $this->assertSame('41234.57', $stay->try_equivalent);

        // Tutar ve cari hareketi hâlâ EUR'dur; TL'ye çevrilmemiştir (§6/L5).
        $this->assertSame('1000.00', $stay->amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'stay',
            'source_id' => $stay->id,
            'currency' => 'EUR',
            'debit' => '1000.00',
        ]);
        $this->assertDatabaseMissing('ledger_entries', ['currency' => 'TRY']);
    }

    #[Test]
    public function bolme_tam_ciktiginda_iki_mod_ayni_sonucu_verir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '40.000,00',
            ]))
            ->assertSessionDoesntHaveErrors();

        $viaTotal = Stay::query()->firstOrFail();

        $this->assertSame('40.0000', $viaTotal->exchange_rate_to_try);
        $this->assertSame('40000.00', $viaTotal->try_equivalent);
        $this->assertSame(
            $viaTotal->try_equivalent,
            Money::fromDecimal($viaTotal->amount)->multiplyByRate($viaTotal->exchange_rate_to_try)->toDecimal(),
        );
    }

    #[Test]
    public function tahsilatta_ve_makbuz_defterinde_de_tl_toplami_girilebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-02',
            'amount' => '200,00',
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'fx_input_mode' => 'total',
            'try_equivalent_input' => '6.820,00',
        ])->assertSessionDoesntHaveErrors();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('34.1000', $payment->exchange_rate_to_try);
        $this->assertSame('6820.00', $payment->try_equivalent);
        $this->assertSame('200.00', $payment->amount);

        $this->actingAs($admin)->post(route('receipt-book.store'), [
            'receipt_date' => '2026-09-02',
            'customer_name' => 'Deniz Turizm',
            'amount' => '100,00',
            'currency' => 'GBP',
            'fx_input_mode' => 'total',
            'try_equivalent_input' => '5.512,50',
        ])->assertSessionDoesntHaveErrors();

        $entry = ReceiptBookEntry::query()->firstOrFail();
        $this->assertSame('55.1250', $entry->exchange_rate_to_try);
        $this->assertSame('5512.50', $entry->try_equivalent);
    }

    #[Test]
    public function tl_modunda_toplam_bos_birakilamaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '',
                'exchange_rate_to_try' => '51,20',
            ]))
            ->assertSessionHasErrors('try_equivalent_input');

        $this->assertSame(0, Stay::query()->count());
    }

    /**
     * Yazım hatası (bir avroya iki milyon lira) DECIMAL(10,4) aralığını
     * aşan bir kur üretir. Bu 500 değil, alanın KENDİSİNE bağlı bir
     * doğrulama hatası olarak döner.
     */
    #[Test]
    public function kura_donusmeyen_tl_toplami_dogrulama_hatasi_verir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'amount' => '1,00',
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '2.000.000,00',
            ]))
            ->assertSessionHasErrors('try_equivalent_input');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function try_secilince_iki_alan_da_yok_sayilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'currency' => 'TRY',
                // Gizli blok tarayıcı tarafından yine gönderilir: mod dolu,
                // değer alanları boştur. Bu kombinasyon reddedilmemelidir.
                'fx_input_mode' => 'rate',
                'exchange_rate_to_try' => '',
                'try_equivalent_input' => '',
            ]))
            ->assertSessionDoesntHaveErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertNull($stay->exchange_rate_to_try);
        $this->assertNull($stay->try_equivalent);
    }

    /**
     * K-110 ikinci kararı: kur/TL artık DÜZENLEMEDE de sorulur. Tutar
     * değiştiğinde eski TL karşılığı o yeni tutara ait değildir.
     */
    #[Test]
    public function duzenlemede_tl_karsiligi_yeniden_girilebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('stays.store'), $this->stayPayload([
                'fx_input_mode' => 'rate',
                'exchange_rate_to_try' => '40,00',
            ]))
            ->assertSessionDoesntHaveErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertSame('40000.00', $stay->try_equivalent);

        $this->actingAs($admin)
            ->put(route('stays.update', $stay), $this->stayPayload([
                'amount' => '1.200,00',
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '49.500,00',
            ]))
            ->assertSessionDoesntHaveErrors();

        $stay->refresh();
        $this->assertSame('1200.00', $stay->amount);
        $this->assertSame('49500.00', $stay->try_equivalent);
        $this->assertSame('41.2500', $stay->exchange_rate_to_try);
    }

    #[Test]
    public function gecmis_duzeltme_ekrani_da_tl_toplamini_kabul_eder(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $stay = Stay::factory()->create([
            'company_id' => $this->company->id,
            'amount' => '500.00',
            'currency' => 'EUR',
            'exchange_rate_to_try' => null,
            'try_equivalent' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('fx-correction.stays.update', $stay), [
                'fx_input_mode' => 'total',
                'try_equivalent_input' => '25.600,00',
            ])
            ->assertSessionDoesntHaveErrors();

        $stay->refresh();
        $this->assertSame('51.2000', $stay->exchange_rate_to_try);
        $this->assertSame('25600.00', $stay->try_equivalent);
    }

    /** Türetilen kurun toplamı tam ifade edip etmediği açıkça bilinebilir. */
    #[Test]
    public function anlik_goruntu_kesinligini_bildirir(): void
    {
        $amount = Money::fromDecimal('1000.00');

        $this->assertFalse(ExchangeRateSnapshot::fromTryTotal($amount, '41234.57')->isExact($amount));
        $this->assertTrue(ExchangeRateSnapshot::fromTryTotal($amount, '40000.00')->isExact($amount));
        $this->assertTrue(ExchangeRateSnapshot::fromRate($amount, '41.2346')->isExact($amount));
    }
}
