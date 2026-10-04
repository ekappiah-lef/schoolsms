<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Dorm\DormCreate;
use App\Http\Requests\Dorm\DormUpdate;
use App\Repositories\DormRepo;

class DormController extends Controller
{
    protected  $dorm;

    public function __construct(DormRepo $dorm)
    {
        $this->middleware('teamSA', ['except' => ['destroy',] ]);
        $this->middleware('super_admin', ['only' => ['destroy',] ]);

        $this->dorm = $dorm;
    }

    public function index()
    {
        $d['dorms'] = $this->dorm->getAll();
        return Ui::render('Dorms/Index', function () {
            return $this->pageProps();
        }, 'pages.support_team.dorms.index', $d);
    }

    protected function pageProps($editing = null): array
    {
        $students = DB::table('student_records')->where('grad', 0)->whereNotNull('dorm_id')->groupBy('dorm_id')->select('dorm_id', DB::raw('count(*) as n'))->pluck('n', 'dorm_id');

        return [
            'session' => Qs::getCurrentSession(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'dorms' => $this->dorm->getAll()->map(function ($d) use ($students) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'description' => $d->description,
                    'students' => (int) ($students[$d->id] ?? 0),
                    'urls' => ['edit' => route('dorms.edit', $d->id), 'destroy' => route('dorms.destroy', $d->id)],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('dorms.store'), 'index' => route('dorms.index')],
        ];
    }

    public function store(DormCreate $req)
    {
        $data = $req->only(['name', 'description']);
        $this->dorm->create($data);

        return Qs::jsonStoreOk();
    }

    public function edit($id)
    {
        $d['dorm'] = $dorm = $this->dorm->find($id);

        if (is_null($dorm)) {
            return Qs::goWithDanger('dorms.index');
        }
        return Ui::render('Dorms/Index', function () use ($dorm) {
            return $this->pageProps(['name' => $dorm->name, 'description' => $dorm->description, 'url' => route('dorms.update', $dorm->id)]);
        }, 'pages.support_team.dorms.edit', $d);
    }

    public function update(DormUpdate $req, $id)
    {
        $data = $req->only(['name', 'description']);
        $this->dorm->update($id, $data);

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        $this->dorm->find($id)->delete();
        return back()->with('flash_success', __('msg.delete_ok'));
    }
}
