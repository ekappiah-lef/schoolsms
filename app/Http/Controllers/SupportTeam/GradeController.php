<?php

namespace App\Http\Controllers\SupportTeam;

use App\Http\Requests\Grade\GradeCreate;
use App\Http\Requests\Grade\GradeUpdate;
use App\Repositories\ExamRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Mk;
use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Repositories\MyClassRepo;

class GradeController extends Controller
{
    protected $exam, $my_class;

    public function __construct(ExamRepo $exam, MyClassRepo $my_class)
    {
        $this->exam = $exam;
        $this->my_class = $my_class;

        $this->middleware('teamSA', ['except' => ['destroy',] ]);
        $this->middleware('super_admin', ['only' => ['destroy',] ]);
    }

    public function index()
    {
         $d['grades'] = $this->exam->allGrades();
         $d['class_types'] = $this->my_class->getTypes();
        return Ui::render('Grades/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.grades.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $types = $this->my_class->getTypes();

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'types' => $types->map(function ($t) { return ['id' => $t->id, 'name' => $t->name]; })->values(),
            'remarks' => Mk::getRemarks(),
            'grades' => $this->exam->allGrades()->sortByDesc('mark_from')->map(function ($g) use ($types) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'from' => (int) $g->mark_from,
                    'to' => (int) $g->mark_to,
                    'remark' => $g->remark,
                    'type' => optional($types->firstWhere('id', $g->class_type_id))->name,
                    'urls' => ['edit' => route('grades.edit', $g->id), 'destroy' => route('grades.destroy', $g->id)],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('grades.store'), 'index' => route('grades.index')],
        ];
    }

    public function store(GradeCreate $req)
    {
        $data = $req->all();

        $this->exam->createGrade($data);
        $this->regrade();
        return back()->with('flash_success', __('msg.store_ok'));
    }

    public function edit($id)
    {
        $d['class_types'] = $this->my_class->getTypes();
        $d['gr'] = $this->exam->findGrade($id);
        $gr = $d['gr'];
        if (is_null($gr)) {
            return Qs::goWithDanger('grades.index');
        }
        return Ui::render('Grades/Index', function () use ($gr) {
            return $this->pageProps([
                'name' => $gr->name,
                'class_type_id' => (string) ($gr->class_type_id ?? ''),
                'mark_from' => (string) $gr->mark_from,
                'mark_to' => (string) $gr->mark_to,
                'remark' => $gr->remark ?? '',
                'url' => route('grades.update', $gr->id),
            ]);
        }, 'pages.support_team.grades.edit', $d);
    }

    public function update(GradeUpdate $req, $id)
    {
        $data = $req->all();

        $this->exam->updateGrade($id, $data);
        $this->regrade();
        return back()->with('flash_success', __('msg.update_ok'));
    }

    public function destroy($id)
    {
        $this->exam->deleteGrade($id);
        $this->regrade();
        return back()->with('flash_success', __('msg.del_ok'));
    }

    /**
     * Apply the grading scale to every mark of the current year, so a change to the scale shows on
     * results at once (totals, averages and positions do not depend on the scale and stay as they are).
     */
    protected function regrade(): void
    {
        $year = Qs::getCurrentSession();
        $marks = app(\App\Repositories\MarkRepo::class);
        $terms = \DB::table('exams')->where('year', $year)->pluck('term', 'id');
        $types = \DB::table('my_classes')->pluck('class_type_id', 'id');

        foreach (\DB::table('marks')->where('year', $year)->get() as $mk) {
            $total = (int) ($mk->{'tex'.($terms[$mk->exam_id] ?? 1)} ?? 0);
            $grade = $marks->getGrade($total, $types[$mk->my_class_id] ?? null);
            $id = $grade ? $grade->id : null;
            if ((int) $mk->grade_id !== (int) $id) {
                \DB::table('marks')->where('id', $mk->id)->update(['grade_id' => $id]);
            }
        }
    }
}
