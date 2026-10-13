<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\StudentRecord;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Report card remarks for a whole class on one page.
 * The class teacher writes each student's remark; the academic admin (or an admin) adds the head's remark.
 * Both are printed on the report card.
 */
class ReportRemarkController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    /** Sections the signed-in user works with: a teacher's own sections, or every section for admins. */
    protected function sections()
    {
        return DB::table('sections as s')->join('my_classes as c', 'c.id', '=', 's.my_class_id')
            ->when(TeacherScope::applies(), function ($q) { $q->whereIn('s.id', TeacherScope::ownSectionIds()); })
            ->orderBy('c.name')->orderBy('s.name')
            ->get(['s.id', 's.name', 's.my_class_id', 'c.name as class']);
    }

    public function index(Request $req)
    {
        $year = Qs::getCurrentSession();
        $exams = Exam::where('year', $year)->orderBy('term')->get();
        $sections = $this->sections();

        $exam = $exams->firstWhere('id', (int) $req->query('exam')) ?: $exams->last();
        $section = $sections->firstWhere('id', (int) $req->query('section')) ?: $sections->first();

        $rows = collect();
        if ($exam && $section) {
            $students = StudentRecord::where(['section_id' => $section->id, 'my_class_id' => $section->my_class_id, 'grad' => 0])->with('user')->get()
                ->filter(function ($sr) { return $sr->user; })->sortBy(function ($sr) { return $sr->user->name; });
            foreach ($students as $sr) {
                $exr = ExamRecord::firstOrCreate(['exam_id' => $exam->id, 'student_id' => $sr->user_id, 'my_class_id' => $section->my_class_id, 'section_id' => $section->id, 'year' => $year]);
                $rows->push([
                    'id' => $exr->id, 'student' => $sr->user->name, 'photo' => $sr->user->photo, 'adm_no' => $sr->adm_no,
                    'total' => $exr->total, 'ave' => $exr->ave, 'pos' => $exr->pos,
                    't_comment' => (string) $exr->t_comment, 'p_comment' => (string) $exr->p_comment,
                    'results_url' => route('marks.show', [Qs::hash($sr->user_id), $year]),
                ]);
            }
        }

        return Inertia::render('Marks/Remarks', [
            'year' => $year,
            'exams' => $exams->map(function ($e) { return ['id' => $e->id, 'name' => $e->name, 'term' => (int) $e->term]; })->values(),
            'sections' => $sections->map(function ($s) { return ['id' => $s->id, 'label' => trim($s->class.' '.$s->name)]; })->values(),
            'exam' => $exam ? $exam->id : null,
            'section' => $section ? $section->id : null,
            'rows' => $rows->values(),
            'canTeacher' => (bool) $section,   // only sections the user may work with are listed
            'canHead' => Qs::userIsTeamSA(),
            'urls' => [
                'self' => route('marks.remarks'),
                'save' => $exam && $section ? route('marks.remarks.save', [$exam->id, $section->id]) : null,
            ],
        ]);
    }

    public function save(Request $req, $exam, $section)
    {
        if (!$this->sections()->contains('id', (int) $section)) {
            return Qs::json('You can only write remarks for your own class.', false);
        }
        $rows = collect(json_decode((string) $req->input('rows'), true) ?: []);
        $head = Qs::userIsTeamSA();
        $n = 0;
        foreach (ExamRecord::where(['exam_id' => $exam, 'section_id' => $section, 'year' => Qs::getCurrentSession()])->whereIn('id', $rows->pluck('id'))->get() as $exr) {
            $r = $rows->firstWhere('id', $exr->id);
            $d = ['t_comment' => mb_substr(trim((string) ($r['t_comment'] ?? '')), 0, 300) ?: null];
            if ($head) {
                $d['p_comment'] = mb_substr(trim((string) ($r['p_comment'] ?? '')), 0, 300) ?: null;
            }
            $exr->update($d);
            $n++;
        }

        return Qs::json("Remarks saved for $n students.");
    }
}
