<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Subjects kept off the report card (schools with the subjects_off_report switch): marks are still entered,
 * but the subject is not printed and not counted in the total, average, class average or position.
 */
class ReportSubjects
{
    private static $hidden;

    public static function on(): bool
    {
        return School::has('subjects_off_report');
    }

    /** Ids of subjects not on the report card (none unless the school has the switch). */
    public static function hiddenIds(): array
    {
        if (!self::on()) return [];
        if (self::$hidden === null) {
            self::$hidden = DB::table('subjects')->where('on_report', false)->pluck('id')->map(function ($v) { return (int) $v; })->all();
        }

        return self::$hidden;
    }

    public static function forget(): void
    {
        self::$hidden = null;
    }

    /** The subjects that go on the report card. */
    public static function filter($subjects)
    {
        $hidden = self::hiddenIds();

        return $hidden ? $subjects->reject(function ($s) use ($hidden) { return in_array((int) $s->id, $hidden, true); })->values() : $subjects;
    }
}
