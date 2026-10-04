<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Support\FinancePeriod;
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
        $session = Qs::getCurrentSession();
        $p = FinancePeriod::fromRequest($req);
        // Fees belong to a school year: show the chosen one (or the term's year), otherwise the current year.
        $feesYear = $p->session ?: $session;

        return Inertia::render('Finance/Dashboard', [
            'session' => $feesYear,
            'period' => $p->toArray(),
            'periods' => FinancePeriod::options(false),
            'fees' => FinanceSummary::fees($feesYear),
            'cashflow' => FinanceSummary::cashflow($p->from, $p->to),
            'balance' => FinanceSummary::cashBalance(),
            'monthly' => FinanceSummary::monthly(12),
            'recent' => FinanceTransaction::orderByDesc('date')->orderByDesc('id')->limit(4)->get()->map(function ($t) {
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
                'manage' => route('payments.manage'),
                'setup' => route('payments.index'),
            ],
        ]);
    }
}
