<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $platform
 * @property string $device_uuid
 * @property string $token_hash
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 * @property-read string|null $last_seen_at_label
 */
class PersonnelTerminal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_terminals';

    protected $fillable = ['name', 'platform', 'device_uuid', 'token_hash', 'token_prefix', 'revoked_at', 'created_by', 'last_seen_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function getLastSeenAtLabelAttribute(): ?string
    {
        return $this->last_seen_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }
}
