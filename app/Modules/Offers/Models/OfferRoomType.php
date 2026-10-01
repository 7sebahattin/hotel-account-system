<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $offer_id
 * @property string $name
 * @property int $sort_order
 */
class OfferRoomType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'sort_order'];
}
