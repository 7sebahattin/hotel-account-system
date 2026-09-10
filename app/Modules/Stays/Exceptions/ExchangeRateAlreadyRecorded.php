<?php

declare(strict_types=1);

namespace App\Modules\Stays\Exceptions;

use RuntimeException;

/**
 * Geçmiş Döviz Düzeltme aracı yalnızca EKSİK kaydı doldurur (v2.1.1-C §3).
 *
 * Kur zaten kayıtlıysa (ister orijinal girişte ister önceki bir düzeltmede)
 * ikinci kez "düzeltme" yapılamaz — bu, normal ekranlardan rastgele
 * düzenlemeyi engelleyen kuralın aynısıdır.
 */
class ExchangeRateAlreadyRecorded extends RuntimeException
{
    public static function make(int $stayId): self
    {
        return new self("#{$stayId} numaralı konaklamanın kur bilgisi zaten kayıtlı; tekrar düzeltilemez.");
    }
}
