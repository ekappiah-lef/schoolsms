<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Requests\MyClass\ClassCreate;
use App\Http\Requests\MyClass\ClassUpdate;
use App\Repositories\MyClassRepo;
use App\Repositories\UserRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use App\Models\MyClass;
use App\Support\ClassOrder;
use Illuminate\Support\Facades\DB;

class MyClassController extends Controller
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
        $d['class_types'] = $this->my_class->getTypes();

        return Ui::render('Classes/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.classes.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $students = DB::table('student_records')->where('grad', 0)->groupBy('my_class_id')->select('my_class_id', DB::raw('count(*) as n'))->pluck('n', 'my_class_id');
        $subjects = DB::table('subjects')->groupBy('my_class_id')->select('my_class_id', DB::raw('count(*) as n'))->pluck('n', 'my_class_id');

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'types' => $this->my_class->getTypes()->map(function ($t) { return ['id' => $t->id, 'name' => $t->name]; })->values(),
            'classes' => ClassOrder::sort(MyClass::withCount('section')->with('class_type')->get())->map(function ($c) use ($students, $subjects) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => optional($c->class_type)->name,
                    'sections' => (int) $c->section_count,
                    'students' => (int) ($students[$c->id] ?? 0),
                    'subjects' => (int) ($subjects[$c->id] ?? 0),
                    'urls' => [
                        'edit' => route('classes.edit', $c->id),
                        'destroy' => route('classes.destroy', $c->id),
                        'students' => route('students.index', ['class' => $c->id]),
                    ],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('classes.store'), 'index' => route('classes.index')],
        ];
    }

    public function store(ClassCreate $req)
    {
        $data = $req->all();
        $mc = $this->my_class->create($data);

        // Create Default Section
        $s =['my_class_id' => $mc->id,
            'name' => 'A',
            'active' => 1,
            'teacher_id' => NULL,
        ];

        $this->my_class->createSection($s);

        return Qs::jsonStoreOk();
    }

    public function edit($id)
    {
        $d['c'] = $c = $this->my_class->find($id);

        if (is_null($c)) {
            return Qs::goWithDanger('classes.index');
        }
        return Ui::render('Classes/Index', function () use ($c) {
            return $this->pageProps([
                'name' => $c->name,
                'type' => optional($c->class_type)->name,
                'url' => route('classes.update', $c->id),
            ]);
        }, 'pages.support_team.classes.edit', $d);
    }

    public function update(ClassUpdate $req, $id)
    {
        $data = $req->only(['name']);
        $this->my_class->update($id, $data);

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        $this->my_class->delete($id);
        return back()->with('flash_success', __('msg.del_ok'));
    }

}
