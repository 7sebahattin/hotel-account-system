<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLog;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §8: giriş, çıkış ve başarısız giriş denetim kaydına yazılır.
 */
class AuthAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('test@example.com|127.0.0.1');
    }

    #[Test]
    public function basarili_giris_kaydedilir(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'sifre-123']);

        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'sifre-123']);

        $log = AuditLog::query()->where('event', AuditEvent::Login->value)->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->name, $log->user_name);
        $this->assertSame('user', $log->auditable_type);
        $this->assertNotNull($log->ip_address);
    }

    #[Test]
    public function cikis_kaydedilir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $log = AuditLog::query()->where('event', AuditEvent::Logout->value)->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
    }

    #[Test]
    public function hatali_sifre_login_failed_olarak_kaydedilir(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'sifre-123']);

        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'yanlis']);

        $log = AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->firstOrFail();

        $this->assertNull($log->user_id, 'Başarısız girişte oturum açmış kullanıcı yoktur');
        $this->assertSame('test@example.com', $log->user_name);
        $this->assertSame(['email' => 'test@example.com'], $log->new_values);
    }

    #[Test]
    public function olmayan_hesapla_giris_denemesi_de_kaydedilir(): void
    {
        $this->post(route('login'), ['email' => 'yok@example.com', 'password' => 'her-neyse']);

        $this->assertSame(
            1,
            AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->count(),
        );
    }

    #[Test]
    public function pasif_hesapla_giris_denemesi_kaydedilir(): void
    {
        User::factory()->inactive()->create(['email' => 'test@example.com', 'password' => 'sifre-123']);

        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'sifre-123']);

        $this->assertSame(
            1,
            AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->count(),
        );
        $this->assertSame(
            0,
            AuditLog::query()->where('event', AuditEvent::Login->value)->count(),
        );
    }

    #[Test]
    public function kisitlamaya_takilan_deneme_de_kaydedilir(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'sifre-123']);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'yanlis']);
        }

        // 5 başarısız deneme + 1 kısıtlama kaydı
        $this->assertSame(
            6,
            AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->count(),
        );
    }

    #[Test]
    public function denetim_kaydinda_sifre_hicbir_zaman_gorunmez(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'sifre-123']);

        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'cok-gizli']);
        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'sifre-123']);

        foreach (AuditLog::query()->get() as $log) {
            $encoded = json_encode([$log->old_values, $log->new_values], JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString('cok-gizli', $encoded);
            $this->assertStringNotContainsString('sifre-123', $encoded);
        }
    }
}
