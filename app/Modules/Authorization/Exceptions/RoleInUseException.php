<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Exceptions;

use RuntimeException;

/** Kullanıcıya atanmış rol silinemez. */
class RoleInUseException extends RuntimeException
{
    public static function make(string $name, int $userCount): self
    {
        return new self("'{$name}' rolü {$userCount} kullanıcıya atanmış durumda, silinemez.");
    }
}
