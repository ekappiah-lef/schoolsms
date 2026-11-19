<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\StudentRecord;
use App\Support\Benchmarks;
use App\Support\ReportTemplate;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A pupil's report remarks, opened from the Tabulation sheet: whatever the class type's report design asks
 * for — benchmark remarks (creche, nursery), written assessments, conduct, interests, the teacher's remarks,
 * and (academic leads) the headteacher's remarks. The class teacher writes them; an HOD may only read them.
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

    /** May the signed-in user write remarks for this section? The class teacher, or the academic leads. */
    public static function canWrite(int $sectionId): bool
    {
        return Qs::userIsAcademicLead() || in_array($sectionId, TeacherScope::ownSectionIds(), true);
    }

    /** What the design asks for: [statements, assessment names, conduct/interests fields]. */
    protected static function parts(array $tpl, int $classId): array
    {
        $statements = ($tpl['layout'] ?? '') === 'benchmarks' ? Benchmarks::statements($tpl, $classId) : [];
        $fields = collect(['conduct', 'interests'])->filter(function ($k) use ($tpl) { return !empty($tpl['comments'][$k]); })
            ->mapWithKeys(function ($k) use ($tpl) { return [$k => $tpl['comments'][$k.'_label']]; })->all();

        return [$statements, array_values($tpl['assessments'] ?? []), $fields];
    }

    /** The pupil's student record and class type, if the user may see them. */
    protected function pupil($student): array
    {
        $sr = StudentRecord::where(['user_id' => (int) Qs::decodeHash($student), 'grad' => 0])->with(['user', 'my_class'])->first();
        abort_unless($sr && TeacherScope::canSeeStudent($sr), 404);

        return [$sr, (int) optional($sr->my_class)->class_type_id];
    }

    public function form($exam, $student)
    {
        $year = Qs::getCurrentSession();
        abort_unless(DB::table('exams')->where(['id' => (int) $exam, 'year' => $year])->exists(), 404);
        [$sr, $type] = $this->pupil($student);
        $tpl = ReportTemplate::for($type);
        [$statements, $assess, $fields] = self::parts($tpl, (int) $sr->my_class_id);
        $r = ExamRecord::where(['exam_id' => (int) $exam, 'student_id' => $sr->user_id, 'year' => $year])->first();
        $canWrite = self::canWrite((int) $sr->section_id);
        $term = (int) DB::table('exams')->where('id', (int) $exam)->value('term');
        $tex = 'tex'.$term;
        $marks = \App\Models\Mark::where(['exam_id' => (int) $exam, 'student_id' => $sr->user_id, 'year' => $year])->with('grade')->get()->keyBy('subject_id');
        $subjects = \App\Support\School::orderSubjects(\App\Support\ReportSubjects::filter(\App\Models\Subject::where('my_class_id', $sr->my_class_id)->orderBy('name')->get())->values())
            ->filter(function ($s) use ($marks) { return isset($marks[$s->id]); });

        return response()->json([
            'name' => optional($sr->user)->name,
            'results' => [
                'columns' => array_column(\App\Support\Assessment::summaryColumns(), 'label'),
                'subjects' => $subjects->map(function ($s) use ($marks, $tex) {
                    $mk = $marks[$s->id];
                    return ['name' => $s->name, 'scores' => array_values(\App\Support\Assessment::summary($mk)), 'total' => $mk->$tex === null ? null : $mk->$tex + 0,
                        'grade' => optional($mk->grade)->name, 'pos' => $mk->grade ? $mk->sub_pos : null];
                })->values(),
                'total' => optional($r)->total, 'ave' => optional($r)->ave, 'position' => true,
            ],
            'areas' => $statements ? Benchmarks::forClass($tpl, (int) $sr->my_class_id) : [],
            'remarks' => (object) json_decode((string) optional($r)->benchmarks, true),
            'assessments' => (object) json_decode((string) optional($r)->assessments, true),
            'assessmentNames' => $assess,
            'fields' => (object) $fields,
            'conduct' => optional($r)->conduct, 'interests' => optional($r)->interests,
            't_comment' => optional($r)->t_comment, 'p_comment' => optional($r)->p_comment,
            'options' => Benchmarks::options($tpl),
            'canWrite' => $canWrite,
            'canHead' => Qs::userIsAcademicLead(),
            'print' => $r ? route('marks.print', [Qs::hash($sr->user_id), (int) $exam, $year]) : null,
            'save' => $canWrite ? route('marks.remarks.save', [(int) $exam, Qs::hash($sr->user_id)]) : null,
        ]);
    }

    public function save(Request $req, $exam, $student)
    {
        $year = Qs::getCurrentSession();
        [$sr, $type] = $this->pupil($student);
        if (!self::canWrite((int) $sr->section_id) || !DB::table('exams')->where(['id' => (int) $exam, 'year' => $year])->exists()) {
            return Qs::json('Only the class teacher can write remarks for this pupil.', false);
        }
        $tpl = ReportTemplate::for($type);
        [$statements, $assess, $fields] = self::parts($tpl, (int) $sr->my_class_id);
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
