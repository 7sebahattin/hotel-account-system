<?php

declare(strict_types=1);

namespace Tests\Feature\ReceiptBook;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiptCustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function modal_formu_cari_secimini_ve_guvenli_scroll_fallbackunu_sunar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->get(route('receipt-book.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-receipt-customer-search', false)
            ->assertSee('name="company_id"', false)
            ->assertSee('data-receipt-customer-results', false)
            ->assertSee('data-receipt-customer-create', false)
            ->assertSee('type="submit"', false);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('#makbuz-dialog {', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - 3rem)', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
    }

    #[Test]
    public function arama_yetki_ve_sonuc_limitini_korur(): void
    {
        $this->get(route('receipt-book.customer-search', ['q' => 'Ahmet']))->assertRedirect(route('login'));
        $admin = $this->userWithRole(RoleName::Admin);
        Company::factory()->create(['name' => 'Ahmet Arslan', 'account_type' => 'individual']);
        Company::factory()->create(['name' => 'Ahmet Otelcilik', 'account_type' => 'company']);
        Company::factory()->create(['name' => 'Mehmet Kaya']);
        Company::factory()->inactive()->create(['name' => 'Ahmet Pasif']);

        $this->actingAs($admin)->getJson(route('receipt-book.customer-search', ['q' => ' ahmet ']))
            ->assertOk()->assertJsonCount(2, 'results')
            ->assertJsonFragment(['name' => 'Ahmet Arslan', 'type' => 'individual'])
            ->assertJsonFragment(['name' => 'Ahmet Otelcilik', 'type' => 'company'])
            ->assertDontSee('Mehmet Kaya')->assertDontSee('Ahmet Pasif');

        Company::factory()->count(12)->create(['name' => 'Ahmet Diğer']);
        $this->actingAs($admin)->getJson(route('receipt-book.customer-search', ['q' => 'Ahmet']))
            ->assertOk()->assertJsonCount(10, 'results');
        $this->actingAs($admin)->getJson(route('receipt-book.customer-search', ['q' => '%_']))
            ->assertOk()->assertJsonCount(0, 'results');
    }

    #[Test]
    public function secilen_cari_kimligi_makbuzun_kanonik_adina_donusur_ve_finansal_hareket_uretmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = Company::factory()->create(['name' => 'Ahmet Arslan']);

        $this->actingAs($admin)->post(route('receipt-book.store'), [
            'receipt_date' => '2026-09-28',
            'customer_name' => 'Değiştirilmiş serbest metin',
            'company_id' => $account->id,
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('Ahmet Arslan', ReceiptBookEntry::query()->sole()->customer_name);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());
    }

    #[Test]
    public function yeni_cari_mevcut_servisle_olusturulup_idsi_makbuzda_kullanilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $response = $this->actingAs($admin)->postJson(route('receipt-book.customer-create'), [
            'name' => 'Ahmet Kurt', 'account_type' => 'individual', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('name', 'Ahmet Kurt');
        $id = $response->json('id');

        $this->assertDatabaseHas('companies', ['id' => $id, 'name' => 'Ahmet Kurt', 'account_type' => 'individual']);
        $this->actingAs($admin)->post(route('receipt-book.store'), [
            'receipt_date' => '2026-09-28', 'customer_name' => 'Ahmet Kurt', 'company_id' => $id,
            'amount' => '100,00', 'currency' => 'TRY',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame('Ahmet Kurt', ReceiptBookEntry::query()->sole()->customer_name);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());
    }

    #[Test]
    public function gecersiz_cari_girdileri_kabul_edilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->getJson(route('receipt-book.customer-search', ['q' => ['invalid']]))
            ->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('receipt-book.customer-create'), [
            'name' => '', 'account_type' => 'individual', 'is_active' => true,
        ])->assertUnprocessable();
        $this->actingAs($admin)->post(route('receipt-book.store'), [
            'receipt_date' => '2026-09-28', 'customer_name' => 'Ahmet', 'company_id' => 99999,
            'amount' => '100,00', 'currency' => 'TRY',
        ])->assertSessionHasErrors('company_id');
        $this->assertSame(0, ReceiptBookEntry::query()->count());
    }
}
