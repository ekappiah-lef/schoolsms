<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Support\TermSelection;
use App\Support\FinanceSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Finance dashboard: fees billed / paid / due, income, expenses and cash position. */
class FinanceDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index(Request $req)
    {
        // One filter: the terms / years ticked in the Academic Period (opens on the current school year).
        $sel = TermSelection::fromRequest($req);

        return Inertia::render('Finance/Dashboard', [
            'selection' => $sel->toArray(),
            'fees' => FinanceSummary::fees($sel),
            'cashflow' => FinanceSummary::cashflow($sel),
            'byMethod' => FinanceSummary::byMethod($sel),
            'invoiced' => FinanceSummary::invoiced($sel),
            'monthly' => FinanceSummary::monthly($sel),
            'recent' => $sel->apply(FinanceTransaction::query(), 'date', true)->orderByDesc('date')->orderByDesc('id')->limit(4)->get()->map(function ($t) {
                return [
                    'type' => $t->type,
                    'category' => $t->category,
                    'amount' => (int) $t->amount,
                    'date' => $t->date->toDateString(),
                    'description' => $t->description,
                ];
            })->values(),
            'urls' => [
                'self' => route('finance.dashboard'),
                'transactions' => route('finance.transactions'),
                'ledger' => route('finance.ledger'),
                'paymentMode' => route('finance.payment_mode'),
                'manage' => route('payments.manage'),
                'setup' => route('payments.index'),
            ],
        ]);
    }

    /** Who paid by one mode (Cash, Mobile payment …) in the chosen terms. */
    public function paymentMode(Request $req)
    {
        $req->validate(['method' => 'required|string|max:30']);

        return response()->json(FinanceSummary::methodPayments($req->query('method'), TermSelection::fromRequest($req)));
    }
}
