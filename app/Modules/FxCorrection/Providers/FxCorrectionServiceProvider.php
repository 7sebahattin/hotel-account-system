<?php

declare(strict_types=1);

namespace App\Modules\FxCorrection\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;

/**
 * Yönetim → Geçmiş Döviz Düzeltme (v2.1.1-C §3).
 *
 * Kendi migration/model/policy'si YOKTUR — `Stays`/`Payments` Service'lerini
 * çağıran, iki modülün verisini tek ekranda birleştiren ince bir kontrolcü
 * katmanıdır (§4.3/3'teki Ledger ekstre ekranı deseniyle AYNI gerekçe).
 */
class FxCorrectionServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'fx-correction';
}
