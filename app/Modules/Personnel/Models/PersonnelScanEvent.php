<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $request_id
 * @property string $payload_hash
 * @property int|null $employee_id
 * @property int|null $terminal_id
 * @property int|null $actor_user_id
 * @property string $result
 * @property string|null $action
 * @property Carbon $occurred_at
 */
class PersonnelScanEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_scan_events';

    protected $fillable = ['request_id', 'payload_hash', 'card_id', 'card_assignment_id', 'employee_id', 'terminal_id', 'actor_user_id', 'source', 'platform', 'device_uuid', 'technology', 'occurred_at', 'requested_action', 'action', 'result', 'duplicate_of_id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Ham okutma değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Ham okutma silinemez.'));
    }
}
