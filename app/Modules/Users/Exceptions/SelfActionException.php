<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

use RuntimeException;

/**
 * Kullanıcının kendi erişimini kaybetmesine yol açacak işlemler.
 *
 * Bu kural Policy'de DEĞİL serviste tutulur: Gate::before admin rolü için
 * policy'leri kısa devre yaptığından, policy tek başına admin'i korumaz.
 */
class SelfActionException extends RuntimeException
{
    public static function cannotDeleteSelf(): self
    {
        return new self('Kendi hesabınızı silemezsiniz.');
    }

    public static function cannotDeactivateSelf(): self
    {
        return new self('Kendi hesabınızı pasifleştiremezsiniz.');
    }
}
