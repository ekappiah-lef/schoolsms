<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Orders classes the way a school reads them: by class type (Creche → Senior
 * Secondary), then naturally by name ("JHS 2" before "JHS 10").
 */
class ClassOrder
{
    public static function sort(Collection $items, string $typeKey = 'class_type_id', string $nameKey = 'name'): Collection
    {
        return $items->sort(function ($a, $b) use ($typeKey, $nameKey) {
            return ((int) data_get($a, $typeKey) <=> (int) data_get($b, $typeKey))
                ?: strnatcasecmp((string) data_get($a, $nameKey), (string) data_get($b, $nameKey));
        })->values();
    }
}
