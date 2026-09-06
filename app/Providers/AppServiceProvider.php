<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
            'role' => Role::class,
        ]);

        // CLAUDE.md §7.1: admin rolü yetki kontrollerini geçer.
        //
        // ÖNEMLİ: kısayol yalnızca ARGÜMANSIZ yetki kontrolleri için geçerlidir
        // (ör. can:'users.manage' middleware'i). Model/kayıt argümanı taşıyan
        // Policy yetenekleri kısa devre YAPILMAZ; aksi halde "kendi hesabını
        // silememe" ve "sistem rolünü koruma" gibi kayıt bazlı kurallar admin
        // için hiç çalışmazdı.
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            $subject = $arguments[0] ?? null;
            $isAuditLog = $subject instanceof AuditLog || $subject === AuditLog::class;

            // Mutlak kural: denetim kaydı hiç kimse tarafından değiştirilemez/silinemez.
            if ($isAuditLog && in_array($ability, ['update', 'delete', 'forceDelete', 'restore'], true)) {
                return false;
            }

            if ($arguments !== []) {
                return null;
            }

            return $user->hasRole(RoleName::Admin->value) ? true : null;
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
