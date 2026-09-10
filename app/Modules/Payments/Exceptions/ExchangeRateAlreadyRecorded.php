<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Geçmiş Döviz Düzeltme aracı yalnızca EKSİK kaydı doldurur (v2.1.1-C §3).
 *
 * Kur zaten kayıtlıysa ikinci kez "düzeltme" yapılamaz — `receipt_no` ve
 * diğer değişmez alanlarla AYNI ilke: tek seferlik, geri dönüşü olmayan yazma.
 */
class ExchangeRateAlreadyRecorded extends RuntimeException
{
    public static function make(int $paymentId): self
    {
        return new self("#{$paymentId} numaralı tahsilatın kur bilgisi zaten kayıtlı; tekrar düzeltilemez.");
    }
}
