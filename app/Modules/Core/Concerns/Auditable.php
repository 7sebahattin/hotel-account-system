<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;

/**
 * Modelin created / updated / deleted / restored olaylarını denetim kaydına yazar.
 *
 * Yalnızca DEĞİŞEN alanlar kaydedilir; hassas alanlar AuditLogger tarafından maskelenir.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        // Closure parametreleri `self` ile tiplenir: trait içinde `self`,
        // trait'i kullanan sınıfa çözülür (User, Role, Company...).
        static::created(function (self $model): void {
            app(AuditLogger::class)->log(
                AuditEvent::Created,
                $model,
                newValues: $model->auditableValues($model->getAttributes()),
            );
        });

        static::updated(function (self $model): void {
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

        static::deleted(function (self $model): void {
            app(AuditLogger::class)->log(AuditEvent::Deleted, $model);
        });

        // static::restored() yalnızca SoftDeletes trait'i ile gelir; olayı doğrudan
        // kaydetmek her modelde çalışır ve SoftDeletes yoksa hiç tetiklenmez.
        static::registerModelEvent('restored', function (self $model): void {
            app(AuditLogger::class)->log(AuditEvent::Restored, $model);
        });
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
