<?php

namespace App\Http\Controllers\SupportTeam;

use App\Support\Money;
use AppSupportMoney;
use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\OptionalFeeCharge;
use App\Models\OptionalFeeReceipt;
use Illuminate\Http\Request;

/** Part/full payments against single additional services (e.g. only the bus). */
class OptionalFeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function pay(Request $req, $id)
    {
        $c = OptionalFeeCharge::findOrFail($id);
        $req->validate([
            'amt_paid' => 'required|'.Money::rule().'|min:0.01|max:'.max($c->balance, 0.01),
            'method' => 'nullable|in:'.implode(',', \App\Support\Fees::METHODS),
            'reference' => 'nullable|string|max:100',
        ], [], ['amt_paid' => 'Amount paid']);

        $c->amt_paid = Money::r($c->amt_paid + $req->amt_paid);
        $c->save();

        $receipt = OptionalFeeReceipt::create([
            'charge_id' => $c->id,
            'amt_paid' => \App\Support\Money::r($req->amt_paid),
            'balance' => $c->balance,
            'year' => $c->year,
            'method' => $req->input('method') ?: 'Cash',
            'reference' => $req->input('reference') ?: null,
        ]);
        \App\Support\ClearEnroll::studentChanged((int) $c->student_id, \App\Support\Money::r($req->amt_paid), 'OF-'.str_pad($receipt->id, 6, '0', STR_PAD_LEFT));

        return response()->json(['ok' => true, 'msg' => __('msg.update_ok'), 'receipt' => ReceiptController::urls('optional', $receipt->id)]);
    }

    /** Reverse every payment on a charge (administrators only, with a reason); receipts go to the audit trail. */
    public function reset(Request $req, $id)
    {
        if (!Qs::userIsTeamAdmin()) {
            return Qs::json('Only an administrator can reverse payments.', false);
        }
        $reason = trim((string) $req->input('reason'));
        if (mb_strlen($reason) < 5) {
            return Qs::json('Give a reason for reversing these payments (at least 5 characters).', false);
        }
        $total = \App\Support\FinanceLog::voidCharge(OptionalFeeCharge::findOrFail($id), $reason);

        return Qs::json(AppSupportMoney::fmt($total).' reversed. The receipts are kept in the audit trail.');
    }
}
