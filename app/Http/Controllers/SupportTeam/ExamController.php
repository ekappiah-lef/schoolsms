<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Requests\Exam\ExamCreate;
use App\Http\Requests\Exam\ExamUpdate;
use App\Repositories\ExamRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    protected $exam;
    public function __construct(ExamRepo $exam)
    {
        $this->middleware('teamSA', ['except' => ['destroy',] ]);
        $this->middleware('super_admin', ['only' => ['destroy',] ]);

        $this->exam = $exam;
    }

    public function index()
    {
        $d['exams'] = $this->exam->all();
        return Ui::render('Exams/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.exams.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $records = DB::table('exam_records')->groupBy('exam_id')->select('exam_id', DB::raw('count(*) as n'), DB::raw('avg(ave) as avg'))->get()->keyBy('exam_id');

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'exams' => $this->exam->all()->sortByDesc(function ($e) { return $e->year.'-'.$e->term; })->map(function ($e) use ($records) {
                $r = $records[$e->id] ?? null;
                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'term' => (int) $e->term,
                    'year' => $e->year,
                    'results' => $r ? (int) $r->n : 0,
                    'average' => $r && $r->avg !== null ? round((float) $r->avg, 1) : null,
                    'urls' => ['show' => route('exams.show', $e->id), 'edit' => route('exams.edit', $e->id), 'destroy' => route('exams.destroy', $e->id)],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('exams.store'), 'index' => route('exams.index')],
        ];
    }

    public function store(ExamCreate $req)
    {
        $data = $req->only(['name', 'term']);
        $data['year'] = Qs::getSetting('current_session');

        $this->exam->create($data);
        return back()->with('flash_success', __('msg.store_ok'));
    }

    public function edit($id)
    {
        $d['ex'] = $this->exam->find($id);
        $ex = $d['ex'];
        if (is_null($ex)) {
            return Qs::goWithDanger('exams.index');
        }
        return Ui::render('Exams/Index', function () use ($ex) {
            return $this->pageProps(['name' => $ex->name, 'term' => (string) $ex->term, 'year' => $ex->year, 'url' => route('exams.update', $ex->id)]);
        }, 'pages.support_team.exams.edit', $d);
    }

    public function update(ExamUpdate $req, $id)
    {
        $data = $req->only(['name', 'term']);

        $this->exam->update($id, $data);
        return back()->with('flash_success', __('msg.update_ok'));
    }

    /** Examination overview: results by class, subject averages, top students, and links to marks, tabulation and report sheets. */
    public function show($id)
    {
        $ex = $this->exam->find($id);
        if (is_null($ex)) {
            return Qs::goWithDanger('exams.index');
        }
        $current = $ex->year === Qs::getCurrentSession();
        $tex = 'tex'.$ex->term;

        $byClass = DB::table('exam_records as r')->join('my_classes as c', 'c.id', '=', 'r.my_class_id')->join('sections as s', 's.id', '=', 'r.section_id')
            ->where('r.exam_id', $ex->id)->groupBy('r.my_class_id', 'r.section_id', 'c.name', 's.name', 'c.class_type_id')
            ->select('r.my_class_id', 'r.section_id', 'c.name as class', 's.name as section', 'c.class_type_id', DB::raw('count(*) as students'), DB::raw('avg(r.ave) as avg'), DB::raw('max(r.ave) as best'))
            ->get();
        $byClass = \App\Support\ClassOrder::sort($byClass, 'class_type_id', 'class');

        $subjects = DB::table('marks as m')->join('subjects as s', 's.id', '=', 'm.subject_id')->where('m.exam_id', $ex->id)->whereNotNull("m.$tex")
            ->groupBy('s.name')->select('s.name', DB::raw("avg(m.$tex) as avg"))->orderByDesc('avg')->get();

        $top = DB::table('exam_records as r')->join('users as u', 'u.id', '=', 'r.student_id')->join('my_classes as c', 'c.id', '=', 'r.my_class_id')
            ->where('r.exam_id', $ex->id)->whereNotNull('r.ave')->orderByDesc('r.ave')->limit(5)
            ->select('u.id', 'u.name', 'c.name as class', 'r.ave')->get();

        $all = DB::table('exam_records')->where('exam_id', $ex->id);

        return Ui::render('Exams/Show', function () use ($ex, $current, $byClass, $subjects, $top, $all) {
            return [
                'exam' => [
                    'name' => $ex->name, 'term' => (int) $ex->term, 'year' => $ex->year, 'current' => $current,
                    'results' => (clone $all)->count(), 'average' => round((float) (clone $all)->avg('ave'), 1),
                ],
                'classes' => $byClass->map(function ($r) use ($ex, $current) {
                    return [
                        'name' => $r->class.' '.$r->section, 'students' => (int) $r->students,
                        'average' => round((float) $r->avg, 1), 'best' => round((float) $r->best, 1),
                        'urls' => $current ? [
                            'tabulation' => route('marks.tabulation', [$ex->id, $r->my_class_id, $r->section_id]),
                            'results' => route('marks.bulk', [$r->my_class_id, $r->section_id]),
                        ] : null,
                    ];
                })->values(),
                'subjects' => $subjects->map(function ($s) { return ['subject' => $s->name, 'average' => round((float) $s->avg, 1)]; })->values(),
                'top' => $top->map(function ($t) { return ['name' => $t->name, 'class' => $t->class, 'average' => round((float) $t->ave, 1), 'url' => route('marks.year_selector', Qs::hash($t->id))]; })->values(),
                'urls' => ['index' => route('exams.index'), 'edit' => route('exams.edit', $ex->id), 'marks' => route('marks.index')],
            ];
        }, 'pages.support_team.exams.index', ['exams' => $this->exam->all()]);
    }

    public function destroy($id)
    {
        $this->exam->delete($id);
        return back()->with('flash_success', __('msg.del_ok'));
    }
}
