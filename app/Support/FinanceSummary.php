<?php

namespace App\Support;

use App\Models\OptionalFeeCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finance figures for the dashboards: what was billed, collected and is
 * still due (school fees and optional services, by class type and class),
 * and the cash flow from fees, other income and expenses.
 */
class FinanceSummary
{
    /** School and optional fees for a session, by class type → class. */
    public static function fees(string $session): array
    {
        // Grouped by the class the fee was billed to, so past years show the class
        // each student was in at the time (not the class they are in now).
        $school = DB::table('payment_records as pr')
            ->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)
            ->groupBy('p.my_class_id')
            ->select('p.my_class_id',
                DB::raw('sum(p.amount) as gross'),
                DB::raw('sum(pr.discount) as discount'),
                DB::raw('sum(coalesce(pr.amt_paid,0)) as paid'),
                DB::raw('count(distinct pr.student_id) as students'))
            ->get()->keyBy('my_class_id');

        $classOf = self::classesInYear($session);
        $optional = DB::table('optional_fee_charges')->where('year', $session)
            ->select('student_id', DB::raw('sum(amount) as amount'), DB::raw('sum(amt_paid) as paid'))
            ->groupBy('student_id')->get()
            ->groupBy(function ($r) use ($classOf) { return $classOf[$r->student_id] ?? 0; })
            ->map(function ($g) { return (object) ['amount' => $g->sum('amount'), 'paid' => $g->sum('paid')]; });

        // Students per class in that year: everyone billed there (or enrolled now, for the current year).
        $enrolled = collect($classOf)->countBy();

        $classes = DB::table('my_classes as c')->leftJoin('class_types as t', 't.id', '=', 'c.class_type_id')
            ->select('c.id', 'c.name', 'c.class_type_id', 't.name as type')->get();

        $rows = ClassOrder::sort($classes, 'class_type_id', 'name')->map(function ($c) use ($school, $optional, $enrolled) {
            $s = $school[$c->id] ?? null;
            $o = $optional[$c->id] ?? null;
            $sExpected = $s ? (int) $s->gross - (int) $s->discount : 0;
            $sPaid = $s ? (int) $s->paid : 0;
            $oExpected = $o ? (int) $o->amount : 0;
            $oPaid = $o ? (int) $o->paid : 0;

            return [
                'id' => $c->id,
                'class' => $c->name,
                'type' => $c->type ?: 'Other',
                'students' => (int) ($enrolled[$c->id] ?? 0),
                'school' => self::triple($sExpected, $sPaid),
                'optional' => self::triple($oExpected, $oPaid),
                'discount' => $s ? (int) $s->discount : 0,
                'total' => self::triple($sExpected + $oExpected, $sPaid + $oPaid),
                'url' => route('payments.manage', $c->id),
            ];
        })->values();

        $types = $rows->groupBy('type')->map(function ($g, $type) {
            return [
                'type' => $type,
                'students' => $g->sum('students'),
                'school' => self::sumTriples($g->pluck('school')),
                'optional' => self::sumTriples($g->pluck('optional')),
                'discount' => $g->sum('discount'),
                'total' => self::sumTriples($g->pluck('total')),
                'classes' => $g->values(),
            ];
        })->values();

        $gross = (int) $school->sum('gross');

