<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function logFor(Company $company, AuditEvent $event): AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', 'company')
            ->where('auditable_id', $company->id)
            ->where('event', $event->value)
            ->latest('id')
            ->firstOrFail();
    }

    #[Test]
    public function firma_olusturma_denetim_kaydina_yazilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        AuditLog::query()->delete();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Denetim Firması',
            'city' => 'Ankara',
        ]);

        $company = Company::query()->firstOrFail();
        $log = $this->logFor($company, AuditEvent::Created);

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->name, $log->user_name);
        $this->assertSame('Denetim Firması', $log->new_values['name']);
        $this->assertSame('Ankara', $log->new_values['city']);
    }

    #[Test]
    public function guncellemede_yalnizca_degisen_alanlar_kaydedilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'Eski Ünvan', 'city' => 'Bursa']);
        AuditLog::query()->delete();

        $this->actingAs($admin)->put(route('companies.update', $company), [
            'name' => 'Yeni Ünvan',
            'city' => 'Bursa',
            'is_active' => '1',
        ]);

        $log = $this->logFor($company, AuditEvent::Updated);

        $this->assertSame(['name' => 'Yeni Ünvan'], $log->new_values);
        $this->assertSame(['name' => 'Eski Ünvan'], $log->old_values);
        $this->assertArrayNotHasKey('city', $log->new_values, 'Değişmeyen alan kaydedilmemeli');
        $this->assertArrayNotHasKey('updated_by', $log->new_values, 'updated_by gürültüdür, kaydedilmez');
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
    }

    #[Test]
    public function hicbir_alan_degismezse_denetim_kaydi_acilmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'Sabit Ünvan', 'city' => 'İzmir']);
        AuditLog::query()->delete();

        $this->actingAs($admin)->put(route('companies.update', $company), [
            'name' => 'Sabit Ünvan',
            'city' => 'İzmir',
            'is_active' => $company->is_active ? '1' : '0',
        ]);

        $this->assertSame(0, AuditLog::query()->where('event', AuditEvent::Updated->value)->count());
    }

    #[Test]
    public function silme_ve_geri_alma_denetim_kaydina_yazilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        AuditLog::query()->delete();

        $this->actingAs($admin)->delete(route('companies.destroy', $company));
        $this->logFor($company, AuditEvent::Deleted);

        $this->actingAs($admin)->patch(route('companies.restore', $company));
        $restored = $this->logFor($company, AuditEvent::Restored);

        $this->assertSame($admin->id, $restored->user_id);
        $this->assertSame(2, AuditLog::query()->whereIn('event', [
            AuditEvent::Deleted->value,
            AuditEvent::Restored->value,
        ])->count());
    }

    /**
     * notes gibi uzun alanlar denetim tablosunu kontrolsüz büyütmemeli.
     * Kısaltma YALNIZCA denetim anlık görüntüsüne uygulanır.
     */
    #[Test]
    public function uzun_alanlar_denetim_kaydinda_kisaltilir_gercek_kayit_kesilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $uzunNot = str_repeat('ç', AuditLogger::MAX_VALUE_LENGTH + 250);
        AuditLog::query()->delete();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Uzun Notlu Firma',
            'notes' => $uzunNot,
        ]);

        $company = Company::query()->firstOrFail();

        // Gerçek kayıt tam uzunlukta saklanır.
        $this->assertSame(
            AuditLogger::MAX_VALUE_LENGTH + 250,
            mb_strlen((string) $company->notes),
            'Gerçek firma kaydı kesilmemeli',
        );

        $log = $this->logFor($company, AuditEvent::Created);

        $this->assertSame(
            AuditLogger::MAX_VALUE_LENGTH + mb_strlen(AuditLogger::TRUNCATED_SUFFIX),
            mb_strlen($log->new_values['notes']),
        );
        $this->assertStringEndsWith(AuditLogger::TRUNCATED_SUFFIX, $log->new_values['notes']);
    }

    #[Test]
    public function kisa_alanlar_kisaltilmadan_kaydedilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        AuditLog::query()->delete();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Kısa Notlu Firma',
            'notes' => 'Acente anlaşması mevcut.',
        ]);

        $log = $this->logFor(Company::query()->firstOrFail(), AuditEvent::Created);

        $this->assertSame('Acente anlaşması mevcut.', $log->new_values['notes']);
    }

    #[Test]
    public function denetim_kaydi_morph_takma_adi_kullanir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('companies.store'), ['name' => 'Morph Testi']);

        $log = AuditLog::query()->where('event', AuditEvent::Created->value)->latest('id')->firstOrFail();

        $this->assertSame('company', $log->auditable_type, 'Sınıf adı değil, morph takma adı saklanmalı');
    }
}
