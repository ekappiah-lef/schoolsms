<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Support\TermSelection;
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
        return Inertia::render('Finance/Transactions', $this->pageProps(null, TermSelection::fromRequest($req)));
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
        $t = FinanceTransaction::findOrFail($id);
        $fields = ['type', 'category', 'amount', 'date', 'method', 'reference', 'description'];
        $before = self::snapshot($t, $fields);
        $t->update($this->validated($req));
        $after = self::snapshot($t->fresh(), $fields);
        if ($before != $after) {
            FinanceLog::add('update_entry', 'finance_transaction', $t->id, ['amount' => (int) $t->amount, 'before' => $before, 'after' => $after]);
        }

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        // Entries stay on record: only an admin or super admin may delete one (not accountants).
        if (!Qs::userIsTeamAdmin()) {
            return back()->with('flash_danger', 'Only an administrator can delete income or expense entries.');
        }
        $t = FinanceTransaction::findOrFail($id);
        FinanceLog::add('delete_entry', 'finance_transaction', $t->id, ['amount' => (int) $t->amount,
            'before' => self::snapshot($t, ['type', 'category', 'amount', 'date', 'method', 'reference', 'description', 'recorded_by'])]);
        $t->delete();

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

    protected static function snapshot(FinanceTransaction $t, array $fields): array
    {
        return collect($fields)->mapWithKeys(function ($f) use ($t) {
            $v = $t->{$f};
            return [$f => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($f === 'amount' ? (int) $v : $v)];
        })->all();
    }

    protected function pageProps($editing = null, ?TermSelection $sel = null): array
    {
        $sel = $sel ?: TermSelection::fromRequest(request());
        $rows = $sel->apply(FinanceTransaction::with('recorder'), 'date', true)->orderByDesc('date')->orderByDesc('id')->get();
        $cf = FinanceSummary::cashflow($sel);

        return [
            'session' => Qs::getCurrentSession(),
            'categories' => self::CATEGORIES,
            'usedCategories' => $rows->groupBy('type')->map(function ($g) { return $g->pluck('category')->unique()->values(); }),
            // The chosen terms only, each starting from zero: all money received (fees included), expenses, balance.
            'totals' => ['received' => $cf['income'], 'fees' => $cf['fees'], 'other' => $cf['otherIncome'], 'expenses' => $cf['expenses'], 'balance' => $cf['net']],
            'selection' => $sel->toArray(),
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
                    'urls' => array_filter(['edit' => route('finance.transactions.edit', $hash), 'destroy' => Qs::userIsTeamAdmin() ? route('finance.transactions.destroy', $hash) : null]),
                ];
            })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('finance.transactions.store'), 'index' => route('finance.transactions'), 'dashboard' => route('finance.dashboard')],
        ];
    }
}
