<?php

namespace App\Support;

/**
 * This school's switches: set in the LEF Control Centre (stored in school_config), or, until it has set
 * them, SCHOOL_FEATURES in the school's .env (config/school.php).
 */
class School
{
    public static function features(): array
    {
        $saved = SchoolConfig::get('features');

        return is_array($saved) ? $saved : (array) config('school.features');
    }

    public static function has(string $feature): bool
    {
        return in_array($feature, self::features(), true);
    }

    /** Switches the pages need to know about. */
    public static function forPage(): array
    {
        return ['subjectPositionsOnly' => self::has('subject_positions_only'), 'coreElective' => self::has('core_elective_subjects')];
    }

    /** Group label of a subject when the school groups subjects (null otherwise). */
    public static function subjectGroup($subject): ?string
    {
        if (!self::has('core_elective_subjects') || !$subject) return null;

        return ($subject->category ?? 'core') === 'elective' ? 'Elective subjects' : 'Core subjects';
    }

    /** Subjects in report order: core first, then elective (when the school groups them), keeping the given order within each. */
    public static function orderSubjects($subjects)
    {
        if (!self::has('core_elective_subjects')) return $subjects;

        return collect($subjects)->sortBy(function ($s, $i) { return [($s->category ?? 'core') === 'elective' ? 1 : 0, $i]; })->values();
    }
}
