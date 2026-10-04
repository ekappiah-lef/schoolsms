<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Requests\Section\SectionCreate;
use App\Http\Requests\Section\SectionUpdate;
use App\Repositories\MyClassRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use App\Support\ClassOrder;
use Illuminate\Support\Facades\DB;
use App\Repositories\UserRepo;

class SectionController extends Controller
{
    protected $my_class, $user;

    public function __construct(MyClassRepo $my_class, UserRepo $user)
    {
        $this->middleware('teamSA', ['except' => ['destroy',] ]);
        $this->middleware('super_admin', ['only' => ['destroy',] ]);

        $this->my_class = $my_class;
        $this->user = $user;
    }

    public function index()
    {
        $d['my_classes'] = $this->my_class->all();
        $d['sections'] = $this->my_class->getAllSections();
        $d['teachers'] = $this->user->getUserByType('teacher');

        return Ui::render('Sections/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.sections.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $students = DB::table('student_records')->where('grad', 0)->groupBy('section_id')->select('section_id', DB::raw('count(*) as n'))->pluck('n', 'section_id');
        $classes = ClassOrder::sort($this->my_class->all());
        $order = $classes->pluck('id')->flip();

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'classes' => $classes->map(function ($c) { return ['id' => $c->id, 'name' => $c->name]; })->values(),
            'teachers' => $this->user->getUserByType('teacher')->map(function ($t) { return ['id' => Qs::hash($t->id), 'name' => $t->name]; })->values(),
            'sections' => $this->my_class->getAllSections()->sortBy(function ($s) use ($order) {
                return sprintf('%05d-%s', $order[$s->my_class_id] ?? 99999, $s->name);
            })->map(function ($s) use ($students) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'class' => optional($s->my_class)->name,
                    'teacher' => optional($s->teacher)->name,
                    'default' => (bool) $s->active,
                    'students' => (int) ($students[$s->id] ?? 0),
                    'capacity' => $s->capacity ? (int) $s->capacity : null,
                    'urls' => [
                        'edit' => route('sections.edit', $s->id),
                        'destroy' => route('sections.destroy', $s->id),
                        'students' => route('students.index', ['class' => $s->my_class_id, 'section' => $s->id]),
                    ],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('sections.store'), 'index' => route('sections.index')],
        ];
    }

    public function store(SectionCreate $req)
    {
        $data = $req->all();
        $data['capacity'] = $req->capacity ?: null;
        $this->my_class->createSection($data);

        return Qs::jsonStoreOk();
    }

    public function edit($id)
    {
        $d['s'] = $s = $this->my_class->findSection($id);
        $d['teachers'] = $this->user->getUserByType('teacher');

        if (is_null($s)) {
            return Qs::goWithDanger('sections.index');
        }
        return Ui::render('Sections/Index', function () use ($s) {
            return $this->pageProps([
                'name' => $s->name,
                'class' => optional($s->my_class)->name,
                'teacher_id' => $s->teacher_id ? Qs::hash($s->teacher_id) : '',
                'capacity' => $s->capacity ?: '',
                'url' => route('sections.update', $s->id),
            ]);
        }, 'pages.support_team.sections.edit', $d);
    }

    public function update(SectionUpdate $req, $id)
    {
        $data = $req->only(['name', 'teacher_id', 'capacity']);
        $data['capacity'] = $req->capacity ?: null;
        $this->my_class->updateSection($id, $data);

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        if($this->my_class->isActiveSection($id)){
            return back()->with('pop_warning', 'Every class must have a default section, You Cannot Delete It');
        }

        $this->my_class->deleteSection($id);
        return back()->with('flash_success', __('msg.del_ok'));
    }

}
