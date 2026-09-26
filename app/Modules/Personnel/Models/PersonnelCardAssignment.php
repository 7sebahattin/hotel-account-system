<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $card_id
 * @property int $employee_id
 * @property-read PersonnelCard $card
 */
class PersonnelCardAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_card_assignments';

    protected $fillable = ['card_id', 'employee_id', 'assigned_at', 'released_at', 'assigned_by', 'released_by'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(PersonnelCard::class, 'card_id');
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Kart ataması silinemez.'));
    }
}
