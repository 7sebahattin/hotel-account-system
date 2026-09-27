<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uid
 * @property string|null $label
 * @property bool $is_active
 * @property-read string $masked_uid
 */
class PersonnelCard extends Model
{
    protected $table = 'personnel_cards';

    protected $fillable = ['uid', 'label', 'technology', 'is_active', 'created_by', 'updated_by'];

    protected $hidden = ['uid'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getMaskedUidAttribute(): string
    {
        return str_repeat('•', max(0, strlen($this->uid) - 4)).substr($this->uid, -4);
    }
}
