<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * ClearEnroll, LEF Signature's school-to-school fee clearance portal, inside the school system:
 * the portal itself (embedded), and the students who left owing fees, ready to upload to it.
 */
class ClearEnrollController extends Controller
{
    const STATUSES = ['cleared' => 'Cleared', 'not_found' => 'Not found', 'flagged' => 'Flagged', 'not_checked' => 'Not checked'];

    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function index()
    {
        return Inertia::render('ClearEnroll/Index', [
            'portal' => trim((string) Qs::getSetting('clearenroll_url')) ?: null,
            'debtors' => $this->debtors(),
            'admissions' => DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
                ->whereNotNull('sr.clearenroll_status')->orderByDesc('sr.clearenroll_checked_at')->limit(50)
                ->get(['u.name', 'sr.clearenroll_status', 'sr.clearenroll_checked_at'])->map(function ($r) {
                    return ['name' => $r->name, 'status' => self::STATUSES[$r->clearenroll_status] ?? $r->clearenroll_status, 'key' => $r->clearenroll_status, 'date' => $r->clearenroll_checked_at];
                })->values(),
            'canSetUrl' => Qs::userIsSuperAdmin(),
            'urls' => ['export' => route('clearenroll.export'), 'settings' => Qs::userIsSuperAdmin() ? route('settings') : null],
        ]);
    }

    /** Download the debtors in a plain spreadsheet (CSV) for ClearEnroll's bulk upload. */
    public function export()
    {
        $rows = $this->debtors();
        $cols = ['Student name', 'Date of birth', 'Gender', 'Admission no.', 'Last class', 'Last term attended', 'Date left', 'Parent / guardian', 'Parent phone', 'Parent email', 'Amount owed (GHS)'];

        return response()->streamDownload(function () use ($rows, $cols) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $cols);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['dob'], $r['gender'], $r['adm_no'], $r['class'], $r['last_term'], $r['left'], $r['parent'], $r['phone'], $r['email'], $r['owed']]);
            }
            fclose($out);
        }, 'clearenroll-debtors-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Students who have left (completed / graduated) and still owe school fees or services. */
    protected function debtors(): array
    {
        $left = DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
            ->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->where('sr.grad', 1)
            ->get(['u.id', 'u.name', 'u.dob', 'u.gender', 'sr.adm_no', 'sr.grad', 'sr.grad_date', 'c.name as class', 'p.name as parent', 'p.phone', 'p.email']);
        if ($left->isEmpty()) return [];

        $ids = $left->pluck('id');
        $school = DB::table('payment_records as pr')->join('payments as pa', 'pa.id', '=', 'pr.payment_id')->whereIn('pr.student_id', $ids)
            ->groupBy('pr.student_id')->selectRaw('pr.student_id, sum(greatest(pa.amount - pr.discount - coalesce(pr.amt_paid,0), 0)) as owed, max(concat(pr.year, " · Term ", coalesce(pa.term, ""))) as last_term')
            ->get()->keyBy('student_id');
        $optional = DB::table('optional_fee_charges')->whereIn('student_id', $ids)->groupBy('student_id')
            ->selectRaw('student_id, sum(greatest(amount - amt_paid, 0)) as owed')->pluck('owed', 'student_id');

        return $left->map(function ($s) use ($school, $optional) {
            $owed = (int) optional($school[$s->id] ?? null)->owed + (int) ($optional[$s->id] ?? 0);
            return [
                'id' => $s->id, 'name' => $s->name, 'dob' => $s->dob, 'gender' => $s->gender, 'adm_no' => $s->adm_no, 'class' => $s->class,
                'last_term' => optional($school[$s->id] ?? null)->last_term, 'left' => $s->grad_date ? 'Completed '.$s->grad_date : 'Completed',
                'parent' => $s->parent, 'phone' => $s->phone, 'email' => $s->email, 'owed' => $owed,
                'url' => route('payments.invoice', Qs::hash($s->id)),
            ];
        })->filter(function ($r) { return $r['owed'] > 0; })->sortByDesc('owed')->values()->all();
    }
}
