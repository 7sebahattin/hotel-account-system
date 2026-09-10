<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ücretlendirme modu: toplam tutar veya günlük ücret (CLAUDE.md §8).
 *
 * Değişmez: `stays.amount` her iki modda da NİHAİ TOPLAM'dır ve cari
 * hareketine yazılan değer odur. Günlük modda toplam yalnızca sunucuda
 * hesaplanır; istekten gelen tutar hiçbir koşulda kullanılmaz.
 */
class StayPricingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private StayService $stays;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->stays = app(StayService::class);
        $this->ledger = app(LedgerService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'room_no' => '204',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',   // 3 gece
            'pricing_mode' => 'total',
            'amount' => '7500,00',
            'currency' => 'TRY',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function dailyPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'pricing_mode' => 'daily',
            'daily_rate' => '2500,00',
            'amount' => null,
        ], $overrides));
    }

    private function balance(): string
    {
        return $this->ledger->balanceForCompany($this->company->id, Currency::TRY)->toDecimal();
    }

    private function activeDebit(Stay $stay): LedgerEntry
    {
        $active = $this->ledger->activeEntriesForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());

        $this->assertCount(1, $active, 'Konaklamanın tam olarak bir geçerli hareketi olmalı');

        return $active->first();
    }

    // ------------------------------------------------------------ oluşturma

    #[Test]
    public function toplam_modunda_kayit_olusturulur(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();

        $this->assertSame(StayPricingMode::Total, $stay->pricing_mode);
        $this->assertNull($stay->daily_rate, 'Toplam modunda günlük ücret boş kalmalı');
        $this->assertSame('7500.00', $stay->amount);
        $this->assertSame(3, $stay->nights);
    }

    #[Test]
    public function gunluk_modunda_kayit_olusturulur(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload())
            ->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();

        $this->assertSame(StayPricingMode::Daily, $stay->pricing_mode);
        $this->assertSame('2500.00', $stay->daily_rate);
        $this->assertSame(3, $stay->nights);
    }

    #[Test]
    public function gunluk_modunda_toplam_gece_sayisiyla_carpilir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload())
            ->assertSessionHasNoErrors();

        // 3 gece × 2.500,00 = 7.500,00
        $this->assertSame('7500.00', Stay::query()->firstOrFail()->amount);
    }

    /**
     * Kullanıcı isteği değiştirip sahte bir toplam gönderse bile sunucu
     * kendi hesabını kullanır. `exclude_if` alanı doğrulanmış veriden düşürür,
     * StayService ayrıca tutarı her koşulda yeniden hesaplar.
     */
    #[Test]
    public function gunluk_modunda_gonderilen_sahte_tutar_yok_sayilir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload(['amount' => '1,00']))
            ->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();

        $this->assertSame('7500.00', $stay->amount, 'Sahte tutar kullanılmamalı');
        $this->assertSame('7500.00', $this->activeDebit($stay)->debit);
    }

    /** Servis katmanı da tek başına korumalı — HTTP doğrulaması atlansa bile. */
    #[Test]
    public function servis_katmani_da_sahte_tutari_ezer(): void
    {
        $stay = $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'Doğrudan Servis',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'daily',
            'daily_rate' => '2500.00',
            'amount' => '1.00',
            'currency' => 'TRY',
        ], $this->admin);

        $this->assertSame('7500.00', $stay->amount);
    }

    /**
     * Kuruşlu tutarlar tamsayı kuruş aritmetiğiyle çarpılır; float kullanılsaydı
     * 0.1+0.2 tipi kayma oluşurdu.
     *
     * @param  numeric-string  $rate
     */
    #[Test]
    #[DataProvider('ondalikTutarlar')]
    public function gunluk_carpimda_ondalik_kaymasi_olmaz(string $rate, string $out, string $expected): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload([
                'daily_rate' => $rate,
                'check_out_date' => $out,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($expected, Stay::query()->firstOrFail()->amount);
    }

    /** @return array<string, array{string, string, string}> */
    public static function ondalikTutarlar(): array
    {
        return [
            '0,10 × 3' => ['0,10', '2026-09-10', '0.30'],
            '0,20 × 3' => ['0,20', '2026-09-10', '0.60'],
            '1234,56 × 7' => ['1234,56', '2026-09-14', '8641.92'],
            '99,99 × 30' => ['99,99', '2026-10-07', '2999.70'],
            '2500,05 × 1' => ['2500,05', '2026-09-08', '2500.05'],
        ];
    }

    // ------------------------------------------------------------ asgari gece

    #[Test]
    public function asgari_bir_gece_zorunludur(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->payload(['check_out_date' => '2026-09-07']))
            ->assertSessionHasErrors('check_out_date');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function ayni_gun_giris_cikis_veritabani_duzeyinde_de_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stays')->insert([
            'company_id' => $this->company->id,
            'guest_name' => 'Günübirlik',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-07',
            'nights' => 0,
            'amount' => '100.00',
            'pricing_mode' => 'total',
            'currency' => 'TRY',
            'status' => 'active',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ doğrulama

    #[Test]
    public function gunluk_modunda_gunluk_ucret_zorunludur(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload(['daily_rate' => null]))
            ->assertSessionHasErrors('daily_rate');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    #[DataProvider('gecersizTutarlar')]
    public function gecersiz_gunluk_ucret_reddedilir(?string $rate): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload(['daily_rate' => $rate]))
            ->assertSessionHasErrors('daily_rate');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    #[DataProvider('gecersizTutarlar')]
    public function toplam_modunda_gecersiz_tutar_reddedilir(?string $amount): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->payload(['amount' => $amount]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Stay::query()->count());
    }

    /** @return array<string, array{string|null}> */
    public static function gecersizTutarlar(): array
    {
        return [
            'sıfır' => ['0'],
            'sıfır kuruşlu' => ['0,00'],
            'negatif' => ['-100,00'],
            'metin' => ['bedava'],
            'boş' => [null],
        ];
    }

    #[Test]
    public function gecersiz_ucret_giris_sekli_reddedilir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->payload(['pricing_mode' => 'haftalik']))
            ->assertSessionHasErrors('pricing_mode');
    }

    #[Test]
    public function toplam_modunda_gonderilen_gunluk_ucret_kaydedilmez(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->payload(['daily_rate' => '999,00']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Stay::query()->firstOrFail()->daily_rate);
    }

    #[Test]
    public function veritabani_toplam_modunda_gunluk_ucreti_kabul_etmez(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stays')->insert([
            'company_id' => $this->company->id,
            'guest_name' => 'Tutarsız',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'nights' => 3,
            'amount' => '7500.00',
            'pricing_mode' => 'total',
            'daily_rate' => '2500.00',
            'currency' => 'TRY',
            'status' => 'active',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ güncelleme

    private function makeStay(array $overrides = []): Stay
    {
        return $this->stays->create(array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '7500.00',
            'currency' => 'TRY',
        ], $overrides), $this->admin);
    }

    #[Test]
    public function toplamdan_gunluge_gecis_ters_kayit_uretir(): void
    {
        $stay = $this->makeStay();

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->dailyPayload(['daily_rate' => '3000,00']))
            ->assertSessionHasNoErrors();

        $stay->refresh();

        $this->assertSame(StayPricingMode::Daily, $stay->pricing_mode);
        $this->assertSame('9000.00', $stay->amount);          // 3 × 3000
        $this->assertSame(3, LedgerEntry::query()->count());  // 7500 D, 7500 C, 9000 D
        $this->assertSame('9000.00', $this->balance());
        $this->assertSame('9000.00', $this->activeDebit($stay)->debit);
    }

    #[Test]
    public function gunlukten_toplama_gecis_ters_kayit_uretir(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->assertSame('7500.00', $stay->amount);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->payload(['amount' => '8000,00']))
            ->assertSessionHasNoErrors();

        $stay->refresh();

        $this->assertSame(StayPricingMode::Total, $stay->pricing_mode);
        $this->assertNull($stay->daily_rate);
        $this->assertSame('8000.00', $stay->amount);
        $this->assertSame(3, LedgerEntry::query()->count());
        $this->assertSame('8000.00', $this->balance());
        $this->assertSame('8000.00', $this->activeDebit($stay)->debit);
    }

    #[Test]
    public function gunluk_ucret_degisimi_ters_kayit_uretir(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->dailyPayload(['daily_rate' => '3000,00']))
            ->assertSessionHasNoErrors();

        $stay->refresh();

        $this->assertSame('9000.00', $stay->amount);
        $this->assertSame(3, LedgerEntry::query()->count());
        $this->assertSame('9000.00', $this->balance());
    }

    #[Test]
    public function tarih_degisiminde_gunluk_modda_gece_ve_tutar_yeniden_hesaplanir(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->dailyPayload(['check_out_date' => '2026-09-12']))
            ->assertSessionHasNoErrors();

        $stay->refresh();

        $this->assertSame(5, $stay->nights);
        $this->assertSame('12500.00', $stay->amount);          // 5 × 2500
        $this->assertSame('12500.00', $this->balance());
        $this->assertSame('12500.00', $this->activeDebit($stay)->debit);
    }

    /** Ücretlendirme değişmediyse gereksiz ters kayıt üretilmez. */
    #[Test]
    public function ayni_ucretlendirme_ile_kaydetmek_yeni_hareket_uretmez(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->dailyPayload(['guest_name' => 'Yeni İsim']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('7500.00', $this->balance());
        $this->assertSame('Yeni İsim', $stay->fresh()->guest_name);
    }

    // ------------------------------------------------------------ denetim

    #[Test]
    public function denetim_kaydi_ucretlendirme_degisimini_tasir(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->dailyPayload(['daily_rate' => '3000,00']))
            ->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('auditable_type', 'stay')->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame('2500.00', $log->old_values['daily_rate']);
        $this->assertSame('3000.00', $log->new_values['daily_rate']);
        $this->assertSame('7500.00', $log->old_values['amount'], 'Tutarlar maskelenmez');
        $this->assertSame('9000.00', $log->new_values['amount']);
        $this->assertTrue($log->new_values['financial_change']);
        $this->assertNotNull($log->new_values['reversal_ledger_entry_id']);
        $this->assertNotNull($log->new_values['new_ledger_entry_id']);
    }

    #[Test]
    public function denetim_kaydi_mod_degisimini_tasir(): void
    {
        $stay = $this->makeStay(['pricing_mode' => 'daily', 'daily_rate' => '2500.00']);

        $this->actingAs($this->admin)
            ->put(route('stays.update', $stay), $this->payload(['amount' => '8000,00']))
            ->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('auditable_type', 'stay')->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame('daily', $log->old_values['pricing_mode']);
        $this->assertSame('total', $log->new_values['pricing_mode']);
        $this->assertSame('2500.00', $log->old_values['daily_rate']);
        $this->assertNull($log->new_values['daily_rate']);
    }

    #[Test]
    public function olusturma_denetim_kaydi_ucretlendirmeyi_tasir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stays.store'), $this->dailyPayload())
            ->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('auditable_type', 'stay')->where('event', 'created')->firstOrFail();

        $this->assertSame('daily', $log->new_values['pricing_mode']);
        $this->assertSame('2500.00', $log->new_values['daily_rate']);
        $this->assertSame('7500.00', $log->new_values['amount']);
        $this->assertSame(3, $log->new_values['nights']);
    }

    // ------------------------------------------------------------ personel

    /**
     * Personel kendi kaydını düzenleme penceresi içinde, ücretlendirme dahil
     * değiştirebilir (CLAUDE.md §7.4); bu da ters kayıt + yeni hareket üretir.
     */
    #[Test]
    public function personel_kendi_kaydinin_ucretlendirmesini_degistirebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $stay = $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'Personel Kaydı',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '7500.00',
            'currency' => 'TRY',
        ], $personel);

        $this->actingAs($personel)
            ->put(route('stays.update', $stay), $this->dailyPayload(['daily_rate' => '3000,00']))
            ->assertSessionHasNoErrors();

        $stay->refresh();

        $this->assertSame(StayPricingMode::Daily, $stay->pricing_mode);
        $this->assertSame('9000.00', $stay->amount);
        $this->assertSame('9000.00', $this->balance());
    }

    #[Test]
    public function personel_suresi_dolmus_kaydin_ucretlendirmesini_degistiremez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $stay = $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'Eski Kayıt',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '7500.00',
            'currency' => 'TRY',
        ], $personel);

        $stay->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        $this->actingAs($personel)
            ->put(route('stays.update', $stay), $this->dailyPayload(['daily_rate' => '3000,00']))
            ->assertForbidden();

        $this->assertSame('7500.00', $stay->fresh()->amount);
    }
}
