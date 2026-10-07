<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Pay;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Support\ClassOrder;
use App\Support\Fees;
use App\Support\Notices;
use App\Models\Payment;
use App\Models\OptionalFeeCharge;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Requests\Payment\PaymentCreate;
use App\Http\Requests\Payment\PaymentUpdate;
use App\Models\Setting;
use App\Repositories\MyClassRepo;
use App\Repositories\PaymentRepo;
use App\Repositories\StudentRepo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;

class PaymentController extends Controller
{
    protected $my_class, $pay, $student, $year;

    public function __construct(MyClassRepo $my_class, PaymentRepo $pay, StudentRepo $student)
    {
        $this->my_class = $my_class;
        $this->pay = $pay;
        $this->year = Qs::getCurrentSession();
        $this->student = $student;

        $this->middleware('teamAccount');
    }

    public function index()
    {
        if (!Ui::isClassic()) {
            // Open the current session straight away instead of an empty year picker.
            $years = $this->pay->getPaymentYears()->pluck('year');
            $year = $years->contains($this->year) ? $this->year : $years->sort()->last();
            return Inertia::render('Payments/Index', $this->feeSetupProps($year));
        }

        $d['selected'] = false;
        $d['years'] = $this->pay->getPaymentYears();

        return view('pages.support_team.payments.index', $d);
    }

    public function show($year)
    {
        $d['payments'] = $p = $this->pay->getPayment(['year' => $year])->get();

        if(($p->count() < 1)){
            return Qs::goWithDanger('payments.index');
        }

        return Ui::render('Payments/Index', function () use ($year) {
            return $this->feeSetupProps($year);
        }, 'pages.support_team.payments.index', function () use ($d, $year) {
            $d['selected'] = true;
            $d['my_classes'] = $this->my_class->all();
            $d['years'] = $this->pay->getPaymentYears();
            $d['year'] = $year;
            return $d;
        });
    }

    public function select_year(Request $req)
    {
        return Qs::goToRoute(['payments.show', $req->year]);
    }

    public function create()
    {
        return Ui::render('Payments/Form', function () {
            return [
                'mode' => 'create',
                'session' => $this->year,
                'classes' => ClassOrder::sort($this->my_class->all())->map(function ($c) {
                    return ['id' => $c->id, 'name' => $c->name];
                })->values(),
                'categories' => $this->categoryOptions(),
                'currentTerm' => \App\Support\FinancePeriod::termOf(now())[1],
                'urls' => ['submit' => route('payments.store'), 'index' => route('payments.index')],
            ];
        }, 'pages.support_team.payments.create', function () {
            $d['my_classes'] = $this->my_class->all();
            return $d;
        });
    }

    public function invoice($st_id, $year = NULL)
    {
        if(!$st_id) {return Qs::goWithDanger();}

        $inv = $year ? $this->pay->getAllMyPR($st_id, $year) : $this->pay->getAllMyPR($st_id);

        $d['sr'] = $sr = $this->student->findByUserId($st_id)->first();
        if(!$sr) {return Qs::goWithDanger('payments.manage', __('msg.srnf'));}
        $pr = $inv->get();
        $d['uncleared'] = $pr->where('paid', 0);
        $d['cleared'] = $pr->where('paid', 1);

        return Ui::render('Payments/Invoice', function () use ($sr, $year, $st_id) {
            $sr->loadMissing(['my_class', 'section']);

            return [
                'student' => [
                    'name' => $sr->user->name,
                    'photo' => $sr->user->photo,
                    'adm_no' => $sr->adm_no,
                    'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
                    'category' => Fees::categoryFor($sr, $this->year),
                    'profile_url' => route('students.show', Qs::hash($sr->id)),
                ],
                'year' => $year,
                'years' => Pay::getYears($st_id)->merge(OptionalFeeCharge::where('student_id', $st_id)->pluck('year'))->filter()->unique()->sort()->reverse()->values(),
                'statement' => Fees::statement((int) $st_id, $year, true),
                'invoice' => Fees::termInvoice((int) $st_id),
                'parentPhone' => optional($sr->my_parent)->phone,
                'momoTest' => config('momo.driver') === 'fake',
                'urls' => [
                    'sendInvoice' => route('payments.send_invoice', Qs::hash($st_id)),
                    'momo' => \App\Support\MoMo::configured() ? route('payments.momo', Qs::hash($st_id)) : null,
                    'all' => route('payments.invoice', Qs::hash($st_id)),
                    'year' => route('payments.invoice', [Qs::hash($st_id), ':year']),
                    'manage' => route('payments.manage', $sr->my_class_id),
                    'link' => Notices::statementUrl((int) $st_id),
                ],
            ];
        }, 'pages.support_team.payments.invoice', $d);
    }

