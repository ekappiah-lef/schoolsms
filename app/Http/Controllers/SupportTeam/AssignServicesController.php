<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\StudentRecord;
use App\Support\Fees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Give one student feeding, bus or extra-curricular from now (billed this term, by the days left where the school uses that). */
class AssignServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index(Request $req)
    {
        $year = Qs::getCurrentSession();
        $id = (int) Qs::decodeHash((string) $req->query('student'));
        $sr = $id ? StudentRecord::where(['user_id' => $id, 'grad' => 0])->with(['user', 'my_class', 'section'])->first() : null;

        return Inertia::render('Finance/AssignServices', [
            'session' => $year,
            'students' => DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
                ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')->leftJoin('sections as s', 's.id', '=', 'sr.section_id')
                ->where('sr.grad', 0)->orderBy('u.name')->get(['sr.user_id', 'u.name', 'sr.adm_no', 'c.name as class', 's.name as section'])
                ->map(function ($s) { return ['value' => Qs::hash($s->user_id), 'label' => $s->name, 'hint' => trim(($s->class ?? '').' '.($s->section ?? '')).($s->adm_no ? ' · '.$s->adm_no : '')]; })->values(),
            'student' => $sr ? [
                'id' => Qs::hash($sr->user_id),
                'name' => $sr->user->name,
                'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
                'class_id' => $sr->my_class_id,
                'services' => Fees::selectionFor($sr->user_id, $year),
                'invoice' => route('payments.invoice', Qs::hash($sr->user_id)),
            ] : null,
            'catalogue' => Fees::catalogue(),
            'urls' => ['self' => route('finance.assign'), 'save' => route('finance.assign.save')],
        ]);
    }

    public function save(Request $req)
    {
        $id = (int) Qs::decodeHash((string) $req->input('student'));
        $sr = StudentRecord::where(['user_id' => $id, 'grad' => 0])->first();
        if (!$sr) return Qs::json('Choose a student.', false);
        $sel = (array) json_decode((string) $req->input('services'), true);
        unset($sel['sales']);
        if (!empty($sel['bus_direction']) && empty($sel['bus_route_id'])) return Qs::json('Choose the bus location, or set the bus to “No bus”.', false);

        $kept = Fees::syncOptional($sr->user_id, Qs::getCurrentSession(), $sel, true);
        \App\Support\ClearEnroll::studentChanged((int) $sr->user_id);

        return Qs::json('Saved.'.($kept ? ' Stopped from next term (this term stays billed): '.implode(', ', $kept).'.' : ''), true);
    }
}
