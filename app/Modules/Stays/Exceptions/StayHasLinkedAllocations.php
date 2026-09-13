<?php

declare(strict_types=1);

namespace App\Modules\Stays\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Bu konaklamaya bugüne kadar bir ödeme dağılımı (payment_allocations)
 * uygulanmışsa — geçmişte iptal/serbest bırakılmış olsa bile, çünkü o tablo
 * da append-only'dir — veritabanı `stay_id` FK kısıtı (RESTRICT) hard-delete'i
 * reddeder. Bu, kayıt kaybı yerine anlaşılır bir hata olarak yüzeye çıkar.
 */
class StayHasLinkedAllocations extends RuntimeException
{
    public static function make(int $stayId, ?Throwable $previous = null): self
    {
        return new self(
            "#{$stayId} numaralı konaklama silinemedi: bu kayda daha önce bir ödeme dağılımı uygulanmış (geçmişte serbest bırakılmış olsa bile).",
            previous: $previous,
        );
    }
}
