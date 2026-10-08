<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Models\OptionalFeeCharge;
use App\Models\OptionalFeeReceipt;
use App\Models\StockMovement;
use App\Support\Fees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * School shop: items in stock (uniform, socks, sweaters, books, stationery…),
 * restocking, selling to students (charged to their optional fees), books
 * handed down between siblings, and returns.
 */
class SalesController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index()
    {
        $year = Qs::getCurrentSession();

        $soldThisYear = OptionalFeeCharge::where(['group' => 'sales', 'year' => $year])->whereNull('handed_down_from')
            ->groupBy('inventory_item_id')->select('inventory_item_id', DB::raw('sum(qty) as n'))->pluck('n', 'inventory_item_id');

        $items = InventoryItem::orderBy('category')->orderBy('name')->get();

        // Students with their brothers and sisters (same parent), for selling and hand-downs.
        $students = DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->where('sr.grad', 0)->orderBy('u.name')
            ->select('u.id', 'u.name', 'sr.adm_no', 'sr.my_parent_id', 'c.name as class_name')->get();
        $byParent = $students->whereNotNull('my_parent_id')->groupBy('my_parent_id');
        $graduates = DB::table('student_records as sr')->join('users as u', 'u.id', '=', 'sr.user_id')
            ->where('sr.grad', 1)->select('u.id', 'u.name', 'sr.my_parent_id')->get()->groupBy('my_parent_id');

        $sales = OptionalFeeCharge::where('group', 'sales')->with('receipts')
            ->join('users as u', 'u.id', '=', 'optional_fee_charges.student_id')
            ->orderByDesc('optional_fee_charges.id')->limit(80)
            ->select('optional_fee_charges.*', 'u.name as student_name')->get();

        return Inertia::render('Finance/Sales', [
            'session' => $year,
            'categories' => InventoryItem::CATEGORIES,
            'items' => $items->map(function ($i) use ($soldThisYear) {
                return [
                    'id' => $i->id, 'name' => $i->name, 'category' => $i->category, 'price' => (int) $i->price,
                    'stock' => (int) $i->stock, 'reorder_level' => (int) $i->reorder_level, 'active' => $i->active,
                    'sold' => (int) ($soldThisYear[$i->id] ?? 0),
                ];
            })->values(),
            'students' => $students->map(function ($s) use ($byParent, $graduates) {
                $sibs = $s->my_parent_id ? collect($byParent[$s->my_parent_id] ?? [])->where('id', '!=', $s->id)
                    ->map(function ($x) { return ['id' => Qs::hash($x->id), 'name' => $x->name.' ('.$x->class_name.')']; })
                    ->merge(collect($graduates[$s->my_parent_id] ?? [])->map(function ($x) { return ['id' => Qs::hash($x->id), 'name' => $x->name.' (graduated)']; }))
                    ->values() : collect();
                return ['id' => Qs::hash($s->id), 'name' => $s->name, 'hint' => trim($s->class_name.' · '.$s->adm_no), 'siblings' => $sibs];
            })->values(),
            'sales' => $sales->map(function ($c) {
                return [
                    'id' => Qs::hash($c->id), 'date' => optional($c->created_at)->toDateString(), 'student' => $c->student_name,
                    'label' => $c->label, 'qty' => (int) $c->qty, 'amount' => (int) $c->amount, 'paid' => (int) $c->amt_paid,
                    'balance' => $c->balance, 'handed_down' => (bool) $c->handed_down_from,
                    'invoice_url' => route('payments.invoice', Qs::hash($c->student_id)),
                    'return_url' => (int) $c->amt_paid === 0 ? route('finance.sales.return', Qs::hash($c->id)) : null,
                ];
            })->values(),
            'movements' => StockMovement::with(['item', 'user'])->orderByDesc('date')->orderByDesc('id')->limit(100)->get()->map(function ($m) {
                return [
                    'date' => $m->date->toDateString(), 'item' => optional($m->item)->name, 'type' => StockMovement::TYPES[$m->type] ?? $m->type,
                    'qty' => (int) $m->qty, 'unit_cost' => $m->unit_cost !== null ? (int) $m->unit_cost : null, 'note' => $m->note, 'by' => optional($m->user)->name,
                ];
            })->values(),
            'totals' => [
                'sales' => (int) OptionalFeeCharge::where(['group' => 'sales', 'year' => $year])->sum('amount'),
                'paid' => (int) OptionalFeeCharge::where(['group' => 'sales', 'year' => $year])->sum('amt_paid'),
                'stockValue' => (int) $items->sum(function ($i) { return $i->price * max($i->stock, 0); }),
                'lowStock' => $items->filter(function ($i) { return $i->active && $i->stock <= $i->reorder_level; })->count(),
            ],
            'urls' => [
                'items' => route('finance.sales.items.store'),
                'item' => route('finance.sales.items.update', ':id'),
                'restock' => route('finance.sales.items.restock', ':id'),
                'sell' => route('finance.sales.sell'),
                'roster' => route('finance.services', 'sales'),
                'self' => route('finance.sales'),
            ],
        ]);
    }

    public function storeItem(Request $req)
    {
        InventoryItem::create($this->validated($req) + ['stock' => 0]);

        return Qs::jsonStoreOk();
    }

    public function updateItem(Request $req, $item)
    {
        InventoryItem::findOrFail($item)->update($this->validated($req));

        return Qs::jsonUpdateOk();
    }

    /** Add stock; the cost can be recorded as an expense so it shows in the ledger. */
    public function restock(Request $req, $item)
    {
        $item = InventoryItem::findOrFail($item);
        $d = $req->validate([
            'qty' => 'required|integer|min:1|max:100000',
            'unit_cost' => 'nullable|integer|min:0',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'note' => 'nullable|string|max:255',
            'record_expense' => 'nullable|boolean',
        ], [], ['qty' => 'Quantity', 'unit_cost' => 'Cost per item']);

        DB::transaction(function () use ($item, $d, $req) {
            $item->increment('stock', (int) $d['qty']);
            StockMovement::create(['item_id' => $item->id, 'qty' => (int) $d['qty'], 'type' => 'restock', 'unit_cost' => $d['unit_cost'] ?? null,
                'note' => $d['note'] ?? null, 'user_id' => Auth::id(), 'date' => $d['date']]);
            if ($req->boolean('record_expense') && !empty($d['unit_cost'])) {
                FinanceTransaction::create(['type' => 'expense', 'category' => 'Inventory purchases', 'amount' => (int) $d['unit_cost'] * (int) $d['qty'],
                    'date' => $d['date'], 'method' => 'Cash', 'description' => 'Restock: '.$item->name.' × '.$d['qty'], 'recorded_by' => Auth::id()]);
            }
        });

        return Qs::jsonUpdateOk();
    }

    /** Sell items to a student (charged to their optional fees), optionally taking payment now. */
    public function sell(Request $req)
    {
        $req->validate([
            'student_id' => 'required|string',
            'lines' => 'required|json',
            'handed_down_from' => 'nullable|string',
            'pay_now' => 'nullable|integer|min:0',
        ], [], ['student_id' => 'Student', 'lines' => 'Items']);

        $studentId = (int) Qs::decodeHash($req->student_id);
        $fromId = $req->filled('handed_down_from') ? (int) Qs::decodeHash($req->handed_down_from) : null;
        $lines = collect(json_decode($req->lines, true))->filter(function ($l) { return !empty($l['item_id']) && (int) ($l['qty'] ?? 0) > 0; });
        if (!$studentId || $lines->isEmpty()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => ['lines' => ['Choose a student and at least one item.']]], 422);
        }

        // Stock check (hand-downs do not use stock).
        foreach ($lines as $l) {
            $item = InventoryItem::find($l['item_id']);
            if (!$item) return response()->json(['message' => 'Invalid', 'errors' => ['lines' => ['An item no longer exists.']]], 422);
            if (empty($l['handed_down']) && $item->stock < (int) $l['qty']) {
                return response()->json(['message' => 'Invalid', 'errors' => ['lines' => ["Only {$item->stock} {$item->name} left in stock."]]], 422);
            }
        }

        $year = Qs::getCurrentSession();
        $charges = DB::transaction(function () use ($lines, $studentId, $fromId, $year, $req) {
            $charges = [];
            foreach ($lines as $l) {
                $charges[] = Fees::sell($studentId, $year, InventoryItem::find($l['item_id']), (int) $l['qty'], !empty($l['handed_down']) ? $fromId : null);
            }
            // Payment taken at the counter, applied to the items in order.
            $left = (int) $req->pay_now;
            foreach ($charges as $c) {
                if ($left <= 0 || $c->amount <= 0) continue;
                $a = min($left, (int) $c->amount);
                $c->update(['amt_paid' => $a]);
                OptionalFeeReceipt::create(['charge_id' => $c->id, 'amt_paid' => $a, 'balance' => $c->amount - $a, 'year' => $year]);
                $left -= $a;
            }
            return $charges;
        });

        $total = collect($charges)->sum('amount');
        // A sale changes what the student owes; ClearEnroll gets the new balance.
        \App\Support\ClearEnroll::studentChanged((int) $charges[0]->student_id, (int) $req->pay_now ? min((int) $req->pay_now, (int) $total) : null, 'Shop sale');
        return response()->json(['ok' => true, 'msg' => 'Sale recorded: '.number_format($total).' charged'.((int) $req->pay_now ? ', '.number_format(min((int) $req->pay_now, $total)).' paid now.' : ' to the student\'s account.')]);
    }

    /** Undo an unpaid sale: the item goes back into stock. */
    public function returnSale($id)
    {
        $c = OptionalFeeCharge::where('group', 'sales')->findOrFail($id);
        if ((int) $c->amt_paid > 0) {
            return back()->with('flash_danger', 'This sale has payments. Reset the payment on the student\'s payments page first.');
        }
        DB::transaction(function () use ($c) {
            if ($c->inventory_item_id && !$c->handed_down_from) {
                InventoryItem::where('id', $c->inventory_item_id)->increment('stock', $c->qty);
                StockMovement::create(['item_id' => $c->inventory_item_id, 'qty' => (int) $c->qty, 'type' => 'return', 'charge_id' => $c->id,
                    'note' => 'Returned: '.$c->label, 'user_id' => Auth::id(), 'date' => now()->toDateString()]);
            }
            $c->delete();
        });
        \App\Support\ClearEnroll::studentChanged((int) $c->student_id);

        return back()->with('flash_success', 'Sale returned and removed from the student\'s account.');
    }

    protected function validated(Request $req): array
    {
        $d = $req->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:'.implode(',', InventoryItem::CATEGORIES),
            'price' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ], [], ['reorder_level' => 'Reorder level']);
        $d['reorder_level'] = (int) ($d['reorder_level'] ?? 0);
        $d['active'] = $req->boolean('active', true);

        return $d;
    }
}
