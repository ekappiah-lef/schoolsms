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
