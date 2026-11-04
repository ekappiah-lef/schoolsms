<?php

namespace App\Models;

use Eloquent;

class OptionalFeeReceipt extends Eloquent
{
    protected $fillable = ['charge_id', 'amt_paid', 'balance', 'year', 'paid_on', 'method', 'reference'];

    // Money: cedis with pesewas
    protected $casts = ['amt_paid' => 'float', 'balance' => 'float', 'paid_on' => 'date'];

    protected static function booted()
    {
        static::creating(function ($r) {
            $r->paid_on = $r->paid_on ?: today();
        });
    }

    public function charge()
    {
        return $this->belongsTo(OptionalFeeCharge::class, 'charge_id');
    }
}
