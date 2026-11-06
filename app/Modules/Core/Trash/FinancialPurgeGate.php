<?php

declare(strict_types=1);

namespace App\Modules\Core\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Çöp kutusundan kalıcı silmede, ledger_entries ve payment_allocations
 * üzerindeki DELETE tetikleyicilerinin AÇTIĞI TEK kapı (CLAUDE.md 6 Kasım 2026).
 *
 * İzin BAĞLANTIYA bağlı MySQL oturum değişkenleridir ve yalnızca bu sınıf
 * tarafından, yalnızca ÇAĞRIDA SAYILAN satır kimlikleri için, 60 saniyeliğine ve
 * yalnızca aktif bir Admin adına açılır; işlem bitince (hata olsa bile) kapanır.
 * Tetikleyici kimlik listesinde olmayan satırı, süresi dolmuş izni veya Admin
 * olmayan aktörü reddeder. UPDATE korumaları bu kapıdan etkilenmez.
 */
class FinancialPurgeGate
{
    /**
     * @param  array<int, int>  $ledgerIds
     * @param  array<int, int>  $allocationIds
     * @param  Closure(): mixed  $callback  Kapı yalnızca bu callback sürerken açıktır.
     */
    public function run(User $actor, array $ledgerIds, array $allocationIds, Closure $callback): mixed
    {
        if (! $actor->hasRole(RoleName::Admin->value) || ! $actor->is_active) {
            throw new AuthorizationException('Kalıcı silme yalnızca aktif admin hesabına açıktır.');
        }

        $this->open($actor, $ledgerIds, $allocationIds);

        try {
            return $callback();
        } finally {
            $this->close();
        }
    }

    /**
     * @param  array<int, int>  $ledgerIds
     * @param  array<int, int>  $allocationIds
     */
    private function open(User $actor, array $ledgerIds, array $allocationIds): void
    {
        DB::statement('SET @lemon_purge_actor = ?', [$actor->getKey()]);
        DB::statement('SET @lemon_purge_until = ?', [Carbon::now('UTC')->addMinute()->format('Y-m-d H:i:s')]);
        DB::statement('SET @lemon_purge_ledger_ids = ?', [implode(',', array_map('intval', $ledgerIds))]);
        DB::statement('SET @lemon_purge_allocation_ids = ?', [implode(',', array_map('intval', $allocationIds))]);
    }

    private function close(): void
    {
        DB::statement('SET @lemon_purge_actor = NULL, @lemon_purge_until = NULL, @lemon_purge_ledger_ids = NULL, @lemon_purge_allocation_ids = NULL');
    }
}
