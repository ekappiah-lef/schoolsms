<?php

namespace App\Models;

use Eloquent;

class FeeOption extends Eloquent
{
    public const GROUPS = ['feeding' => 'Feeding', 'extracurricular' => 'Extra-curricular'];

    protected $fillable = ['group', 'name', 'amount', 'active', 'sort'];

    protected $casts = ['active' => 'boolean'];
}
