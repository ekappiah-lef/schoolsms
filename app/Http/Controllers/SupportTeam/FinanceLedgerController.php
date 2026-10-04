<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Support\FinancePeriod;
use App\Support\FinanceSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Every movement of money with a running balance, so each figure on the dashboard can be traced. */
class FinanceLedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index(Request $req)
    {
        $p = FinancePeriod::fromRequest($req);
        $ledger = FinanceSummary::ledger($p->from, $p->to);

        return Inertia::render('Finance/Ledger', [
            'session' => Qs::getCurrentSession(),
            'period' => $p->toArray(),
            'periods' => FinancePeriod::options(),
            'opening' => $ledger['opening'],
            'closing' => $ledger['closing'],
            'invoiced' => $p->session ? FinanceSummary::invoiced($p->session, $p->term) : null,
            'rows' => $ledger['rows'],
            'filters' => ['dir' => $req->query('dir'), 'source' => $req->query('source'), 'category' => $req->query('category')],
            'urls' => ['self' => route('finance.ledger'), 'dashboard' => route('finance.dashboard'), 'transactions' => route('finance.transactions')],
        ]);
    }
}
