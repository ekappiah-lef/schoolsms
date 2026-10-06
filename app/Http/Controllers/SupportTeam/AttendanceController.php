<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Support\ClassOrder;
use App\Support\Notices;
use App\Support\TeacherScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Daily class register. Class teachers mark their own section (admins and the academic admin
 * any section); parents of absent children can then be sent a kind absence alert.
 */
class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    public function index(Request $req)
    {
        $sections = $this->sections();
        $sectionId = (int) $req->query('section', optional($sections->first())['id']);
        if (!$sections->contains('id', $sectionId)) {
            $sectionId = (int) optional($sections->first())['id'];
        }
        $date = $this->date($req->query('date'));

        $students = $sectionId ? StudentRecord::where(['section_id' => $sectionId, 'grad' => 0])->with('user')->get()
            ->filter(function ($sr) { return $sr->user; })->sortBy('user.name')->values() : collect();
        $marks = Attendance::where('section_id', $sectionId)->whereDate('date', $date)->get()->keyBy('student_id');

        // The last 10 register days for this section, for a quick overview.
        $recent = Attendance::where('section_id', $sectionId)->where('date', '<=', $date->toDateString())
            ->select('date', DB::raw("sum(status = 'present') as present"), DB::raw("sum(status = 'late') as late"), DB::raw("sum(status = 'absent') as absent"))
            ->groupBy('date')->orderByDesc('date')->limit(10)->get();

        return Inertia::render('Attendance/Index', [
            'sections' => $sections->values(),
            'sectionId' => $sectionId ?: null,
            'date' => $date->toDateString(),
            'saved' => $marks->isNotEmpty(),
            'students' => $students->map(function ($sr) use ($marks) {
                $m = $marks[$sr->user_id] ?? null;
                return [
                    'id' => $sr->user_id, 'name' => $sr->user->name, 'photo' => $sr->user->photo, 'adm_no' => $sr->adm_no,
                    'status' => $m->status ?? 'present', 'note' => $m->note ?? '', 'alerted' => (bool) optional($m)->alerted_at,
                ];
            })->values(),
            'recent' => $recent->map(function ($r) {
                return ['date' => Carbon::parse($r->date)->toDateString(), 'present' => (int) $r->present, 'late' => (int) $r->late, 'absent' => (int) $r->absent];
            })->values(),
            'urls' => ['self' => route('attendance.index'), 'save' => route('attendance.store'), 'alert' => route('attendance.alert')],
        ]);
    }

    public function store(Request $req)
    {
        $d = $req->validate([
            'section_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'marks' => 'required|json',
        ]);
        $section = $this->allowedSection((int) $d['section_id']);
        if (!$section) {
            return Qs::json('You can only take the register for your own class.', false);
        }
        $valid = StudentRecord::where(['section_id' => $section->id, 'grad' => 0])->pluck('user_id')->map(function ($v) { return (int) $v; })->all();
        $year = Qs::getCurrentSession();

        DB::transaction(function () use ($d, $section, $valid, $year) {
            foreach (json_decode($d['marks'], true) as $m) {
                $sid = (int) ($m['id'] ?? 0);
                if (!in_array($sid, $valid, true) || !isset(Attendance::STATUSES[$m['status'] ?? ''])) continue;
                Attendance::updateOrCreate(
                    ['student_id' => $sid, 'date' => $d['date']],
                    ['my_class_id' => $section->my_class_id, 'section_id' => $section->id, 'status' => $m['status'],
                        'note' => mb_substr((string) ($m['note'] ?? ''), 0, 255) ?: null, 'recorded_by' => Auth::id(), 'year' => $year]
                );
            }
        });

        $absent = Attendance::where('section_id', $section->id)->whereDate('date', $d['date'])->where('status', 'absent')->count();

        return Qs::json('Register saved'.($absent ? ": {$absent} absent." : ': everyone present.'));
    }

    /** Send the absence alert to parents of children marked absent who have not been alerted yet that day. */
    public function alert(Request $req)
    {
        $d = $req->validate(['section_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d']);
        if (!$this->allowedSection((int) $d['section_id'])) {
            return Qs::json('You can only send alerts for your own class.', false);
        }
        $rows = Attendance::where('section_id', $d['section_id'])->whereDate('date', $d['date'])->where('status', 'absent')->whereNull('alerted_at')->get();
        if (!$rows->count()) {
            return Qs::json('No absent students are waiting for an alert.', false);
        }

        $sent = 0;
        $held = 0;
        foreach ($rows as $a) {
            $sr = StudentRecord::where('user_id', $a->student_id)->first();
            if (!$sr) continue;
            $res = collect(Notices::sendAbsence($sr, $d['date']));
            if ($res->where('status', 'sent')->count()) {
                $sent++;
                $a->update(['alerted_at' => now()]);
            } else {
                $held++;
            }
        }

        $noun = $sent === 1 ? 'child' : 'children';

        return Qs::json("Absence alerts sent to the parents of {$sent} {$noun}".($held ? " ({$held} not sent: demo mode or no contact)" : '').'.', true);
    }

    /** Sections this user can take the register for, as [{id, name}] labelled "Class Section". */
    protected function sections()
    {
        $q = Section::with('my_class');
        if (TeacherScope::applies()) {
            $q->whereIn('id', TeacherScope::ownSectionIds());
        }
        $rows = $q->get()->filter(function ($s) { return $s->my_class; })->map(function ($s) {
            return ['id' => (int) $s->id, 'name' => $s->my_class->name.' '.$s->name, 'class_type_id' => $s->my_class->class_type_id];
        })->values();

        return ClassOrder::sort($rows, 'class_type_id', 'name')->map(function ($s) {
            return ['id' => $s['id'], 'name' => $s['name']];
        })->values();
    }

    protected function allowedSection(int $id): ?Section
    {
        $section = Section::find($id);
        if (!$section || (TeacherScope::applies() && !in_array($id, TeacherScope::ownSectionIds(), true))) {
            return null;
        }

        return $section;
    }

    protected function date($value): Carbon
    {
        try {
            $d = $value ? Carbon::parse($value) : today();
        } catch (\Throwable $e) {
            $d = today();
        }

        return $d->isFuture() ? today() : $d->startOfDay();
    }
}
