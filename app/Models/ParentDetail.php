<?php

namespace App\Models;

use Eloquent;

class ParentDetail extends Eloquent
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static function fields(): array
    {
        $f = [];
        foreach (['father', 'mother'] as $p) {
            foreach (['name', 'phone', 'ghana_card', 'occupation', 'workplace', 'email', 'residential_address', 'postal_address'] as $k) {
                $f[] = "{$p}_{$k}";
            }
        }

        return array_merge($f, ['guardian_name', 'guardian_phone', 'guardian_ghana_card', 'guardian_relationship', 'guardian_address']);
    }
}
