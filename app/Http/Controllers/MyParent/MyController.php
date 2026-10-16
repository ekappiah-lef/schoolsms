<?php

namespace App\Http\Controllers\MyParent;
use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Repositories\StudentRepo;
use App\Support\Fees;
use App\Support\Notices;
use Illuminate\Http\Request;
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

    /** Account statement for a child (or all children) over a period: bills, payments and the running balance. */
    public function statement(Request $req)
    {
        [$children, $ids, $from, $to] = $this->statementScope($req);

        return \Inertia\Inertia::render('Parent/Statement', [
            'children' => $children->map(function ($s) { return ['id' => Qs::hash($s->user_id), 'name' => $s->user->name]; })->values(),
            'filters' => ['child' => $req->query('child', 'all'), 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'statement' => Fees::accountStatement($ids, $from, $to),
            'urls' => ['self' => route('my_children.statement'), 'pdf' => route('my_children.statement_pdf')],
        ]);
    }

    public function statementPdf(Request $req)
    {
        [$children, $ids, $from, $to] = $this->statementScope($req);
        $st = Fees::accountStatement($ids, $from, $to);
        $names = $children->whereIn('user_id', $ids)->map(function ($s) { return $s->user->name; })->values()->all();

        return \App\Support\Pdf::loadView('pdf.statement', [
            'school' => Qs::getSystemName(), 'parent' => Auth::user()->name, 'children' => $names, 'many' => count($ids) > 1,
            'period' => $from->format('j M Y').' – '.$to->format('j M Y'), 'st' => $st,
        ])->setPaper('a4')->download('Statement-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    /** The parent's children, the chosen child (or all), and the period (default: this school year to today). */
    protected function statementScope(Request $req): array
    {
        $children = $this->student->getRecord(['my_parent_id' => Auth::id()])->with('user')->get()->filter(function ($s) { return $s->user; })->sortBy('user.name')->values();
        $chosen = $req->query('child', 'all') === 'all' ? null : Qs::decodeHash($req->query('child'));
        $ids = $children->pluck('user_id')->filter(function ($id) use ($chosen) { return !$chosen || (int) $id === (int) $chosen; })->map(function ($v) { return (int) $v; })->values()->all();
        $parse = function ($v, $d) { try { return $v ? \Carbon\Carbon::parse($v) : $d; } catch (\Throwable $e) { return $d; } };
        $to = $parse($req->query('to'), today())->min(today());
        $from = $parse($req->query('from'), \Carbon\Carbon::create((int) substr(Qs::getCurrentSession(), 0, 4), 8, 1))->min($to);

        return [$children, $ids ?: [0], $from, $to];
    }

}
