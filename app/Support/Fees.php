<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\BusRoute;
use App\Models\FeeDiscount;
use App\Models\FeeOption;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\OptionalFeeCharge;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Models\StudentRecord;
use Illuminate\Support\Collection;

/**
 * School fees (itemised, billed by class and new/continuing category) and
 * optional fees (feeding, bus, extra-curricular, books) for one student.
 *
 * School fees keep using the existing payments / payment_records / receipts
 * tables, so the pay, reset and receipt screens are unchanged.
 */
class Fees
{
    /** How a payment was made (shown on receipts and the finance dashboard). */
    const METHODS = ['Cash', 'Mobile payment', 'Bank transfer', 'Cheque'];

    /** "new" when the student joined in the given session, otherwise "old". */
    public static function categoryFor(StudentRecord $sr, string $year): string
    {
        return $sr->admitted_session && $sr->admitted_session === $year ? 'new' : 'old';
    }

    /** Fees for the student's class (or all classes) that apply to their category. */
    public static function paymentsFor(StudentRecord $sr, string $year): Collection
    {
        $category = self::categoryFor($sr, $year);

        return Payment::where('year', $year)
            ->where(function ($q) use ($sr) {
                $q->where('my_class_id', $sr->my_class_id)->orWhereNull('my_class_id');
            })
            ->whereIn('student_category', ['all', $category])
            ->get();
    }

    /** Whether a fee should be billed to the student (used by the class billing action). */
    public static function appliesTo(Payment $p, StudentRecord $sr, string $year): bool
    {
        $category = $p->student_category ?: 'all';

        return $category === 'all' || $category === self::categoryFor($sr, $year);
    }

    /** Create the student's school-fee records for the session (idempotent). */
    public static function billSchoolFees(StudentRecord $sr, string $year): void
    {
        foreach (self::paymentsFor($sr, $year) as $p) {
            $rec = PaymentRecord::firstOrCreate(['student_id' => $sr->user_id, 'payment_id' => $p->id, 'year' => $year]);
            $rec->ref_no ?: $rec->update(['ref_no' => mt_rand(100000, 99999999)]);
        }
        self::applyDiscount($sr, $year);
    }

    /* ------------------------------------------------------------------
     | Tuition discounts
     * -----------------------------------------------------------------*/

    /** The part of a fee a tuition discount applies to: its "Tuition" items, or the whole fee if it is not itemised. */
    public static function tuitionBase(Payment $p): int
    {
        $p->loadMissing('items');
        if ($p->items->isEmpty()) {
            return (int) $p->amount;
        }

        return (int) $p->items->filter(function ($i) { return stripos($i->name, 'tuition') !== false; })->sum('amount');
    }

    /** Recalculate the discount on a student's school-fee records for a session. */
    public static function applyDiscount(StudentRecord $sr, ?string $year = null): void
    {
        $year = $year ?: Qs::getCurrentSession();
        $percent = $sr->fee_discount_id ? (int) optional(FeeDiscount::find($sr->fee_discount_id))->percent : 0;

        PaymentRecord::where(['student_id' => $sr->user_id, 'year' => $year])->with('payment.items')->get()
            ->each(function (PaymentRecord $pr) use ($percent) {
                self::writeDiscount($pr, $percent);
            });
    }

    /** After a fee's amount/breakdown changes, refresh every student's discount on it. */
    public static function refreshPaymentDiscounts(Payment $payment): void
    {
        $payment->load('items');
        $percents = StudentRecord::whereIn('user_id', PaymentRecord::where('payment_id', $payment->id)->pluck('student_id'))
            ->leftJoin('fee_discounts as d', 'd.id', '=', 'student_records.fee_discount_id')
            ->pluck('d.percent', 'student_records.user_id');

        PaymentRecord::where('payment_id', $payment->id)->get()->each(function (PaymentRecord $pr) use ($payment, $percents) {
            $pr->setRelation('payment', $payment);
            self::writeDiscount($pr, (int) ($percents[$pr->student_id] ?? 0));
        });
    }

    protected static function writeDiscount(PaymentRecord $pr, int $percent): void
    {
        if (!$pr->payment) {
            return;
        }
        $discount = (int) round(self::tuitionBase($pr->payment) * $percent / 100);
        $owed = max((int) $pr->payment->amount - $discount, 0);
        $paid = (int) $pr->amt_paid;
        $pr->update(['discount' => $discount, 'balance' => max($owed - $paid, 0), 'paid' => $paid >= $owed ? 1 : 0]);
    }

