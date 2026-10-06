<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MomoPayment extends Model
{
    protected $fillable = ['reference', 'student_id', 'phone', 'amount', 'currency', 'status', 'financial_transaction_id', 'reason', 'initiated_by', 'applied_at'];

    protected $casts = ['applied_at' => 'datetime'];
}
