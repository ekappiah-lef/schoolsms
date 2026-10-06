<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\OptionalFeeCharge;
use App\Models\OptionalFeeReceipt;
use Illuminate\Http\Request;

/** Part/full payments against single optional services (e.g. only the bus). */
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
            'amt_paid' => 'required|integer|min:1|max:'.max($c->balance, 1),
            'method' => 'nullable|in:'.implode(',', \App\Support\Fees::METHODS),
            'reference' => 'nullable|string|max:100',
        ], [], ['amt_paid' => 'Amount paid']);

        $c->amt_paid = (int) $c->amt_paid + (int) $req->amt_paid;
        $c->save();

        $receipt = OptionalFeeReceipt::create([
            'charge_id' => $c->id,
            'amt_paid' => (int) $req->amt_paid,
            'balance' => $c->balance,
            'year' => $c->year,
            'method' => $req->input('method') ?: 'Cash',
            'reference' => $req->input('reference') ?: null,
        ]);

        return response()->json(['ok' => true, 'msg' => __('msg.update_ok'), 'receipt' => ReceiptController::urls('optional', $receipt->id)]);
    }

    public function reset($id)
    {
        $c = OptionalFeeCharge::findOrFail($id);
        $c->receipts()->delete();
        $c->update(['amt_paid' => 0]);

        return back()->with('flash_success', __('msg.update_ok'));
    }
}
