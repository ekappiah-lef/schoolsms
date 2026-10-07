<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ReportTemplate;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Report-card design per class type. The academic admin (and admins) edit it; class teachers can
 * see the design of their own class type. A live preview renders the real report sheet with a sample student.
 */
class ReportDesignController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    /** Class types this user may see: all for admins / academic admin, their own class's type for a teacher. */
    protected function types()
    {
        $q = DB::table('class_types')->orderBy('id');
        if (Qs::userIsTeacher()) {
            $q->whereIn('id', DB::table('my_classes')->whereIn('id', DB::table('sections')->whereIn('id', TeacherScope::ownSectionIds())->pluck('my_class_id'))->pluck('class_type_id'));
        }

        return $q->get(['id', 'name']);
    }

    public function index(Request $req)
    {
        $types = $this->types();
        $type = $types->firstWhere('id', (int) $req->query('type')) ?? $types->first();

        return Inertia::render('Reports/Design', [
            'types' => $types->map(function ($t) { return ['id' => $t->id, 'name' => $t->name, 'url' => route('reports.design', ['type' => $t->id])]; })->values(),
            'type' => $type ? ['id' => $type->id, 'name' => $type->name] : null,
            'template' => $type ? ReportTemplate::for($type->id) : null,
            'standard' => ReportTemplate::defaults(),
            'columns' => ReportTemplate::COLUMNS,
            'canEdit' => Qs::userIsTeamSA(),
            'urls' => $type ? ['save' => route('reports.design.save', $type->id), 'preview' => route('reports.design.preview', $type->id)] : null,
        ]);
    }

    public function save(Request $req, $type)
    {
        if (!Qs::userIsTeamSA()) {
            return Qs::json(__('msg.denied'), false);
        }
        abort_unless(DB::table('class_types')->where('id', $type)->exists(), 404);
        ReportTemplate::save((int) $type, $this->clean($req));

        return Qs::json('Report card design saved for '.DB::table('class_types')->where('id', $type)->value('name').'.');
    }

    /** The real report sheet for a sample student, with the design being edited (saved or not). */
    public function preview(Request $req, $type)
    {
        abort_unless($this->types()->contains('id', (int) $type), 403);
        $tpl = $req->filled('template') ? $this->clean($req) : ReportTemplate::for((int) $type);
        $ct = DB::table('class_types')->where('id', $type)->first();
        $class = DB::table('my_classes')->where('class_type_id', $type)->orderBy('name')->first();
        $subjects = $class ? DB::table('subjects')->where('my_class_id', $class->id)->orderBy('name')->get(['id', 'name']) : collect();
        if ($subjects->isEmpty()) {
            $subjects = collect(['English Language', 'Mathematics', 'Integrated Science'])->map(function ($n, $i) { return (object) ['id' => $i + 1, 'name' => $n]; });
        }
        $grade = (object) ['name' => 'B2', 'remark' => 'Very good'];
        $marks = $subjects->map(function ($s, $i) use ($grade) {
            return (object) ['subject_id' => $s->id, 'exam_id' => 0, 't1' => 15 + $i % 4, 't2' => 16, 'tca' => 31 + $i % 4, 'exm' => 48, 'tex1' => 79 + $i % 4, 'grade' => $grade, 'sub_pos' => 2 + $i];
        });
        $vals = function ($n) { return implode(',', array_map(function ($i) { return 5 - $i % 3; }, range(0, max($n - 1, 0)))); };
        $groups = collect($tpl['groups'])->keyBy('key');

        return view('pages.support_team.marks.print.index', [
            'preview' => true, 'tpl' => $tpl,
            'sr' => (object) ['user' => (object) ['name' => 'Sample Student', 'photo' => null, 'dob' => null], 'adm_no' => 'SAMPLE/001', 'house' => '', 'age' => 9],
            'my_class' => (object) ['name' => optional($class)->name ?? $ct->name], 'class_type' => $ct,
            'ex' => (object) ['id' => 0, 'term' => 1, 'year' => Qs::getCurrentSession()], 'tex' => 'tex1',
            'subjects' => $subjects, 'marks' => $marks,
            'exr' => (object) ['total' => $marks->sum('tex1'), 'ave' => round($marks->avg('tex1'), 1), 'class_ave' => 72.4,
                'af' => $vals(count(optional($groups['af'] ?? null)['items'] ?? [])), 'ps' => $vals(count(optional($groups['ps'] ?? null)['items'] ?? [])),
                't_comment' => 'A hardworking pupil. Keep it up.', 'p_comment' => 'Good result.'],
            'nextFees' => ['set' => true, 'label' => '2nd Term '.str_replace('-', ' – ', Qs::getCurrentSession()), 'school' => 1750, 'services' => 500, 'owed' => 0, 'total' => 2250],
            'attendance' => ['days' => 60, 'present' => 57, 'late' => 2, 'absent' => 3],
            's' => Setting::all()->flatMap(function ($s) { return [$s->type => $s->description]; }),
            'year' => Qs::getCurrentSession(), 'student_id' => 0, 'exam_id' => 0,
        ]);
    }

    /** Validate and tidy a design sent from the page. */
    protected function clean(Request $req): array
    {
        $t = json_decode((string) $req->input('template'), true);
        abort_unless(is_array($t), 422);
        $lines = function ($v, $max = 30) {
            return array_values(array_slice(array_filter(array_map(function ($x) { return mb_substr(trim((string) $x), 0, 80); }, (array) $v), 'strlen'), 0, $max));
        };
        $text = function ($v, $max = 80) { return mb_substr(trim((string) $v), 0, $max); };
        $base = ReportTemplate::defaults();

        return [
            'title' => $text($t['title'] ?? $base['title']) ?: 'REPORT SHEET',
            'columns' => collect($t['columns'] ?? [])->filter(function ($c) { return isset(ReportTemplate::COLUMNS[$c['key'] ?? '']); })
                ->map(function ($c) use ($text) { return ['key' => $c['key'], 'label' => $text($c['label'] ?? '', 40) ?: ReportTemplate::COLUMNS[$c['key']], 'show' => !empty($c['show'])]; })->values()->all(),
            'summary' => !empty($t['summary']),
            'groups' => collect(['af', 'ps'])->map(function ($k) use ($t, $text, $lines, $base) {
                $g = collect($t['groups'] ?? [])->firstWhere('key', $k) ?? collect($base['groups'])->firstWhere('key', $k);
                return ['key' => $k, 'title' => $text($g['title'] ?? '', 40) ?: strtoupper($k), 'show' => !empty($g['show']), 'items' => $lines($g['items'] ?? [])];
            })->all(),
            'key' => ['show' => !empty($t['key']['show']), 'scale' => $lines($t['key']['scale'] ?? [], 10)],
            'attendance' => !empty($t['attendance']),
            'comments' => [
                'teacher' => !empty($t['comments']['teacher']), 'teacher_label' => $text($t['comments']['teacher_label'] ?? '', 60) ?: $base['comments']['teacher_label'],
                'head' => !empty($t['comments']['head']), 'head_label' => $text($t['comments']['head_label'] ?? '', 60) ?: $base['comments']['head_label'],
                'next_term_begins' => !empty($t['comments']['next_term_begins']), 'next_term_fees' => !empty($t['comments']['next_term_fees']),
            ],
            'signatures' => $lines($t['signatures'] ?? [], 4),
            'footer' => $text($t['footer'] ?? '', 200),
        ];
    }
}
