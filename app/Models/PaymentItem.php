<?php

namespace App\Models;

use Eloquent;

class PaymentItem extends Eloquent
{
    protected $fillable = ['payment_id', 'name', 'amount', 'sort'];
}
