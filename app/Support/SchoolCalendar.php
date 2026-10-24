<?php

namespace App\Support;

use App\Models\SchoolTerm;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The school calendar (Administration → School calendar): term starts, ends and mid-term breaks per school year.
 * A school year runs 1 August – 31 July. Between terms (vacation) the term that has just ended is still "this term",
 * until the next one starts. Without dates for a year, terms follow the months: Aug–Dec, Jan–Apr, May–Jul.
 */
class SchoolCalendar
{
    protected static $cache = [];

    /** The terms entered for a school year ("2026-2027"), keyed 1–3. */
    public static function terms(string $year): array
    {
        if (!array_key_exists($year, self::$cache)) {
            self::$cache[$year] = Schema::hasTable('school_terms')
                ? SchoolTerm::where('year', $year)->get()->keyBy('term')->all()
                : [];
        }

        return self::$cache[$year];
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** The day terms 2 and 3 take over in a school year starting in $y (their start date, or 1 January / 1 May). */
    protected static function boundaries(int $y): array
    {
        $t = self::terms($y.'-'.($y + 1));
        $b2 = isset($t[2]) && $t[2]->starts_on ? $t[2]->starts_on->copy()->startOfDay() : Carbon::create($y + 1, 1, 1);
        $b3 = isset($t[3]) && $t[3]->starts_on ? $t[3]->starts_on->copy()->startOfDay() : Carbon::create($y + 1, 5, 1);

        return [$b2, $b3->max($b2->copy()->addDay())];
    }

    /** [school-year start, term] for a date. */
    public static function termOf(Carbon $d): array
    {
        $y = $d->month >= 8 ? $d->year : $d->year - 1;
        [$b2, $b3] = self::boundaries($y);
        $day = $d->copy()->startOfDay();

        return [$y, $day->lt($b2) ? 1 : ($day->lt($b3) ? 2 : 3)];
    }

    /** The dates that count as a term (vacation included) in a school year starting in $y. */
    public static function termRange(int $y, int $term): array
    {
        [$b2, $b3] = self::boundaries($y);
        if ($term === 1) return [Carbon::create($y, 8, 1)->startOfDay(), $b2->copy()->subDay()->endOfDay()];
        if ($term === 2) return [$b2->copy()->startOfDay(), $b3->copy()->subDay()->endOfDay()];
        return [$b3->copy()->startOfDay(), Carbon::create($y + 1, 7, 31)->endOfDay()];
    }

    /** This term's calendar row, or null when its dates are not entered. */
    public static function current(): ?SchoolTerm
    {
        [$y, $t] = self::termOf(now());
        $row = self::terms($y.'-'.($y + 1))[$t] ?? null;

        return $row && $row->starts_on && $row->ends_on ? $row : null;
    }

    /** First day of the next term (next school year's first term after the third), or null. */
    public static function nextBegins(): ?Carbon
    {
        [$y, $t] = self::termOf(now());
        $row = $t < 3 ? (self::terms($y.'-'.($y + 1))[$t + 1] ?? null) : (self::terms(($y + 1).'-'.($y + 2))[1] ?? null);

        return $row && $row->starts_on ? $row->starts_on->copy() : null;
    }

    /** School days (Monday–Friday, mid-term break left out) from $from to $to in a term. */
    public static function schoolDays(SchoolTerm $row, Carbon $from, Carbon $to): int
    {
        $n = 0;
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            if (!$d->isWeekday()) continue;
            if ($row->midterm_starts_on && $row->midterm_ends_on && $d->between($row->midterm_starts_on, $row->midterm_ends_on)) continue;
            $n++;
        }

        return $n;
    }

    /** Share of this term still ahead (for services started mid-term), or null without this term's dates. */
    public static function shareLeft(): ?float
    {
        $row = self::current();
        if (!$row) return null;
        $total = self::schoolDays($row, $row->starts_on, $row->ends_on);
        if ($total < 1 || today()->gt($row->ends_on)) return 1.0;

        return min(1.0, self::schoolDays($row, today()->max($row->starts_on), $row->ends_on) / $total);
    }

    /** syncSettings() once a day (the current term moves on by itself as the dates pass). */
    public static function syncDaily(): void
    {
        \Illuminate\Support\Facades\Cache::remember('school_calendar_synced_'.date('Ymd'), 86400, function () {
            self::syncSettings();
            return true;
        });
    }

    /**
     * Keep the two dates other screens read (report cards: "Next term begins"; Settings) in step with the calendar.
     * They are stored as m/d/Y.
     */
    public static function syncSettings(): void
    {
        $row = self::current();
        $next = self::nextBegins();
        if ($row) DB::table('settings')->updateOrInsert(['type' => 'term_ends'], ['description' => $row->ends_on->format('m/d/Y')]);
        if ($next) DB::table('settings')->updateOrInsert(['type' => 'term_begins'], ['description' => $next->format('m/d/Y')]);
    }
}
