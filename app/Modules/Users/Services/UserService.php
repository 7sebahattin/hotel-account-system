<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Core\Trash\TrashActionFailed;
use App\Modules\Users\Exceptions\LastActiveAdminException;
use App\Modules\Users\Exceptions\SelfActionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kullanıcı yönetiminin dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Rol atamaları spatie'nin string tabanlı API'si üzerinden yapılır; bu modül
 * Authorization modülünün Role modelini doğrudan kullanmaz.
 */
class UserService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, email: string, password: string, is_active?: bool}  $data
     */
    public function create(array $data, string $role, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $role, $actor): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->created_by = $actor?->getKey();
            $user->save();

            $this->assignRole($user, $role);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, is_active?: bool}  $data
     */
    public function update(User $user, array $data, ?string $role = null): void
    {
        DB::transaction(function () use ($user, $data, $role): void {
            $user->name = $data['name'];
            $user->email = $data['email'];

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            if (array_key_exists('is_active', $data)) {
                $this->guardActiveState($user, (bool) $data['is_active']);
                $user->is_active = (bool) $data['is_active'];
            }

            $user->save();

            if ($role !== null) {
                $this->assignRole($user, $role);
            }
        });
    }

    public function setActive(User $user, bool $active, ?User $actor = null): void
    {
        if (! $active && $actor !== null && $actor->is($user)) {
            throw SelfActionException::cannotDeactivateSelf();
        }

        $this->guardActiveState($user, $active);

        $user->is_active = $active;
        $user->save();
    }

    /**
     * Çöp kutusundaki (soft delete) kullanıcılar.
     *
     * @return Collection<int, User>
     */
    public function trashed(int $limit = 300): Collection
    {
        return User::onlyTrashed()->orderByDesc('deleted_at')->limit($limit)->get();
    }

    public function trashedCount(): int
    {
        return User::onlyTrashed()->count();
    }

    public function findIncludingTrashed(int $id): User
    {
        return User::withTrashed()->findOrFail($id);
    }

    public function restoreFromTrash(User $user): void
    {
        $user->restore();
    }

    /**
     * Çöpteki kullanıcıyı KALICI siler — yalnızca Admin. Kullanıcı kayıt oluşturduysa
     * (created_by, denetim vb.) veritabanı bağlantıları silmeyi reddeder.
     *
     * @throws TrashActionFailed
     */
    public function forceDelete(User $user, User $actor): void
    {
        if (! $actor->hasRole(RoleName::Admin->value)) {
            throw new AuthorizationException('Kalıcı silme yalnızca admin hesabına açıktır.');
        }
        if (! $user->trashed()) {
            throw new TrashActionFailed('Kullanıcı çöp kutusunda değil.');
        }

        try {
            DB::transaction(fn () => $user->forceDelete());
        } catch (\Throwable $e) {
            if ($e instanceof QueryException) {
                throw new TrashActionFailed("«{$user->name}» kullanıcısı sistemde kayıt oluşturduğu için kalıcı silinemedi; kayıt çöpte kaldı.", 0, $e);
            }
            throw $e;
        }
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($actor !== null && $actor->is($user)) {
            throw SelfActionException::cannotDeleteSelf();
        }

        if ($this->isLastActiveAdmin($user)) {
            throw LastActiveAdminException::make();
        }

        $user->delete();
    }

    /**
     * Rolü uygular ve değişiklik varsa denetim kaydı yazar.
     * Bir kullanıcı tek role sahiptir; rol değişimi eskisini kaldırır.
     */
    public function assignRole(User $user, string $role): void
    {
        $before = $user->getRoleNames()->sort()->values()->all();

        if ($before === [$role]) {
            return;
        }

        if ($before === [RoleName::Admin->value] && $role !== RoleName::Admin->value
            && $this->isLastActiveAdmin($user)) {
            throw LastActiveAdminException::make();
        }

        $user->syncRoles([$role]);

        $this->auditLogger->log(
            event: AuditEvent::PermissionChanged,
            subject: $user,
            oldValues: ['roles' => $before],
            newValues: ['roles' => [$role]],
        );
    }

    /**
     * Kullanıcı sistemdeki son aktif admin mi?
     *
     * Silinmiş (soft delete) kullanıcılar sayılmaz — geri getirilene kadar
     * sisteme giriş yapamazlar.
     */
    public function isLastActiveAdmin(User $user): bool
    {
        if (! $user->is_active || ! $user->hasRole(RoleName::Admin->value)) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Admin->value))
            ->doesntExist();
    }

    private function guardActiveState(User $user, bool $active): void
    {
        if (! $active && $this->isLastActiveAdmin($user)) {
            throw LastActiveAdminException::make();
        }
    }
}
