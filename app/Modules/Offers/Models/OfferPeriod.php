<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $offer_id
 * @property string $label
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int $sort_order
 */
class OfferPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'start_date', 'end_date', 'sort_order'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
