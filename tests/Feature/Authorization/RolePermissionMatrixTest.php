<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §7.3 rol matrisinin birebir doğrulaması.
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function uc_sistem_rolu_olusturulur_ve_silinemez_isaretlidir(): void
    {
        foreach (RoleName::cases() as $roleName) {
            $role = Role::byName($roleName->value);

            $this->assertTrue($role->isSystem(), "{$roleName->value} sistem rolü olmalı");
        }

        $this->assertSame(3, Role::query()->count());
    }

    #[Test]
    public function admin_tum_yetkilere_sahiptir(): void
    {
        $role = Role::byName(RoleName::Admin->value);

        $this->assertEqualsCanonicalizing(
            Permission::values(),
            $role->permissions->pluck('name')->all(),
        );
    }

    #[Test]
    public function patron_kullanici_ve_rol_yonetemez_ama_finansal_veriyi_gorur(): void
    {
        $role = Role::byName(RoleName::Patron->value);
        $granted = $role->permissions->pluck('name')->all();

        foreach ([Permission::UsersView, Permission::UsersManage, Permission::RolesManage,
            Permission::MonthlyAccountCreate, Permission::MonthlyAccountDelete,
            Permission::MonthlyAccountManageProviders] as $forbidden) {
            $this->assertNotContains($forbidden->value, $granted);
        }

        foreach ([
            Permission::LedgerView, Permission::LedgerViewAll,
            Permission::DashboardFinancial, Permission::ExportExcel,
            Permission::AuditView, Permission::PaymentsCancel,
            Permission::CompaniesDelete, Permission::StaysDelete,
            // Room Registry (K-100): patron oda tanımlarını da yönetir.
            Permission::RoomsManage,
            // v2.1.1-C: patron geçmiş döviz düzeltmesi ve makbuz defterini de yönetir.
            Permission::FxCorrectHistorical,
            Permission::ReceiptBookView,
            Permission::ReceiptBookCreate,
            Permission::MonthlyAccountView,
            Permission::MonthlyAccountUpdate,
            Permission::MonthlyAccountViewAll,
        ] as $expected) {
            $this->assertContains($expected->value, $granted);
        }

        // Kaldırılan dört kasa yetkisi artık rolün parçası değildir.
        $this->assertCount(23, $granted);
    }

    #[Test]
    public function personel_finansal_toplamlari_ve_yonetim_yetkilerini_gormez(): void
    {
        $role = Role::byName(RoleName::Personel->value);
        $granted = $role->permissions->pluck('name')->all();

        foreach ([
            Permission::LedgerView, Permission::LedgerViewAll,
            Permission::DashboardFinancial, Permission::ExportExcel,
            Permission::AuditView, Permission::PaymentsCancel,
            Permission::CompaniesDelete, Permission::StaysDelete,
            Permission::UsersView, Permission::UsersManage, Permission::RolesManage,
            // Personel kendi kasasını görür ama TÜM vardiyaları göremez.
            // Room Registry (K-100): oda tanımı yönetimi Admin/Patron'dadır.
            Permission::RoomsManage,
            // v2.1.1-C: geçmiş döviz düzeltmesi Admin/Patron'dadır, personelde YOKTUR.
            Permission::FxCorrectHistorical,
            Permission::MonthlyAccountDelete,
            Permission::MonthlyAccountViewAll,
            Permission::MonthlyAccountManageProviders,
        ] as $forbidden) {
            $this->assertNotContains($forbidden->value, $granted, "personel {$forbidden->value} yetkisine sahip olmamalı");
        }

        $this->assertEqualsCanonicalizing([
            Permission::CompaniesView->value,
            Permission::CompaniesCreate->value,
            Permission::CompaniesUpdate->value,
            Permission::StaysView->value,
            Permission::StaysCreate->value,
            Permission::StaysUpdate->value,
            Permission::PaymentsView->value,
            Permission::PaymentsCreate->value,
            /*
             * Gün 10 — kasa: personel fiziksel kasadan sorumludur, kendi
             * vardiyasını görmek ve saymak zorundadır. Bu, "finansal toplamları
             * görmez" kuralıyla çelişmez: yasak olan FİRMA cari bakiyesi ve
             * toplam alacaktır; buradaki tutar personelin elindeki paradır.
             */
            // v2.1.1-C: personel de tahsilat makbuzu defteri girebilir (PaymentsCreate ile AYNI seviye).
            Permission::ReceiptBookView->value,
            Permission::ReceiptBookCreate->value,
            Permission::MonthlyAccountView->value,
            Permission::MonthlyAccountCreate->value,
            Permission::MonthlyAccountUpdate->value,
        ], $granted);
    }

    #[Test]
    public function seeder_tekrar_calistirilabilir(): void
    {
        $this->seedRoles();
        $this->seedRoles();

        $this->assertSame(3, Role::query()->count());
        $this->assertSame(count(Permission::cases()), Role::byName(RoleName::Admin->value)->permissions->count());
    }
}
