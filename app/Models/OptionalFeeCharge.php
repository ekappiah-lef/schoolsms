<?php

namespace App\Models;

use Eloquent;

class OptionalFeeCharge extends Eloquent
{
    public const GROUPS = ['feeding' => 'Feeding', 'bus' => 'Bus', 'extracurricular' => 'Extra-curricular', 'sales' => 'Sales'];

    protected $fillable = ['student_id', 'year', 'term', 'group', 'label', 'fee_option_id', 'bus_route_id', 'bus_direction', 'inventory_item_id', 'qty', 'handed_down_from', 'amount', 'amt_paid'];

    public function receipts()
    {
        return $this->hasMany(OptionalFeeReceipt::class, 'charge_id');
    }

    public function getBalanceAttribute(): int
    {
        return max((int) $this->amount - (int) $this->amt_paid, 0);
    }
}
