<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

use RuntimeException;

/**
 * Kasa defteri iş kuralı ihlallerinin ortak atası.
 *
 * Controller'lar tek bir tipi yakalayıp kullanıcıya Türkçe mesaj gösterebilsin
 * diye ortak taban kullanılır; mesajlar zaten kullanıcıya gösterilebilir.
 */
class CashBookException extends RuntimeException {}
