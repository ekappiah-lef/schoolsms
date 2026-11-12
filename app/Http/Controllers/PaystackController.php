<?php

namespace App\Http\Controllers;

use App\Models\OnlinePayment;
use App\Models\StudentRecord;
use App\Support\Fees;
use App\Support\Money;
use App\Support\Notices;
use App\Support\Paystack;
use Illuminate\Http\Request;

/** Fees paid online through Paystack, from the parent's fees statement (signed link). */
class PaystackController extends Controller
{
    public function start(Request $req, $student)
    {
        $sr = StudentRecord::where('user_id', (int) $student)->with('user')->first();
        abort_unless($sr, 404);
        $due = Fees::termInvoice($sr->user_id)['total'];
        $req->validate(['amount' => 'required|'.Money::rule().'|min:1|max:'.max($due, 1)],
            ['amount.max' => 'The amount cannot be more than what is owed ('.Money::fmt($due).').'], ['amount' => 'Amount']);

        try {
            [, $url] = Paystack::start($sr, Money::r($req->amount), null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['amount' => [$e->getMessage()]]], 422);
        }

        return response()->json(['ok' => true, 'url' => $url]);
    }

    /** Paystack sends the parent back here: confirm, then show the statement again. */
    public function callback(Request $req)
    {
        $p = OnlinePayment::where('reference', (string) $req->query('reference'))->first();
        abort_unless($p, 404);
        $p = Paystack::verify($p);

        return redirect(Notices::statementUrl($p->student_id))->with('online_payment', ['status' => $p->status, 'amount' => $p->amount]);
    }

    /** Paystack tells the school about a payment (even if the parent never came back). Checked with Paystack, never trusted. */
    public function webhook(Request $req)
    {
        if (!Paystack::validSignature($req->getContent(), $req->header('x-paystack-signature'))) {
            return response()->json(['ok' => false], 401);
        }
        $p = OnlinePayment::where('reference', (string) $req->input('data.reference'))->first();
        if ($p) Paystack::verify($p);

        return response()->json(['ok' => true]);
    }
}
