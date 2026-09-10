<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

use RuntimeException;

/**
 * CLAUDE.md §7.5: son aktif admin pasifleştirilemez, silinemez ve rolü alınamaz.
 * Aksi halde sisteme yönetici erişimi tamamen kaybedilir.
 */
class LastActiveAdminException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Bu kullanıcı sistemdeki son aktif yöneticidir. '.
            'Önce başka bir kullanıcıya Admin rolü verin.'
        );
    }
}
