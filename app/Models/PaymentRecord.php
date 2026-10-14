<?php

namespace App\Models;

use App\User;
use Eloquent;

class PaymentRecord extends Eloquent
{
    protected $fillable =['student_id', 'payment_id', 'amt_paid', 'discount', 'year', 'paid', 'balance', 'ref_no'];

    // Money: cedis with pesewas
    protected $casts = ['amt_paid' => 'float', 'balance' => 'float', 'discount' => 'float'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function receipt()
    {
        return $this->hasMany(Receipt::class, 'pr_id');
    }
}
