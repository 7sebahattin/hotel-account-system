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

    /**
     * Kalıcı silme (hard delete) — stays.delete'ten AYRI ve BİLEREK dar bir
     * yetkidir. stays.delete zaten "İptal et" akışına bağlıdır (CLAUDE.md
     * §7.2 notu) ve Patron'da da vardır; bu yeni yetki yalnızca Admin'e
     * atanır (RoleName::permissions()) ve StayPolicy::forceDelete() onu
     * yalnızca ZATEN İPTAL EDİLMİŞ bir kayıtta kabul eder — ledger append-only
     * olduğu için (§6/L1) aktif bir kaydın borcu hard-delete ile kaldırılamaz.
     */
    case StaysForceDelete = 'stays.force_delete';

    /** Oda tanımları (Yönetim → Odalar). Görüntüleme ayrı yetki DEĞİLDİR — cashbook.manage_categories ile aynı desen. */
    case RoomsManage = 'rooms.manage';

    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsCancel = 'payments.cancel';

    case LedgerView = 'ledger.view';
    case LedgerViewAll = 'ledger.view_all';

    /*
     * Kasa defteri (Gün 10). Üç yetki yeterlidir; hareket/ters kayıt için ayrı
     * yetki AÇILMAZ: kayıt bazlı kısıt (kendi açık vardiyası) Policy'dedir.
     */
    case CashBookView = 'cashbook.view';
    case CashBookOperate = 'cashbook.operate';
    case CashBookViewAll = 'cashbook.view_all';
    case CashBookManageCategories = 'cashbook.manage_categories';

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
            self::StaysUpdate, self::StaysDelete, self::StaysForceDelete => 'Konaklamalar',
            self::RoomsManage => 'Odalar',
            self::PaymentsView, self::PaymentsCreate, self::PaymentsCancel => 'Tahsilatlar',
            self::LedgerView, self::LedgerViewAll => 'Cari Hesap',
            self::CashBookView, self::CashBookOperate, self::CashBookViewAll,
            self::CashBookManageCategories => 'Kasa Defteri',
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
            self::StaysForceDelete => 'Konaklamayı kalıcı olarak sil (yalnızca iptal edilmiş kayıt)',
            self::RoomsManage => 'Oda tanımlarını yönet',
            self::PaymentsView => 'Tahsilatları görüntüle',
            self::PaymentsCreate => 'Cari tahsilat',
            self::PaymentsCancel => 'Tahsilat iptal et',
            self::LedgerView => 'Firma cari ekstresini görüntüle',
            self::LedgerViewAll => 'Tüm firma bakiyelerini ve toplam alacağı görüntüle',
            self::CashBookView => 'Kasa defterini ve açık vardiyayı görüntüle',
            self::CashBookOperate => 'Kasa hareketi gir, vardiya aç/kapat, devir yap',
            self::CashBookViewAll => 'Tüm vardiyaları, günlük defteri ve kasa farklarını görüntüle',
            self::CashBookManageCategories => 'Kasa hareket türlerini (kategori) yönet',
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
