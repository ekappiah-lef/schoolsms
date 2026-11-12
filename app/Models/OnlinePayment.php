<?php

namespace App\Models;

use Eloquent;

class OnlinePayment extends Eloquent
{
    protected $fillable = ['provider', 'reference', 'student_id', 'amount', 'currency', 'email', 'phone', 'network', 'status', 'channel', 'provider_id', 'reason', 'initiated_by', 'applied_at'];

    protected $casts = ['amount' => 'float', 'applied_at' => 'datetime'];
}
