<?php

namespace App\Models;

use App\User;
use Eloquent;

class Receipt extends Eloquent
{
    protected $fillable = ['pr_id', 'year', 'balance', 'amt_paid', 'method', 'reference'];

    // Money: cedis with pesewas
    protected $casts = ['amt_paid' => 'float', 'balance' => 'float'];

    public function pr()
    {
        return $this->belongsTo(PaymentRecord::class, 'pr_id');
    }

}
