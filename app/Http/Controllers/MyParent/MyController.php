<?php

namespace App\Http\Controllers\MyParent;
use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Repositories\StudentRepo;
use App\Support\Fees;
use App\Support\Notices;
use Illuminate\Support\Facades\Auth;

class MyController extends Controller
{
    protected $student;
    public function __construct(StudentRepo $student)
    {
        $this->student = $student;
    }

    public function children()
    {
        $data['students'] = $this->student->getRecord(['my_parent_id' => Auth::user()->id])->with(['my_class', 'section'])->get();

        return Ui::render('Parent/Children', function () use ($data) {
            return [
                'children' => $data['students']->filter(function ($s) { return $s->user; })->sortBy('user.name')->map(function ($s) {
                    $t = Fees::statement($s->user_id)['totals'];
                    return [
                        'name' => $s->user->name, 'photo' => $s->user->photo, 'adm_no' => $s->adm_no,
                        'class' => trim(optional($s->my_class)->name.' '.optional($s->section)->name),
                        'status' => $s->grad ? 'graduated' : 'active',
                        'fees' => $t,
                        'urls' => [
                            'profile' => route('students.show', Qs::hash($s->id)),
                            'results' => route('marks.year_selector', Qs::hash($s->user_id)),
                            'fees' => Notices::statementUrl($s->user_id),
                        ],
                    ];
                })->values(),
            ];
        }, 'pages.parent.children', $data);
    }

}
