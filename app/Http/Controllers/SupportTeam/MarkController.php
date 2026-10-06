<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Mk;
use App\Helpers\Ui;
use App\Support\ClassOrder;
use App\Support\TeacherScope;
use App\Http\Requests\Mark\MarkSelector;
use App\Models\Setting;
use App\Repositories\ExamRepo;
use App\Repositories\MarkRepo;
use App\Repositories\MyClassRepo;
use App\Http\Controllers\Controller;
use App\Repositories\StudentRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MarkController extends Controller
{
    protected $my_class, $exam, $student, $year, $user, $mark;

    public function __construct(MyClassRepo $my_class, ExamRepo $exam, StudentRepo $student, MarkRepo $mark)
    {
        $this->exam =  $exam;
        $this->mark =  $mark;
        $this->student =  $student;
        $this->my_class =  $my_class;
        $this->year =  Qs::getSetting('current_session');

       // $this->middleware('teamSAT', ['except' => ['show', 'year_selected', 'year_selector', 'print_view'] ]);
    }

    public function index()
    {
        $d['exams'] = $this->exam->getExam(['year' => $this->year]);
        $d['my_classes'] = $this->my_class->all();
        $d['sections'] = $this->my_class->getAllSections();
        $d['subjects'] = $this->my_class->getAllSubjects();
        $d['selected'] = false;

        return Ui::render('Marks/Index', function () use ($d) {
            return $this->selectorProps($d['exams'], null);
        }, 'pages.support_team.marks.index', $d);
    }

    /** Options for the exam/class/section/subject picker (sections & subjects load per class via /ajax). */
    protected function selectorProps($exams, ?array $current): array
    {
        return [
            'session' => $this->year,
            'current' => $current,
            'exams' => $exams->map(function ($e) {
                return ['id' => $e->id, 'name' => $e->name, 'term' => $e->term];
            })->values(),
            'classes' => ClassOrder::sort($this->my_class->all())->filter(function ($c) {
                return !TeacherScope::applies() || in_array((int) $c->id, TeacherScope::markClassIds(), true);
            })->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name];
            })->values(),
            'urls' => [
                'select' => route('marks.selector'),
                'classSubjects' => route('get_class_subjects', ':id'),
            ],
        ];
    }

    public function year_selector($student_id)
    {
        if (!Ui::isClassic()) {
            $years = $this->exam->getExamYears($student_id);
            if ($this->student->exists($student_id) && $years->count()) {
                return redirect()->route('marks.show', [Qs::hash($student_id), $years->sortByDesc('year')->first()->year]);
            }
            return $this->noStudentRecord();
        }

       return $this->verifyStudentExamYear($student_id);
    }

    public function year_selected(Request $req, $student_id)
    {
        if(!$this->verifyStudentExamYear($student_id, $req->year)){
            return $this->noStudentRecord();
        }

        $student_id = Qs::hash($student_id);
        return redirect()->route('marks.show', [$student_id, $req->year]);
    }

    public function show($student_id, $year)
    {
        $own = \App\Models\StudentRecord::where('user_id', $student_id)->first();
        if ($own && !TeacherScope::canSeeStudent($own)) {
            return redirect(route('dashboard'))->with('pop_error', 'You can only view results for students in your own class.');
        }
        /* Prevent Other Students/Parents from viewing Result of others */
        if(Auth::user()->id != $student_id && !Qs::userIsTeamSAT() && !Qs::userIsMyChild($student_id, Auth::user()->id)){
            return redirect(route('dashboard'))->with('pop_error', __('msg.denied'));
        }

        if(Mk::examIsLocked() && !Qs::userIsTeamSA()){
            Session::put('marks_url', route('marks.show', [Qs::hash($student_id), $year]));

            if(!$this->checkPinVerified($student_id)){
                return redirect()->route('pins.enter', Qs::hash($student_id));
            }
        }

        if(!$this->verifyStudentExamYear($student_id, $year)){
            return $this->noStudentRecord();
        }

        $wh = ['student_id' => $student_id, 'year' => $year ];
        $d['marks'] = $this->exam->getMark($wh);
        $d['exam_records'] = $exr = $this->exam->getRecord($wh);
        $d['exams'] = $this->exam->getExam(['year' => $year]);
        $d['sr'] = $this->student->getRecord(['user_id' => $student_id])->first();
        $d['my_class'] = $mc = $this->my_class->getMC(['id' => $exr->first()->my_class_id])->first();
        $d['class_type'] = $this->my_class->findTypeByClass($mc->id);
        $d['subjects'] = $this->my_class->findSubjectByClass($mc->id);
        $d['year'] = $year;
        $d['student_id'] = $student_id;
        $d['skills'] = $this->exam->getSkillByClassType() ?: NULL;
        //$d['ct'] = $d['class_type']->code;
        //$d['mark_type'] = Qs::getMarkType($d['ct']);

        return Ui::render('Marks/Sheet', function () use ($d, $student_id, $year) { return $this->sheetProps($d, $student_id, $year); }, 'pages.support_team.marks.show.index', $d);
    }

    /** A student's results for one year: every exam with subject scores, comments and skill ratings. */
    protected function sheetProps(array $d, $student_id, $year): array
    {
        $sr = $d['sr'];
        $hash = Qs::hash($student_id);
        $skills = $d['skills'] ?: collect();
        $ratings = function ($csv, $n) { $v = $csv ? explode(',', $csv) : []; return array_map(function ($i) use ($v) { return isset($v[$i]) && $v[$i] !== '' ? (int) $v[$i] : null; }, range(0, max($n - 1, -1))); };
        $af = $skills->where('skill_type', 'AF')->pluck('name')->values();
        $ps = $skills->where('skill_type', 'PS')->pluck('name')->values();

        $exams = [];
        foreach ($d['exams']->sortBy('term') as $ex) {
            foreach ($d['exam_records']->where('exam_id', $ex->id) as $exr) {
                $tex = 'tex'.$ex->term;
                $exams[] = [
                    'id' => $ex->id, 'name' => $ex->name, 'term' => (int) $ex->term,
                    'subjects' => $d['subjects']->map(function ($sub) use ($d, $ex, $tex) {
                        $mk = $d['marks']->where('subject_id', $sub->id)->where('exam_id', $ex->id)->first();
                        return [
                            'name' => $sub->name,
                            't1' => $mk->t1 ?? null, 't2' => $mk->t2 ?? null, 'exm' => $mk->exm ?? null, 'total' => $mk->$tex ?? null,
                            'grade' => optional(optional($mk)->grade)->name, 'remark' => optional(optional($mk)->grade)->remark,
                            'pos' => $mk && $mk->grade ? $mk->sub_pos : null,
                        ];
                    })->values(),
                    'total' => $exr->total, 'ave' => $exr->ave, 'class_ave' => $exr->class_ave, 'pos' => $exr->pos,
                    't_comment' => $exr->t_comment, 'p_comment' => $exr->p_comment,
                    'af' => $ratings($exr->af, $af->count()), 'ps' => $ratings($exr->ps, $ps->count()),
                    'urls' => [
                        'print' => route('marks.print', [$hash, $ex->id, $year]),
                        'comment' => route('marks.comment_update', $exr->id),
                        'af' => route('marks.skills_update', ['AF', $exr->id]),
                        'ps' => route('marks.skills_update', ['PS', $exr->id]),
                    ],
                ];
            }
        }

        return [
            'student' => [
                'name' => $sr->user->name, 'photo' => $sr->user->photo, 'adm_no' => $sr->adm_no,
                'class' => trim(optional($d['my_class'])->name.' '.optional($sr->section)->name),
                'profile' => Qs::userIsTeamSAT() || Qs::userIsMyChild($student_id, Auth::id()) ? route('students.show', Qs::hash($sr->id)) : null,
            ],
            'year' => $year,
            'years' => $this->exam->getExamYears($student_id)->pluck('year')->sort()->reverse()->values()->map(function ($y) use ($hash) {
                return ['value' => $y, 'url' => route('marks.show', [$hash, $y])];
            }),
            'exams' => $exams,
            'skills' => ['af' => $af, 'ps' => $ps],
            'canComment' => Qs::userIsTeamSAT(),
            'canHeadComment' => Qs::userIsTeamSA(),
        ];
    }

    public function print_view($student_id, $exam_id, $year)
    {
        /* Prevent Other Students/Parents from viewing Result of others */
        if(Auth::user()->id != $student_id && !Qs::userIsTeamSA() && !Qs::userIsMyChild($student_id, Auth::user()->id)){
            return redirect(route('dashboard'))->with('pop_error', __('msg.denied'));
        }

        if(Mk::examIsLocked() && !Qs::userIsTeamSA()){
            Session::put('marks_url', route('marks.show', [Qs::hash($student_id), $year]));

            if(!$this->checkPinVerified($student_id)){
                return redirect()->route('pins.enter', Qs::hash($student_id));
            }
        }

        if(!$this->verifyStudentExamYear($student_id, $year)){
            return $this->noStudentRecord();
        }

        $wh = ['student_id' => $student_id, 'exam_id' => $exam_id, 'year' => $year ];
        $d['marks'] = $mks = $this->exam->getMark($wh);
        $d['exr'] = $exr = $this->exam->getRecord($wh)->first();
        $d['my_class'] = $mc = $this->my_class->find($exr->my_class_id);
        $d['section_id'] = $exr->section_id;
        $d['ex'] = $exam = $this->exam->find($exam_id);
        $d['tex'] = 'tex'.$exam->term;
        $d['sr'] = $sr =$this->student->getRecord(['user_id' => $student_id])->first();
        $d['class_type'] = $this->my_class->findTypeByClass($mc->id);
        $d['subjects'] = $this->my_class->findSubjectByClass($mc->id);

        $d['ct'] = $ct = $d['class_type']->code;
        $d['year'] = $year;
        $d['student_id'] = $student_id;
        $d['exam_id'] = $exam_id;

        $d['skills'] = $this->exam->getSkillByClassType() ?: NULL;
        $d['s'] = Setting::all()->flatMap(function($s){
            return [$s->type => $s->description];
        });

        //$d['mark_type'] = Qs::getMarkType($ct);

        return view('pages.support_team.marks.print.index', $d);
    }

    public function selector(MarkSelector $req)
    {
        if (!TeacherScope::canMark((int) $req->my_class_id, (int) $req->section_id, (int) $req->subject_id)) {
            return back()->with('pop_error', 'You can only enter marks for your own class, or for the subjects you teach.');
        }
        $data = $req->only(['exam_id', 'my_class_id', 'section_id', 'subject_id']);
        $d2 = $req->only(['exam_id', 'my_class_id', 'section_id']);
        $d = $req->only(['my_class_id', 'section_id']);
        $d['session'] = $data['year'] = $d2['year'] = $this->year;

        $students = $this->student->getRecord($d)->get();
        if($students->count() < 1){
            return back()->with('pop_error', __('msg.rnf'));
        }

        foreach ($students as $s){
            $data['student_id'] = $d2['student_id'] = $s->user_id;
            $this->exam->createMark($data);
            $this->exam->createRecord($d2);
        }

        return redirect()->route('marks.manage', [$req->exam_id, $req->my_class_id, $req->section_id, $req->subject_id]);
    }

    public function manage($exam_id, $class_id, $section_id, $subject_id)
    {
        if (!TeacherScope::canMark((int) $class_id, (int) $section_id, (int) $subject_id)) {
            return redirect()->route('marks.index')->with('flash_danger', 'You can only enter marks for your own class, or for the subjects you teach.');
        }
        $d = ['exam_id' => $exam_id, 'my_class_id' => $class_id, 'section_id' => $section_id, 'subject_id' => $subject_id, 'year' => $this->year];

        $d['marks'] = $this->exam->getMark($d);
        if($d['marks']->count() < 1){
            return $this->noStudentRecord();
        }

        $d['m'] =  $d['marks']->first();
        $d['exams'] = $this->exam->all();
        $d['my_classes'] = $this->my_class->all();
        $d['sections'] = $this->my_class->getAllSections();
        $d['subjects'] = $this->my_class->getAllSubjects();
        if(Qs::userIsTeacher()){
            $d['subjects'] = $this->my_class->findSubjectByTeacher(Auth::user()->id)->where('my_class_id', $class_id);
        }
        $d['selected'] = true;
        $d['class_type'] = $ct = $this->my_class->findTypeByClass($class_id);

        return Ui::render('Marks/Manage', function () use ($d, $ct, $exam_id, $class_id, $section_id, $subject_id) {
            $m = $d['m'];
            $exam = $m->exam;
            $tex = 'tex'.$exam->term;
            $marks = $d['marks']->load('user.student_record')->sortBy('user.name')->values();

            // Grade bands for this class type, falling back to the general bands (as MarkRepo::getGrade does).
            $grades = \App\Models\Grade::where('class_type_id', $ct->id)->get();
            $general = \App\Models\Grade::whereNull('class_type_id')->get();

            return array_merge($this->selectorProps($this->exam->getExam(['year' => $this->year]), [
                'exam_id' => (int) $exam_id, 'my_class_id' => (int) $class_id, 'section_id' => (int) $section_id, 'subject_id' => (int) $subject_id,
            ]), [
                'context' => [
                    'exam' => $exam->name,
                    'term' => $exam->term,
                    'year' => $m->year,
                    'class' => optional($m->my_class)->name,
                    'section' => optional($m->section)->name,
                    'subject' => optional($m->subject)->name,
                ],
                'grades' => $grades->map(function ($g) { return ['name' => $g->name, 'from' => (int) $g->mark_from, 'to' => (int) $g->mark_to, 'remark' => $g->remark]; })->values(),
                'generalGrades' => $general->map(function ($g) { return ['name' => $g->name, 'from' => (int) $g->mark_from, 'to' => (int) $g->mark_to, 'remark' => $g->remark]; })->values(),
                'rows' => $marks->map(function ($mk) use ($tex) {
                    return [
                        'id' => $mk->id,
                        'name' => optional($mk->user)->name,
                        'photo' => optional($mk->user)->photo,
                        'adm_no' => optional(optional($mk->user)->student_record)->adm_no,
                        't1' => $mk->t1,
                        't2' => $mk->t2,
                        'exm' => $mk->exm,
                        'total' => $mk->$tex,
                        'grade' => optional($mk->grade)->name,
                        'position' => $mk->sub_pos,
                    ];
                }),
                'urls' => array_merge($this->selectorProps(collect(), null)['urls'], [
                    'update' => route('marks.update', [$exam_id, $class_id, $section_id, $subject_id]),
                    'tabulation' => Qs::userIsTeamSA() ? route('marks.tabulation', [$exam_id, $class_id, $section_id]) : null,
                ]),
            ]);
        }, 'pages.support_team.marks.manage', $d);
    }

    public function update(Request $req, $exam_id, $class_id, $section_id, $subject_id)
    {
        if (!TeacherScope::canMark((int) $class_id, (int) $section_id, (int) $subject_id)) {
            return Qs::json('You can only enter marks for your own class, or for the subjects you teach.', false);
        }
        $p = ['exam_id' => $exam_id, 'my_class_id' => $class_id, 'section_id' => $section_id, 'subject_id' => $subject_id, 'year' => $this->year];

        $d = $d3 = $all_st_ids = [];

        $exam = $this->exam->find($exam_id);
        $marks = $this->exam->getMark($p);
        $class_type = $this->my_class->findTypeByClass($class_id);

        $mks = $req->all();

        /** Test, Exam, Grade **/
        foreach($marks->sortBy('user.name') as $mk)
        {
            $all_st_ids[] = $mk->student_id;

                $d['t1'] = $t1 = $mks['t1_'.$mk->id];
                $d['t2'] = $t2 = $mks['t2_'.$mk->id];
                $d['tca'] = $tca = $t1 + $t2;
                $d['exm'] = $exm = $mks['exm_'.$mk->id];


            /** SubTotal Grade, Remark, Cum, CumAvg**/

            $d['tex'.$exam->term] = $total = $tca + $exm;

            if($total > 100){
                $d['tex'.$exam->term] = $d['t1'] = $d['t2'] = $d['t3'] = $d['t4'] = $d['tca'] = $d['exm'] = NULL;
            }

         /*   if($exam->term < 3){
                $grade = $this->mark->getGrade($total, $class_type->id);
            }

            if($exam->term == 3){
                $d['cum'] = $this->mark->getSubCumTotal($total, $st_id, $subject_id, $class_id, $this->year);
                $d['cum_ave'] = $cav = $this->mark->getSubCumAvg($total, $st_id, $subject_id, $class_id, $this->year);
                $grade = $this->mark->getGrade(round($cav), $class_type->id);
            }*/
            $grade = $this->mark->getGrade($total, $class_type->id);
            $d['grade_id'] = $grade ? $grade->id : NULL;

            $this->exam->updateMark($mk->id, $d);
        }

        /** Sub Position Begin  **/

        foreach($marks->sortBy('user.name') as $mk)
        {

            $d2['sub_pos'] = $this->mark->getSubPos($mk->student_id, $exam, $class_id, $subject_id, $this->year);

            $this->exam->updateMark($mk->id, $d2);
        }

        /*Sub Position End*/

        /* Exam Record Update */

        unset( $p['subject_id'] );

        foreach ($all_st_ids as $st_id) {

            $p['student_id'] =$st_id;
            $d3['total'] = $this->mark->getExamTotalTerm($exam, $st_id, $class_id, $this->year);
            $d3['ave'] = $this->mark->getExamAvgTerm($exam, $st_id, $class_id, $section_id, $this->year);
            $d3['class_ave'] = $this->mark->getClassAvg($exam, $class_id, $this->year);
            $d3['pos'] = $this->mark->getPos($st_id, $exam, $class_id, $section_id, $this->year);

            $this->exam->updateRecord($p, $d3);
        }
        /*Exam Record End*/

       return Qs::jsonUpdateOk();
    }

    public function batch_fix()
    {
        $d['exams'] = $this->exam->getExam(['year' => $this->year]);
        $d['my_classes'] = $this->my_class->all();
        $d['sections'] = $this->my_class->getAllSections();
        $d['selected'] = false;

        return Ui::render('Marks/Tools', function () use ($d) { return $this->toolsProps('fix', $d); }, 'pages.support_team.marks.batch_fix', $d);
    }

    public function batch_update(Request $req): \Illuminate\Http\JsonResponse
    {
        $exam_id = $req->exam_id;
        $class_id = $req->my_class_id;
        $section_id = $req->section_id;

        $w = ['exam_id' => $exam_id, 'my_class_id' => $class_id, 'section_id' => $section_id, 'year' => $this->year];

        $exam = $this->exam->find($exam_id);
        $exrs = $this->exam->getRecord($w);
        $marks = $this->exam->getMark($w);

        /** Marks Fix Begin **/

        $class_type = $this->my_class->findTypeByClass($class_id);
        $tex = 'tex'.$exam->term;

        foreach($marks as $mk){

            $total = $mk->$tex;
            $d['grade_id'] = $this->mark->getGrade($total, $class_type->id);

            /*      if($exam->term == 3){
                      $d['cum'] = $this->mark->getSubCumTotal($total, $mk->student_id, $mk->subject_id, $class_id, $this->year);
                      $d['cum_ave'] = $cav = $this->mark->getSubCumAvg($total, $mk->student_id, $mk->subject_id, $class_id, $this->year);
                      $grade = $this->mark->getGrade(round($mk->cum_ave), $class_type->id);
                  }*/

            $this->exam->updateMark($mk->id, $d);
        }

        /* Marks Fix End*/

        /** Exam Record Update  **/
        foreach($exrs as $exr){

            $st_id = $exr->student_id;

            $d3['total'] = $this->mark->getExamTotalTerm($exam, $st_id, $class_id, $this->year);
            $d3['ave'] = $this->mark->getExamAvgTerm($exam, $st_id, $class_id, $section_id, $this->year);
            $d3['class_ave'] = $this->mark->getClassAvg($exam, $class_id, $this->year);
            $d3['pos'] = $this->mark->getPos($st_id, $exam, $class_id, $section_id, $this->year);

            $this->exam->updateRecord(['id' => $exr->id], $d3);
        }

        /** END Exam Record Update END **/

        return Qs::jsonUpdateOk();
    }

    public function comment_update(Request $req, $exr_id)
    {
        if (!$this->mayEditRecord($exr_id)) {
            return Qs::json('Only the class teacher can comment on these results.', false);
        }
        $d = Qs::userIsTeamSA() ? $req->only(['t_comment', 'p_comment']) : $req->only(['t_comment']);

        $this->exam->updateRecord(['id' => $exr_id], $d);
        return Qs::jsonUpdateOk();
    }

    public function skills_update(Request $req, $skill, $exr_id)
    {
        if (!$this->mayEditRecord($exr_id)) {
            return Qs::json('Only the class teacher can rate these skills.', false);
        }
        $d = [];
        if($skill == 'AF' || $skill == 'PS'){
            $sk = strtolower($skill);
            $d[$skill] = implode(',', $req->$sk);
        }

        $this->exam->updateRecord(['id' => $exr_id], $d);
        return Qs::jsonUpdateOk();
    }

    public function bulk($class_id = NULL, $section_id = NULL)
    {
        if ($section_id && TeacherScope::applies() && !in_array((int) $section_id, TeacherScope::ownSectionIds(), true)) {
            return redirect()->route('marks.bulk')->with('flash_danger', 'You can only open results for your own class.');
        }
        $d['my_classes'] = $this->my_class->all();
        $d['selected'] = false;

        if($class_id && $section_id){
            $d['sections'] = $this->my_class->getAllSections()->where('my_class_id', $class_id);
            $d['students'] = $st = $this->student->getRecord(['my_class_id' => $class_id, 'section_id' => $section_id])->get()->sortBy('user.name');
            if($st->count() < 1){
                return redirect()->route('marks.bulk')->with('flash_danger', __('msg.srnf'));
            }
            $d['selected'] = true;
            $d['my_class_id'] = $class_id;
            $d['section_id'] = $section_id;
        }

        return Ui::render('Marks/Tools', function () use ($d) {
            return array_merge($this->toolsProps('sheets', $d), [
                'selected' => $d['selected'] ? ['class' => (int) $d['my_class_id'], 'section' => (int) $d['section_id']] : null,
                'students' => $d['selected'] ? $d['students']->map(function ($s) {
                    return ['name' => $s->user->name, 'photo' => $s->user->photo, 'adm_no' => $s->adm_no, 'url' => route('marks.year_selector', Qs::hash($s->user_id))];
                })->values() : [],
            ]);
        }, 'pages.support_team.marks.bulk', $d);
    }

    /** Shared props for the marks tools page: tabulation, marksheets by class and fixing totals. */
    protected function toolsProps(string $tool, array $d): array
    {
        return [
            'tool' => $tool,
            'year' => $this->year,
            'exams' => $this->exam->getExam(['year' => $this->year])->map(function ($e) { return ['id' => $e->id, 'name' => $e->name, 'term' => (int) $e->term]; })->values(),
            'classes' => ClassOrder::sort($this->my_class->all())->filter(function ($c) {
                return !TeacherScope::applies() || \App\Models\Section::where('my_class_id', $c->id)->whereIn('id', TeacherScope::ownSectionIds())->exists();
            })->map(function ($c) { return ['id' => $c->id, 'name' => $c->name]; })->values(),
            'sections' => $this->my_class->getAllSections()->filter(function ($x) {
                return !TeacherScope::applies() || in_array((int) $x->id, TeacherScope::ownSectionIds(), true);
            })->map(function ($x) { return ['id' => $x->id, 'name' => $x->name, 'class_id' => $x->my_class_id]; })->values(),
            'selected' => null,
            'urls' => [
                'tabulation' => route('marks.tabulation'),
                'sheets' => route('marks.bulk'),
                'fix' => route('marks.batch_fix'),
                'fixUpdate' => route('marks.batch_update'),
                'entry' => route('marks.index'),
            ],
        ];
    }

    /** Teachers may only comment on / rate students of the section they are class teacher of. */
    protected function mayEditRecord($exr_id): bool
    {
        if (!TeacherScope::applies()) {
            return true;
        }
        $section = \App\Models\ExamRecord::where('id', $exr_id)->value('section_id');

        return $section && in_array((int) $section, TeacherScope::ownSectionIds(), true);
    }

    public function bulk_select(Request $req)
    {
        return redirect()->route('marks.bulk', [$req->my_class_id, $req->section_id]);
    }

    public function tabulation($exam_id = NULL, $class_id = NULL, $section_id = NULL)
    {
        $d['my_classes'] = $this->my_class->all();
        $d['exams'] = $this->exam->getExam(['year' => $this->year]);
        $d['selected'] = FALSE;

        if($class_id && $exam_id && $section_id){

            $wh = ['my_class_id' => $class_id, 'section_id' => $section_id, 'exam_id' => $exam_id, 'year' => $this->year];

            $sub_ids = $this->mark->getSubjectIDs($wh);
            $st_ids = $this->mark->getStudentIDs($wh);

            if(count($sub_ids) < 1 OR count($st_ids) < 1) {
                return Qs::goWithDanger('marks.tabulation', __('msg.srnf'));
            }

            $d['subjects'] = $this->my_class->getSubjectsByIDs($sub_ids);
            $d['students'] = $this->student->getRecordByUserIDs($st_ids)->get()->sortBy('user.name');
            $d['sections'] = $this->my_class->getAllSections();

            $d['selected'] = TRUE;
            $d['my_class_id'] = $class_id;
            $d['section_id'] = $section_id;
            $d['exam_id'] = $exam_id;
            $d['year'] = $this->year;
            $d['marks'] = $mks = $this->exam->getMark($wh);
            $d['exr'] = $exr = $this->exam->getRecord($wh);

            $d['my_class'] = $mc = $this->my_class->find($class_id);
            $d['section']  = $this->my_class->findSection($section_id);
            $d['ex'] = $exam = $this->exam->find($exam_id);
            $d['tex'] = 'tex'.$exam->term;
            //$d['class_type'] = $this->my_class->findTypeByClass($mc->id);
            //$d['ct'] = $ct = $d['class_type']->code;
        }

        return Ui::render('Marks/Tools', function () use ($d) {
            $props = $this->toolsProps('tabulation', $d);
            if (!$d['selected']) return $props;
            $tex = $d['tex'];
            return array_merge($props, [
                'selected' => ['exam' => (int) $d['exam_id'], 'class' => (int) $d['my_class_id'], 'section' => (int) $d['section_id']],
                'sheet' => [
                    'title' => $d['my_class']->name.' '.$d['section']->name.' · '.$d['ex']->name.' · '.$d['year'],
                    'subjects' => $d['subjects']->map(function ($s) { return ['id' => $s->id, 'name' => $s->name, 'short' => strtoupper($s->slug ?: $s->name)]; })->values(),
                    'rows' => $d['students']->values()->map(function ($s) use ($d, $tex) {
                        $r = $d['exr']->where('student_id', $s->user_id)->first();
                        return [
                            'name' => $s->user->name,
                            'scores' => $d['subjects']->map(function ($sub) use ($d, $s, $tex) { return optional($d['marks']->where('student_id', $s->user_id)->where('subject_id', $sub->id)->first())->$tex; })->values(),
                            'total' => optional($r)->total, 'ave' => optional($r)->ave, 'pos' => optional($r)->pos,
                            'url' => route('marks.year_selector', Qs::hash($s->user_id)),
                        ];
                    }),
                    'print' => route('marks.print_tabulation', [$d['exam_id'], $d['my_class_id'], $d['section_id']]),
                ],
            ]);
        }, 'pages.support_team.marks.tabulation.index', $d);
    }

    public function print_tabulation($exam_id, $class_id, $section_id)
    {
        $wh = ['my_class_id' => $class_id, 'section_id' => $section_id, 'exam_id' => $exam_id, 'year' => $this->year];

        $sub_ids = $this->mark->getSubjectIDs($wh);
        $st_ids = $this->mark->getStudentIDs($wh);

        if(count($sub_ids) < 1 OR count($st_ids) < 1) {
            return Qs::goWithDanger('marks.tabulation', __('msg.srnf'));
        }

        $d['subjects'] = $this->my_class->getSubjectsByIDs($sub_ids);
        $d['students'] = $this->student->getRecordByUserIDs($st_ids)->get()->sortBy('user.name');

        $d['my_class_id'] = $class_id;
        $d['exam_id'] = $exam_id;
        $d['year'] = $this->year;
        $wh = ['exam_id' => $exam_id, 'my_class_id' => $class_id];
        $d['marks'] = $mks = $this->exam->getMark($wh);
        $d['exr'] = $exr = $this->exam->getRecord($wh);

        $d['my_class'] = $mc = $this->my_class->find($class_id);
        $d['section']  = $this->my_class->findSection($section_id);
        $d['ex'] = $exam = $this->exam->find($exam_id);
        $d['tex'] = 'tex'.$exam->term;
        $d['s'] = Setting::all()->flatMap(function($s){
            return [$s->type => $s->description];
        });
        //$d['class_type'] = $this->my_class->findTypeByClass($mc->id);
        //$d['ct'] = $ct = $d['class_type']->code;

        return view('pages.support_team.marks.tabulation.print', $d);
    }

    public function tabulation_select(Request $req)
    {
        return redirect()->route('marks.tabulation', [$req->exam_id, $req->my_class_id, $req->section_id]);
    }

    protected function verifyStudentExamYear($student_id, $year = null)
    {
        $years = $this->exam->getExamYears($student_id);
        $student_exists = $this->student->exists($student_id);

        if(!$year){
            if($student_exists && $years->count() > 0)
            {
                $d =['years' => $years, 'student_id' => Qs::hash($student_id)];

                return view('pages.support_team.marks.select_year', $d);
            }

            return $this->noStudentRecord();
        }

        return ($student_exists && $years->contains('year', $year)) ? true  : false;
    }

    protected function noStudentRecord()
    {
        return redirect()->route('dashboard')->with('flash_danger', __('msg.srnf'));
    }

    protected function checkPinVerified($st_id)
    {
        return Session::has('pin_verified') && Session::get('pin_verified') == $st_id;
    }

}
