<?php

declare(strict_types=1);

namespace App\Modules\Stays\Exceptions;

use RuntimeException;

/**
 * İptal edilmiş konaklama düzenlenemez.
 *
 * V1'de iptali geri alma akışı YOKTUR; yanlış iptal edilen konaklama için
 * yeni bir konaklama kaydı açılır.
 */
class CannotUpdateCancelledStay extends RuntimeException
{
    public static function make(int $stayId): self
    {
        return new self(
            "#{$stayId} numaralı konaklama iptal edilmiş durumda ve düzenlenemez. ".
            'Gerekirse yeni bir konaklama kaydı oluşturun.'
        );
    }
}