    public static function discountOptions(): array
    {
        return FeeDiscount::where('active', true)->orderByDesc('percent')->get()
            ->map(function ($d) { return ['id' => $d->id, 'name' => $d->name, 'percent' => (int) $d->percent]; })->values()->all();
    }

    /** Payment amount follows its breakdown when one exists. */
    public static function syncItems(Payment $payment, array $items): void
    {
        $payment->items()->delete();
        $clean = collect($items)->filter(function ($i) {
            return trim((string) ($i['name'] ?? '')) !== '' && is_numeric($i['amount'] ?? null);
        })->values();

        foreach ($clean as $n => $i) {
            $payment->items()->create(['name' => trim($i['name']), 'amount' => (int) $i['amount'], 'sort' => $n]);
        }

        if ($clean->count()) {
            $payment->update(['amount' => (int) $clean->sum('amount')]);
        }
        self::refreshPaymentDiscounts($payment);
    }

    /* ------------------------------------------------------------------
     | Optional services
     * -----------------------------------------------------------------*/

    /**
     * Normalise the services picked on the admission/edit form.
     * Input: feeding[] / extracurricular[] / books[] (fee option ids), bus_route_id, bus_direction.
     * Returns the charge rows (group, label, amount, keys) they produce.
     */
    public static function chargesFromSelection(array $sel): array
    {
        $ids = collect(['feeding', 'extracurricular'])
            ->flatMap(function ($g) use ($sel) { return (array) ($sel[$g] ?? []); })
            ->filter()->map(function ($v) { return (int) $v; })->unique();

        $rows = FeeOption::whereIn('id', $ids)->where('active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(function (FeeOption $o) {
                return ['group' => $o->group, 'label' => $o->name, 'amount' => (int) $o->amount, 'fee_option_id' => $o->id, 'bus_route_id' => null, 'bus_direction' => null];
            })->all();

        $routeId = (int) ($sel['bus_route_id'] ?? 0);
        $dir = $sel['bus_direction'] ?? null;
        if ($routeId && isset(BusRoute::DIRECTIONS[$dir]) && ($route = BusRoute::where('active', true)->find($routeId))) {
            $rows[] = [
                'group' => 'bus',
                'label' => 'Bus · '.$route->name.' ('.BusRoute::DIRECTIONS[$dir].')',
                'amount' => $route->priceFor($dir),
                'fee_option_id' => null,
                'bus_route_id' => $route->id,
                'bus_direction' => $dir,
            ];
        }

