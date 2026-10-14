<?php

namespace App\Support;

/**
 * Money is kept in cedis with pesewas (2 decimals). Every amount read or calculated goes through r(),
 * so sums and balances never pick up float noise (e.g. 0.1 + 0.2), and a balance under half a pesewa is zero.
 */
class Money
{
    public static function r($value): float
    {
        return round((float) $value, 2) + 0.0;   // + 0.0 turns -0.0 into 0.0
    }

    /** "1,250.50" */
    public static function fmt($value): string
    {
        return number_format(self::r($value), 2);
    }

    /** Validation rule for an amount entered in a form: up to 2 decimals. */
    public static function rule(): string
    {
        return 'numeric|regex:/^\d+(\.\d{1,2})?$/';
    }
}
