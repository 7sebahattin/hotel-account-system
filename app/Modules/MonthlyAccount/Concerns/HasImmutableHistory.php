<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Concerns;

use LogicException;

trait HasImmutableHistory
{
    protected static function bootHasImmutableHistory(): void
    {
        static::updating(function (): void {
            throw new LogicException('Aylık hesap geçmişi değiştirilemez; yeni sürüm kaydedin.');
        });

        static::deleting(function (): void {
            throw new LogicException('Aylık hesap geçmişi silinemez.');
        });
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Aylık hesap geçmişi değiştirilemez; yeni sürüm kaydedin.');
    }

    public function delete(): bool
    {
        throw new LogicException('Aylık hesap geçmişi silinemez.');
    }
}
