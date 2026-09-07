<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;

/**
 * Ledger modülünün HTTP arayüzü yoktur: route, controller ve görünüm dosyası
 * bulunmaz. Cari ekstre ekranı Gün 8'de gelecektir.
 */
class LedgerServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'ledger';
}
