<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('test@example.com|127.0.0.1');
    }

    #[Test]
    public function giris_sayfasi_acilir(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Giriş yap');
    }

    #[Test]
    public function dogru_bilgilerle_giris_yapilir(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function basarili_giriste_last_login_at_guncellenir(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'sifre-123',
            'last_login_at' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    #[Test]
    public function hatali_sifre_ile_giris_yapilamaz(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'yanlis-sifre',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function pasif_kullanici_giris_yapamaz(): void
    {
        User::factory()->inactive()->create([
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ])->assertSessionHasErrors(['email' => __('auth.inactive')]);

        $this->assertGuest();
    }

    #[Test]
    public function bes_basarisiz_denemeden_sonra_kisitlanir(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => 'test@example.com',
                'password' => 'yanlis-sifre',
            ]);
        }

        // 6. deneme doğru şifreyle bile kısıtlamaya takılır.
        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'sifre-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Çok fazla başarısız deneme',
            session('errors')->first('email'),
        );
        $this->assertGuest();
    }

    #[Test]
    public function kullanici_cikis_yapabilir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function misafir_kullanici_ana_sayfaya_erisemez(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    #[Test]
    public function giris_yapmis_kullanici_ana_sayfayi_gorur(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ahmet Yılmaz']))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Ahmet Yılmaz');
    }
}
