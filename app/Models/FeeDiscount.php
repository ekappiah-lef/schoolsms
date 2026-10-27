<?php

namespace App\Models;

use App\Support\Fees;
use App\Support\Money;
use Eloquent;

/**
 * Discount on tuition: a percentage (e.g. "Staff children", 100%) or a fixed amount (GHS 43.00).
 * "Next term only" discounts (e.g. 5% early payment) come off the student's next term's tuition once.
 */
class FeeDiscount extends Eloquent
{
    protected $fillable = ['name', 'kind', 'percent', 'amount', 'next_term_only', 'active'];

    protected $casts = ['active' => 'boolean', 'next_term_only' => 'boolean', 'percent' => 'float', 'amount' => 'float'];

    /** How much comes off a bill for this fee (never more than its tuition). */
    public function offFor(Payment $p): float
    {
        $tuition = Fees::tuitionBase($p);
        if ($tuition <= 0) return 0.0;
        $off = $this->kind === 'fixed' ? Money::r($this->amount) : Money::r($tuition * $this->percent / 100);

        return min($off, $tuition);
    }

    /** "50%", "5% next term", "GHS 43.00". */
    public function label(): string
    {
        $v = $this->kind === 'fixed' ? 'GHS '.Money::fmt($this->amount) : rtrim(rtrim(number_format($this->percent, 2), '0'), '.').'%';

        return $v.($this->next_term_only ? ' next term' : '');
    }
}
