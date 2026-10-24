<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\SchoolTerm;
use App\Support\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Term starts, ends and mid-term breaks for each school year; dates can be changed at any time. */
class SchoolCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function index(Request $req)
    {
        $current = Qs::getCurrentSession();
        $start = (int) substr($current, 0, 4);
        // The year before, this year and the next two, plus any year that has dates.
        $years = collect(range($start - 1, $start + 2))->map(function ($y) { return $y.'-'.($y + 1); })
            ->merge(SchoolTerm::distinct()->pluck('year'))->unique()->sort()->values();
        $year = $years->contains($req->query('year')) ? $req->query('year') : $current;
        [$ny, $nt] = SchoolCalendar::termOf(now());
        $rows = SchoolCalendar::terms($year);
        $d = function ($v) { return $v ? $v->toDateString() : ''; };

        return Inertia::render('Calendar/Index', [
            'year' => $year,
            'years' => $years,
            'now' => $ny.'-'.($ny + 1) === $year ? $nt : null,
            'terms' => collect([1, 2, 3])->map(function ($t) use ($rows, $d) {
                $r = $rows[$t] ?? null;
                return [
                    'term' => $t,
                    'starts_on' => $r ? $d($r->starts_on) : '',
                    'ends_on' => $r ? $d($r->ends_on) : '',
                    'midterm_starts_on' => $r ? $d($r->midterm_starts_on) : '',
                    'midterm_ends_on' => $r ? $d($r->midterm_ends_on) : '',
                    'days' => $r && $r->starts_on && $r->ends_on ? SchoolCalendar::schoolDays($r, $r->starts_on, $r->ends_on) : null,
                ];
            })->values(),
            'urls' => ['self' => route('calendar.index'), 'save' => route('calendar.save')],
        ]);
    }

    public function save(Request $req)
    {
        $req->validate(['year' => ['required', 'regex:/^\d{4}-\d{4}$/']]);
        $year = $req->input('year');
        $terms = (array) json_decode((string) $req->input('terms'), true);
        $parse = function ($v) { try { return $v ? Carbon::parse($v)->startOfDay() : null; } catch (\Throwable $e) { return false; } };
        $clean = [];
        $prevEnd = null;
        foreach ([1, 2, 3] as $t) {
            $r = (array) ($terms[$t - 1] ?? []);
            [$s, $e, $ms, $me] = [$parse($r['starts_on'] ?? ''), $parse($r['ends_on'] ?? ''), $parse($r['midterm_starts_on'] ?? ''), $parse($r['midterm_ends_on'] ?? '')];
            if (in_array(false, [$s, $e, $ms, $me], true)) return Qs::json("Term $t: a date is not valid.", false);
            if ($s && $e && $e->lt($s)) return Qs::json("Term $t ends before it starts.", false);
            if ($prevEnd && $s && $s->lte($prevEnd)) return Qs::json("Term $t starts before term ".($t - 1).' ends.', false);
            if (($ms xor $me) || ($ms && $me && $me->lt($ms))) return Qs::json("Term $t: give the mid-term break's first and last day.", false);
            if ($ms && $s && $e && ($ms->lt($s) || $me->gt($e))) return Qs::json("Term $t: the mid-term break must fall within the term.", false);
            $clean[$t] = ['starts_on' => $s, 'ends_on' => $e, 'midterm_starts_on' => $ms, 'midterm_ends_on' => $me];
            $prevEnd = $e ?: $prevEnd;
        }
        foreach ($clean as $t => $v) {
            SchoolTerm::updateOrCreate(['year' => $year, 'term' => $t], $v);
        }
        SchoolCalendar::forget();
        SchoolCalendar::syncSettings();

        return Qs::json('Calendar saved.', true);
    }
}
