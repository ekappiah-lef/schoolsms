<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\FeeDiscount;
use App\Models\OptionalFeeCharge;
use App\Models\StudentRecord;
use App\Support\Fees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** The accountant's page for one student: discount, and feeding, bus or extra-curricular for next term or this term. */
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
                'next_services' => Fees::nextServices($sr, $year),
                'discount' => (string) ($sr->fee_discount_id ?: $sr->next_discount_id ?: ''),
                'staff_child' => (bool) $sr->staff_child,
                'invoice' => route('payments.invoice', Qs::hash($sr->user_id)),
            ] : null,
            'catalogue' => Fees::catalogue(),
            'discounts' => Fees::discountOptions(),
            'direct' => ['year' => $year, 'term' => Fees::termNow($year), 'years' => $this->directYears($year)],
            'urls' => ['self' => route('finance.assign'), 'save' => route('finance.assign.save'), 'direct' => route('finance.assign.direct')],
        ]);
    }

    /** This school year and the three before it. */
    protected function directYears(string $year): array
    {
        $start = (int) substr($year, 0, 4);

        return array_map(function ($i) use ($start) { return ($start - $i).'-'.($start - $i + 1); }, [0, 1, 2, 3]);
    }

    /** Every direct bill; only the super admin can remove one, and only while nothing is paid on it. */
    public function directBills()
    {
        return Inertia::render('Finance/DirectBills', [
            'bills' => DB::table('optional_fee_charges as c')->join('users as u', 'u.id', '=', 'c.student_id')
                ->where('c.group', 'direct')->orderByDesc('c.id')
                ->get(['c.id', 'c.label', 'c.year', 'c.term', 'c.amount', 'c.amt_paid', 'c.created_at', 'u.name'])
                ->map(function ($c) {
                    return ['id' => $c->id, 'student' => $c->name, 'label' => $c->label, 'year' => $c->year, 'term' => (int) $c->term, 'amount' => (float) $c->amount, 'paid' => (float) $c->amt_paid, 'date' => $c->created_at,
                        'remove' => Qs::userIsSuperAdmin() && (float) $c->amt_paid <= 0 ? route('finance.assign.direct.remove', $c->id) : null];
                }),
        ]);
    }

    /** Bill direct: one amount for one student for the term it belongs to (e.g. arrears the migration missed). */
    public function direct(Request $req)
    {
        $current = Qs::getCurrentSession();
        $d = $req->validate(['label' => 'required|string|max:120', 'amount' => 'required|numeric|min:0.01|max:1000000', 'year' => 'required|in:'.implode(',', $this->directYears($current)), 'term' => 'required|in:1,2,3'], [], ['label' => 'Reason']);
        if ($d['year'] === $current && (int) $d['term'] > Fees::termNow($current)) return Qs::json('That term has not started yet.', false);
        $sr = StudentRecord::where(['user_id' => (int) Qs::decodeHash((string) $req->input('student')), 'grad' => 0])->first();
        if (!$sr) return Qs::json('Choose a student.', false);
        OptionalFeeCharge::create(['student_id' => $sr->user_id, 'year' => $d['year'], 'term' => (int) $d['term'], 'group' => 'direct',
            'label' => trim($d['label']), 'amount' => \App\Support\Money::r($d['amount']), 'amt_paid' => 0]);
        \App\Support\ClearEnroll::studentChanged((int) $sr->user_id);

        return Qs::json('Billed.', true);
    }

    /** A direct bill entered by mistake: removed only while nothing has been paid on it. */
    public function removeDirect($charge)
    {
        if (!Qs::userIsSuperAdmin()) return Qs::json(__('msg.denied'), false);
        $c = OptionalFeeCharge::where('group', 'direct')->find((int) $charge);
        if (!$c) return Qs::json(__('msg.rnf'), false);
        if ($c->amt_paid > 0) return Qs::json('This bill has payments on it and cannot be removed.', false);
        $student = (int) $c->student_id;
        $c->delete();
        \App\Support\ClearEnroll::studentChanged($student);

        return Qs::json('Removed.', true);
    }

    public function save(Request $req)
    {
        $id = (int) Qs::decodeHash((string) $req->input('student'));
        $sr = StudentRecord::where(['user_id' => $id, 'grad' => 0])->first();
        if (!$sr) return Qs::json('Choose a student.', false);
        $sel = (array) json_decode((string) $req->input('services'), true);
        unset($sel['sales']);
        if (!empty($sel['bus_direction']) && empty($sel['bus_route_id'])) return Qs::json('Choose the bus location, or set the bus to “No bus”.', false);

        $year = Qs::getCurrentSession();
        // One Discount box: a "next term only" type (e.g. 5% early payment) is kept apart from the standing one.
        $picked = FeeDiscount::find((int) $req->input('discount'));
        $standing = $sr->fee_discount_id;
        $once = $sr->next_discount_id;
        if ($picked && $picked->next_term_only) $once = $picked->id;
        elseif ($picked) $standing = $picked->id;
        elseif ($standing) $standing = null;
        else $once = null;
        $discountChanged = (int) $sr->fee_discount_id !== (int) $standing;
        $sr->update(['fee_discount_id' => $standing, 'next_discount_id' => $once, 'staff_child' => $req->boolean('staff_child')]);
        if ($discountChanged) Fees::applyDiscount($sr->fresh(), $year);

        // Next term: this term's services and bills stay as they are. This term: billed now.
        $kept = [];
        if ($req->input('term') === 'current') {
            $kept = Fees::syncOptional($sr->user_id, $year, $sel);
            $sr->update(['next_services' => null]);
        } else {
            Fees::planServices($sr, $year, $sel);
        }
        if ($discountChanged || $req->input('term') === 'current') \App\Support\ClearEnroll::studentChanged((int) $sr->user_id);

        return Qs::json('Saved.'.($kept ? ' Stopped from next term (this term stays billed): '.implode(', ', $kept).'.' : ''), true);
    }
}
