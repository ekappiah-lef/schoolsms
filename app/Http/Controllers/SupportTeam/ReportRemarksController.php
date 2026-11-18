<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\StudentRecord;
use App\Support\Benchmarks;
use App\Support\ClassOrder;
use App\Support\ReportTemplate;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Report remarks, one pupil at a time for a whole class: whatever the class type's report design asks for —
 * benchmark remarks (creche, nursery), written assessments, conduct, interests, the teacher's remarks, and
 * (academic leads) the headteacher's remarks.
 */
class ReportRemarksController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
        $this->middleware(function ($request, $next) {
            abort_unless(ReportTemplate::detailedOn(), 404);
            return $next($request);
        });
    }

    /** Sections the user writes remarks for: a teacher their own; the academic leads every section. */
    public static function sections()
    {
        $own = TeacherScope::applies() ? TeacherScope::ownSectionIds() : null;
        if ($own === [] ) return collect();
        $classes = ClassOrder::sort(DB::table('my_classes')->get(), 'class_type_id', 'name');

        return $classes->flatMap(function ($c) use ($own) {
            return DB::table('sections')->where('my_class_id', $c->id)->orderBy('name')->get()
                ->filter(function ($s) use ($own) { return $own === null || in_array((int) $s->id, $own, true); })
                ->map(function ($s) use ($c) { return ['id' => (int) $s->id, 'name' => $c->name.' '.$s->name, 'class_id' => (int) $c->id, 'class_type_id' => (int) $c->class_type_id]; });
        })->values();
    }

    /** What the design asks for: [statements, assessment names, conduct/interests fields]. */
    protected static function parts(array $tpl, int $classId): array
    {
        $statements = ($tpl['layout'] ?? '') === 'benchmarks' ? Benchmarks::statements($tpl, $classId) : [];
        $fields = collect(['conduct', 'interests'])->filter(function ($k) use ($tpl) { return !empty($tpl['comments'][$k]); })
            ->mapWithKeys(function ($k) use ($tpl) { return [$k => $tpl['comments'][$k.'_label']]; })->all();

        return [$statements, array_values($tpl['assessments'] ?? []), $fields];
    }

    public function index(Request $req)
    {
        $year = Qs::getCurrentSession();
        $sections = self::sections();
        $exams = DB::table('exams')->where('year', $year)->orderBy('term')->get(['id', 'name', 'term']);
        $section = $sections->firstWhere('id', (int) $req->query('section')) ?? $sections->first();
        $exam = $exams->firstWhere('id', (int) $req->query('exam')) ?? $exams->last();

        $pupils = collect();
        $pupil = null;
        $tpl = null;
        $fields = [];
        if ($section && $exam) {
            $tpl = ReportTemplate::for($section['class_type_id']);
            [$statements, $assess, $fields] = self::parts($tpl, $section['class_id']);
            $records = ExamRecord::where(['exam_id' => $exam->id, 'year' => $year])->get()->keyBy('student_id');
            $of = count($statements) + count($assess) + count($fields) + 1;
            $pupils = StudentRecord::where(['section_id' => $section['id'], 'grad' => 0])->with('user')->get()->sortBy('user.name')->values()
                ->map(function ($sr) use ($records, $of) {
                    $r = $records[$sr->user_id] ?? null;
                    $done = $r ? count(array_filter((array) json_decode((string) $r->benchmarks, true), 'strlen'))
                        + count(array_filter((array) json_decode((string) $r->assessments, true), 'strlen'))
                        + (int) filled($r->conduct) + (int) filled($r->interests) + (int) filled($r->t_comment) : 0;
                    return ['id' => Qs::hash($sr->user_id), 'uid' => (int) $sr->user_id, 'name' => optional($sr->user)->name, 'done' => min($done, $of), 'of' => $of];
                });
            $chosen = $pupils->firstWhere('id', (string) $req->query('pupil')) ?? $pupils->first();
            if ($chosen) {
                $r = $records[$chosen['uid']] ?? null;
                $pupil = [
                    'id' => $chosen['id'], 'name' => $chosen['name'],
                    'areas' => $statements ? Benchmarks::forClass($tpl, $section['class_id']) : [],
                    'remarks' => (object) json_decode((string) optional($r)->benchmarks, true),
                    'assessments' => (object) json_decode((string) optional($r)->assessments, true),
                    'conduct' => optional($r)->conduct, 'interests' => optional($r)->interests,
                    't_comment' => optional($r)->t_comment, 'p_comment' => optional($r)->p_comment,
                    'print' => $r ? route('marks.print', [$chosen['id'], $exam->id, $year]) : null,
                ];
            }
            $assessNames = $assess;
        }

        return Inertia::render('Marks/Remarks', [
            'sections' => $sections->map(function ($s) { return ['id' => $s['id'], 'name' => $s['name']]; })->values(),
            'exams' => $exams->map(function ($e) { return ['id' => $e->id, 'name' => $e->name]; })->values(),
            'sectionId' => $section['id'] ?? null,
            'examId' => optional($exam)->id,
            'pupils' => $pupils->map(function ($p) { unset($p['uid']); return $p; })->values(),
            'pupil' => $pupil,
            'options' => $tpl ? Benchmarks::options($tpl) : [],
            'fields' => (object) $fields,
            'assessmentNames' => $assessNames ?? [],
            'canHead' => Qs::userIsAcademicLead(),
            'urls' => ['self' => route('marks.remarks'), 'save' => $pupil && $exam ? route('marks.remarks.save', [$exam->id, $pupil['id']]) : null],
        ]);
    }

    public function save(Request $req, $exam, $student)
    {
        $year = Qs::getCurrentSession();
        $sr = StudentRecord::where(['user_id' => (int) Qs::decodeHash($student), 'grad' => 0])->first();
        $section = $sr ? self::sections()->firstWhere('id', (int) $sr->section_id) : null;
        if (!$section || !DB::table('exams')->where(['id' => (int) $exam, 'year' => $year])->exists()) {
            return Qs::json('You can only write remarks for pupils in your own class.', false);
        }
        $tpl = ReportTemplate::for($section['class_type_id']);
        [$statements, $assess, $fields] = self::parts($tpl, $section['class_id']);
        $keep = function ($names, $in, $max) {
            $out = [];
            foreach ($names as $n) {
                $v = mb_substr(trim((string) ($in[$n] ?? '')), 0, $max);
                if ($v !== '') $out[$n] = $v;
            }
            return $out;
        };

        $r = ExamRecord::firstOrCreate(
            ['exam_id' => (int) $exam, 'student_id' => $sr->user_id, 'year' => $year],
            ['my_class_id' => $sr->my_class_id, 'section_id' => $sr->section_id]
        );
        $d = ['t_comment' => trim((string) $req->input('t_comment')) ?: null];
        if ($statements) $d['benchmarks'] = json_encode($keep($statements, (array) json_decode((string) $req->input('remarks'), true), 60));
        if ($assess) $d['assessments'] = json_encode($keep($assess, (array) json_decode((string) $req->input('assessments'), true), 1000));
        foreach (array_keys($fields) as $k) $d[$k] = mb_substr(trim((string) $req->input($k)), 0, 120) ?: null;
        if (Qs::userIsAcademicLead() && $req->has('p_comment')) $d['p_comment'] = trim((string) $req->input('p_comment')) ?: null;
        $r->update($d);

        return Qs::json('Saved for '.optional($sr->user)->name.'.');
    }
}
