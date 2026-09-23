<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;
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
    /** Maskelenen alanın yerine yazılan işaret. Değerin kendisi asla kaydedilmez. */
    public const MASK = '***';

    /**
     * Denetim anlık görüntüsünde bir metin değerinin azami uzunluğu.
     *
     * notes gibi uzun alanlar denetim tablosunu kontrolsüz büyütür. Sınır
     * YALNIZCA denetim kaydına uygulanır; gerçek kayıt hiçbir zaman kesilmez.
     */
    public const MAX_VALUE_LENGTH = 500;

    public const TRUNCATED_SUFFIX = '… (kısaltıldı)';

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
        $user = $this->actor($user);

        return AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            // ?? zaten null zincirini bastırır; ?-> gereksiz olurdu.
            'user_name' => $user->name ?? $subjectLabel,
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
     * Belirli kayıt türlerindeki son veri değişiklikleri (pano "Son
     * Güncellemeler"). Giriş/çıkış/dışa aktarma gibi oturum olayları
     * dahil edilmez. Kullanıcı adı anlık görüntüden (`user_name`) okunur;
     * ilişki yüklenmez. TEK sorgu.
     *
     * @param  array<int, string>  $types  morph map anahtarları
     * @param  array<int, AuditEvent>  $events
     * @return Collection<int, AuditLog>
     */
    public function recentChanges(array $types, array $events, int $limit = 5): Collection
    {
        return AuditLog::query()
            ->whereIn('auditable_type', $types)
            ->whereIn('event', array_map(static fn (AuditEvent $event): string => $event->value, $events))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
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
     * Oturum açmış kullanıcı. Misafir isteklerinde (ör. başarısız giriş) null döner.
     */
    private function actor(?User $user): ?User
    {
        if ($user !== null) {
            return $user;
        }

        return Auth::check() ? Auth::user() : null;
    }

    /**
     * Hassas alanların DEĞERİNİ maskeler, anahtarını korur.
     *
     * Anahtar korunur ki "şifre değiştirildi" bilgisi denetim kaydında görünsün;
     * değer hiçbir zaman yazılmaz — hash'li hali bile.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function mask(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::MASKED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = self::MASK;
            }
        }

        return $this->shorten($values);
    }

    /**
     * Uzun metin değerlerini denetim anlık görüntüsü için kısaltır.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function shorten(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! is_string($value) || mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
                continue;
            }

            $values[$key] = mb_substr($value, 0, self::MAX_VALUE_LENGTH).self::TRUNCATED_SUFFIX;
        }

        return $values;
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
