<?php

declare(strict_types=1);

namespace App\Modules\Trash\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;

/**
 * Ortak Çöp Kutusu (6 Kasım 2026, kullanıcı talimatı): silinen kayıtlar türüne
 * göre gruplanır; geri alma ve (yalnızca Admin için) kalıcı silme buradadır.
 * Modül başka modülün modeline dokunmaz; yalnızca Core\Trash sözleşmesini bilir.
 */
class TrashServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'trash';
}
