<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * V1'in tüm yetkileri (CLAUDE.md §7.2).
 *
 * Kod içinde yetki adı serbest string olarak yazılmaz; her yerde bu enum kullanılır.
 * Route middleware'inde string gerektiğinde ->value ile verilir.
 */
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    case RolesManage = 'roles.manage';

    case CompaniesView = 'companies.view';
    case CompaniesCreate = 'companies.create';
    case CompaniesUpdate = 'companies.update';
    case CompaniesDelete = 'companies.delete';
    case CompaniesRestore = 'companies.restore';

    case StaysView = 'stays.view';
    case StaysCreate = 'stays.create';
    case StaysUpdate = 'stays.update';
    case StaysDelete = 'stays.delete';

    /** Oda tanımları (Yönetim → Odalar). Görüntüleme ayrı yetki değildir. */
    case RoomsManage = 'rooms.manage';

    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsCancel = 'payments.cancel';

    case LedgerView = 'ledger.view';
    case LedgerViewAll = 'ledger.view_all';

    case DashboardFinancial = 'dashboard.financial';

    case ExportExcel = 'export.excel';

    case AuditView = 'audit.view';

    /** Geçmiş Döviz Düzeltme — yalnızca Admin/Patron (v2.1.1-C §3). */
    case FxCorrectHistorical = 'fx.correct_historical';

    /** Tahsilat Makbuzu Defteri — mevcut muhasebe Tahsilatlarından AYRI, yazdırma amaçlı modül (v2.1.1-C §4). */
    case ReceiptBookView = 'receipt_book.view';
    case ReceiptBookCreate = 'receipt_book.create';

    /** Yetki yönetimi ekranındaki gruplama başlığı. */
    public function group(): string
    {
        return match ($this) {
            self::UsersView, self::UsersManage => 'Kullanıcılar',
            self::RolesManage => 'Roller ve Yetkiler',
            self::CompaniesView, self::CompaniesCreate, self::CompaniesUpdate,
            self::CompaniesDelete, self::CompaniesRestore => 'Firmalar',
            self::StaysView, self::StaysCreate,
            self::StaysUpdate, self::StaysDelete => 'Konaklamalar',
            self::RoomsManage => 'Odalar',
            self::PaymentsView, self::PaymentsCreate, self::PaymentsCancel => 'Tahsilatlar',
            self::LedgerView, self::LedgerViewAll => 'Cari Hesap',
            self::DashboardFinancial => 'Dashboard',
            self::ExportExcel => 'Dışa Aktarma',
            self::AuditView => 'Denetim Kaydı',
            self::FxCorrectHistorical => 'Geçmiş Döviz Düzeltme',
            self::ReceiptBookView, self::ReceiptBookCreate => 'Tahsilat Makbuzu Defteri',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UsersView => 'Kullanıcıları görüntüle',
            self::UsersManage => 'Kullanıcı ekle / düzenle / pasifleştir',
            self::RolesManage => 'Rol ve yetki yönetimi',
            self::CompaniesView => 'Firmaları görüntüle',
            self::CompaniesCreate => 'Firma ekle',
            self::CompaniesUpdate => 'Firma düzenle',
            self::CompaniesDelete => 'Firma sil',
            self::CompaniesRestore => 'Silinmiş firmayı geri al',
            self::StaysView => 'Konaklamaları görüntüle',
            self::StaysCreate => 'Konaklama ekle',
            self::StaysUpdate => 'Konaklama düzenle',
            self::StaysDelete => 'Konaklama sil',
            self::RoomsManage => 'Oda tanımlarını yönet',
            self::PaymentsView => 'Tahsilatları görüntüle',
            self::PaymentsCreate => 'Cari tahsilat',
            self::PaymentsCancel => 'Tahsilat iptal et',
            self::LedgerView => 'Firma cari ekstresini görüntüle',
            self::LedgerViewAll => 'Tüm firma bakiyelerini ve toplam alacağı görüntüle',
            self::DashboardFinancial => 'Finansal dashboard kartlarını görüntüle',
            self::ExportExcel => 'Excel dışa aktarma',
            self::AuditView => 'Denetim kaydını görüntüle',
            self::FxCorrectHistorical => 'Geçmiş kaydın eksik döviz kurunu düzelt',
            self::ReceiptBookView => 'Tahsilat makbuzu defterini görüntüle / yazdır',
            self::ReceiptBookCreate => 'Tahsilat makbuzu defterine yeni makbuz ekle',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
