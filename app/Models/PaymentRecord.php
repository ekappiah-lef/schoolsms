<?php

namespace App\Models;

use App\User;
use Eloquent;

class PaymentRecord extends Eloquent
{
    protected $fillable =['student_id', 'payment_id', 'amount', 'amt_paid', 'discount', 'discount_fixed', 'year', 'paid', 'balance', 'ref_no'];

    // Money: cedis with pesewas
    protected $casts = ['amount' => 'float', 'amt_paid' => 'float', 'balance' => 'float', 'discount' => 'float', 'discount_fixed' => 'boolean'];

    /** What this student is billed: the record's own amount (e.g. a balance brought forward; negative = credit), else the fee's. */
    public function billAmount(): float
    {
        return \App\Support\Money::r($this->amount !== null ? $this->amount : optional($this->payment)->amount);
    }

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
