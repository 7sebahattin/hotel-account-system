<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class OfferRoomType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'sort_order'];
}
