<?php

declare(strict_types=1);

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

/**
 * CLAUDE.md §5.2: cari hareketi olan firma silinemez — Admin dahil kimse.
 *
 * Firma silinirse hareketleri sahipsiz kalır ve bakiye izlenemez hale gelir.
 * Kullanılmayan firma için doğru işlem pasifleştirmektir.
 */
class CompanyHasLedgerEntries extends RuntimeException
{
    public static function make(string $companyName): self
    {
        return new self(
            "'{$companyName}' firmasının cari hareketleri bulunduğu için silinemez. ".
            'Kullanımdan kaldırmak için firmayı pasif duruma alın.'
        );
    }
}
