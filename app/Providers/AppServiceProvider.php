<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // CLAUDE.md §13: N+1, sessizce yutulan mass assignment ve eksik
        // attribute erişimi geliştirme/testte hata olarak patlar.
        Model::shouldBeStrict(! $this->app->isProduction());

        // CLAUDE.md §5.7: polimorfik ilişkiler sınıf adı yerine takma ad tutar,
        // böylece sınıf taşınsa/yeniden adlandırılsa da veri bozulmaz.
        // Yeni modüller geldikçe buraya kendi girdilerini ekler.
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
