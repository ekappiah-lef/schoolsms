<?php

namespace App\Models;

use Eloquent;

class OptionalFeeReceipt extends Eloquent
{
    protected $fillable = ['charge_id', 'amt_paid', 'balance', 'year', 'method', 'reference'];

    // Money: cedis with pesewas
    protected $casts = ['amt_paid' => 'float', 'balance' => 'float'];

    public function charge()
    {
        return $this->belongsTo(OptionalFeeCharge::class, 'charge_id');
    }
}
