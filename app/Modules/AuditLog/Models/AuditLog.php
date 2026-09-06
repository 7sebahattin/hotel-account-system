<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Models;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Denetim kaydı. CLAUDE.md §5.6: değiştirilemez ve silinemez.
 *
 * updated_at kolonu yoktur; update() ve delete() çağrıları exception fırlatır.
 * Yazma yalnızca AuditLogger servisi üzerinden yapılır.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Denetim kaydı güncellenemez. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Denetim kaydı değiştirilemez.');
    }

    /** Denetim kaydı silinemez. */
    public function delete(): bool
    {
        throw new RuntimeException('Denetim kaydı silinemez.');
    }

    /** Görüntülenen kaydın kısa adı. */
    public function subjectLabel(): string
    {
        if ($this->auditable_type === null) {
            return '—';
        }

        return $this->auditable_type.' #'.$this->auditable_id;
    }
}
