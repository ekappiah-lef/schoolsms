<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Audit trail: reversed (voided) receipts and every change to income / expense entries. Read-only. */
class FinanceAuditController extends Controller
{
    const ACTIONS = ['void_receipt' => 'Payment reversed', 'cancel_bill' => 'Bill cancelled', 'restore_bill' => 'Bill restored', 'update_entry' => 'Entry edited', 'delete_entry' => 'Entry deleted'];

    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index()
    {
        $rows = DB::table('finance_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('users as s', 's.id', '=', 'l.student_id')
            ->orderByDesc('l.id')
            ->select('l.*', 'u.name as by_name', 's.name as student_name')->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'at' => (string) $l->created_at,
                    'action' => $l->action,
                    'label' => self::ACTIONS[$l->action] ?? $l->action,
                    'by' => $l->by_name,
                    'student' => $l->student_name,
                    'student_url' => $l->student_id ? route('payments.invoice', Qs::hash($l->student_id)) : null,
                    'amount' => $l->amount !== null ? \App\Support\Money::r($l->amount) : null,
                    'reason' => $l->reason,
                    'before' => $l->before ? json_decode($l->before, true) : null,
                    'after' => $l->after ? json_decode($l->after, true) : null,
                    'restore_url' => $l->action === 'cancel_bill' && !$l->after && \App\Support\BillCancel::allowed() ? route('finance.audit.restore', $l->id) : null,
                ];
            });

        return Inertia::render('Finance/AuditTrail', [
            'rows' => $rows,
            'actions' => collect(self::ACTIONS)->map(function ($label, $value) { return ['value' => $value, 'label' => $label]; })->values(),
            'totals' => ['cancelled' => \App\Support\Money::r($rows->where('action', 'cancel_bill')->where('after', null)->sum('amount')), 'cancelledCount' => $rows->where('action', 'cancel_bill')->where('after', null)->count(),
                'reversed' => \App\Support\Money::r($rows->where('action', 'void_receipt')->sum('amount')), 'reversedCount' => $rows->where('action', 'void_receipt')->count()],
        ]);
    }

    public function restore($log)
    {
        if (!\App\Support\BillCancel::allowed()) return Qs::json(__('msg.denied'), false);
        [$ok, $msg] = \App\Support\BillCancel::restore((int) $log);

        return Qs::json($msg, $ok);
    }
}
