<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Requests\Subject\SubjectCreate;
use App\Http\Requests\Subject\SubjectUpdate;
use App\Repositories\MyClassRepo;
use App\Repositories\UserRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use App\Support\ClassOrder;

class SubjectController extends Controller
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
        $d['teachers'] = $this->user->getUserByType('teacher');
        $d['subjects'] = $this->my_class->getAllSubjects();

        return Ui::render('Subjects/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.subjects.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $classes = ClassOrder::sort($this->my_class->all());
        $order = $classes->pluck('id')->flip();

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'classes' => $classes->map(function ($c) { return ['id' => $c->id, 'name' => $c->name]; })->values(),
            'teachers' => $this->user->getUserByType('teacher')->map(function ($t) { return ['id' => Qs::hash($t->id), 'name' => $t->name]; })->values(),
            'subjects' => $this->my_class->getAllSubjects()->sortBy(function ($s) use ($order) {
                return sprintf('%05d-%s', $order[$s->my_class_id] ?? 99999, $s->name);
            })->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'class' => optional($s->my_class)->name,
                    'teacher' => optional($s->teacher)->name,
                    'urls' => ['edit' => route('subjects.edit', $s->id), 'destroy' => route('subjects.destroy', $s->id)],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('subjects.store'), 'index' => route('subjects.index')],
        ];
    }

    public function store(SubjectCreate $req)
    {
        $data = $req->all();
        $this->my_class->createSubject($data);

        return Qs::jsonStoreOk();
    }

    public function edit($id)
    {
        $d['s'] = $sub = $this->my_class->findSubject($id);
        $d['my_classes'] = $this->my_class->all();
        $d['teachers'] = $this->user->getUserByType('teacher');

        if (is_null($sub)) {
            return Qs::goWithDanger('subjects.index');
        }
        return Ui::render('Subjects/Index', function () use ($sub) {
            return $this->pageProps([
                'name' => $sub->name,
                'slug' => $sub->slug,
                'my_class_id' => (string) $sub->my_class_id,
                'teacher_id' => $sub->teacher_id ? Qs::hash($sub->teacher_id) : '',
                'url' => route('subjects.update', $sub->id),
            ]);
        }, 'pages.support_team.subjects.edit', $d);
    }

    public function update(SubjectUpdate $req, $id)
    {
        $data = $req->all();
        $this->my_class->updateSubject($id, $data);

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        $this->my_class->deleteSubject($id);
        return back()->with('flash_success', __('msg.del_ok'));
    }
}
