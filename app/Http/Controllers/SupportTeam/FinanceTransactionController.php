<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Support\FinancePeriod;
use App\Support\FinanceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/** Income (capital injections, donations, other) and expenses outside student fees. */
class FinanceTransactionController extends Controller
{
    /** Suggested categories; any other text can be typed. */
    public const CATEGORIES = [
        'income' => ['Capital injection', 'Donation', 'Grant', 'Uniform & book sales', 'Other income'],
        'expense' => ['Salaries', 'Utilities', 'Rent', 'Food supplies', 'Transport & fuel', 'Maintenance & repairs', 'Stationery', 'Bank charges', 'Other expense'],
    ];

    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index(Request $req)
    {
        return Inertia::render('Finance/Transactions', $this->pageProps(null, FinancePeriod::fromRequest($req)));
    }

    public function edit($id)
    {
        $t = FinanceTransaction::findOrFail($id);

        return Inertia::render('Finance/Transactions', $this->pageProps([
            'type' => $t->type,
            'category' => $t->category,
            'amount' => (string) $t->amount,
            'date' => $t->date->toDateString(),
            'method' => $t->method ?? '',
            'reference' => $t->reference ?? '',
            'description' => $t->description ?? '',
            'url' => route('finance.transactions.update', Qs::hash($t->id)),
        ]));
    }

    public function store(Request $req)
    {
        FinanceTransaction::create($this->validated($req) + ['recorded_by' => Auth::id()]);

        return Qs::jsonStoreOk();
    }

    public function update(Request $req, $id)
    {
        FinanceTransaction::findOrFail($id)->update($this->validated($req));

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        FinanceTransaction::findOrFail($id)->delete();

        return back()->with('flash_success', __('msg.del_ok'));
    }

    protected function validated(Request $req): array
    {
        return $req->validate([
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:60',
            'amount' => 'required|integer|min:1',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'method' => 'nullable|string|max:30',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);
    }

    protected function pageProps($editing = null, ?FinancePeriod $p = null): array
    {
        $p = $p ?: FinancePeriod::fromRequest(request());
        $rows = FinanceTransaction::with('recorder')->whereBetween('date', [$p->from->toDateString(), $p->to->toDateString()])
            ->orderByDesc('date')->orderByDesc('id')->get();

        return [
            'session' => Qs::getCurrentSession(),
            'categories' => self::CATEGORIES,
            'usedCategories' => $rows->groupBy('type')->map(function ($g) { return $g->pluck('category')->unique()->values(); }),
            // Balance at the end of the chosen period (today's balance for current periods).
            'balance' => FinanceSummary::balanceAt($p->to),
            // For a term or school year the figures cover that period only: all money received
            // (fees included), expenses and the period's balance, so the three tiles tally.
            'termTotals' => $p->session ? (function () use ($p) {
                $cf = FinanceSummary::cashflow($p->from, $p->to);
                return ['received' => $cf['income'], 'expenses' => $cf['expenses'], 'balance' => $cf['income'] - $cf['expenses']];
            })() : null,
            'period' => $p->toArray(),
            'periods' => FinancePeriod::options(),
            'transactions' => $rows->map(function ($t) {
                $hash = Qs::hash($t->id);
                return [
                    'id' => $hash,
                    'type' => $t->type,
                    'category' => $t->category,
                    'amount' => (int) $t->amount,
                    'date' => $t->date->toDateString(),
                    'method' => $t->method,
                    'reference' => $t->reference,
                    'description' => $t->description,
                    'by' => optional($t->recorder)->name,
                    'urls' => ['edit' => route('finance.transactions.edit', $hash), 'destroy' => route('finance.transactions.destroy', $hash)],
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('finance.transactions.store'), 'index' => route('finance.transactions'), 'dashboard' => route('finance.dashboard')],
        ];
    }
}
