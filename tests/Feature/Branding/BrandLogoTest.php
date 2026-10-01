<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Branding\Services\BrandLogoService;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
    }

    #[Test]
    public function yukleme_yokken_varyanta_uygun_varsayilan_logo_kullanilir(): void
    {
        $logos = app(BrandLogoService::class);

        $this->assertStringContainsString('default-logo-on-light.svg', (string) $logos->url('on-light'));
        $this->assertStringContainsString('default-logo-on-dark.svg', (string) $logos->url('on-dark'));

        Storage::disk('public')->put('branding/logo-on-light.png', 'bozuk görsel');
        $this->assertStringContainsString('default-logo-on-light.svg', (string) $logos->url('on-light'));

        config()->set('hotel.brand.logo', 'images/branding/default-logo-on-light.svg');
        $this->assertStringContainsString('default-logo-on-light.svg', (string) $logos->url('on-dark'));
    }

    #[Test]
    public function yuklenen_logo_varsayilani_gecer_ve_ayni_varyant_yeniden_degistirilebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->image('ilk.png'),
        ])->assertRedirect(route('branding.logos.edit'));

        Storage::disk('public')->assertExists('branding/logo-on-light.png');
        $logos = app(BrandLogoService::class);
        $this->assertStringContainsString('/branding/logo/on-light', (string) $logos->url('on-light'));
        $this->assertStringContainsString('default-logo-on-dark.svg', (string) $logos->url('on-dark'));

        $this->actingAs($admin)->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->image('ikinci.jpg'),
        ])->assertRedirect(route('branding.logos.edit'));

        Storage::disk('public')->assertMissing('branding/logo-on-light.png');
        Storage::disk('public')->assertExists('branding/logo-on-light.jpg');
        $this->assertStringContainsString('/branding/logo/on-light', (string) $logos->url('on-light'));
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());
    }

    #[Test]
    public function yuklenen_logo_storage_symlink_olmadan_public_route_uzerinden_servis_edilir(): void
    {
        $logos = app(BrandLogoService::class);
        $logos->replace('on-light', UploadedFile::fake()->image('logo.png'));

        $this->get(route('branding.logo.file', 'on-light'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/branding/logo/gecersiz')->assertNotFound();
    }

    #[Test]
    public function admin_ve_patron_yukler_personel_erismez(): void
    {
        $this->get(route('branding.logos.edit'))->assertRedirect(route('login'));
        $this->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->image('giris-yok.png'),
        ])->assertRedirect(route('login'));

        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('branding.logos.edit'))
                ->assertOk()
                ->assertSee('data-brand-variant="on-light"', false)
                ->assertSee('data-brand-variant="on-dark"', false);
            $this->actingAs($user)->put(route('branding.logos.update', 'on-dark'), [
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])->assertRedirect(route('branding.logos.edit'));
        }

        $personel = $this->userWithRole(RoleName::Personel);
        $this->actingAs($personel)->get(route('branding.logos.edit'))->assertForbidden();
        $this->actingAs($personel)->put(route('branding.logos.update', 'on-dark'), [
            'logo' => UploadedFile::fake()->image('yasak.png'),
        ])->assertForbidden();
        $this->actingAs($personel)->get(route('home'))->assertDontSee('Marka Logoları');
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertOk()->assertSee('/branding/logo/on-dark', false);
    }

    #[Test]
    public function gecersiz_ve_buyuk_dosya_reddedilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');
        $this->actingAs($admin)->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->create('sahte.png', 1, 'text/plain'),
        ])->assertSessionHasErrors('logo');
        $this->actingAs($admin)->put(route('branding.logos.update', 'on-light'), [
            'logo' => UploadedFile::fake()->image('buyuk.png')->size(2049),
        ])->assertSessionHasErrors('logo');
        Storage::disk('public')->assertMissing('branding/logo-on-light.png');
    }

    #[Test]
    public function sidebar_makbuz_ve_print_dogru_varyanti_kullanir_finansal_kayit_uretmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->get(route('home'))
            ->assertOk()->assertSee('data-brand-variant="on-dark"', false);
        $this->actingAs($admin)->get(route('receipt-book.index'))
            ->assertOk()->assertSee('data-brand-variant="on-light"', false);

        $this->actingAs($admin)->post(route('receipt-book.store'), [
            'receipt_date' => '2026-09-28',
            'customer_name' => 'Logo Test',
            'amount' => '100.00',
            'currency' => 'TRY',
        ])->assertSessionDoesntHaveErrors();

        $entry = ReceiptBookEntry::query()->sole();
        $this->actingAs($admin)->get(route('receipt-book.print', $entry))
            ->assertOk()->assertSee('data-brand-variant="on-light"', false);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());
    }
}
