<?php

namespace App\Models;

use Eloquent;

class BusRoute extends Eloquent
{
    public const DIRECTIONS = ['in' => 'In only', 'out' => 'Out only', 'both' => 'In & out'];

    protected $fillable = ['name', 'amount_both', 'amount_one_way', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function priceFor(string $direction): int
    {
        return (int) ($direction === 'both' ? $this->amount_both : $this->amount_one_way);
    }
}
