<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * created_by / updated_by alanlarını otomatik doldurur (CLAUDE.md §4.2).
 *
 * updated_by, 'updating' olayında yazılır. Bu bilinçlidir: 'saving' kullanılsaydı
 * kayıt hiç değişmese bile model kirlenir ve gereksiz UPDATE + denetim kaydı üretirdi.
 */
trait HasCreator
{
    public static function bootHasCreator(): void
    {
        static::creating(function (self $model): void {
            $userId = Auth::id();

            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function (self $model): void {
            $model->updated_by = Auth::id() ?? $model->updated_by;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
