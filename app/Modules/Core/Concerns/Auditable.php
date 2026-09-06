<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelin created / updated / deleted / restored olaylarını denetim kaydına yazar.
 *
 * Yalnızca DEĞİŞEN alanlar kaydedilir; hassas alanlar AuditLogger tarafından maskelenir.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log(
                AuditEvent::Created,
                $model,
                newValues: $model->auditableValues($model->getAttributes()),
            );
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableValues($model->getChanges());

            // Yalnızca gürültü alanları değiştiyse (ör. updated_at) kayıt açma.
            if ($changes === []) {
                return;
            }

            app(AuditLogger::class)->log(
                AuditEvent::Updated,
                $model,
                oldValues: array_intersect_key($model->getOriginal(), $changes),
                newValues: $changes,
            );
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->log(AuditEvent::Deleted, $model);
        });

        // 'restored' olayı yalnızca SoftDeletes kullanan modellerde tanımlıdır.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model): void {
                app(AuditLogger::class)->log(AuditEvent::Restored, $model);
            });
        }
    }

    /**
     * Denetim kaydına girmeyecek, bilgi taşımayan alanlar.
     *
     * @return array<int, string>
     */
    protected function auditIgnored(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditableValues(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditIgnored()));
    }
}
