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

    /** Student payments: send a payment prompt to the parent's phone (MTN, Telecel, AirtelTigo). */
    public function staffCharge(Request $req, $id)
    {
        if (!\App\Helpers\Qs::userIsTeamAccount()) return \App\Helpers\Qs::json(__('msg.denied'), false);
        $sr = StudentRecord::where('user_id', (int) $id)->with('user')->first();
        abort_unless($sr, 404);
        $due = Fees::termInvoice($sr->user_id)['total'];
        $d = $req->validate([
            'phone' => 'required|string|max:20',
            'network' => 'required|in:mtn,vod,atl',
            'amount' => 'required|'.Money::rule().'|min:1|max:'.max($due, 1),
        ], ['amount.max' => 'The amount cannot be more than what is owed ('.Money::fmt($due).').'], ['phone' => 'Mobile money number']);

        try {
            [$p, $next] = Paystack::charge($sr, Money::r($d['amount']), $d['phone'], $d['network'], \Illuminate\Support\Facades\Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['phone' => [$e->getMessage()]]], 422);
        }

        return response()->json(['ok' => true, 'next' => $next, 'reference' => $p->reference,
            'status_url' => route('paystack.status', $p->reference), 'otp_url' => route('paystack.otp', $p->reference)]);
    }

    public function otp(Request $req, $reference)
    {
        $p = OnlinePayment::where('reference', $reference)->where('status', 'pending')->firstOrFail();
        $otp = $req->validate(['otp' => 'required|string|max:10'])['otp'];
        try {
            return response()->json(['ok' => true, 'next' => Paystack::submitOtp($p, $otp)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['otp' => [$e->getMessage()]]], 422);
        }
    }

    /** Where the prompt has got to; a confirmed payment is applied once. */
    public function status($reference)
    {
        $p = Paystack::verify(OnlinePayment::where('reference', $reference)->firstOrFail());

        return response()->json(['status' => $p->status, 'amount' => Money::r($p->amount), 'reason' => $p->status === 'failed' ? ($p->reason ?: 'The payment was not completed.') : null]);
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
