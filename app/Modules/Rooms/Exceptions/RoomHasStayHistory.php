<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Exceptions;

use RuntimeException;

/** Konaklama geçmişi olan bir oda hard-delete edilmek istendiğinde fırlatılır. */
class RoomHasStayHistory extends RuntimeException
{
    public static function make(string $roomNo): self
    {
        return new self(sprintf(
            '"%s" odası bir veya daha fazla konaklama kaydında kullanılmıştır, silinemez. Yerine pasifleştirin.',
            $roomNo,
        ));
    }
}
