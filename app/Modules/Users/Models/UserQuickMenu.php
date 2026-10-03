<?php

declare(strict_types=1);

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuickMenu extends Model
{
    protected $fillable = ['user_id', 'shortcuts'];

    protected function casts(): array
    {
        return ['shortcuts' => 'array'];
    }
}