        return $rows;
    }

    /**
     * Make the student's feeding, bus and extra-curricular charges for the year
     * match the selection. Unpaid charges that were deselected are removed;
     * charges with payments are kept (they must be reset first) and reported.
     * Shop sales are never touched here: they are sold and returned under Sales.
     */
    public static function syncOptional(int $studentId, string $year, array $sel): array
    {
        $wanted = collect(self::chargesFromSelection($sel));
        $existing = OptionalFeeCharge::where(['student_id' => $studentId, 'year' => $year])->where('group', '!=', 'sales')->get();
        $key = function ($r) { return ($r['fee_option_id'] ?? '').'|'.($r['bus_route_id'] ?? '').'|'.($r['bus_direction'] ?? ''); };

        $kept = [];
        foreach ($existing as $c) {
            $k = $key($c->toArray());
            if ($wanted->contains(function ($w) use ($key, $k) { return $key($w) === $k; })) {
                continue;
            }
            if ((int) $c->amt_paid > 0) {
                $kept[] = $c->label;
                continue;
            }
            $c->delete();
        }

        $have = $existing->map(function ($c) use ($key) { return $key($c->toArray()); });
        foreach ($wanted as $w) {
            if (!$have->contains($key($w))) {
                OptionalFeeCharge::create($w + ['student_id' => $studentId, 'year' => $year, 'amt_paid' => 0]);
            }
        }

        // Shop items picked on the admission form are sold straight away.
        foreach ((array) ($sel['sales'] ?? []) as $itemId) {
            if ($item = InventoryItem::where('active', true)->find((int) $itemId)) {
                self::sell($studentId, $year, $item, 1);
            }
        }

        return $kept;
    }

    /**
     * Sell an item to a student: adds it to their optional fees and takes it out of stock.
     * A hand-down from a sibling is recorded at no charge and does not touch stock.
     */
    public static function sell(int $studentId, string $year, InventoryItem $item, int $qty, ?int $handedDownFrom = null, ?string $date = null): OptionalFeeCharge
    {
        $handDown = $handedDownFrom !== null;
        $label = $item->name.($qty > 1 ? ' × '.$qty : '');
        if ($handDown) {
            $from = \App\User::find($handedDownFrom);
            $label .= ' (handed down from '.optional($from)->name.')';
        }
        $charge = OptionalFeeCharge::create([
            'student_id' => $studentId, 'year' => $year, 'group' => 'sales', 'label' => $label,
            'inventory_item_id' => $item->id, 'qty' => $qty, 'handed_down_from' => $handedDownFrom,
            'amount' => $handDown ? 0 : (int) $item->price * $qty, 'amt_paid' => 0,
        ]);
        if (!$handDown) {
            $item->decrement('stock', $qty);
            StockMovement::create(['item_id' => $item->id, 'qty' => -$qty, 'type' => 'sale', 'charge_id' => $charge->id,
                'note' => 'Sold to '.optional(\App\User::find($studentId))->name, 'user_id' => auth()->id(), 'date' => $date ?: now()->toDateString()]);
        }

        return $charge;
    }

    /** The current selection, in the shape the form uses. */
    public static function selectionFor(int $studentId, string $year): array
    {
        $charges = OptionalFeeCharge::where(['student_id' => $studentId, 'year' => $year])->get();
        $bus = $charges->firstWhere('group', 'bus');
        $ids = function ($g) use ($charges) {
            return $charges->where('group', $g)->pluck('fee_option_id')->filter()->map(function ($v) { return (int) $v; })->values()->all();
        };

        return [
            'feeding' => $ids('feeding'),
            'extracurricular' => $ids('extracurricular'),
            'sales' => [],
            'bus_route_id' => $bus ? (int) $bus->bus_route_id : '',
            'bus_direction' => $bus ? $bus->bus_direction : '',
        ];
    }

    /** Active options, shop items and routes for the forms. */
    public static function catalogue(): array
    {
        $options = FeeOption::where('active', true)->orderBy('sort')->orderBy('id')->get();
        $groups = collect(FeeOption::GROUPS)->map(function ($label, $g) use ($options) {
            return [
                'key' => $g,
                'label' => $label,
                'options' => $options->where('group', $g)->map(function ($o) {
                    return ['id' => $o->id, 'name' => $o->name, 'amount' => (int) $o->amount];
                })->values(),
            ];
        })->values();
        $groups->push([
            'key' => 'sales',
            'label' => 'Shop items',
            'options' => InventoryItem::where('active', true)->where('stock', '>', 0)->orderBy('category')->orderBy('name')->get()->map(function ($i) {
                return ['id' => $i->id, 'name' => $i->name, 'amount' => (int) $i->price, 'stock' => (int) $i->stock];
            })->values(),
        ]);

        return [
            'groups' => $groups->values(),
            'routes' => BusRoute::where('active', true)->orderBy('name')->get()->map(function ($r) {
                return ['id' => $r->id, 'name' => $r->name, 'both' => (int) $r->amount_both, 'one_way' => (int) $r->amount_one_way];
            })->values(),
            'directions' => collect(BusRoute::DIRECTIONS)->map(function ($label, $k) { return ['value' => $k, 'label' => $label]; })->values(),
        ];
    }

    /* ------------------------------------------------------------------
     | Statement: both invoices with breakdowns and running balances
     * -----------------------------------------------------------------*/

    public static function statement(int $studentId, ?string $year = null, bool $withUrls = false): array
    {
        $records = PaymentRecord::where('student_id', $studentId)->with(['payment.items', 'receipt'])
            ->when($year, function ($q) use ($year) { $q->where('year', $year); })
            ->orderByDesc('year')->orderBy('id')->get()
            ->filter(function ($pr) { return $pr->payment; });

        $school = $records->map(function ($pr) use ($withUrls) {
            $gross = (int) $pr->payment->amount;
            $discount = (int) $pr->discount;
            $amount = max($gross - $discount, 0);
            $paid = (int) $pr->amt_paid;

            return [
                'id' => Qs::hash($pr->id),
                'title' => $pr->payment->title,
                'gross' => $gross,
                'discount' => $discount,
                'category' => $pr->payment->student_category ?: 'all',
                'ref_no' => $pr->ref_no,
                'year' => $pr->year,
                'amount' => $amount,
                'paid' => $paid,
                'balance' => max($amount - $paid, 0),
                'cleared' => (bool) $pr->paid,
                'items' => $pr->payment->items->map(function ($i) {
                    return ['name' => $i->name, 'amount' => (int) $i->amount];
                })->values(),
                'receipts' => $pr->receipt->sortByDesc('id')->map(function ($r) use ($withUrls) {
                    return ['id' => 's'.$r->id, 'number' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => (int) $r->amt_paid, 'balance' => (int) $r->balance, 'date' => optional($r->created_at)->toIso8601String(),
                        'urls' => $withUrls ? \App\Http\Controllers\SupportTeam\ReceiptController::urls('school', $r->id) : null];
                })->values(),
                'urls' => $withUrls ? [
                    'pay' => route('payments.pay_now', Qs::hash($pr->id)),
                    'reset' => Qs::userIsTeamAdmin() ? route('payments.reset_record', Qs::hash($pr->id)) : null,
                    'receipt' => route('payments.receipts', Qs::hash($pr->id)),
                ] : null,
            ];
        })->values();

        $charges = OptionalFeeCharge::where('student_id', $studentId)->with('receipts')
            ->when($year, function ($q) use ($year) { $q->where('year', $year); })
            ->orderByDesc('year')->orderByRaw("field(`group`, 'feeding', 'bus', 'extracurricular', 'sales')")->orderBy('id')->get();

        $optional = $charges->map(function (OptionalFeeCharge $c) use ($withUrls) {
            return [
                'id' => Qs::hash($c->id),
                'group' => $c->group,
                'group_label' => OptionalFeeCharge::GROUPS[$c->group] ?? ucfirst($c->group),
                'label' => $c->label,
                'year' => $c->year,
                'amount' => (int) $c->amount,
                'paid' => (int) $c->amt_paid,
                'balance' => $c->balance,
                'cleared' => $c->balance < 1,
                'receipts' => $c->receipts->sortByDesc('id')->map(function ($r) use ($withUrls) {
                    return ['id' => 'o'.$r->id, 'number' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => (int) $r->amt_paid, 'balance' => (int) $r->balance, 'date' => optional($r->created_at)->toIso8601String(),
                        'urls' => $withUrls ? \App\Http\Controllers\SupportTeam\ReceiptController::urls('optional', $r->id) : null];
                })->values(),
                'urls' => $withUrls ? [
                    'pay' => route('optional_fees.pay', Qs::hash($c->id)),
                    'reset' => Qs::userIsTeamAdmin() ? route('optional_fees.reset', Qs::hash($c->id)) : null,
                ] : null,
            ];
        })->values();

        $sum = function (Collection $rows) {
            return ['amount' => (int) $rows->sum('amount'), 'paid' => (int) $rows->sum('paid'), 'balance' => (int) $rows->sum('balance')];
        };
        $t1 = $sum($school);
        $t2 = $sum($optional);

        return [
            'school' => ['records' => $school, 'totals' => $t1],
            'optional' => ['charges' => $optional, 'totals' => $t2],
            'totals' => [
                'amount' => $t1['amount'] + $t2['amount'],
                'paid' => $t1['paid'] + $t2['paid'],
                'balance' => $t1['balance'] + $t2['balance'],
            ],
        ];
    }

    /**
     * The invoice for the current term: this term's school fees and this year's optional
     * services, plus a "balance brought forward" of everything still unpaid from earlier
     * terms and years, and the total now due.
     */
    public static function termInvoice(int $studentId): array
    {
        $session = Qs::getCurrentSession();
        $records = PaymentRecord::where('student_id', $studentId)->with('payment')->get()->filter(function ($pr) { return $pr->payment; });
        $thisYear = $records->where('year', $session);
        // The current term is the latest term billed this year (fees without a term count as current).
        $term = $thisYear->map(function ($pr) { return (int) $pr->payment->term; })->filter()->max();
        $isCurrent = function ($pr) use ($session, $term) {
            return $pr->year === $session && (!$term || !$pr->payment->term || (int) $pr->payment->term === $term);
        };
        $line = function ($label, $amount, $paid) {
            $amount = max((int) $amount, 0);
            return ['label' => $label, 'amount' => $amount, 'paid' => (int) $paid, 'balance' => max($amount - (int) $paid, 0)];
        };
        $termName = function ($pr) { return ($pr->payment->term ? 'Term '.$pr->payment->term.' · ' : '').$pr->year; };

        $current = $records->filter($isCurrent)->map(function ($pr) use ($line) {
            return $line($pr->payment->title, (int) $pr->payment->amount - (int) $pr->discount, $pr->amt_paid);
        })->values();
        $charges = OptionalFeeCharge::where('student_id', $studentId)->get();
        foreach ($charges->where('year', $session) as $c) {
            $current->push($line($c->label, $c->amount, $c->amt_paid));
        }

        $forward = $records->reject($isCurrent)
            ->sortBy(function ($pr) { return $pr->year.'-'.(int) $pr->payment->term; })
            ->map(function ($pr) use ($line, $termName) { return $line($pr->payment->title.' ('.$termName($pr).')', (int) $pr->payment->amount - (int) $pr->discount, $pr->amt_paid); })
            ->filter(function ($l) { return $l['balance'] > 0; })->values();
        foreach ($charges->where('year', '!=', $session)->sortBy('year') as $c) {
            $l = $line($c->label.' ('.$c->year.')', $c->amount, $c->amt_paid);
            if ($l['balance'] > 0) $forward->push($l);
        }

        $sum = function ($rows, $k) { return (int) collect($rows)->sum($k); };
        $currentDue = $sum($current, 'balance');
        $forwardDue = $sum($forward, 'balance');

        // The short table parents see: school fees for the term, one line per service, one line for
        // everything brought forward, and the total.
        $schoolNow = $records->filter($isCurrent);
        $table = [];
        if ($schoolNow->isNotEmpty()) {
            $amt = (int) $schoolNow->sum(function ($pr) { return max((int) $pr->payment->amount - (int) $pr->discount, 0); });
            $paid = (int) $schoolNow->sum('amt_paid');
            $table[] = ['label' => 'School fees'.($term ? ' · Term '.$term : ''), 'amount' => $amt, 'paid' => $paid, 'balance' => max($amt - $paid, 0)];
        }
        foreach (OptionalFeeCharge::GROUPS as $g => $label) {
            $cs = $charges->where('year', $session)->where('group', $g);
            if ($cs->isEmpty()) continue;
            $amt = (int) $cs->sum('amount');
            $paid = (int) $cs->sum('amt_paid');
            $table[] = ['label' => $label, 'amount' => $amt, 'paid' => $paid, 'balance' => max($amt - $paid, 0)];
        }
        if ($forwardDue > 0) {
            $table[] = ['label' => 'Balance from earlier terms', 'amount' => $forwardDue, 'paid' => 0, 'balance' => $forwardDue, 'forward' => true];
        }

        return [
            'table' => $table,
            'label' => ($term ? 'Term '.$term.' · ' : '').$session,
            'session' => $session,
            'term' => $term,
            'current' => ['lines' => $current, 'amount' => $sum($current, 'amount'), 'paid' => $sum($current, 'paid'), 'balance' => $currentDue],
            'forward' => ['lines' => $forward, 'total' => $forwardDue],
            'total' => $currentDue + $forwardDue,
        ];
    }

    /**
     * What a student will owe for the term after ($year, $term), for the report card, from Fee setup
     * and the student's own bills (not typed in by hand):
     *   school fees for the next term (their class and category, less their discount; what is left to
     *   pay if already billed), plus their optional services' share for that term (feeding, bus, clubs),
     *   plus everything still unpaid up to and including ($year, $term). set = false when the next
     *   term's school fees are not in Fee setup yet.
     */
    public static function nextTermFees(StudentRecord $sr, string $year, int $term): array
    {
        $start = (int) substr($year, 0, 4);
        [$nYear, $nTerm] = $term < 3 ? [$year, $term + 1] : [($start + 1).'-'.($start + 2), 1];
        $nKey = $nYear.':'.$nTerm;
        $upTo = $year.':'.$term;

        // School fees: already billed for the next term, or from Fee setup.
        $records = PaymentRecord::where('student_id', $sr->user_id)->with('payment.items')->get()->filter(function ($pr) { return $pr->payment; });
        $billedNext = $records->filter(function ($pr) use ($nKey) { return $pr->year.':'.$pr->payment->term === $nKey; });
        if ($billedNext->isNotEmpty()) {
            $school = (int) $billedNext->sum(function ($pr) { return max((int) $pr->payment->amount - (int) $pr->discount - (int) $pr->amt_paid, 0); });
            $set = true;
        } else {
            $percent = $sr->fee_discount_id ? (int) optional(FeeDiscount::find($sr->fee_discount_id))->percent : 0;
            $fees = self::paymentsFor($sr, $nYear)->where('term', $nTerm);
            $school = (int) $fees->sum(function ($p) use ($percent) { return (int) $p->amount - (int) round(self::tuitionBase($p) * $percent / 100); });
            $set = $fees->isNotEmpty();
        }

        // Unpaid school fees up to this term.
        $owed = (int) $records->filter(function ($pr) use ($upTo) { return $pr->year.':'.$pr->payment->term <= $upTo; })
            ->sum(function ($pr) { return max((int) $pr->payment->amount - (int) $pr->discount - (int) $pr->amt_paid, 0); });

        // Optional services and sales, cut into terms the same way as the finance pages.
        $services = 0;
        foreach (OptionalFeeCharge::where('student_id', $sr->user_id)->get() as $c) {
            if ($c->group === 'sales') {
                $due = [FinanceSummary::saleTerm($c->created_at, $c->year) => max((int) $c->amount - (int) $c->amt_paid, 0)];
            } else {
                $shares = FinanceSummary::termShares((int) $c->amount);
                $paid = FinanceSummary::paidByTerm((int) $c->amount, (int) $c->amt_paid);
                $due = array_map(function ($s, $p) { return max($s - $p, 0); }, $shares, $paid);
                $due = array_combine([1, 2, 3], $due);
            }
            foreach ($due as $t => $amt) {
                $k = $c->year.':'.$t;
                if ($k <= $upTo) $owed += $amt;
                elseif ($k === $nKey) $services += $amt;
            }
        }

        return [
            'label' => ['1st', '2nd', '3rd'][$nTerm - 1].' Term '.str_replace('-', ' – ', $nYear),
            'set' => $set, 'school' => $school, 'services' => $services, 'owed' => $owed,
            'total' => $school + $services + $owed,
        ];
    }

    /**
     * Apply one payment to everything the student owes, oldest first: earlier years and terms,
     * then this term's school fees, then optional services. Creates a receipt for each bill paid
     * and returns [['kind' => 'school'|'optional', 'id' => receipt id, 'label', 'amount']].
     */
    public static function applyPayment(int $studentId, int $amount, string $method, ?string $reference = null): array
    {
        $left = $amount;
        $applied = [];

        $records = PaymentRecord::where('student_id', $studentId)->with('payment')->get()
            ->filter(function ($pr) { return $pr->payment; })
            ->sortBy(function ($pr) { return $pr->year.'-'.(int) $pr->payment->term.'-'.str_pad($pr->id, 8, '0', STR_PAD_LEFT); });
        $charges = OptionalFeeCharge::where('student_id', $studentId)->orderBy('year')->orderBy('id')->get();

        $queue = [];
        foreach ($records as $pr) $queue[] = ['school', $pr->year, (int) $pr->payment->term, $pr];
        foreach ($charges as $c) $queue[] = ['optional', $c->year, 9, $c];
        usort($queue, function ($a, $b) { return [$a[1], $a[2]] <=> [$b[1], $b[2]]; });

        foreach ($queue as [$kind, , , $bill]) {
            if ($left <= 0) break;
            if ($kind === 'school') {
                $owed = max((int) $bill->payment->amount - (int) $bill->discount - (int) $bill->amt_paid, 0);
                if (!$owed) continue;
                $pay = min($owed, $left);
                $paid = (int) $bill->amt_paid + $pay;
                $balance = (int) $bill->payment->amount - (int) $bill->discount - $paid;
                $bill->update(['amt_paid' => $paid, 'balance' => max($balance, 0), 'paid' => $balance < 1 ? 1 : 0]);
                $r = \App\Models\Receipt::create(['pr_id' => $bill->id, 'amt_paid' => $pay, 'balance' => max($balance, 0), 'year' => $bill->year, 'method' => $method, 'reference' => $reference]);
                $applied[] = ['kind' => 'school', 'id' => $r->id, 'label' => $bill->payment->title.' ('.$bill->year.')', 'amount' => $pay];
            } else {
                $owed = max((int) $bill->amount - (int) $bill->amt_paid, 0);
                if (!$owed) continue;
                $pay = min($owed, $left);
                $bill->update(['amt_paid' => (int) $bill->amt_paid + $pay]);
                $r = \App\Models\OptionalFeeReceipt::create(['charge_id' => $bill->id, 'amt_paid' => $pay, 'balance' => $bill->fresh()->balance, 'year' => $bill->year, 'method' => $method, 'reference' => $reference]);
                $applied[] = ['kind' => 'optional', 'id' => $r->id, 'label' => $bill->label.' ('.$bill->year.')', 'amount' => $pay];
            }
            $left -= $pay;
        }

        return $applied;
    }
}
