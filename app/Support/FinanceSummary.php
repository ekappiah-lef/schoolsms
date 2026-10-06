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
     * Bills sent to parents for a school year (and optionally one term): school fees after discounts,
     * optional services and shop sales. Also what has been paid against those bills and what is still owed.
     *
     * Services (feeding, bus, clubs) are billed for the whole year: a third belongs to each term (the
     * remainder to term 1) and payments on them settle the terms oldest first. Shop sales belong to the
     * term they were sold in. The same rules are used everywhere (term table, fee items, allocations),
     * so the terms of a year always add up to the year.
     */
    public static function invoiced(string $session, ?int $term = null): array
    {
        $school = DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)
            ->when($term, function ($q) use ($term) { $q->where('p.term', $term); })
            ->selectRaw('coalesce(sum(p.amount),0) as gross, coalesce(sum(pr.discount),0) as discount, coalesce(sum(pr.amt_paid),0) as paid, count(distinct pr.student_id) as students')->first();

        $services = ['billed' => 0, 'paid' => 0];
        $sales = ['billed' => 0, 'paid' => 0];
        foreach (DB::table('optional_fee_charges')->where('year', $session)->get(['group', 'amount', 'amt_paid', 'created_at']) as $c) {
            if ($c->group === 'sales') {
                if ($term && self::saleTerm($c->created_at, $session) !== $term) continue;
                $sales['billed'] += (int) $c->amount;
                $sales['paid'] += (int) $c->amt_paid;
            } else {
                $services['billed'] += $term ? self::termShares((int) $c->amount)[$term] : (int) $c->amount;
                $services['paid'] += $term ? self::paidByTerm((int) $c->amount, (int) $c->amt_paid)[$term] : (int) $c->amt_paid;
            }
        }

        $schoolBilled = (int) $school->gross - (int) $school->discount;
        $billed = $schoolBilled + $services['billed'] + $sales['billed'];
        $paid = (int) $school->paid + $services['paid'] + $sales['paid'];

        return [
            'session' => $session, 'term' => $term, 'students' => (int) $school->students,
            'gross' => (int) $school->gross, 'discount' => (int) $school->discount,
            'school' => $schoolBilled, 'services' => $services['billed'], 'sales' => $sales['billed'],
            'optional' => $services['billed'] + $sales['billed'],
            'billed' => $billed, 'paid' => $paid, 'outstanding' => $billed - $paid,
        ];
    }

    /** Part of a year-long service charge that belongs to each term: a third each, the remainder in term 1. */
    public static function termShares(int $amount): array
    {
        $third = intdiv($amount, 3);

        return [1 => $third + $amount % 3, 2 => $third, 3 => $third];
    }

    /** Money paid on a year-long service charge, applied to its terms oldest first. */
    public static function paidByTerm(int $amount, int $paid): array
    {
        $out = [];
        $lo = 0;
        foreach (self::termShares($amount) as $t => $share) {
            $hi = $t === 3 ? PHP_INT_MAX : $lo + $share;
            $out[$t] = max(0, min($paid, $hi) - $lo);
            $lo += $share;
        }

        return $out;
    }

    /** Term of the school year a shop sale belongs to (sold before / after the year: first / last term). */
    public static function saleTerm($soldAt, string $session): int
    {
        [$y, $t] = FinancePeriod::termOf(Carbon::parse($soldAt));
        $start = (int) substr($session, 0, 4);

        return $y < $start ? 1 : ($y > $start ? 3 : $t);
    }

    protected static $allocations;

    /**
     * Every fee payment ever received, matched to the bill it paid:
     *   [received at "Y-m-d H:i:s", amount, bill "YYYY-YYYY:T", kind school|services|sales].
     * School-fee receipts belong to their bill's term, sales to the term sold in, and payments for
     * year-long services fill that charge's terms oldest first (as paidByTerm).
     */
    public static function allocations(): array
    {
        if (self::$allocations !== null) return self::$allocations;

        $out = [];
        $school = DB::table('receipts as r')->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->select('r.created_at', 'r.amt_paid', 'p.year', 'p.term')->get();
        foreach ($school as $r) {
            $out[] = [(string) $r->created_at, (int) $r->amt_paid, $r->year.':'.$r->term, 'school'];
        }

        $optional = DB::table('optional_fee_receipts as r')->join('optional_fee_charges as c', 'c.id', '=', 'r.charge_id')
            ->orderBy('r.charge_id')->orderBy('r.created_at')->orderBy('r.id')
            ->select('r.charge_id', 'r.created_at', 'r.amt_paid', 'c.year', 'c.group', 'c.amount', 'c.created_at as sold_at')->get();
        $before = [];
        foreach ($optional as $r) {
            $at = (string) $r->created_at;
            if ($r->group === 'sales') {
                $out[] = [$at, (int) $r->amt_paid, $r->year.':'.self::saleTerm($r->sold_at, $r->year), 'sales'];
                continue;
            }
            $b = $before[$r->charge_id] ?? 0;
            $a = $b + (int) $r->amt_paid;
            $upto = self::paidByTerm((int) $r->amount, $a);
            $was = self::paidByTerm((int) $r->amount, $b);
            foreach ([1, 2, 3] as $t) {
                if ($upto[$t] - $was[$t] > 0) $out[] = [$at, $upto[$t] - $was[$t], $r->year.':'.$t, 'services'];
            }
            $before[$r->charge_id] = $a;
        }

        return self::$allocations = $out;
    }

    /**
     * Every term from the first record to the end of this school year (later terms of this year are
     * "upcoming": services are already billed for them, no money has moved yet). For each term:
     *   Bills:  invoiced = paid so far + still owed; paid so far = paid before + during + after the term.
     *   Cash:   cash at start + fees received + other income − expenses = cash at end.
     *   Link:   fees received = for this term's bills + for earlier bills (arrears) + for later bills (in advance),
     *           and "for this term's bills" is the same money as "paid during the term".
     */
    public static function termly(): array
    {
        $first = FinancePeriod::firstActivity();
        [$startY] = FinancePeriod::termOf($first);
        [$cy] = FinancePeriod::termOf(now());
        $alloc = self::allocations();
        $cashNow = self::cashBalance();
        $rows = [];
        for ($y = $cy; $y >= $startY; $y--) {
            for ($t = 3; $t >= 1; $t--) {
                [$from, $to] = FinancePeriod::termRange($y, $t);
                if ($to->lt($first)) continue; // before anything was recorded
                $session = $y.'-'.($y + 1);
                $key = $session.':'.$t;
                $upcoming = $from->gt(now());
                $f = $from->format('Y-m-d H:i:s');
                $e = $to->format('Y-m-d H:i:s');

                $m = ['paidBefore' => 0, 'paidDuring' => 0, 'paidAfter' => 0, 'feesThis' => 0, 'feesEarlier' => 0, 'feesLater' => 0];
                foreach ($alloc as [$at, $amt, $bill]) {
                    $inTerm = $at >= $f && $at <= $e;
                    if ($bill === $key) $m[$at < $f ? 'paidBefore' : ($inTerm ? 'paidDuring' : 'paidAfter')] += $amt;
                    if ($inTerm) $m[$bill === $key ? 'feesThis' : ($bill < $key ? 'feesEarlier' : 'feesLater')] += $amt;
                }

                $inv = self::invoiced($session, $t);
                $cf = $upcoming ? null : self::cashflow($from, $to->min(now()->endOfDay()));
                $rows[] = [
                    'key' => $key, 'session' => $session, 'term' => $t,
                    'label' => 'Term '.$t.' · '.$y.' – '.($y + 1),
                    'from' => $from->toDateString(), 'to' => $to->toDateString(),
                    'current' => !$upcoming && $to->gte(now()), 'upcoming' => $upcoming,
                    // Bills for the term
                    'invoiced' => $inv['billed'], 'school' => $inv['school'], 'services' => $inv['services'], 'sales' => $inv['sales'], 'discount' => $inv['discount'],
                    'paid' => $inv['paid'], 'outstanding' => $inv['outstanding'],
                    // Cash that moved during the term
                    'opening' => $cf ? $cf['opening'] : $cashNow,
                    'fees' => $cf ? $cf['fees'] : 0, 'other' => $cf ? $cf['otherIncome'] : 0,
                    'received' => $cf ? $cf['income'] : 0, 'expenses' => $cf ? $cf['expenses'] : 0,
                    'net' => $cf ? $cf['net'] : 0, 'closing' => $cf ? $cf['closing'] : $cashNow,
                ] + $m;
            }
        }

        return $rows;
    }

    /**
     * Every fee item for the chosen terms ({session: [terms]}): school-fee items (Tuition, P.T.A, Medicals,
     * Uniform …), then optional services (Feeding, Bus, Extra-curricular) and shop sales. What was billed,
     * paid and is still owed, school-wide and per student; the items add up to invoiced() for those terms.
     * Payments on a school bill are shared across its items in proportion to their amounts; discounts come
     * off tuition first.
     */
    public static function itemBreakdown(array $periods): array
    {
        $lines = [];
        $classNames = DB::table('my_classes')->pluck('name', 'id');
        foreach ($periods as $session => $terms) {
            $records = \App\Models\PaymentRecord::where('year', $session)
                ->with(['payment.items', 'payment.my_class'])
                ->whereHas('payment', function ($q) use ($terms) { $q->whereIn('term', $terms); })
                ->get();
            foreach ($records as $pr) {
                $p = $pr->payment;
                $items = $p->items->count() ? $p->items->map(function ($i) { return [$i->name, (int) $i->amount]; })->all() : [[$p->title, (int) $p->amount]];
                foreach (self::splitBill($items, (int) $pr->discount, (int) $pr->amt_paid) as [$name, $amt, $paid]) {
                    $lines[] = ['kind' => 'school', 'item' => $name, 'student_id' => $pr->student_id, 'class' => optional($p->my_class)->name, 'fee' => $p->title.' · '.$p->year, 'amount' => $amt, 'paid' => $paid];
                }
            }

            $classOf = self::classesInYear($session);
            foreach (DB::table('optional_fee_charges')->where('year', $session)->get() as $c) {
                if ($c->group === 'sales') {
                    if (!in_array(self::saleTerm($c->created_at, $session), $terms, true)) continue;
                    $amt = (int) $c->amount;
                    $paid = (int) $c->amt_paid;
                } else {
                    $shares = self::termShares((int) $c->amount);
                    $paidBy = self::paidByTerm((int) $c->amount, (int) $c->amt_paid);
                    $amt = array_sum(array_intersect_key($shares, array_flip($terms)));
                    $paid = array_sum(array_intersect_key($paidBy, array_flip($terms)));
                }
                if (!$amt && !$paid) continue;
                $lines[] = ['kind' => 'optional', 'item' => OptionalFeeCharge::GROUPS[$c->group] ?? ucfirst($c->group), 'student_id' => $c->student_id,
                    'class' => $classNames[$classOf[$c->student_id] ?? 0] ?? null, 'fee' => $c->label, 'amount' => $amt, 'paid' => $paid];
            }
        }

        $students = DB::table('users as u')->leftJoin('student_records as sr', 'sr.user_id', '=', 'u.id')
            ->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')
            ->whereIn('u.id', collect($lines)->pluck('student_id')->unique())
            ->select('u.id', 'u.name', 'sr.adm_no', 'p.name as parent', 'p.phone as parent_phone')->get()->keyBy('id');

        $totals = [];
        $rows = [];
        foreach ($lines as $l) {
            $name = $l['item'];
            $totals[$name] = $totals[$name] ?? ['name' => $name, 'kind' => $l['kind'], 'billed' => 0, 'paid' => 0, 'students' => []];
            $totals[$name]['billed'] += $l['amount'];
            $totals[$name]['paid'] += $l['paid'];
            $totals[$name]['students'][$l['student_id']] = true;
            $s = $students[$l['student_id']] ?? null;
            $rows[] = [
                'item' => $name, 'student' => optional($s)->name, 'adm_no' => optional($s)->adm_no,
                'parent' => optional($s)->parent, 'parent_phone' => optional($s)->parent_phone,
                'class' => $l['class'], 'fee' => $l['fee'],
                'amount' => $l['amount'], 'paid' => $l['paid'], 'balance' => $l['amount'] - $l['paid'],
                'invoice_url' => route('payments.invoice', \App\Helpers\Qs::hash($l['student_id'])),
            ];
        }

        $order = array_merge(['Tuition', 'Medicals', 'Maintenance', 'P.T.A', 'Toiletries', 'Uniform', 'ID card', 'Admission form', 'Books'], array_values(OptionalFeeCharge::GROUPS));
        $items = collect($totals)
            ->sortBy(function ($t) use ($order) { $i = array_search($t['name'], $order, true); return ($t['kind'] === 'school' ? 0 : 100) + ($i === false ? 50 : $i); })
            ->map(function ($t) { return ['students' => count($t['students']), 'due' => $t['billed'] - $t['paid']] + $t; })->values();

        return ['items' => $items, 'rows' => $rows];
    }

    /**
     * Split one school bill into its items: [name, amount after discount, paid]. The discount comes off
     * tuition first (then the other items); the payment is shared in proportion to the amounts, with the
     * rounding left-over given out a cedi at a time, so the items always add up to the bill exactly.
     */
    public static function splitBill(array $items, int $discount, int $paid): array
    {
        $order = array_keys($items);
        usort($order, function ($a, $b) use ($items) { return (stripos($items[$b][0], 'tuition') !== false) <=> (stripos($items[$a][0], 'tuition') !== false); });
        $net = array_map(function ($i) { return $i[1]; }, $items);
        foreach ($order as $k) {
            $cut = min($discount, $net[$k]);
            $net[$k] -= $cut;
            $discount -= $cut;
        }
        $owed = array_sum($net);
        $paid = min($paid, $owed);
        $share = [];
        foreach ($net as $k => $amt) {
            $share[$k] = $owed > 0 ? intdiv($amt * $paid, $owed) : 0;
        }
        $left = $paid - array_sum($share);
        foreach ($net as $k => $amt) {
            if ($left <= 0) break;
            $add = min($left, $amt - $share[$k]);
            $share[$k] += $add;
            $left -= $add;
        }

        $out = [];
        foreach ($items as $k => $i) {
            $out[] = [$i[0], $net[$k], $share[$k]];
        }

        return $out;
    }

    /** Fee payments received in a period, by the school year of the bill they paid: this year's, earlier years', later years'. */
    public static function receivedFor(Carbon $from, Carbon $to, string $session): array
    {
        $f = $from->format('Y-m-d H:i:s');
        $t = $to->format('Y-m-d H:i:s');
        $out = ['this' => 0, 'earlier' => 0, 'later' => 0];
        foreach (self::allocations() as [$at, $amt, $bill]) {
            if ($at < $f || $at > $t) continue;
            $y = explode(':', $bill)[0];
            $out[$y === $session ? 'this' : ($y < $session ? 'earlier' : 'later')] += $amt;
        }

        return $out;
    }

    /** Fee payments received in a period by one method (cash, MTN MoMo …): who paid, for what, and when. */
    public static function methodPayments(string $method, Carbon $from, Carbon $to): array
    {
        $match = function ($q) use ($method) {
            $method === 'Cash' ? $q->where(function ($w) { $w->where('r.method', 'Cash')->orWhereNull('r.method')->orWhere('r.method', ''); }) : $q->where('r.method', $method);
        };
        $school = DB::table('receipts as r')->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->join('users as u', 'u.id', '=', 'pr.student_id')->leftJoin('my_classes as c', 'c.id', '=', 'p.my_class_id')
            ->whereBetween('r.created_at', [$from, $to])->where($match)
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.reference', 'p.title', 'p.term', 'p.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return ['key' => 's'.$r->id, 'at' => (string) $r->created_at, 'student' => $r->name, 'class' => $r->class_name, 'for' => $r->title.' · Term '.$r->term.' '.$r->year,
                    'reference' => $r->reference, 'amount' => (int) $r->amt_paid, 'url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id))];
            });
        $optional = DB::table('optional_fee_receipts as r')->join('optional_fee_charges as ch', 'ch.id', '=', 'r.charge_id')
            ->join('users as u', 'u.id', '=', 'ch.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'ch.student_id')->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->whereBetween('r.created_at', [$from, $to])->where($match)
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.reference', 'ch.label', 'ch.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return ['key' => 'o'.$r->id, 'at' => (string) $r->created_at, 'student' => $r->name, 'class' => $r->class_name, 'for' => $r->label.' · '.$r->year,
                    'reference' => $r->reference, 'amount' => (int) $r->amt_paid, 'url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id))];
            });

        $rows = $school->concat($optional)->sortByDesc(function ($r) { return $r['at'].'|'.$r['key']; })->values();

        return ['method' => $method, 'total' => (int) $rows->sum('amount'), 'count' => $rows->count(), 'students' => $rows->pluck('student')->unique()->count(), 'rows' => $rows];
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

    /** Fee payments received in a period, by how they were paid (cash, MTN MoMo, bank…). */
    public static function byMethod(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('receipts')->whereBetween('created_at', [$from, $to])->select('method', DB::raw('sum(amt_paid) as total'), DB::raw('count(*) as n'))->groupBy('method')->get()
            ->concat(DB::table('optional_fee_receipts')->whereBetween('created_at', [$from, $to])->select('method', DB::raw('sum(amt_paid) as total'), DB::raw('count(*) as n'))->groupBy('method')->get());

        return $rows->groupBy(function ($r) { return $r->method ?: 'Cash'; })->map(function ($g, $m) {
            return ['method' => $m, 'total' => (int) $g->sum('total'), 'count' => (int) $g->sum('n')];
        })->sortByDesc('total')->values()->all();
    }
}
