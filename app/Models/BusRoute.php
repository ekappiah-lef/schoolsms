<?php

namespace App\Models;

use Eloquent;

class BusRoute extends Eloquent
{
    public const DIRECTIONS = ['in' => 'In only', 'out' => 'Out only', 'both' => 'In & out'];

    protected $fillable = ['name', 'amount_both', 'amount_one_way', 'active'];

    protected $casts = ['active' => 'boolean', 'amount_both' => 'float', 'amount_one_way' => 'float'];

    public function priceFor(string $direction): float
    {
        return \App\Support\Money::r(($direction === 'both' ? $this->amount_both : $this->amount_one_way));
    }
}
