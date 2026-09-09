<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Nakit kanallı bir tahsilat, açık vardiyayı yöneten kullanıcı DIŞINDA
 * biri tarafından girilemez/iptal edilemez (v2.1.1-A).
 *
 * CLAUDE.md §8/Kasa Defteri "Hesap verebilirlik" ilkesinin doğal uzantısıdır:
 * bir vardiyaya hareket girmek yalnızca vardiya sahibine açıktır — admin/patron
 * dahil kimse başkasının vardiyasına hareket yazamaz. Nakit tahsilat kaydı da
 * bir kasa hareketi ÜRETTİĞİ için aynı kural geçerlidir.
 */
class CashSessionNotOwnedByActor extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Nakit tahsilat yalnızca açık kasa vardiyasını yöneten kullanıcı tarafından kaydedilebilir.'
        );
    }
}
