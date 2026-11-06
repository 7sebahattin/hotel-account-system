<?php

declare(strict_types=1);

namespace App\Modules\Core\Providers;

use App\Modules\Core\Trash\TrashRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Tüm modül ServiceProvider'larının ortak tabanı.
 *
 * Modül klasöründe hangi dizinler varsa onları otomatik kaydeder; olmayan
 * dizin için hiçbir şey yapmaz. Böylece her modül yalnızca gerçekten
 * ihtiyaç duyduğu klasörü açar (CLAUDE.md §4.1).
 *
 * Beklenen modül düzeni:
 *   Config/<namespace>.php      -> config('<namespace>.*')
 *   Database/Migrations/        -> otomatik yüklenir
 *   Resources/views/            -> view('<namespace>::...')
 *   Resources/lang/             -> __('<namespace>::...')
 *   Routes/web.php              -> 'web' middleware grubu içinde
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Modülün view / çeviri / config namespace'i. Örn: 'auth' */
    protected string $namespace;

    /**
     * Model => Policy eşlemesi (CLAUDE.md §7.1: yetki kararı yalnızca Policy'de).
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    /**
     * Çöp kutusu sağlayıcıları (Core\Trash\TrashProvider uygulayan sınıflar).
     * Modül, silinebilir kayıt türlerini burada ortak çöp kutusuna bağlar.
     *
     * @var array<int, class-string<\App\Modules\Core\Trash\TrashProvider>>
     */
    protected array $trashProviders = [];

    private ?string $modulePath = null;

    public function register(): void
    {
        $this->app->singleton(TrashRegistry::class);

        $config = $this->path('Config/'.$this->namespace.'.php');

        if (is_file($config)) {
            $this->mergeConfigFrom($config, $this->namespace);
        }
    }

    public function boot(): void
    {
        if (is_dir($views = $this->path('Resources/views'))) {
            $this->loadViewsFrom($views, $this->namespace);
        }

        if (is_dir($lang = $this->path('Resources/lang'))) {
            $this->loadTranslationsFrom($lang, $this->namespace);
        }

        if (is_dir($migrations = $this->path('Database/Migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        foreach ($this->trashProviders as $provider) {
            $this->app->make(TrashRegistry::class)->register($provider);
        }

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Rota önbelleği varken dosya yüklenmez (Laravel'in loadRoutesFrom() ile
        // aynı kural). Her istekte yüklenen rota dosyası opcache'te sürekli sıcak
        // kalıyordu; yayın sonrası `route:cache` bu yüzden ESKİ bayt kodla
        // üretildi ve yeni rotalar önbelleğe girmedi (27 Eylül 2026, v8).
        if (! $this->app->routesAreCached() && is_file($routes = $this->path('Routes/web.php'))) {
            Route::middleware('web')->group($routes);
        }
    }

    /** Modül kökünden göreli yol. Provider Providers/ altında olduğu için iki seviye yukarı. */
    protected function path(string $relative = ''): string
    {
        $this->modulePath ??= dirname((new ReflectionClass($this))->getFileName(), 2);

        return $relative === '' ? $this->modulePath : $this->modulePath.'/'.$relative;
    }
}
