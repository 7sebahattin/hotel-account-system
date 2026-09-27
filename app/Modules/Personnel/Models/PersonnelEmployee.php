<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $employee_code
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property int $created_by
 * @property-read string $full_name
 */
class PersonnelEmployee extends Model
{
    protected $table = 'personnel_employees';

    protected $fillable = ['employee_code', 'first_name', 'last_name', 'start_date', 'end_date', 'phone', 'email', 'position', 'department', 'notes', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
