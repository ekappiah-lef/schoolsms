<?php

namespace App\Http\Controllers;

use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\MyClass;
use App\Models\Receipt;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Repositories\UserRepo;
use App\Support\ClassOrder;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    protected $user;
    public function __construct(UserRepo $user)
    {
        $this->user = $user;
    }


    public function index()
    {
        return redirect()->route('dashboard');
    }

    public function privacy_policy()
    {
        $data['app_name'] = config('app.name');
        $data['app_url'] = config('app.url');
        $data['contact_phone'] = Qs::getSetting('phone');
        return view('pages.other.privacy_policy', $data);
    }

    public function terms_of_use()
    {
        $data['app_name'] = config('app.name');
        $data['app_url'] = config('app.url');
        $data['contact_phone'] = Qs::getSetting('phone');
        return view('pages.other.terms_of_use', $data);
    }

    public function dashboard()
    {
        return Ui::render('Dashboard/Index', function () {
            return $this->dashboardProps();
        }, 'pages.support_team.dashboard', function () {
            $d = [];
            if (Qs::userIsTeamSAT()) {
                $d['users'] = $this->user->getAll();
            }
            return $d;
        });
    }

    public function ui_mode($mode)
    {
        session(['ui_mode' => $mode]);

        // Some new pages (e.g. the all-students list) have no classic version, so start classic mode on the dashboard.
        return $mode === 'classic' ? redirect()->route('dashboard') : redirect()->back(302, [], route('dashboard'));
    }

    /**
     * Dashboard widgets, built only from existing tables. Each block is only
     * computed for roles that can already reach the matching module.
     */
    protected function dashboardProps(): array
    {
        $session = Qs::getCurrentSession();
        $user = Auth::user();

        $d = [
            'session' => $session,
            'term' => [
                'begins' => Qs::getSetting('term_begins'),
                'ends' => Qs::getSetting('term_ends'),
            ],
        ];

        if (Qs::userIsTeamSA()) {
            $counts = User::select('user_type', DB::raw('count(*) as total'))->groupBy('user_type')->pluck('total', 'user_type');

            $d['stats'] = [
                'students' => StudentRecord::where('grad', 0)->count(),
                'graduated' => StudentRecord::where('grad', 1)->count(),
                'teachers' => (int) ($counts['teacher'] ?? 0),
                'parents' => (int) ($counts['parent'] ?? 0),
                'staff' => (int) (($counts['admin'] ?? 0) + ($counts['super_admin'] ?? 0) + ($counts['academic_admin'] ?? 0) + ($counts['accountant'] ?? 0) + ($counts['librarian'] ?? 0)),
                'admins' => (int) (($counts['admin'] ?? 0) + ($counts['super_admin'] ?? 0) + ($counts['academic_admin'] ?? 0)),
                'accountants' => (int) ($counts['accountant'] ?? 0),
                'linkedParents' => StudentRecord::where('grad', 0)->whereNotNull('my_parent_id')->distinct()->count('my_parent_id'),
                'classes' => MyClass::count(),
                'sections' => Section::count(),
            ];
            $d['links'] = [
                'students' => route('students.index'),
                'admit' => route('students.create'),
                'users' => Qs::userIsTeamAdmin() ? route('users.index') : null,
                'classes' => route('classes.index'),
            ];
        }

        if (Qs::userIsTeamSAT()) {
            $d['enrolment'] = $this->enrolmentByClass();
            $d['performance'] = $this->latestExamPerformance($session);
            $d['classMonitor'] = $this->classMonitor($d['enrolment']);
            // Teachers: only the class(es) they are class teacher of.
            if (\App\Support\TeacherScope::applies()) {
                $own = \App\Models\Section::whereIn('id', \App\Support\TeacherScope::ownSectionIds())->pluck('my_class_id')->map(function ($v) { return (int) $v; })->all();
                $d['classMonitor'] = array_values(array_filter($d['classMonitor'], function ($c) use ($own) { return in_array((int) $c['id'], $own, true); }));
            }
            $d['exams'] = $this->sessionExams($session);
        }

        if (Qs::userIsTeamAccount()) {
            $d['fees'] = $this->feeSummary($session);
            $d['finance'] = [
                'fees' => \App\Support\FinanceSummary::fees($session),
                'cashflow' => \App\Support\FinanceSummary::cashflow(now()->startOfYear(), now()->endOfDay()),
                'balance' => \App\Support\FinanceSummary::cashBalance(),
                'urls' => ['dashboard' => route('finance.dashboard'), 'transactions' => route('finance.transactions'), 'ledger' => route('finance.ledger')],
            ];
        }

        if (Qs::userIsTeacher()) {
            $d['teaching'] = [
                'subjects' => Qs::findTeacherSubjects($user->id)->map(function ($s) {
                    return ['id' => $s->id, 'name' => $s->name, 'class' => optional($s->my_class)->name];
                })->values(),
                'sections' => Section::where('teacher_id', $user->id)->with('my_class')->get()->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'name' => optional($s->my_class)->name.' '.$s->name,
                        'students' => StudentRecord::where(['section_id' => $s->id, 'grad' => 0])->count(),
                        'url' => route('students.index', ['class' => $s->my_class_id, 'section' => $s->id]),
                    ];
                })->values(),
                'links' => ['marks' => route('marks.index'), 'timetables' => route('tt.index')],
            ];
        }

        if (Qs::userIsStudent() && ($sr = StudentRecord::where('user_id', $user->id)->with(['my_class', 'section'])->first())) {
            $d['student'] = [
                'adm_no' => $sr->adm_no,
                'class' => optional($sr->my_class)->name.' '.optional($sr->section)->name,
                'links' => [
                    'profile' => route('students.show', Qs::hash($sr->id)),
                    'marksheet' => route('marks.year_selector', Qs::hash($user->id)),
                    'timetables' => route('tt.index'),
                ],
            ];
        }

        if (Qs::userIsParent()) {
            $d['children'] = Qs::findMyChildren($user->id)->map(function ($sr) {
                return [
                    'name' => $sr->user->name,
                    'photo' => $sr->user->photo,
                    'adm_no' => $sr->adm_no,
                    'class' => optional($sr->my_class)->name,
                    'profile' => route('students.show', Qs::hash($sr->id)),
                    'marksheet' => route('marks.year_selector', Qs::hash($sr->user_id)),
                ];
            })->values();
        }

        return $d;
    }

    protected function enrolmentByClass(): array
    {
        $rows = DB::table('student_records as sr')
            ->join('users as u', 'u.id', '=', 'sr.user_id')
            ->join('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->where('sr.grad', 0)
            ->groupBy('c.id', 'c.name', 'c.class_type_id', 'u.gender')
            ->select('c.id', 'c.name', 'c.class_type_id', 'u.gender', DB::raw('count(*) as total'))
            ->get();

        return ClassOrder::sort($rows->groupBy('id')->map(function ($g) {
            $male = (int) $g->where('gender', 'Male')->sum('total');
            $female = (int) $g->where('gender', 'Female')->sum('total');
            $all = (int) $g->sum('total');

            return [
                'class' => $g->first()->name,
                'class_type_id' => $g->first()->class_type_id,
                'url' => route('students.index', ['class' => $g->first()->id]),
                'male' => $male,
                'female' => $female,
                'unspecified' => $all - $male - $female,
                'total' => $all,
            ];
        }), 'class_type_id', 'class')->all();
    }

    /** Per-class detail for the "Class monitor" panel: sections, class teachers, subjects. */
    protected function classMonitor(array $enrolment): array
    {
        $byName = collect($enrolment)->keyBy('class');
        $total = max(1, collect($enrolment)->sum('total'));
        $subjects = DB::table('subjects')->groupBy('my_class_id')->select('my_class_id', DB::raw('count(*) as n'))->pluck('n', 'my_class_id');
        $perSection = DB::table('student_records')->where('grad', 0)->groupBy('section_id')->select('section_id', DB::raw('count(*) as n'))->pluck('n', 'section_id');

        return ClassOrder::sort(MyClass::with(['section.teacher', 'class_type'])->get())->map(function ($c) use ($byName, $total, $subjects, $perSection) {
            $e = $byName[$c->name] ?? null;
            $enrolled = $e ? $e['total'] : 0;
            // Capacity is known only when every section has one.
            $capacity = $c->section->count() && $c->section->every(function ($s) { return $s->capacity; }) ? (int) $c->section->sum('capacity') : null;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'type' => optional($c->class_type)->name,
                'enrolled' => $enrolled,
                'male' => $e ? $e['male'] : 0,
                'female' => $e ? $e['female'] : 0,
                'share' => round($enrolled / $total * 100, 1),
                'subjects' => (int) ($subjects[$c->id] ?? 0),
                'capacity' => $capacity,
                'sectionsWithCapacity' => $c->section->filter(function ($s) { return $s->capacity; })->count(),
                'sections' => $c->section->sortBy('name')->map(function ($s) use ($perSection) {
                    return [
                        'name' => $s->name,
                        'teacher' => optional($s->teacher)->name,
                        'students' => (int) ($perSection[$s->id] ?? 0),
                        'capacity' => $s->capacity ? (int) $s->capacity : null,
                    ];
                })->values(),
                'urls' => [
                    'roster' => route('students.index', ['class' => $c->id]),
                    'admit' => Qs::userIsTeamSA() ? route('students.create') : null,
                ],
            ];
        })->values()->all();
    }

    /** Exams in the current session with how far marks entry has progressed. */
    protected function sessionExams(string $session): array
    {
        $records = DB::table('exam_records')->where('year', $session)->groupBy('exam_id')
            ->select('exam_id', DB::raw('count(*) as records'), DB::raw('count(distinct my_class_id) as classes'), DB::raw('avg(ave) as average'))
            ->get()->keyBy('exam_id');

        return Exam::where('year', $session)->orderBy('term')->get()->map(function ($e) use ($records) {
            $r = $records[$e->id] ?? null;
            return [
                'id' => $e->id,
                'name' => $e->name,
                'term' => $e->term,
                'records' => $r ? (int) $r->records : 0,
                'classes' => $r ? (int) $r->classes : 0,
                'average' => $r && $r->average !== null ? round((float) $r->average, 1) : null,
            ];
        })->values()->all();
    }

    protected function latestExamPerformance(string $session): ?array
    {
        $exam = Exam::where('year', $session)
            ->whereIn('id', ExamRecord::whereNotNull('ave')->select('exam_id'))
            ->orderByDesc('term')->first();

        if (! $exam) {
            return null;
        }

        $rows = DB::table('exam_records as er')
            ->join('my_classes as c', 'c.id', '=', 'er.my_class_id')
            ->where('er.exam_id', $exam->id)
            ->whereNotNull('er.ave')
            ->groupBy('c.id', 'c.name', 'c.class_type_id')
            ->select('c.name', 'c.class_type_id', DB::raw('avg(er.ave) as average'), DB::raw('max(er.ave + 0) as best'), DB::raw('count(*) as students'))
            ->get();

        return [
            'exam' => $exam->name,
            'classes' => ClassOrder::sort($rows->map(function ($r) {
                return [
                    'class' => $r->name,
                    'class_type_id' => $r->class_type_id,
                    'average' => round((float) $r->average, 1),
                    'best' => round((float) $r->best, 1),
                    'students' => (int) $r->students,
                ];
            }), 'class_type_id', 'class')->all(),
        ];
    }

    protected function feeIntakeGroups(string $session): array
    {
        $row = function ($label, $expected, $collected) {
            return ['label' => $label, 'expected' => (int) $expected, 'collected' => (int) $collected];
        };
        $fees = \App\Support\FinanceSummary::fees($session);

        $byFee = DB::table('payment_records as pr')
            ->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)
            ->groupBy('p.title')
            ->select('p.title', DB::raw('sum(p.amount - pr.discount) as expected'), DB::raw('sum(coalesce(pr.amt_paid, 0)) as collected'))
            ->orderBy('p.title')->get()
            ->map(function ($r) use ($row) { return $row($r->title, $r->expected, $r->collected); })->values();

        return [
            'type' => collect($fees['byType'])->filter(function ($t) { return $t['school']['expected'] > 0; })
                ->map(function ($t) use ($row) { return $row($t['type'], $t['school']['expected'], $t['school']['paid']); })->values(),
            'fee' => $byFee,
            'source' => collect([$row('School fees', $fees['school']['expected'], $fees['school']['paid'])])
                ->merge(collect($fees['services'])->filter(function ($s) { return $s['expected'] > 0; })
                    ->map(function ($s) use ($row) { return $row($s['label'], $s['expected'], $s['paid']); }))->values(),
        ];
    }

    protected function feeSummary(string $session): array
    {
        $items = DB::table('payment_records as pr')
            ->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)
            ->groupBy('p.id', 'p.title')
            ->select(
                'p.title',
                DB::raw('sum(p.amount - pr.discount) as expected'),
                DB::raw('sum(coalesce(pr.amt_paid, 0)) as collected'),
                DB::raw('count(*) as records'),
                DB::raw('sum(pr.paid) as cleared')
            )
            ->get();

        $expected = (int) $items->sum('expected');
        $collected = (int) $items->sum('collected');

        $recent = Receipt::where('year', $session)
            ->with(['pr.payment', 'pr.student'])
            ->orderByDesc('id')->limit(6)->get()
            ->filter(function ($r) {
                return $r->pr && $r->pr->student;
            })
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'student' => $r->pr->student->name,
                    'photo' => $r->pr->student->photo,
                    'fee' => optional($r->pr->payment)->title,
                    'amount' => (int) $r->amt_paid,
                    'balance' => (int) $r->balance,
                    'date' => optional($r->created_at)->toIso8601String(),
                    'url' => route('payments.invoice', Qs::hash($r->pr->student_id)),
                ];
            })->values();

        // Receipts per month for the last six months (collection trend).
        $from = now()->startOfMonth()->subMonths(5);
        $monthly = Receipt::where('created_at', '>=', $from)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('sum(amt_paid) as total'), DB::raw('count(*) as receipts'))
            ->groupBy('ym')->pluck('total', 'ym');
        $trend = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $from->copy()->addMonths($i);
            $trend[] = ['month' => $m->format('M'), 'label' => $m->format('M Y'), 'total' => (int) ($monthly[$m->format('Y-m')] ?? 0)];
        }
        $students = (int) DB::table('payment_records')->where('year', $session)->distinct()->count('student_id');

        return [
            'trend' => $trend,
            'perStudent' => $students ? (int) round($expected / $students) : 0,
            'students' => $students,
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => max($expected - $collected, 0),
            'records' => (int) $items->sum('records'),
            'cleared' => (int) $items->sum('cleared'),
            // Collected vs billed, grouped three ways for the "Fee intake" chart.
            'groups' => $this->feeIntakeGroups($session),
            'recent' => $recent,
            'links' => ['manage' => route('payments.manage'), 'setup' => route('payments.index')],
        ];
    }
}