    /** Email + SMS the current term's invoice (with balance brought forward) to one student's parent. */
    public function sendInvoice($st_id)
    {
        $sr = $this->student->findByUserId($st_id)->first();
        if (!$sr) {
            return Qs::json(__('msg.srnf'), false);
        }
        $results = collect(Notices::sendInvoice($sr));
        $sent = $results->where('status', 'sent')->count();

        return Qs::json($sent ? "Invoice sent ({$sent} message".($sent === 1 ? '' : 's').').' : 'Invoice not sent: '.($results->pluck('error')->filter()->first() ?: 'no parent contact on record.'), (bool) $sent);
    }

    /** Send the current term's invoice to every parent in a class. */
    public function sendClassInvoices($class_id)
    {
        $students = $this->student->getRecord(['my_class_id' => $class_id])->get();
        $sent = 0;
        $held = 0;
        foreach ($students as $sr) {
            $r = collect(Notices::sendInvoice($sr));
            $r->where('status', 'sent')->count() ? $sent++ : $held++;
        }

        return Qs::json("Invoices sent for {$sent} of {$students->count()} students".($held ? " ({$held} not sent: demo mode or no contact)" : '').'.', true);
    }

    public function receipts($pr_id)
    {
        if(!$pr_id) {return Qs::goWithDanger();}

        try {
             $d['pr'] = $pr = $this->pay->getRecord(['id' => $pr_id])->with('receipt')->first();
        } catch (ModelNotFoundException $ex) {
            return back()->with('flash_danger', __('msg.rnf'));
        }
        $d['receipts'] = $pr->receipt;
        $d['payment'] = $pr->payment;
        $d['sr'] = $this->student->findByUserId($pr->student_id)->first();
        $d['s'] = Setting::all()->flatMap(function($s){
            return [$s->type => $s->description];
        });

        return view('pages.support_team.payments.receipt', $d);
    }

    public function pdf_receipts($pr_id)
    {
        if(!$pr_id) {return Qs::goWithDanger();}

        try {
            $d['pr'] = $pr = $this->pay->getRecord(['id' => $pr_id])->with('receipt')->first();
        } catch (ModelNotFoundException $ex) {
            return back()->with('flash_danger', __('msg.rnf'));
        }
        $d['receipts'] = $pr->receipt;
        $d['payment'] = $pr->payment;
        $d['sr'] = $sr =$this->student->findByUserId($pr->student_id)->first();
        $d['s'] = Setting::all()->flatMap(function($s){
            return [$s->type => $s->description];
        });

        $pdf_name = 'Receipt_'.$pr->ref_no;

        return PDF::loadView('pages.support_team.payments.receipt', $d)->download($pdf_name);

        //return $this->downloadReceipt('pages.support_team.payments.receipt', $d, $pdf_name);
    }

    protected function downloadReceipt($page, $data, $name = NULL){
        $path = 'receipts/file.html';
        $disk = Storage::disk('local');
        $disk->put($path, view($page, $data) );
        $html = $disk->get($path);
        return PDF::loadHTML($html)->download($name);
    }

    public function pay_now(Request $req, $pr_id)
    {
        $pr = $this->pay->findRecord($pr_id);
        $payment = $this->pay->find($pr->payment_id);
        // A payment must be a whole amount, at least 1 and no more than what is still owed on the bill.
        $owed = max((int) $payment->amount - (int) $pr->discount - (int) $pr->amt_paid, 0);
        $this->validate($req, [
            'amt_paid' => 'required|integer|min:1|max:'.max($owed, 1),
            'method' => 'nullable|in:'.implode(',', Fees::METHODS),
            'reference' => 'nullable|string|max:100',
        ], ['amt_paid.max' => 'Only '.number_format($owed).' is owed on this bill.'], ['amt_paid' => 'Amount Paid']);
        if ($owed < 1) {
            return response()->json(['message' => 'This bill is already fully paid.', 'errors' => ['amt_paid' => ['This bill is already fully paid.']]], 422);
        }
        $d['amt_paid'] = $amt_p = $pr->amt_paid + $req->amt_paid;
        // Amount owed is the fee less the student's tuition discount.
        $d['balance'] = $bal = $payment->amount - (int) $pr->discount - $amt_p;
        $d['paid'] = $bal < 1 ? 1 : 0;

        $this->pay->updateRecord($pr_id, $d);

        $d2['amt_paid'] = $req->amt_paid;
        $d2['balance'] = $bal;
        $d2['pr_id'] = $pr_id;
        $d2['year'] = $pr->year;
        $d2['method'] = $req->input('method') ?: 'Cash';
        $d2['reference'] = $req->input('reference') ?: null;

        $receipt = $this->pay->createReceipt($d2);
        return response()->json(['ok' => true, 'msg' => __('msg.update_ok'), 'receipt' => ReceiptController::urls('school', $receipt->id)]);
    }

