<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class OfferPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'start_date', 'end_date', 'sort_order'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
