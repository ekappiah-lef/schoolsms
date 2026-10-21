<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Support\ClassOrder;
use App\Support\FinancePeriod;
use App\Support\FinanceSummary;
use App\Support\Money;
use App\Support\TermSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Analytics for the school's leaders: results, attendance, fees and enrolment for any year and term
 * (the dashboard shows only the current term).
 */
class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function index(Request $req)
    {
        $current = Qs::getCurrentSession();
        $years = DB::table('exams')->pluck('year')->merge(DB::table('payment_records')->distinct()->pluck('year'))->push($current)
            ->filter()->unique()->sortDesc()->values();
        $year = $years->contains($req->query('year')) ? $req->query('year') : $current;
        $exams = Exam::where('year', $year)->orderBy('term')->orderBy('id')->get(['id', 'name', 'term']);
        $withMarks = DB::table('exam_records')->whereIn('exam_id', $exams->pluck('id'))->whereNotNull('ave')->distinct()->pluck('exam_id')->all();
        $exam = $exams->firstWhere('id', (int) $req->query('exam'))
            ?: $exams->filter(function ($e) use ($withMarks) { return in_array($e->id, $withMarks); })->last()
            ?: $exams->last();
        $term = in_array((int) $req->query('term'), [1, 2, 3], true) ? (int) $req->query('term') : ($exam ? (int) $exam->term : 1);

        return Inertia::render('Analytics/Index', [
            'filters' => ['year' => $year, 'term' => $term, 'exam' => $exam ? $exam->id : null],
            'years' => $years,
            'exams' => $exams->map(function ($e) { return ['value' => (string) $e->id, 'label' => $e->name.' (Term '.$e->term.')']; })->values(),
            'results' => $exam ? $this->results($exam) : null,
            'attendance' => $this->attendance($year, $term),
            'fees' => $this->fees($year, $term),
            'enrolment' => $this->enrolment(),
            'urls' => ['self' => route('analytics')],
        ]);
    }

    /** One exam: average per class, grades, average per subject. */
    protected function results(Exam $exam): array
    {
        $tex = 'tex'.(int) $exam->term;
        $classes = DB::table('exam_records as er')->join('my_classes as c', 'c.id', '=', 'er.my_class_id')
            ->where('er.exam_id', $exam->id)->whereNotNull('er.ave')
            ->groupBy('c.id', 'c.name', 'c.class_type_id')
            ->select('c.name', 'c.class_type_id', DB::raw('avg(er.ave) as average'), DB::raw('count(*) as students'))->get()
            ->map(function ($r) { return ['class' => $r->name, 'class_type_id' => $r->class_type_id, 'average' => round((float) $r->average, 1), 'students' => (int) $r->students]; });
        $grades = DB::table('marks as m')->join('grades as g', 'g.id', '=', 'm.grade_id')->where('m.exam_id', $exam->id)->whereNotNull("m.$tex")
            ->groupBy('g.name')->select('g.name', DB::raw('count(*) as n'))->orderBy('g.name')->get()
            ->map(function ($r) { return ['grade' => (string) $r->name, 'count' => (int) $r->n]; })->values();
        $subjects = DB::table('marks as m')->join('subjects as s', 's.id', '=', 'm.subject_id')->where('m.exam_id', $exam->id)->whereNotNull("m.$tex")
            ->groupBy('s.name')->select('s.name', DB::raw("avg(m.$tex) as average"), DB::raw('count(*) as n'))->orderByDesc('average')->get()
            ->map(function ($r) { return ['name' => $r->name, 'average' => round((float) $r->average, 1), 'count' => (int) $r->n]; })->values();

        return [
            'exam' => $exam->name,
            'classes' => ClassOrder::sort($classes, 'class_type_id', 'class')->values()->all(),
            'grades' => $grades,
            'subjects' => $subjects,
            'students' => (int) DB::table('exam_records')->where('exam_id', $exam->id)->whereNotNull('ave')->count(),
            'average' => round((float) DB::table('exam_records')->where('exam_id', $exam->id)->whereNotNull('ave')->avg('ave'), 1),
        ];
    }

    /** Attendance in the term: present rate per class. */
    protected function attendance(string $year, int $term): array
    {
        [$from, $to] = FinancePeriod::termRange((int) substr($year, 0, 4), $term);
        $rows = DB::table('attendances as a')->join('my_classes as c', 'c.id', '=', 'a.my_class_id')
            ->whereBetween('a.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('c.id', 'c.name', 'c.class_type_id')
            ->select('c.name', 'c.class_type_id', DB::raw("sum(a.status <> 'absent') as present"), DB::raw("sum(a.status = 'absent') as absent"), DB::raw('count(distinct a.date) as days'))->get()
            ->map(function ($r) {
                $all = (int) $r->present + (int) $r->absent;
                return ['class' => $r->name, 'class_type_id' => $r->class_type_id, 'rate' => $all ? round(100 * $r->present / $all, 1) : 0, 'present' => (int) $r->present, 'absent' => (int) $r->absent, 'days' => (int) $r->days];
            });
        $present = $rows->sum('present'); $absent = $rows->sum('absent');

        return ['classes' => ClassOrder::sort($rows, 'class_type_id', 'class')->values()->all(), 'rate' => $present + $absent ? round(100 * $present / ($present + $absent), 1) : null,
            'days' => (int) $rows->max('days')];
    }

    /** Fees billed, paid and owed in the term, by class type. */
    protected function fees(string $year, int $term): array
    {
        $sel = TermSelection::of([$year.':'.$term]);
        $inv = FinanceSummary::invoiced($sel);
        $byType = collect(FinanceSummary::fees($sel)['byType'] ?? [])->map(function ($t) {
            return ['type' => $t['type'], 'billed' => Money::r($t['total']['expected'] ?? 0), 'paid' => Money::r($t['total']['paid'] ?? 0), 'owed' => Money::r($t['total']['due'] ?? 0)];
        })->filter(function ($t) { return $t['billed'] > 0; })->values();

        return ['billed' => Money::r($inv['billed']), 'paid' => Money::r($inv['paid']), 'owed' => Money::r($inv['outstanding']), 'byType' => $byType];
    }

    /** Students now in school per class, boys and girls. */
    protected function enrolment(): array
    {
        $rows = DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')->join('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->where('sr.grad', 0)->groupBy('c.id', 'c.name', 'c.class_type_id')
            ->select('c.name', 'c.class_type_id', DB::raw("sum(u.gender = 'Male') as male"), DB::raw("sum(u.gender = 'Female') as female"))->get()
            ->map(function ($r) { return ['class' => $r->name, 'class_type_id' => $r->class_type_id, 'male' => (int) $r->male, 'female' => (int) $r->female]; });

        return ClassOrder::sort($rows, 'class_type_id', 'class')->values()->all();
    }
}
