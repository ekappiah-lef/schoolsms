<?php

namespace App\Models;

use App\User;
use Eloquent;

/** Money in (capital, donations, other income) or out (expenses), outside student fees. */
class FinanceTransaction extends Eloquent
{
    public const TYPES = ['income' => 'Income', 'expense' => 'Expense'];

    protected $fillable = ['type', 'category', 'amount', 'date', 'method', 'reference', 'description', 'recorded_by'];

    protected $casts = ['date' => 'date'];

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
