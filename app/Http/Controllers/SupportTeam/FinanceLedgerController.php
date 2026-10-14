<?php

namespace App\Http\Controllers\SupportTeam;

use App\Http\Controllers\Controller;
use App\Support\TermSelection;
use App\Support\FinanceSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Every movement of money with a running balance, so each figure on the dashboard can be traced. */
class FinanceLedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
        $this->middleware('super_admin', ['only' => ['trialBalance']]);
    }

    /** Trial balance for the chosen terms (super admin). */
    public function trialBalance(Request $req)
    {
        $sel = TermSelection::fromRequest($req);

        return Inertia::render('Finance/TrialBalance', [
            'selection' => $sel->toArray(),
            'tb' => FinanceSummary::trialBalance($sel),
            'urls' => ['self' => route('finance.trial_balance'), 'ledger' => route('finance.ledger')],
        ]);
    }

    public function index(Request $req)
    {
        $sel = TermSelection::fromRequest($req);
        $ledger = FinanceSummary::ledger($sel);
        $cf = FinanceSummary::cashflow($sel);

        return Inertia::render('Finance/Ledger', [
            'selection' => $sel->toArray(),
            'invoiced' => FinanceSummary::invoiced($sel),
            'received' => $cf['income'],
            'expenses' => $cf['expenses'],
            'rows' => $ledger['rows'],
            'filters' => ['dir' => $req->query('dir'), 'source' => $req->query('source'), 'category' => $req->query('category')],
            'urls' => ['self' => route('finance.ledger'), 'dashboard' => route('finance.dashboard'), 'transactions' => route('finance.transactions')],
        ]);
    }
}