    public function manage($class_id = NULL)
    {
        $d['my_classes'] = $this->my_class->all();
        $d['selected'] = false;

        if($class_id){
            $d['students'] = $st = $this->student->getRecord(['my_class_id' => $class_id])->get()->sortBy('user.name');
            if($st->count() < 1){
                return Qs::goWithDanger('payments.manage');
            }
            $d['selected'] = true;
            $d['my_class_id'] = $class_id;
        }

        return Ui::render('Payments/Manage', function () use ($d, $class_id) {
            $students = [];
            if ($class_id) {
                // Per-student totals for the current session, in one grouped query.
                $totals = DB::table('payment_records as pr')
                    ->join('payments as p', 'p.id', '=', 'pr.payment_id')
                    ->where('pr.year', $this->year)
                    ->whereIn('pr.student_id', $d['students']->pluck('user_id'))
                    ->groupBy('pr.student_id')
                    ->select('pr.student_id', DB::raw('sum(p.amount - pr.discount) as amount'), DB::raw('sum(coalesce(pr.amt_paid,0)) as paid'), DB::raw('count(*) as items'))
                    ->get()->keyBy('student_id');
                $optional = OptionalFeeCharge::where('year', $this->year)
                    ->whereIn('student_id', $d['students']->pluck('user_id'))
                    ->groupBy('student_id')
                    ->select('student_id', DB::raw('sum(amount) as amount'), DB::raw('sum(amt_paid) as paid'))
                    ->get()->keyBy('student_id');

                $students = $d['students']->map(function ($s) use ($totals, $optional) {
                    $t = $totals[$s->user_id] ?? null;
                    $amount = $t ? (int) $t->amount : 0;
                    $paid = $t ? (int) $t->paid : 0;
                    $o = $optional[$s->user_id] ?? null;
                    $oAmount = $o ? (int) $o->amount : 0;
                    $oPaid = $o ? (int) $o->paid : 0;

                    return [
                        'id' => Qs::hash($s->user_id),
                        'name' => $s->user->name,
                        'photo' => $s->user->photo,
                        'adm_no' => $s->adm_no,
                        'section' => optional($s->section)->name,
                        'amount' => $amount,
                        'paid' => $paid,
                        'balance' => max($amount - $paid, 0),
                        'items' => $t ? (int) $t->items : 0,
                        'optional' => ['amount' => $oAmount, 'paid' => $oPaid, 'balance' => max($oAmount - $oPaid, 0)],
                        'invoice_url' => route('payments.invoice', [Qs::hash($s->user_id), $this->year]),
                        'all_url' => route('payments.invoice', Qs::hash($s->user_id)),
                    ];
                })->values();
            }

            return [
                'session' => $this->year,
                'classId' => $class_id ? (int) $class_id : null,
                'classes' => ClassOrder::sort($this->my_class->all())->map(function ($c) {
                    return ['id' => $c->id, 'name' => $c->name];
                })->values(),
                'students' => $students,
                'urls' => ['select' => route('payments.select_class'), 'sendInvoices' => $class_id ? route('payments.send_class_invoices', $class_id) : null],
            ];
        }, 'pages.support_team.payments.manage', $d);
    }

