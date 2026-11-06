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

    /** Ortak Çöp Kutusu ekranını görüntüle / kayıt geri al (6 Kasım 2026). */
    case TrashView = 'trash.view';

    /**
     * Çöp kutusundaki kaydı veritabanından KALICI sil — yalnızca Admin
     * (RoleName::Admin tüm yetkileri alır; Policy/sağlayıcı ayrıca rolü doğrular).
     */
    case TrashForceDelete = 'trash.force_delete';

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

    /**
     * Makbuz düzenleme/silme — kullanıcı talimatıyla (27 Eylül 2026) yalnızca
     * Admin'e atanır. Makbuz defteri muhasebe kaydı DEĞİLDİR (cari/kasa yan
     * etkisi yok), bu yüzden düzeltme ters kayıt değil doğrudan düzenlemedir;
     * her değişiklik ve silme `Auditable` ile eski değerleriyle loglanır.
     */
    case ReceiptBookUpdate = 'receipt_book.update';
    case ReceiptBookDelete = 'receipt_book.delete';

    case OffersView = 'offers.view';
    case OffersCreate = 'offers.create';
    case OffersUpdate = 'offers.update';

    case MonthlyAccountView = 'monthly_account.view';
    case MonthlyAccountCreate = 'monthly_account.create';
    case MonthlyAccountUpdate = 'monthly_account.update';
    case MonthlyAccountDelete = 'monthly_account.delete';
    case MonthlyAccountViewAll = 'monthly_account.view_all';
    case MonthlyAccountManageProviders = 'monthly_account.manage_providers';

    case PersonnelView = 'personnel.view';
    case PersonnelManage = 'personnel.manage';
    case PersonnelAttendance = 'personnel.attendance';
    case PersonnelPayroll = 'personnel.payroll';
    case PersonnelScan = 'personnel.scan';
    case PersonnelTerminals = 'personnel.terminals';

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
            self::TrashView, self::TrashForceDelete => 'Çöp Kutusu',
            self::PaymentsView, self::PaymentsCreate, self::PaymentsCancel => 'Tahsilatlar',
            self::LedgerView, self::LedgerViewAll => 'Cari Hesap',
            self::DashboardFinancial => 'Dashboard',
            self::ExportExcel => 'Dışa Aktarma',
            self::AuditView => 'Denetim Kaydı',
            self::FxCorrectHistorical => 'Geçmiş Döviz Düzeltme',
            self::ReceiptBookView, self::ReceiptBookCreate,
            self::ReceiptBookUpdate, self::ReceiptBookDelete => 'Tahsilat Makbuzu Defteri',
            self::OffersView, self::OffersCreate, self::OffersUpdate => 'Teklifler',
            self::MonthlyAccountView, self::MonthlyAccountCreate, self::MonthlyAccountUpdate,
            self::MonthlyAccountDelete, self::MonthlyAccountViewAll,
            self::MonthlyAccountManageProviders => 'Aylık Hesap',
            self::PersonnelView, self::PersonnelManage, self::PersonnelAttendance,
            self::PersonnelPayroll, self::PersonnelScan, self::PersonnelTerminals => 'Personel Takibi',
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
            self::TrashView => 'Çöp kutusunu görüntüle ve kayıt geri al',
            self::TrashForceDelete => 'Çöp kutusundaki kaydı kalıcı olarak sil',
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
            self::ReceiptBookUpdate => 'Tahsilat makbuzunu düzenle',
            self::ReceiptBookDelete => 'Tahsilat makbuzunu sil',
            self::OffersView => 'Teklifleri görüntüle ve yazdır',
            self::OffersCreate => 'Teklif oluştur',
            self::OffersUpdate => 'Teklif düzenle',
            self::MonthlyAccountView => 'Aylık hesabı görüntüle',
            self::MonthlyAccountCreate => 'Günlük hesap ekle',
            self::MonthlyAccountUpdate => 'Günlük hesabı düzenle',
            self::MonthlyAccountDelete => 'Günlük hesabı sil / geri getir',
            self::MonthlyAccountViewAll => 'Diğer kullanıcıların hesap alanlarına eriş',
            self::MonthlyAccountManageProviders => 'Banka / ödeme kuruluşu tanımlarını yönet',
            self::PersonnelView => 'Personel ve puantajı görüntüle',
            self::PersonnelManage => 'Personel, vardiya ve kartları yönet',
            self::PersonnelAttendance => 'Puantaj düzelt',
            self::PersonnelPayroll => 'Hakediş ve ödemeleri yönet',
            self::PersonnelScan => 'Kart okut',
            self::PersonnelTerminals => 'Terminal erişimini yönet',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
