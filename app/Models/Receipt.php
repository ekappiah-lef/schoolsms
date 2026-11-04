<?php

namespace App\Models;

use App\User;
use Eloquent;

class Receipt extends Eloquent
{
    protected $fillable = ['pr_id', 'year', 'balance', 'amt_paid', 'paid_on', 'method', 'reference'];

    // Money: cedis with pesewas
    protected $casts = ['amt_paid' => 'float', 'balance' => 'float', 'paid_on' => 'date'];

    protected static function booted()
    {
        static::creating(function ($r) {
            $r->paid_on = $r->paid_on ?: today();
        });
    }

    public function pr()
    {
        return $this->belongsTo(PaymentRecord::class, 'pr_id');
    }

}
