<?php

namespace App\Models;

use Eloquent;

class FeeOption extends Eloquent
{
    public const GROUPS = ['feeding' => 'Feeding', 'extracurricular' => 'Extra-curricular'];

    protected $fillable = ['group', 'name', 'amount', 'active', 'sort'];

    protected $casts = ['active' => 'boolean', 'amount' => 'float'];

    /** The price for a class type (schools with service_prices_by_level), else the service's own price. */
    public function priceFor(?int $classTypeId): float
    {
        if ($classTypeId && \App\Support\School::has('service_prices_by_level')) {
            $p = \Illuminate\Support\Facades\DB::table('fee_option_prices')->where(['fee_option_id' => $this->id, 'class_type_id' => $classTypeId])->value('amount');
            if ($p !== null) return \App\Support\Money::r($p);
        }
        return \App\Support\Money::r($this->amount);
    }
}
