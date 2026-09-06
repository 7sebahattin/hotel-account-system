<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Enums;

enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Cancelled = 'cancelled';
    case Exported = 'exported';
    case PermissionChanged = 'permission_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Oluşturuldu',
            self::Updated => 'Güncellendi',
            self::Deleted => 'Silindi',
            self::Restored => 'Geri alındı',
            self::Login => 'Giriş yapıldı',
            self::Logout => 'Çıkış yapıldı',
            self::LoginFailed => 'Başarısız giriş',
            self::Cancelled => 'İptal edildi',
            self::Exported => 'Dışa aktarıldı',
            self::PermissionChanged => 'Yetki değişikliği',
        };
    }
}
