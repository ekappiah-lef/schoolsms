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
 * Benchmarks report (creche, nursery): the class teacher picks or types a remark for each statement for each
 * pupil, with conduct, interests and comments, one pupil at a time.
 */
class BenchmarkController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    /** Sections of classes whose report is the benchmarks layout; a teacher: only their own. */
    public static function sections()
    {
        $types = DB::table('class_types')->pluck('id')->filter(function ($id) { return (ReportTemplate::for((int) $id)['layout'] ?? '') === 'benchmarks'; });
        if ($types->isEmpty()) return collect();
        $own = TeacherScope::applies() ? TeacherScope::ownSectionIds() : null;
        $classes = ClassOrder::sort(DB::table('my_classes')->whereIn('class_type_id', $types)->get(), 'class_type_id', 'name');

        return $classes->flatMap(function ($c) use ($own) {
            return DB::table('sections')->where('my_class_id', $c->id)->orderBy('name')->get()
                ->filter(function ($s) use ($own) { return $own === null || in_array((int) $s->id, $own, true); })
                ->map(function ($s) use ($c) { return ['id' => (int) $s->id, 'name' => $c->name.' '.$s->name, 'class_id' => (int) $c->id, 'class_type_id' => (int) $c->class_type_id]; });
        })->values();
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
        if ($section && $exam) {
            $tpl = ReportTemplate::for($section['class_type_id']);
            $statements = Benchmarks::statements($tpl, $section['class_id']);
            $records = ExamRecord::where(['exam_id' => $exam->id, 'year' => $year])->get()->keyBy('student_id');
            $pupils = StudentRecord::where(['section_id' => $section['id'], 'grad' => 0])->with('user')->get()->sortBy('user.name')->values()
                ->map(function ($sr) use ($records, $statements) {
                    $r = $records[$sr->user_id] ?? null;
                    $done = $r ? count(array_filter((array) json_decode((string) $r->benchmarks, true), 'strlen')) : 0;
                    return ['id' => Qs::hash($sr->user_id), 'uid' => (int) $sr->user_id, 'name' => optional($sr->user)->name, 'done' => $done, 'of' => count($statements)];
                });
            $chosen = $pupils->firstWhere('id', (string) $req->query('pupil')) ?? $pupils->first();
            if ($chosen) {
                $r = $records[$chosen['uid']] ?? null;
                $pupil = [
                    'id' => $chosen['id'], 'name' => $chosen['name'],
                    'areas' => Benchmarks::forClass($tpl, $section['class_id']),
                    'remarks' => (object) json_decode((string) optional($r)->benchmarks, true),
                    'conduct' => optional($r)->conduct, 'interests' => optional($r)->interests,
                    't_comment' => optional($r)->t_comment, 'p_comment' => optional($r)->p_comment,
                    'print' => $r ? route('marks.print', [$chosen['id'], $exam->id, $year]) : null,
                ];
            }
            $fields = collect(['conduct', 'interests'])->filter(function ($k) use ($tpl) { return !empty($tpl['comments'][$k]); })
                ->mapWithKeys(function ($k) use ($tpl) { return [$k => $tpl['comments'][$k.'_label']]; });
        }

        return Inertia::render('Marks/Benchmarks', [
            'sections' => $sections->map(function ($s) { return ['id' => $s['id'], 'name' => $s['name']]; })->values(),
            'exams' => $exams->map(function ($e) { return ['id' => $e->id, 'name' => $e->name]; })->values(),
            'sectionId' => $section['id'] ?? null,
            'examId' => optional($exam)->id,
            'pupils' => $pupils->map(function ($p) { unset($p['uid']); return $p; })->values(),
            'pupil' => $pupil,
            'options' => isset($tpl) ? Benchmarks::options($tpl) : [],
            'fields' => $fields ?? (object) [],
            'canHead' => Qs::userIsAcademicLead(),
            'urls' => ['self' => route('marks.benchmarks'), 'save' => $pupil && $exam ? route('marks.benchmarks.save', [$exam->id, $pupil['id']]) : null],
        ]);
    }

    public function save(Request $req, $exam, $student)
    {
        $year = Qs::getCurrentSession();
        $sr = StudentRecord::where(['user_id' => (int) Qs::decodeHash($student), 'grad' => 0])->first();
        $section = $sr ? self::sections()->firstWhere('id', (int) $sr->section_id) : null;
        if (!$section || !DB::table('exams')->where(['id' => (int) $exam, 'year' => $year])->exists()) {
            return Qs::json('You can only write benchmarks for pupils in your own class.', false);
        }
        $tpl = ReportTemplate::for($section['class_type_id']);
        $statements = Benchmarks::statements($tpl, $section['class_id']);
        $in = (array) json_decode((string) $req->input('remarks'), true);
        $remarks = [];
        foreach ($statements as $s) {
            $v = mb_substr(trim((string) ($in[$s] ?? '')), 0, 60);
            if ($v !== '') $remarks[$s] = $v;
        }

        $r = ExamRecord::firstOrCreate(
            ['exam_id' => (int) $exam, 'student_id' => $sr->user_id, 'year' => $year],
            ['my_class_id' => $sr->my_class_id, 'section_id' => $sr->section_id]
        );
        $d = ['benchmarks' => json_encode($remarks), 't_comment' => trim((string) $req->input('t_comment')) ?: null];
        foreach (['conduct', 'interests'] as $k) if ($req->has($k)) $d[$k] = mb_substr(trim((string) $req->input($k)), 0, 120) ?: null;
        if (Qs::userIsAcademicLead() && $req->has('p_comment')) $d['p_comment'] = trim((string) $req->input('p_comment')) ?: null;
        $r->update($d);

        return Qs::json('Saved for '.optional($sr->user)->name.' ('.count($remarks).' of '.count($statements).').');
    }
}
