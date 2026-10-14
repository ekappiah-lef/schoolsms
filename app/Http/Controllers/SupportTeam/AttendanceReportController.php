<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MyClass;
use App\Models\StudentRecord;
use App\Support\ClassOrder;
use App\Support\Notices;
use App\Support\Pdf;
use App\Support\TeacherScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Attendance report: who was absent or late, on which day, for a date range and a class or section.
 * Admins and the academic admin see every class; a class teacher sees their own class.
 * A child's attendance for the range can be sent to the parents as a short PDF by email.
 */
class AttendanceReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    public function index(Request $req)
    {
        [$from, $to] = $this->range($req);
        $classId = (int) $req->query('class') ?: null;
        $sectionId = (int) $req->query('section') ?: null;
        $status = in_array($req->query('status'), ['absent', 'late'], true) ? $req->query('status') : 'all';

        $marks = $this->marks($from, $to, $classId, $sectionId)->get();
        $students = StudentRecord::whereIn('user_id', $marks->pluck('student_id')->unique())->with(['user', 'my_class', 'section'])->get()->keyBy('user_id');
        $name = function ($id) use ($students) { return optional(optional($students[$id] ?? null)->user)->name ?? '—'; };
        $class = function ($id) use ($students) { $sr = $students[$id] ?? null; return $sr ? trim(optional($sr->my_class)->name.' '.optional($sr->section)->name) : '—'; };

        $count = function ($rows, $s) { return $rows->where('status', $s)->count(); };
        $present = $count($marks, 'present');
        $late = $count($marks, 'late');
        $absent = $count($marks, 'absent');

        $days = $marks->groupBy(function ($m) { return $m->date->toDateString(); })->sortKeysDesc()->map(function ($rows, $date) use ($count) {
            return ['date' => $date, 'present' => $count($rows, 'present'), 'late' => $count($rows, 'late'), 'absent' => $count($rows, 'absent'),
                'registers' => $rows->pluck('section_id')->unique()->count()];
        })->values();

        $exceptions = $marks->whereIn('status', $status === 'all' ? ['absent', 'late'] : [$status])
            ->sortBy(function ($m) use ($name) { return [-$m->date->timestamp, $m->status === 'absent' ? 0 : 1, $name($m->student_id)]; })
            ->map(function ($m) use ($name, $class) {
                return ['id' => $m->id, 'date' => $m->date->toDateString(), 'student' => $name($m->student_id), 'class' => $class($m->student_id),
                    'status' => $m->status, 'note' => $m->note, 'alerted' => (bool) $m->alerted_at];
            })->values();

        $byStudent = $marks->groupBy('student_id')->map(function ($rows, $id) use ($count, $name, $class, $students, $from, $to) {
            $sr = $students[$id] ?? null;
            $n = $rows->count();
            return [
                'id' => Qs::hash($id), 'name' => $name($id), 'class' => $class($id), 'days' => $n,
                'present' => $count($rows, 'present'), 'late' => $count($rows, 'late'), 'absent' => $count($rows, 'absent'),
                'rate' => $n ? round(($n - $count($rows, 'absent')) / $n * 100) : null,
                'urls' => [
                    'student' => $sr ? route('students.show', Qs::hash($sr->id)) : null,
                    'pdf' => route('attendance.report.pdf', [Qs::hash($id), 'from' => $from->toDateString(), 'to' => $to->toDateString()]),
                    'send' => route('attendance.report.send', [Qs::hash($id), 'from' => $from->toDateString(), 'to' => $to->toDateString()]),
                ],
            ];
        })->sort(function ($a, $b) { return [$b['absent'], $b['late'], $a['name']] <=> [$a['absent'], $a['late'], $b['name']]; })->values();

        return Inertia::render('Attendance/Report', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'class' => $classId, 'section' => $sectionId, 'status' => $status],
            'classes' => $this->classes(),
            'summary' => ['marks' => $marks->count(), 'present' => $present, 'late' => $late, 'absent' => $absent, 'days' => $days->count(),
                'students' => $byStudent->count(), 'rate' => $marks->count() ? round(($present + $late) / $marks->count() * 100, 1) : null],
            'days' => $days,
            'exceptions' => $exceptions,
            'students' => $byStudent,
            'urls' => ['self' => route('attendance.report'), 'register' => route('attendance.index'), 'sendAll' => route('attendance.report.send_all')],
        ]);
    }

    /** One child's attendance for the range as a short PDF (for printing or for the parents). */
    public function pdf(Request $req, $student)
    {
        [$sr, $from, $to] = $this->child($req, $student);
        $file = 'Attendance-'.preg_replace('/[^A-Za-z0-9]+/', '-', $sr->user->name).'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.attendance', $this->childData($sr, $from, $to))->setPaper('a4')->stream($file);
    }

    /** Email one child's attendance PDF to the parents. */
    public function send(Request $req, $student)
    {
        [$sr, $from, $to] = $this->child($req, $student);
        $r = collect($this->sendFor($sr, $from, $to));
        $sent = $r->where('status', 'sent')->where('channel', 'email')->count();

        return Qs::json($sent ? 'Attendance report emailed to the parent.' : 'Not sent: '.($r->pluck('error')->filter()->first() ?: 'no parent email on record.'), (bool) $sent);
    }

    /** Email the attendance PDF to the parents of every child in the report (same filters). */
    public function sendAll(Request $req)
    {
        [$from, $to] = $this->range($req);
        $ids = $this->marks($from, $to, (int) $req->input('class') ?: null, (int) $req->input('section') ?: null)->distinct()->pluck('student_id');
        $sent = $held = 0;
        foreach (StudentRecord::whereIn('user_id', $ids)->with('user')->get() as $sr) {
            collect($this->sendFor($sr, $from, $to))->where('status', 'sent')->where('channel', 'email')->count() ? $sent++ : $held++;
        }

        return Qs::json("Attendance reports emailed for {$sent} of ".count($ids).' students'.($held ? " ({$held} not sent: demo mode or no parent email)" : '').'.', true);
    }

    /* ------------------------------------------------------------------ */

    protected function sendFor(StudentRecord $sr, Carbon $from, Carbon $to): array
    {
        $d = $this->childData($sr, $from, $to);
        $pdf = Pdf::loadView('pdf.attendance', $d)->setPaper('a4')->output();

        return Notices::sendAttendance($sr, $d['period'], $pdf);
    }

    protected function childData(StudentRecord $sr, Carbon $from, Carbon $to): array
    {
        $rows = Attendance::where('student_id', $sr->user_id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date')->get();
        $n = $rows->count();
        $absent = $rows->where('status', 'absent')->count();

        return [
            'school' => Qs::getSystemName(),
            'logo' => Qs::getSetting('logo'),
            'student' => $sr->user->name,
            'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
            'period' => $from->isSameDay($to) ? $from->format('j M Y') : $from->format('j M Y').' – '.$to->format('j M Y'),
            'days' => $n,
            'present' => $rows->where('status', 'present')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $absent,
            'rate' => $n ? round(($n - $absent) / $n * 100) : null,
            'exceptions' => $rows->whereIn('status', ['absent', 'late'])->map(function ($m) {
                return ['date' => $m->date->format('D j M Y'), 'status' => ucfirst($m->status), 'note' => $m->note];
            })->values(),
        ];
    }

    /** The student (in the user's scope) and the date range. */
    protected function child(Request $req, $student): array
    {
        $sr = StudentRecord::where('user_id', Qs::decodeHash($student))->with(['user', 'my_class', 'section'])->first();
        abort_unless($sr && $sr->user && TeacherScope::canSeeStudent($sr), 404);
        [$from, $to] = $this->range($req);

        return [$sr, $from, $to];
    }

    protected function marks(Carbon $from, Carbon $to, ?int $classId, ?int $sectionId)
    {
        return Attendance::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when(TeacherScope::applies(), function ($q) { $q->whereIn('section_id', TeacherScope::ownSectionIds()); })
            ->when($classId, function ($q) use ($classId) { $q->where('my_class_id', $classId); })
            ->when($sectionId, function ($q) use ($sectionId) { $q->where('section_id', $sectionId); });
    }

    /** From–to dates (default: the last 7 days up to today), never in the future, at most a year. */
    protected function range(Request $req): array
    {
        $parse = function ($v, $default) {
            try { return $v ? Carbon::parse($v)->startOfDay() : $default; } catch (\Throwable $e) { return $default; }
        };
        $to = $parse($req->input('to'), today())->min(today());
        $from = $parse($req->input('from'), $to->copy()->subDays(6))->min($to);

        return [$from->max($to->copy()->subYear()), $to];
    }

    /** Classes with their sections, limited to the user's own sections for a class teacher. */
    protected function classes()
    {
        $own = TeacherScope::applies() ? TeacherScope::ownSectionIds() : null;
        $classes = MyClass::with('section')->get()->map(function ($c) use ($own) {
            $sections = $c->section->when($own !== null, function ($s) use ($own) { return $s->whereIn('id', $own); })->sortBy('name');
            return ['id' => $c->id, 'name' => $c->name, 'class_type_id' => $c->class_type_id,
                'sections' => $sections->map(function ($s) { return ['id' => $s->id, 'name' => $s->name]; })->values()];
        })->filter(function ($c) { return count($c['sections']); });

        return ClassOrder::sort($classes, 'class_type_id', 'name')->values();
    }
}
