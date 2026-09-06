<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Denetim kaydı yazan tek kapı (CLAUDE.md §8).
 *
 * Hiçbir modül AuditLog::create() çağırmaz; hepsi buradan geçer.
 */
class AuditLogger
{
    /**
     * Hiçbir koşulda denetim kaydına yazılmayacak alanlar.
     *
     * @var array<int, string>
     */
    private const MASKED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        AuditEvent $event,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?string $subjectLabel = null,
    ): AuditLog {
        $user ??= Auth::user();

        return AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name ?? $subjectLabel,
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->mask($oldValues),
            'new_values' => $this->mask($newValues),
            'ip_address' => Request::ip(),
            'user_agent' => $this->truncate(Request::userAgent(), 255),
            'url' => $this->truncate(Request::fullUrl(), 255),
        ]);
    }

    /**
     * Başarısız giriş: oturum açmış kullanıcı yoktur, denenen e-posta kaydedilir.
     */
    public function logFailedLogin(string $email): AuditLog
    {
        return $this->log(
            event: AuditEvent::LoginFailed,
            newValues: ['email' => $email],
            subjectLabel: $email,
        );
    }

    /**
     * Hassas alanları kaldırır. Şifre alanı hash'li olsa bile loglanmaz.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function mask(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_diff_key($values, array_flip(self::MASKED));
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
