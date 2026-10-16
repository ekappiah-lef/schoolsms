<?php

namespace App\Support;

/** This school's switches (config/school.php, from SCHOOL_FEATURES in its .env). */
class School
{
    public static function has(string $feature): bool
    {
        return in_array($feature, (array) config('school.features'), true);
    }

    /** Switches the pages need to know about. */
    public static function forPage(): array
    {
        return ['subjectPositionsOnly' => self::has('subject_positions_only')];
    }
}
