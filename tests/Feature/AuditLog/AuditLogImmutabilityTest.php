<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLog;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * CLAUDE.md §5.6: denetim kaydı değiştirilemez ve silinemez — admin dahil.
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function makeLog(): AuditLog
    {
        return app(AuditLogger::class)->log(AuditEvent::Exported);
    }

    #[Test]
    public function tabloda_updated_at_kolonu_yoktur(): void
    {
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
    }

    #[Test]
    public function kayit_guncellenemez(): void
    {
        $log = $this->makeLog();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Denetim kaydı değiştirilemez.');

        $log->update(['event' => AuditEvent::Login->value]);
    }

    #[Test]
    public function kayit_silinemez(): void
    {
        $log = $this->makeLog();

        try {
            $log->delete();
            $this->fail('RuntimeException bekleniyordu');
        } catch (RuntimeException $e) {
            $this->assertSame('Denetim kaydı silinemez.', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    #[Test]
    public function admin_bile_silme_yetkisine_sahip_degildir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $log = $this->makeLog();

        $this->assertFalse($admin->can('delete', $log));
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', AuditLog::class));
    }

    #[Test]
    public function denetim_kaydini_degistiren_hicbir_http_ucu_yoktur(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit'))
            ->map(fn ($route) => $route->methods());

        $this->assertNotEmpty($routes, 'Denetim kaydı ekranı bulunmalı');

        foreach ($routes as $methods) {
            $this->assertEmpty(
                array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']),
                'Denetim kaydı için yazma ucu tanımlanmamalıdır',
            );
        }
    }

    #[Test]
    public function patron_denetim_kaydini_okuyabilir_ama_degistiremez(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $log = $this->makeLog();

        $this->assertTrue($patron->can('viewAny', AuditLog::class));
        $this->assertFalse($patron->can('delete', $log));
    }
}
