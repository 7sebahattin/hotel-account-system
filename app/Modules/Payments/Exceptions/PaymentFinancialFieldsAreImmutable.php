<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Tahsilatın finansal alanları oluşturulduktan sonra DEĞİŞTİRİLEMEZ
 * (CLAUDE.md §8). Düzeltme yolu: iptal + yeniden giriş.
 */
class PaymentFinancialFieldsAreImmutable extends RuntimeException
{
    /** @param array<int, string> $fields */
    public static function make(array $fields): self
    {
        return new self(
            'Tahsilatın finansal alanları değiştirilemez ('.implode(', ', $fields).'). '
            .'Düzeltme için tahsilatı iptal edip yeniden girin.'
        );
    }
}
