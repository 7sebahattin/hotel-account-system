<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Exceptions;

use RuntimeException;

/** Sistem rolü (admin/patron/personel) silinemez ve adı değiştirilemez. */
class SystemRoleException extends RuntimeException
{
    public static function cannotDelete(string $name): self
    {
        return new self("'{$name}' bir sistem rolüdür, silinemez.");
    }

    public static function cannotRename(string $name): self
    {
        return new self("'{$name}' bir sistem rolüdür, adı değiştirilemez.");
    }
}