    protected function feeSetupProps(?string $year): array
    {
        $payments = $year ? $this->pay->getPayment(['year' => $year])->with('items')->get() : collect();

        // How many student records each fee has, and what has been collected against it.
        $usage = DB::table('payment_records')->whereIn('payment_id', $payments->pluck('id'))
            ->groupBy('payment_id')
            ->select('payment_id', DB::raw('count(*) as records'), DB::raw('sum(coalesce(amt_paid,0)) as collected'), DB::raw('sum(discount) as discount'))
            ->get()->keyBy('payment_id');

        return [
            'year' => $year,
            'session' => $this->year,
            'years' => $this->pay->getPaymentYears()->pluck('year')->sort()->reverse()->values(),
            'payments' => $payments->map(function ($p) use ($usage) {
                $u = $usage[$p->id] ?? null;
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'amount' => (int) $p->amount,
                    'ref_no' => $p->ref_no,
                    'class' => optional($p->my_class)->name,
                    'class_id' => $p->my_class_id,
                    'category' => $p->student_category ?: 'all',
                    'items' => $p->items->map(function ($i) { return ['name' => $i->name, 'amount' => (int) $i->amount]; })->values(),
                    'method' => ucwords($p->method),
                    'description' => $p->description,
                    'records' => $u ? (int) $u->records : 0,
                    'collected' => $u ? (int) $u->collected : 0,
                    'expected' => $u ? (int) $p->amount * (int) $u->records - (int) $u->discount : 0,
                    'urls' => [
                        'edit' => route('payments.edit', $p->id),
                        'destroy' => route('payments.destroy', $p->id),
                    ],
                ];
            })->values(),
            'categories' => Payment::CATEGORIES,
            'urls' => [
                'config' => route('finance.config'),
                'create' => route('payments.create'),
                'year' => route('payments.show', ':year'),
                'manage' => route('payments.manage'),
            ],
        ];
    }

    protected function categoryOptions(): array
    {
        return collect(Payment::CATEGORIES)->map(function ($label, $value) { return ['value' => $value, 'label' => $label]; })->values()->all();
    }

    public function select_class(Request $req)
    {
        $this->validate($req, [
            'my_class_id' => 'required|exists:my_classes,id'
        ], [], ['my_class_id' => 'Class']);

        $wh['my_class_id'] = $class_id = $req->my_class_id;

        $pay1 = $this->pay->getPayment(['my_class_id' => $class_id, 'year' => $this->year])->get();
        $pay2 = $this->pay->getGeneralPayment(['year' => $this->year])->get();
        $payments = $pay2->count() ? $pay1->merge($pay2) : $pay1;
        $students = $this->student->getRecord($wh)->get();

        if($payments->count() && $students->count()){
            foreach($payments as $p){
                foreach($students as $st){
                    // New-student / continuing-student fees only go to that group.
                    if (!Fees::appliesTo($p, $st, $this->year)) {
                        continue;
                    }
                    $pr['student_id'] = $st->user_id;
                    $pr['payment_id'] = $p->id;
                    $pr['year'] = $this->year;
                    $rec = $this->pay->createRecord($pr);
                    $rec->ref_no ?: $rec->update(['ref_no' => mt_rand(100000, 99999999)]);

                }
            }
            foreach ($students as $st) {
                Fees::applyDiscount($st, $this->year);
            }
        }

        return Qs::goToRoute(['payments.manage', $class_id]);
    }

    public function store(PaymentCreate $req)
    {
        $data = $req->except(['items']);
        $data['year'] = $this->year;
        $data['ref_no'] = Pay::genRefCode();
        $data['student_category'] = $req->student_category ?: 'all';
        $data['term'] = $req->term ?: null;
        $payment = $this->pay->create($data);

        if ($req->filled('items')) {
            Fees::syncItems($payment, (array) json_decode($req->items, true));
        }

        return Qs::jsonStoreOk();
    }

    public function edit($id)
    {
        $d['payment'] = $pay = $this->pay->find($id);
        if (is_null($pay)) {
            return Qs::goWithDanger('payments.index');
        }

        return Ui::render('Payments/Form', function () use ($pay) {
            return [
                'mode' => 'edit',
                'session' => $pay->year,
                'categories' => $this->categoryOptions(),
                'payment' => [
                    'title' => $pay->title,
                    'amount' => (int) $pay->amount,
                    'student_category' => $pay->student_category ?: 'all',
                    'term' => $pay->term,
                    'items' => $pay->items->map(function ($i) { return ['name' => $i->name, 'amount' => (int) $i->amount]; })->values(),
                    'class' => optional($pay->my_class)->name,
                    'method' => ucwords($pay->method),
                    'description' => $pay->description,
                    'ref_no' => $pay->ref_no,
                ],
                'urls' => ['submit' => route('payments.update', $pay->id), 'index' => route('payments.show', $pay->year)],
            ];
        }, 'pages.support_team.payments.edit', $d);
    }

    public function update(PaymentUpdate $req, $id)
    {
        $data = $req->except(['items']);
        if ($req->has('student_category')) {
            $data['student_category'] = $req->student_category ?: 'all';
        }
        if ($req->has('term')) {
            $data['term'] = $req->term ?: null;
        }
        $this->pay->update($id, $data);

        if ($req->has('items')) {
            Fees::syncItems($this->pay->find($id), (array) json_decode((string) $req->items, true));
        }

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        $this->pay->find($id)->delete();

        return Qs::deleteOk('payments.index');
    }

    /**
     * Reverse every payment on a bill (administrators only, with a reason). The receipts are kept in
     * the finance audit trail with who reversed them and why; the bill returns to unpaid.
     */
    public function reset_record(Request $req, $id)
    {
        if (!Qs::userIsTeamAdmin()) {
            return Qs::json('Only an administrator can reverse payments.', false);
        }
        $reason = trim((string) $req->input('reason'));
        if (mb_strlen($reason) < 5) {
            return Qs::json('Give a reason for reversing these payments (at least 5 characters).', false);
        }
        $total = \App\Support\FinanceLog::voidSchoolBill(\App\Models\PaymentRecord::findOrFail($id), $reason);

        return Qs::json(number_format($total).' reversed. The receipts are kept in the audit trail.');
    }
}
