<?php

namespace App\Http\Controllers;

use App\Helpers\Qs;
use App\Models\MomoPayment;
use App\Models\StudentRecord;
use App\Support\Fees;
use App\Support\MoMo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MTN MoMo payments: started by a parent from the fees statement link (signed URL) or by the
 * accounts office for a student; the page polls the status; MTN may also call back.
 */
class MomoController extends Controller
{
    /** Parent pays from the signed fees-statement page. */
    public function start(Request $req, $student)
    {
        return $this->begin($req, (int) $student, null);
    }

    /** Accounts office sends a payment prompt to the parent's phone. */
    public function staffStart(Request $req, $id)
    {
        if (!Qs::userIsTeamAccount()) {
            return Qs::json(__('msg.denied'), false);
        }

        return $this->begin($req, (int) $id, Auth::id());
    }

    /** Current status; also asks MTN and applies the payment once it has succeeded. */
    public function status($reference)
    {
        $p = MomoPayment::where('reference', $reference)->firstOrFail();
        $p = MoMo::refresh($p);

        return response()->json([
            'status' => $p->status,
            'amount' => (int) $p->amount,
            'reason' => $p->status === 'failed' ? ($p->reason ?: 'The payment was not completed.') : null,
            'transaction' => $p->financial_transaction_id,
        ]);
    }

    /** MTN calls this URL when a payment finishes. The status is re-checked with MTN, never trusted from the body. */
    public function callback(Request $req)
    {
        $ref = $req->header('X-Reference-Id') ?: $req->input('referenceId');
        $p = $ref ? MomoPayment::where('reference', $ref)->first() : null;
        if (!$p && $req->input('externalId')) {
            $p = MomoPayment::find((int) $req->input('externalId'));
        }
        if ($p) {
            MoMo::refresh($p);
        }

        return response()->json(['ok' => true]);
    }

    protected function begin(Request $req, int $studentId, ?int $by)
    {
        $sr = StudentRecord::where('user_id', $studentId)->with('user')->first();
        if (!$sr) {
            return Qs::json('Student not found.', false);
        }
        $due = Fees::termInvoice($studentId)['total'];
        $req->validate([
            'phone' => 'required|string|max:20',
            'amount' => 'required|integer|min:1|max:'.max($due, 1),
        ], ['amount.max' => 'The amount cannot be more than what is owed ('.number_format($due).').'], ['phone' => 'MoMo number']);

        try {
            $p = MoMo::requestToPay($studentId, $req->phone, (int) $req->amount, $by, Qs::getSystemName().' fees: '.$sr->user->name);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['phone' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'msg' => 'Payment request sent. Approve it on the phone with the MoMo PIN.',
            'reference' => $p->reference,
            'status_url' => route('momo.status', $p->reference),
            'test' => config('momo.driver') === 'fake',
        ]);
    }
}
