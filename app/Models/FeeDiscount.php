<?php

namespace App\Models;

use Eloquent;

/** Discount on tuition, e.g. "Director's special package" (100%). */
class FeeDiscount extends Eloquent
{
    protected $fillable = ['name', 'percent', 'active'];

    protected $casts = ['active' => 'boolean'];
}
