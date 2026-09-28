<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class OfferPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_period_id', 'offer_room_type_id', 'price'];
}
