<?php

namespace App\Models;

use Eloquent;

class OptionalFeeReceipt extends Eloquent
{
    protected $fillable = ['charge_id', 'amt_paid', 'balance', 'year', 'method', 'reference'];

    public function charge()
    {
        return $this->belongsTo(OptionalFeeCharge::class, 'charge_id');
    }
}