        return [
            'school' => self::sumTriples($rows->pluck('school')) + ['gross' => $gross, 'discount' => (int) $school->sum('discount')],
            'optional' => self::sumTriples($rows->pluck('optional')),
            'total' => self::sumTriples($rows->pluck('total')),
            'byType' => $types,
            'services' => self::services($session),
        ];
    }

    /**
     * Class each student was in during a school year: the class their school fees
     * were billed to that year, falling back to their current class.
     */
    public static function classesInYear(string $session): array
    {
        $billed = DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)->whereNotNull('p.my_class_id')
            ->groupBy('pr.student_id')->select('pr.student_id', DB::raw('max(p.my_class_id) as cls'))->pluck('cls', 'student_id')->all();

        if ($session === \App\Helpers\Qs::getCurrentSession()) {
            $current = DB::table('student_records')->where('grad', 0)->pluck('my_class_id', 'user_id')->all();
            $billed = $billed + $current;
        }

        return $billed;
    }

    /** Optional services by group: feeding, bus, extra-curricular, books. */
    public static function services(string $session): array
    {
        $rows = OptionalFeeCharge::where('year', $session)->groupBy('group')
            ->select('group', DB::raw('sum(amount) as amount'), DB::raw('sum(amt_paid) as paid'), DB::raw('count(distinct student_id) as students'))
            ->get()->keyBy('group');

        return collect(OptionalFeeCharge::GROUPS)->map(function ($label, $g) use ($rows) {
            $r = $rows[$g] ?? null;
            return ['group' => $g, 'label' => $label, 'students' => $r ? (int) $r->students : 0, 'url' => route('finance.services', $g)]
                + self::triple($r ? (int) $r->amount : 0, $r ? (int) $r->paid : 0);
        })->values()->all();
    }

    /**
     * Cash flow statement for a period. It always reconciles:
     *   opening (cash before $from) + money in − money out = closing (cash at $to).
     * Money in = school-fee receipts + optional-service receipts (by service) + other income (by category).
     * Money out = expenses (by category).
     */
    public static function cashflow(Carbon $from, Carbon $to): array
    {
        $schoolFees = (int) DB::table('receipts')->whereBetween('created_at', [$from, $to])->sum('amt_paid');

        $optional = DB::table('optional_fee_receipts as r')->join('optional_fee_charges as c', 'c.id', '=', 'r.charge_id')
            ->whereBetween('r.created_at', [$from, $to])->groupBy('c.group')
            ->select('c.group', DB::raw('sum(r.amt_paid) as total'), DB::raw('count(*) as n'))->get()->keyBy('group');
        $optionalLines = collect(\App\Models\OptionalFeeCharge::GROUPS)->map(function ($label, $g) use ($optional) {
            return ['key' => $g, 'label' => $label, 'total' => (int) optional($optional[$g] ?? null)->total, 'count' => (int) optional($optional[$g] ?? null)->n];
        })->values();
        $optionalFees = (int) $optionalLines->sum('total');

        $tx = DB::table('finance_transactions')->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('type', 'category')->select('type', 'category', DB::raw('sum(amount) as total'), DB::raw('count(*) as n'))->get();
        $byCat = function ($rows) {
            return $rows->sortByDesc('total')->map(function ($r) { return ['category' => $r->category, 'total' => (int) $r->total, 'count' => (int) $r->n]; })->values();
        };
        $income = $byCat($tx->where('type', 'income'));
        $expenses = $byCat($tx->where('type', 'expense'));
        $otherIncome = (int) $income->sum('total');
        $out = (int) $expenses->sum('total');
        $in = $schoolFees + $optionalFees + $otherIncome;

        $opening = self::balanceAt($from->copy()->subSecond());
        $closing = $opening + $in - $out;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening' => $opening,
            'schoolFees' => $schoolFees,
            'schoolFeesCount' => (int) DB::table('receipts')->whereBetween('created_at', [$from, $to])->count(),
            'optionalFees' => $optionalFees,
            'optionalByService' => $optionalLines,
            'fees' => $schoolFees + $optionalFees,
            'otherIncome' => $otherIncome,
            'incomeByCategory' => $income,
            'income' => $in,
            'expenses' => $out,
            'expensesByCategory' => $expenses,
            'net' => $in - $out,
            'closing' => $closing,
            // Safety check: the statement must match the cash recorded up to $to.
            'reconciled' => $closing === self::balanceAt($to),
        ];
    }

    /**
     * Bills sent to parents for a school year (and optionally one term):
     * school fees after discounts, plus optional services (a third per term) and shop sales (by date sold).
     * Also what has been paid against those bills and what is still owed.
     */
    public static function invoiced(string $session, ?int $term = null): array
    {
        $school = DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)
            ->when($term, function ($q) use ($term) { $q->where('p.term', $term); })
            ->selectRaw('coalesce(sum(p.amount - pr.discount),0) as billed, coalesce(sum(pr.amt_paid),0) as paid, count(distinct pr.student_id) as students')->first();

        // Optional services (feeding, bus, clubs) are billed for the year and paid through it:
        // a third belongs to each term. Shop sales belong to the term they were sold in.
        $services = DB::table('optional_fee_charges')->where('year', $session)->where('group', '!=', 'sales')
            ->selectRaw('coalesce(sum(amount),0) as billed, coalesce(sum(amt_paid),0) as paid')->first();
        $share = function ($v) use ($term) { $v = (int) $v; return $term ? intdiv($v, 3) + ($term === 1 ? $v % 3 : 0) : $v; };
        $sales = DB::table('optional_fee_charges')->where('year', $session)->where('group', 'sales')->get(['amount', 'amt_paid', 'created_at'])
            ->filter(function ($c) use ($term, $session) {
                if (!$term) return true;
                [$y, $t] = FinancePeriod::termOf(\Carbon\Carbon::parse($c->created_at));
                $start = (int) substr($session, 0, 4);
                $t = $y < $start ? 1 : ($y > $start ? 3 : $t); // sold before / after the year: first / last term
                return $t === $term;
            });
        $optional = (object) [
            'billed' => $share($services->billed) + (int) $sales->sum('amount'),
            'paid' => $share($services->paid) + (int) $sales->sum('amt_paid'),
        ];

        $billed = (int) $school->billed + (int) $optional->billed;
        $paid = (int) $school->paid + (int) $optional->paid;

        return [
            'session' => $session, 'term' => $term, 'students' => (int) $school->students,
            'school' => (int) $school->billed, 'optional' => (int) $optional->billed,
            'billed' => $billed, 'paid' => $paid, 'outstanding' => max($billed - $paid, 0),
        ];
    }

    /** Every term so far: bills sent, money received, expenses and the balance at the end of the term. */
    public static function termly(): array
    {
        $first = FinancePeriod::firstActivity();
        [$startY] = FinancePeriod::termOf($first);
        [$cy, $ct] = FinancePeriod::termOf(now());
        $rows = [];
        for ($y = $cy; $y >= $startY; $y--) {
            for ($t = 3; $t >= 1; $t--) {
                if ($y === $cy && $t > $ct) continue;
                [$from, $to] = FinancePeriod::termRange($y, $t);
                if ($to->lt($first)) continue; // before anything was recorded
                $to = $to->min(now()->endOfDay());
                $cf = self::cashflow($from, $to);
                $inv = self::invoiced($y.'-'.($y + 1), $t);
                $rows[] = [
                    'key' => 'term:'.$y.'-'.($y + 1).':'.$t, 'session' => $y.'-'.($y + 1), 'term' => $t,
                    'label' => 'Term '.$t.' · '.$y.' – '.($y + 1),
                    // Bills for the term: invoiced = paid + still owed.
                    'invoiced' => $inv['billed'], 'paid' => $inv['billed'] - $inv['outstanding'], 'outstanding' => $inv['outstanding'], 'opening' => $cf['opening'],
                    // Cash that moved during the term (fee payments include arrears from earlier terms).
                    'fees' => $cf['fees'], 'other' => $cf['otherIncome'],
                    'received' => $cf['income'], 'expenses' => $cf['expenses'], 'net' => $cf['net'], 'closing' => $cf['closing'],
                    'current' => $y === $cy && $t === $ct,
                ];
            }
        }

        return $rows;
    }

    /**
     * Every fee item (Tuition, P.T.A, Medicals, Uniform …) for a school year
     * (optionally one term): what was billed, paid and is still owed, school-wide
     * and per student. Payments are shared across a bill's items in proportion to
     * their amounts; tuition discounts reduce the tuition item.
     */
    public static function itemBreakdown(string $session, ?int $term = null): array
    {
        $records = \App\Models\PaymentRecord::where('year', $session)
            ->with(['payment.items', 'payment.my_class'])
            ->whereHas('payment', function ($q) use ($term) { if ($term) $q->where('term', $term); })
            ->get();

        $students = DB::table('users as u')->leftJoin('student_records as sr', 'sr.user_id', '=', 'u.id')
            ->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')
            ->whereIn('u.id', $records->pluck('student_id')->unique())
            ->select('u.id', 'u.name', 'sr.adm_no', 'p.name as parent', 'p.phone as parent_phone', 'p.id as parent_id')->get()->keyBy('id');

        $totals = [];
        $rows = [];
        foreach ($records as $pr) {
            $p = $pr->payment;
            if (!$p) continue;
            $items = $p->items->count() ? $p->items->map(function ($i) { return [$i->name, (int) $i->amount]; })->all() : [[$p->title, (int) $p->amount]];
            // Discount comes off tuition (or the whole fee when it is not itemised).
            $discount = (int) $pr->discount;
            $net = [];
            foreach ($items as [$name, $amt]) {
                $cut = $discount > 0 && (stripos($name, 'tuition') !== false || count($items) === 1) ? min($discount, $amt) : 0;
                $discount -= $cut;
                $net[] = [$name, $amt - $cut];
            }
            $owed = array_sum(array_column($net, 1));
            $ratio = $owed > 0 ? min(1, (int) $pr->amt_paid / $owed) : 1;
            foreach ($net as [$name, $amt]) {
                $paid = (int) round($amt * $ratio);
                $totals[$name] = $totals[$name] ?? ['name' => $name, 'billed' => 0, 'paid' => 0, 'students' => 0];
                $totals[$name]['billed'] += $amt;
                $totals[$name]['paid'] += $paid;
                $totals[$name]['students']++;
                $s = $students[$pr->student_id] ?? null;
                $rows[] = [
                    'item' => $name, 'student' => optional($s)->name, 'adm_no' => optional($s)->adm_no,
                    'parent' => optional($s)->parent, 'parent_phone' => optional($s)->parent_phone,
                    'class' => optional($p->my_class)->name, 'fee' => $p->title,
                    'amount' => $amt, 'paid' => $paid, 'balance' => max($amt - $paid, 0),
                    'invoice_url' => route('payments.invoice', \App\Helpers\Qs::hash($pr->student_id)),
                ];
            }
        }

        $order = ['Tuition', 'Medicals', 'Maintenance', 'P.T.A', 'Toiletries', 'Uniform', 'ID card', 'Admission form', 'Books'];
        $items = collect($totals)->sortBy(function ($t) use ($order) { $i = array_search($t['name'], $order, true); return $i === false ? 99 : $i; })
            ->map(function ($t) { return $t + ['due' => max($t['billed'] - $t['paid'], 0)]; })->values();

        return ['items' => $items, 'rows' => $rows];
    }

    /** Cash at a moment: everything received up to then minus everything spent up to then. */
    public static function balanceAt(Carbon $at): int
    {
        $in = (int) DB::table('receipts')->where('created_at', '<=', $at)->sum('amt_paid')
            + (int) DB::table('optional_fee_receipts')->where('created_at', '<=', $at)->sum('amt_paid')
            + (int) DB::table('finance_transactions')->where('type', 'income')->where('date', '<=', $at->toDateString())->sum('amount');
        $out = (int) DB::table('finance_transactions')->where('type', 'expense')->where('date', '<=', $at->toDateString())->sum('amount');

        return $in - $out;
    }

    /** Cash now: all money received minus all expenses, ever recorded. */
    public static function cashBalance(): int
    {
        return self::balanceAt(now()->endOfDay());
    }

    /**
     * Every movement of money, oldest first, with the balance after each one:
     * school-fee receipts, optional-service receipts, other income and expenses.
     */
    public static function ledger(Carbon $from, Carbon $to): array
    {
        $school = DB::table('receipts as r')
            ->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')
            ->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->join('users as u', 'u.id', '=', 'pr.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'pr.student_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->whereBetween('r.created_at', [$from, $to])
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.balance', 'p.title', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return [
                    'key' => 's'.$r->id, 'at' => (string) $r->created_at, 'dir' => 'in', 'source' => 'School fees', 'category' => $r->title,
                    'party' => $r->name, 'detail' => trim(($r->class_name ? $r->class_name.' · ' : '').'balance left '.number_format($r->balance)),
                    'ref' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => (int) $r->amt_paid,
                    'url' => route('receipts.show', ['school', \App\Helpers\Qs::hash($r->id)]),
                    'party_url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id)),
                ];
            });

        $optional = DB::table('optional_fee_receipts as r')
            ->join('optional_fee_charges as ch', 'ch.id', '=', 'r.charge_id')
            ->join('users as u', 'u.id', '=', 'ch.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'ch.student_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->whereBetween('r.created_at', [$from, $to])
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.balance', 'ch.group', 'ch.label', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return [
                    'key' => 'o'.$r->id, 'at' => (string) $r->created_at, 'dir' => 'in',
                    'source' => \App\Models\OptionalFeeCharge::GROUPS[$r->group] ?? ucfirst($r->group), 'category' => $r->label,
                    'party' => $r->name, 'detail' => trim(($r->class_name ? $r->class_name.' · ' : '').'balance left '.number_format($r->balance)),
                    'ref' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => (int) $r->amt_paid,
                    'url' => route('receipts.show', ['optional', \App\Helpers\Qs::hash($r->id)]),
                    'party_url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id)),
                ];
            });

        $tx = DB::table('finance_transactions as t')->leftJoin('users as u', 'u.id', '=', 't.recorded_by')
            ->whereBetween('t.date', [$from->toDateString(), $to->toDateString()])
            ->select('t.*', 'u.name as recorder')->get()
            ->map(function ($t) {
                return [
                    'key' => 't'.$t->id, 'at' => $t->date.' 12:00:00', 'dir' => $t->type === 'income' ? 'in' : 'out',
                    'source' => $t->type === 'income' ? 'Other income' : 'Expense', 'category' => $t->category,
                    'party' => $t->description ?: '—', 'detail' => trim(implode(' · ', array_filter([$t->method, $t->recorder ? 'recorded by '.$t->recorder : null]))),
                    'ref' => $t->reference ?: 'TX-'.str_pad($t->id, 5, '0', STR_PAD_LEFT), 'amount' => (int) $t->amount,
                    'url' => route('finance.transactions.edit', \App\Helpers\Qs::hash($t->id)), 'party_url' => null,
                ];
            });

        $opening = self::balanceAt($from->copy()->subSecond());
        $balance = $opening;
        $rows = $school->concat($optional)->concat($tx)->sortBy(function ($r) { return $r['at'].'|'.$r['key']; })->values()
            ->map(function ($r) use (&$balance) {
                $balance += $r['dir'] === 'in' ? $r['amount'] : -$r['amount'];
                return $r + ['balance' => $balance];
            });

        return ['opening' => $opening, 'closing' => $balance, 'rows' => $rows->reverse()->values()];
    }

    /** Income (fees + other) and expenses per month for the last $months months. */
    public static function monthly(int $months = 12): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $fmt = "DATE_FORMAT(%s, '%%Y-%%m')";
        $school = DB::table('receipts')->where('created_at', '>=', $from)->groupBy('ym')
            ->select(DB::raw(sprintf($fmt, 'created_at').' as ym'), DB::raw('sum(amt_paid) as t'))->pluck('t', 'ym');
        $optional = DB::table('optional_fee_receipts')->where('created_at', '>=', $from)->groupBy('ym')
            ->select(DB::raw(sprintf($fmt, 'created_at').' as ym'), DB::raw('sum(amt_paid) as t'))->pluck('t', 'ym');
        $tx = DB::table('finance_transactions')->where('date', '>=', $from->toDateString())->groupBy('ym', 'type')
            ->select(DB::raw(sprintf($fmt, 'date').' as ym'), 'type', DB::raw('sum(amount) as t'))->get();

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $from->copy()->addMonths($i);
            $k = $m->format('Y-m');
            $fees = (int) ($school[$k] ?? 0) + (int) ($optional[$k] ?? 0);
            $other = (int) $tx->where('ym', $k)->where('type', 'income')->sum('t');
            $exp = (int) $tx->where('ym', $k)->where('type', 'expense')->sum('t');
            $out[] = ['month' => $m->format('M'), 'label' => $m->format('M Y'), 'fees' => $fees, 'other' => $other, 'income' => $fees + $other, 'expenses' => $exp];
        }

        return $out;
    }

    public static function triple(int $expected, int $paid): array
    {
        return ['expected' => $expected, 'paid' => $paid, 'due' => max($expected - $paid, 0)];
    }

    protected static function sumTriples($list): array
    {
        $list = collect($list);
        return ['expected' => (int) $list->sum('expected'), 'paid' => (int) $list->sum('paid'), 'due' => (int) $list->sum('due')];
    }
}
