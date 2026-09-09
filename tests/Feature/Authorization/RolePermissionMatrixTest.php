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

        foreach ([Permission::UsersView, Permission::UsersManage, Permission::RolesManage] as $forbidden) {
            $this->assertNotContains($forbidden->value, $granted);
        }

        foreach ([
            Permission::LedgerView, Permission::LedgerViewAll,
            Permission::DashboardFinancial, Permission::ExportExcel,
            Permission::AuditView, Permission::PaymentsCancel,
            Permission::CompaniesDelete, Permission::StaysDelete,
            // Gün 10: patron kasayı hem işletir hem tüm vardiyaları görür.
            Permission::CashBookView, Permission::CashBookOperate, Permission::CashBookViewAll,
            // v2.1.1-B: patron kasa hareket türlerini (kategori) de yönetir.
            Permission::CashBookManageCategories,
            // Room Registry (K-100): patron oda tanımlarını da yönetir.
            Permission::RoomsManage,
        ] as $expected) {
            $this->assertContains($expected->value, $granted);
        }

        $this->assertCount(21, $granted);
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
            Permission::CashBookViewAll,
            // Room Registry (K-100): oda tanımı yönetimi Admin/Patron'dadır.
            Permission::RoomsManage,
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
            Permission::CashBookView->value,
            Permission::CashBookOperate->value,
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
