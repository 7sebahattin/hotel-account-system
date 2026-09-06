<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * Sistem rolleri (CLAUDE.md §7.5). Silinemez, adları değiştirilemez.
 */
enum RoleName: string
{
    case Admin = 'admin';
    case Patron = 'patron';
    case Personel = 'personel';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Patron => 'Patron',
            self::Personel => 'Personel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Tam sistem kontrolü; kullanıcı, rol ve yetki yönetimi dahil.',
            self::Patron => 'Tüm firma, konaklama, tahsilat ve finansal verileri görür ve düzenler. Kullanıcı/rol yönetemez.',
            self::Personel => 'Veri girişi yapar; kendi kayıtlarını sınırlı süre düzeltir. Finansal toplamları görmez.',
        };
    }

    /**
     * Rolün sahip olduğu yetkiler (CLAUDE.md §7.3 rol matrisi).
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),

            self::Patron => [
                Permission::CompaniesView,
                Permission::CompaniesCreate,
                Permission::CompaniesUpdate,
                Permission::CompaniesDelete,
                Permission::StaysView,
                Permission::StaysCreate,
                Permission::StaysUpdate,
                Permission::StaysDelete,
                Permission::PaymentsView,
                Permission::PaymentsCreate,
                Permission::PaymentsCancel,
                Permission::LedgerView,
                Permission::LedgerViewAll,
                Permission::DashboardFinancial,
                Permission::ExportExcel,
                Permission::AuditView,
            ],

            self::Personel => [
                Permission::CompaniesView,
                Permission::CompaniesCreate,
                Permission::CompaniesUpdate,
                Permission::StaysView,
                Permission::StaysCreate,
                Permission::StaysUpdate,
                Permission::PaymentsView,
                Permission::PaymentsCreate,
            ],
        };
    }
}
