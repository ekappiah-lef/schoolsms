<?php

namespace App\Support;

use App\Models\OptionalFeeCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finance figures for the finance pages, always for a TermSelection (one or more terms / years).
 *
 * Rules, the same everywhere so every page agrees:
 *  - School fees belong to the term on the bill (payments.term); invoiced = fee − discount.
 *  - Additional services (feeding, bus, clubs) are billed for the whole year: a third belongs to each
 *    term (the remainder to term 1), and payments on them settle the terms oldest first.
 *  - Shop sales belong to the term they were sold in.
 *  - Money received, other income and expenses belong to the term of the day they happened.
 *  - Each term starts from zero: its balance = money received in the term − expenses in the term.
 */
class FinanceSummary
{
    /* ------------------------------------------------------------------ bills */

    /** Part of a year-long service charge that belongs to each term: a third each, the remainder in term 1. */
    public static function termShares(float $amount): array
    {
        $third = floor(Money::r($amount) * 100 / 3) / 100;

        return [1 => Money::r($amount - 2 * $third), 2 => $third, 3 => $third];
    }

    /** Money paid on a year-long service charge, applied to its terms oldest first. */
    public static function paidByTerm(float $amount, float $paid): array
    {
        $out = [];
        $lo = 0;
        foreach (self::termShares($amount) as $t => $share) {
            $hi = $t === 3 ? PHP_INT_MAX : $lo + $share;
            $out[$t] = Money::r(max(0, min($paid, $hi) - $lo));
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

    /**
     * Optional charges (services and sales) of a school year that fall in the given terms:
     * [student_id, group, label, amount, paid] with services cut to the terms' share.
     */
    public static function chargeLines(string $session, array $terms): array
    {
        $out = [];
        foreach (DB::table('optional_fee_charges')->where('year', $session)->get(['student_id', 'group', 'label', 'term', 'amount', 'amt_paid', 'created_at']) as $c) {
            // Shop sales: the term of the sale. Services: billed term by term, so the term on the charge
            // (a charge without a term, from before, is spread a third per term).
            if ($c->group === 'sales' || $c->term) {
                if (!in_array($c->term ? (int) $c->term : self::saleTerm($c->created_at, $session), $terms, true)) continue;
                $amt = \App\Support\Money::r($c->amount);
                $paid = \App\Support\Money::r($c->amt_paid);
            } else {
                $pick = array_flip($terms);
                $amt = array_sum(array_intersect_key(self::termShares(\App\Support\Money::r($c->amount)), $pick));
                $paid = array_sum(array_intersect_key(self::paidByTerm(\App\Support\Money::r($c->amount), \App\Support\Money::r($c->amt_paid)), $pick));
            }
            if (!$amt && !$paid) continue;
            $out[] = (object) ['student_id' => $c->student_id, 'group' => $c->group, 'label' => $c->label, 'amount' => $amt, 'paid' => $paid];
        }

        return $out;
    }

    /** School-fee bills of a school year in the given terms: one row per student bill. */
    protected static function schoolLines(string $session, array $terms)
    {
        return DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->where('pr.year', $session)->whereIn('p.term', $terms)
            ->select('pr.student_id', 'p.my_class_id', DB::raw('coalesce(pr.amount, p.amount) as amount'), 'pr.discount', DB::raw('coalesce(pr.amt_paid,0) as paid'))->get();
    }

    /**
     * Bills sent to parents for the selection: school fees after discounts, additional services and
     * shop sales; what has been paid against them (whenever it was paid) and what is still owed.
     */
    public static function invoiced(TermSelection $sel): array
    {
        $out = ['gross' => 0, 'discount' => 0, 'school' => 0, 'services' => 0, 'sales' => 0, 'paid' => 0, 'students' => []];
        foreach ($sel->periods() as $session => $terms) {
            foreach (self::schoolLines($session, $terms) as $r) {
                $out['gross'] += \App\Support\Money::r($r->amount);
                $out['discount'] += \App\Support\Money::r($r->discount);
                $out['school'] += \App\Support\Money::r($r->amount) - \App\Support\Money::r($r->discount);
                $out['paid'] += Money::r($r->paid);
                $out['students'][$r->student_id] = true;
            }
            foreach (self::chargeLines($session, $terms) as $c) {
                $out[$c->group === 'sales' ? 'sales' : 'services'] += $c->amount;
                $out['paid'] += $c->paid;
            }
        }
        $billed = $out['school'] + $out['services'] + $out['sales'];

        return [
            'students' => count($out['students']), 'gross' => $out['gross'], 'discount' => $out['discount'],
            'school' => $out['school'], 'services' => $out['services'], 'sales' => $out['sales'], 'optional' => $out['services'] + $out['sales'],
            'billed' => $billed, 'paid' => $out['paid'], 'outstanding' => $billed - $out['paid'],
        ];
    }

    /** School and additional fees for the selection, by class type → class, and by service. */
    public static function fees(TermSelection $sel): array
    {
        $byClass = []; // class id => [gross, discount, sPaid, oAmount, oPaid, students]
        $services = [];
        $add = function ($cls, $k, $v) use (&$byClass) {
            $byClass[$cls] = $byClass[$cls] ?? ['gross' => 0, 'discount' => 0, 'sPaid' => 0, 'oAmount' => 0, 'oPaid' => 0, 'students' => []];
            $byClass[$cls][$k] += $v;
        };
        foreach ($sel->periods() as $session => $terms) {
            // Grouped by the class the fee was billed to, so past years show the class each student was in then.
            // A fee for "All classes" counts under the class the student was in that year.
            $classOf = self::classesInYear($session);
            foreach (self::schoolLines($session, $terms) as $r) {
                $cls = $r->my_class_id ?: ($classOf[$r->student_id] ?? 0);
                $add($cls, 'gross', \App\Support\Money::r($r->amount));
                $add($cls, 'discount', \App\Support\Money::r($r->discount));
                $add($cls, 'sPaid', Money::r($r->paid));
                $byClass[$cls]['students'][$r->student_id] = true;
            }
            foreach (self::chargeLines($session, $terms) as $c) {
                $cls = $classOf[$c->student_id] ?? 0;
                $add($cls, 'oAmount', $c->amount);
                $add($cls, 'oPaid', $c->paid);
                $byClass[$cls]['students'][$c->student_id] = true;
                $services[$c->group] = $services[$c->group] ?? ['amount' => 0, 'paid' => 0, 'students' => []];
                $services[$c->group]['amount'] += $c->amount;
                $services[$c->group]['paid'] += $c->paid;
                $services[$c->group]['students'][$c->student_id] = true;
            }
        }

        $classes = DB::table('my_classes as c')->leftJoin('class_types as t', 't.id', '=', 'c.class_type_id')
            ->select('c.id', 'c.name', 'c.class_type_id', 't.name as type')->get();

        $rows = ClassOrder::sort($classes, 'class_type_id', 'name')->map(function ($c) use ($byClass) {
            $b = $byClass[$c->id] ?? ['gross' => 0, 'discount' => 0, 'sPaid' => 0, 'oAmount' => 0, 'oPaid' => 0, 'students' => []];
            $sExpected = $b['gross'] - $b['discount'];

            return [
                'id' => $c->id,
                'class' => $c->name,
                'type' => $c->type ?: 'Other',
                'students' => count($b['students']),
                'school' => self::triple($sExpected, $b['sPaid']),
                'optional' => self::triple($b['oAmount'], $b['oPaid']),
                'discount' => $b['discount'],
                'total' => self::triple($sExpected + $b['oAmount'], $b['sPaid'] + $b['oPaid']),
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

        $gross = array_sum(array_column($byClass, 'gross'));
        $discount = array_sum(array_column($byClass, 'discount'));

        return [
            'school' => self::sumTriples($rows->pluck('school')) + ['gross' => $gross, 'discount' => $discount],
            'optional' => self::sumTriples($rows->pluck('optional')),
            'total' => self::sumTriples($rows->pluck('total')),
            'byType' => $types,
            'services' => collect(OptionalFeeCharge::GROUPS)->map(function ($label, $g) use ($services) {
                $s = $services[$g] ?? ['amount' => 0, 'paid' => 0, 'students' => []];
                return ['group' => $g, 'label' => $label, 'students' => count($s['students']), 'url' => route('finance.services', $g)] + self::triple($s['amount'], $s['paid']);
            })->values()->all(),
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

    /**
     * Every fee item for the selection: school-fee items (Tuition, P.T.A, Medicals …), then optional
     * services (Feeding, Bus, Extra-curricular) and shop sales. What was billed, paid and is still owed,
     * school-wide and per student; the items add up to invoiced() for the same selection.
     */
    public static function itemBreakdown(TermSelection $sel): array
    {
        $lines = [];
        $classNames = DB::table('my_classes')->pluck('name', 'id');
        foreach ($sel->periods() as $session => $terms) {
            $classOf = self::classesInYear($session);
            $records = \App\Models\PaymentRecord::where('year', $session)
                ->with(['payment.items', 'payment.my_class'])
                ->whereHas('payment', function ($q) use ($terms) { $q->whereIn('term', $terms); })
                ->get();
            foreach ($records as $pr) {
                $p = $pr->payment;
                $items = $p->items->count() ? $p->items->map(function ($i) { return [$i->name, Money::r($i->amount)]; })->all() : [[$p->title, \App\Support\Money::r($p->amount)]];
                foreach (self::splitBill($items, \App\Support\Money::r($pr->discount), \App\Support\Money::r($pr->amt_paid)) as [$name, $amt, $paid]) {
                    $lines[] = ['kind' => 'school', 'item' => $name, 'student_id' => $pr->student_id, 'class' => optional($p->my_class)->name ?? ($classNames[$classOf[$pr->student_id] ?? 0] ?? null), 'fee' => $p->title.' · '.$p->year, 'amount' => $amt, 'paid' => $paid];
                }
            }

            foreach (self::chargeLines($session, $terms) as $c) {
                $lines[] = ['kind' => 'optional', 'item' => OptionalFeeCharge::GROUPS[$c->group] ?? ucfirst($c->group), 'student_id' => $c->student_id,
                    'class' => $classNames[$classOf[$c->student_id] ?? 0] ?? null, 'fee' => $c->label.' · '.$session, 'amount' => $c->amount, 'paid' => $c->paid];
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
    public static function splitBill(array $items, float $discount, float $paid): array
    {
        // Worked in pesewas so the shares add up exactly.
        $items = array_map(function ($i) { return [$i[0], (int) round($i[1] * 100)]; }, $items);
        $discount = (int) round($discount * 100);
        $paid = (int) round($paid * 100);
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
            $out[] = [$i[0], $net[$k] / 100, $share[$k] / 100];
        }

        return $out;
    }

    /* ------------------------------------------------------------------- cash */

    /**
     * Money in and out during the selection. Each term starts from zero:
     *   fees received (school + optional, for any bill, old or new) + other income − expenses = balance.
     */
    public static function cashflow(TermSelection $sel): array
    {
        $schoolQ = $sel->apply(DB::table('receipts'), 'created_at');
        $schoolFees = Money::r((clone $schoolQ)->sum('amt_paid'));

        $optional = $sel->apply(DB::table('optional_fee_receipts as r')->join('optional_fee_charges as c', 'c.id', '=', 'r.charge_id'), 'r.created_at')
            ->groupBy('c.group')->select('c.group', DB::raw('sum(r.amt_paid) as total'), DB::raw('count(*) as n'))->get()->keyBy('group');
        $optionalLines = collect(OptionalFeeCharge::GROUPS)->map(function ($label, $g) use ($optional) {
            return ['key' => $g, 'label' => $label, 'total' => \App\Support\Money::r(optional($optional[$g] ?? null)->total), 'count' => (int) optional($optional[$g] ?? null)->n];
        })->values();
        $optionalFees = \App\Support\Money::r($optionalLines->sum('total'));

        $tx = $sel->apply(DB::table('finance_transactions'), 'date', true)
            ->groupBy('type', 'category')->select('type', 'category', DB::raw('sum(amount) as total'), DB::raw('count(*) as n'))->get();
        $byCat = function ($rows) {
            return $rows->sortByDesc('total')->map(function ($r) { return ['category' => $r->category, 'total' => \App\Support\Money::r($r->total), 'count' => (int) $r->n]; })->values();
        };
        $income = $byCat($tx->where('type', 'income'));
        $expenses = $byCat($tx->where('type', 'expense'));
        $otherIncome = \App\Support\Money::r($income->sum('total'));
        $out = \App\Support\Money::r($expenses->sum('total'));
        $in = $schoolFees + $optionalFees + $otherIncome;

        return [
            'schoolFees' => $schoolFees,
            'schoolFeesCount' => (int) (clone $schoolQ)->count(),
            'optionalFees' => $optionalFees,
            'optionalByService' => $optionalLines,
            'fees' => $schoolFees + $optionalFees,
            'otherIncome' => $otherIncome,
            'incomeByCategory' => $income,
            'income' => $in,
            'expenses' => $out,
            'expensesByCategory' => $expenses,
            'net' => $in - $out,
        ];
    }

    /** Every term of the school's history (to the end of this school year), newest first: bills and cash. */
    public static function termly(): array
    {
        $rows = [];
        foreach (TermSelection::allKeys() as $key) {
            [$session, $t] = explode(':', $key);
            $t = (int) $t;
            $y = (int) substr($session, 0, 4);
            [$from, $to] = FinancePeriod::termRange($y, $t);
            $sel = TermSelection::of([$key]);
            $inv = self::invoiced($sel);
            $cf = self::cashflow($sel);
            $rows[] = [
                'key' => $key, 'session' => $session, 'term' => $t,
                'label' => 'Term '.$t.' · '.$y.' – '.($y + 1),
                'from' => $from->toDateString(), 'to' => $to->toDateString(),
                'current' => $from->lte(now()) && $to->gte(now()), 'upcoming' => $from->gt(now()),
                // Bills for the term: invoiced = paid so far + still owed.
                'invoiced' => $inv['billed'], 'paid' => $inv['paid'], 'outstanding' => $inv['outstanding'],
                // Money that moved during the term (fees received include old fees paid during it).
                'fees' => $cf['fees'], 'other' => $cf['otherIncome'], 'received' => $cf['income'],
                'expenses' => $cf['expenses'], 'net' => $cf['net'],
            ];
        }

        return $rows;
    }

    /** Fee payments received in the selection, by mode (Cash, Mobile payment, Bank transfer, Cheque). */
    public static function byMethod(TermSelection $sel): array
    {
        $rows = $sel->apply(DB::table('receipts'), 'created_at')->select('method', DB::raw('sum(amt_paid) as total'), DB::raw('count(*) as n'))->groupBy('method')->get()
            ->concat($sel->apply(DB::table('optional_fee_receipts'), 'created_at')->select('method', DB::raw('sum(amt_paid) as total'), DB::raw('count(*) as n'))->groupBy('method')->get());
        $order = array_flip(Fees::METHODS);

        return $rows->groupBy(function ($r) { return $r->method ?: 'Cash'; })->map(function ($g, $m) {
            return ['method' => $m, 'total' => \App\Support\Money::r($g->sum('total')), 'count' => \App\Support\Money::r($g->sum('n'))];
        })->sortBy(function ($r) use ($order) { return $order[$r['method']] ?? 99; })->values()->all();
    }

    /** Fee payments received in the selection by one mode: who paid, for what, and when. */
    /**
     * The students behind the Fees card for the selection (scope: total | school | optional), from the same
     * bill lines as fees(), so the totals here equal the card: billed, discount, paid and still owed per student,
     * with each bill. kind: paid | owed | discount picks who is listed.
     */
    public static function feeStudents(TermSelection $sel, string $scope, string $kind): array
    {
        $students = [];
        $add = function ($id, $line) use (&$students) {
            $students[$id] = $students[$id] ?? ['billed' => 0, 'discount' => 0, 'paid' => 0, 'owed' => 0, 'lines' => []];
            foreach (['billed', 'discount', 'paid', 'owed'] as $k) $students[$id][$k] += $line[$k];
            $students[$id]['lines'][] = $line;
        };
        foreach ($sel->periods() as $session => $terms) {
            $year = str_replace('-', ' – ', $session);
            if ($scope !== 'optional') {
                $rows = DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')
                    ->where('pr.year', $session)->whereIn('p.term', $terms)
                    ->select('pr.student_id', 'p.title', 'p.term', DB::raw('coalesce(pr.amount, p.amount) as amount'), 'pr.discount', DB::raw('coalesce(pr.amt_paid,0) as paid'))->get();
                foreach ($rows as $r) {
                    $billed = \App\Support\Money::r($r->amount) - \App\Support\Money::r($r->discount);
                    $add($r->student_id, ['label' => $r->title.' · Term '.$r->term.' · '.$year, 'billed' => $billed, 'discount' => \App\Support\Money::r($r->discount),
                        'paid' => Money::r($r->paid), 'owed' => max($billed - Money::r($r->paid), 0)]);
                }
            }
            if ($scope !== 'school') {
                foreach (self::chargeLines($session, $terms) as $c) {
                    $add($c->student_id, ['label' => (OptionalFeeCharge::GROUPS[$c->group] ?? ucfirst($c->group)).': '.$c->label.' · '.$year, 'billed' => $c->amount,
                        'discount' => 0, 'paid' => $c->paid, 'owed' => max($c->amount - $c->paid, 0)]);
                }
            }
        }

        $field = ['paid' => 'paid', 'owed' => 'owed', 'discount' => 'discount'][$kind];
        $students = array_filter($students, function ($s) use ($field) { return $s[$field] > 0; });
        $info = DB::table('users as u')->leftJoin('student_records as sr', 'sr.user_id', '=', 'u.id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')
            ->whereIn('u.id', array_keys($students))
            ->select('u.id', 'u.name', 'sr.adm_no', 'c.name as class', 'p.name as parent', 'p.phone as parent_phone')->get()->keyBy('id');

        $rows = collect($students)->map(function ($s, $id) use ($info, $field) {
            $u = $info[$id] ?? null;
            $lines = collect($s['lines'])->filter(function ($l) use ($field) { return $l[$field] > 0; })->values();

            return ['id' => $id, 'student' => optional($u)->name, 'adm_no' => optional($u)->adm_no, 'class' => optional($u)->class,
                'parent' => optional($u)->parent, 'parent_phone' => optional($u)->parent_phone,
                'billed' => $s['billed'], 'discount' => $s['discount'], 'paid' => $s['paid'], 'owed' => $s['owed'], 'amount' => $s[$field],
                'lines' => $lines, 'url' => route('payments.invoice', \App\Helpers\Qs::hash($id))];
        })->sortByDesc('amount')->values();

        return ['kind' => $kind, 'scope' => $scope, 'total' => \App\Support\Money::r($rows->sum('amount')), 'students' => $rows->count(), 'rows' => $rows];
    }

    public static function methodPayments(string $method, TermSelection $sel): array
    {
        $match = function ($q) use ($method) {
            $method === 'Cash' ? $q->where(function ($w) { $w->where('r.method', 'Cash')->orWhereNull('r.method')->orWhere('r.method', ''); }) : $q->where('r.method', $method);
        };
        $school = $sel->apply(DB::table('receipts as r')->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->join('users as u', 'u.id', '=', 'pr.student_id')->leftJoin('my_classes as c', 'c.id', '=', 'p.my_class_id'), 'r.created_at')->where($match)
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.reference', 'p.title', 'p.term', 'p.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return ['key' => 's'.$r->id, 'at' => (string) $r->created_at, 'student' => $r->name, 'class' => $r->class_name, 'for' => $r->title.' · '.$r->year,
                    'reference' => $r->reference, 'amount' => \App\Support\Money::r($r->amt_paid), 'url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id))];
            });
        $optional = $sel->apply(DB::table('optional_fee_receipts as r')->join('optional_fee_charges as ch', 'ch.id', '=', 'r.charge_id')
            ->join('users as u', 'u.id', '=', 'ch.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'ch.student_id')->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id'), 'r.created_at')->where($match)
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.reference', 'ch.label', 'ch.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return ['key' => 'o'.$r->id, 'at' => (string) $r->created_at, 'student' => $r->name, 'class' => $r->class_name, 'for' => $r->label.' · '.$r->year,
                    'reference' => $r->reference, 'amount' => \App\Support\Money::r($r->amt_paid), 'url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id))];
            });

        $rows = $school->concat($optional)->sortByDesc(function ($r) { return $r['at'].'|'.$r['key']; })->values();

        return ['method' => $method, 'total' => \App\Support\Money::r($rows->sum('amount')), 'count' => $rows->count(), 'students' => $rows->pluck('student')->unique()->count(), 'rows' => $rows];
    }

    /** Cash at a moment: everything received up to then minus everything spent up to then. */
    public static function balanceAt(Carbon $at): float
    {
        $in = \App\Support\Money::r(DB::table('receipts')->where('created_at', '<=', $at)->sum('amt_paid'))
            + \App\Support\Money::r(DB::table('optional_fee_receipts')->where('created_at', '<=', $at)->sum('amt_paid'))
            + \App\Support\Money::r(DB::table('finance_transactions')->where('type', 'income')->where('date', '<=', $at->toDateString())->sum('amount'));
        $out = \App\Support\Money::r(DB::table('finance_transactions')->where('type', 'expense')->where('date', '<=', $at->toDateString())->sum('amount'));

        return $in - $out;
    }

    /**
     * Trial balance for the selection (cash basis, the same money as the ledger):
     *   debit  — each money account (Cash, Mobile payment, Bank transfer, Cheque) at the end of the selection, and expenses by category;
     *   credit — the balance brought forward from before the selection, and income by kind (school fees, services, shop, other income).
     * Debits equal credits because each account = brought forward + money in − money out.
     * What students still owe on the selection's bills is given as a note (it is not cash yet).
     */
    public static function trialBalance(TermSelection $sel): array
    {
        $ranges = $sel->ranges();
        if (!$ranges) {
            return ['rows' => [], 'debit' => 0, 'credit' => 0, 'from' => null, 'to' => null, 'receivable' => 0];
        }
        $start = $ranges[0][0];
        $method = function ($m) { return $m ?: 'Cash'; };

        // Money per account before the selection (brought forward) and within it.
        $byMethod = function ($query, string $col, bool $date, $sum) use ($sel, $start) {
            $before = (clone $query)->where($col, '<', $date ? $start->toDateString() : $start)->groupBy('method')->select('method', DB::raw("sum($sum) as t"))->pluck('t', 'method');
            $within = $sel->apply(clone $query, $col, $date)->groupBy('method')->select('method', DB::raw("sum($sum) as t"))->pluck('t', 'method');
            return [$before, $within];
        };
        [$sfB, $sfW] = $byMethod(DB::table('receipts'), 'created_at', false, 'amt_paid');
        [$ofB, $ofW] = $byMethod(DB::table('optional_fee_receipts'), 'created_at', false, 'amt_paid');
        [$inB, $inW] = $byMethod(DB::table('finance_transactions')->where('type', 'income'), 'date', true, 'amount');
        [$exB, $exW] = $byMethod(DB::table('finance_transactions')->where('type', 'expense'), 'date', true, 'amount');

        $accounts = [];
        $add = function ($list, int $sign) use (&$accounts, $method) {
            foreach ($list as $m => $t) $accounts[$method($m)] = Money::r(($accounts[$method($m)] ?? 0) + $sign * $t);
        };
        $opening = [];
        foreach ([[$sfB, 1], [$ofB, 1], [$inB, 1], [$exB, -1]] as [$l, $s]) {
            foreach ($l as $m => $t) $opening[$method($m)] = Money::r(($opening[$method($m)] ?? 0) + $s * $t);
        }
        $accounts = $opening;
        foreach ([[$sfW, 1], [$ofW, 1], [$inW, 1], [$exW, -1]] as [$l, $s]) $add($l, $s);
        $order = array_flip(Fees::METHODS);
        uksort($accounts, function ($a, $b) use ($order) { return [($order[$a] ?? 99), $a] <=> [($order[$b] ?? 99), $b]; });

        $rows = [];
        $line = function (string $group, string $name, float $amount, string $side) use (&$rows) {
            if (abs($amount) < 0.005) return;
            // A negative balance belongs on the other side.
            if ($amount < 0) { $amount = -$amount; $side = $side === 'debit' ? 'credit' : 'debit'; }
            $rows[] = ['group' => $group, 'name' => $name, 'debit' => $side === 'debit' ? Money::r($amount) : 0.0, 'credit' => $side === 'credit' ? Money::r($amount) : 0.0];
        };

        foreach ($accounts as $m => $t) $line('Money accounts', $m, $t, 'debit');
        $expenses = $sel->apply(DB::table('finance_transactions')->where('type', 'expense'), 'date', true)->groupBy('category')
            ->select('category', DB::raw('sum(amount) as t'))->orderBy('category')->pluck('t', 'category');
        foreach ($expenses as $c => $t) $line('Expenses', $c, (float) $t, 'debit');

        $line('Brought forward', 'Balance brought forward', array_sum($opening), 'credit');
        $line('Income', 'School fees', (float) $sfW->sum(), 'credit');
        $groups = $sel->apply(DB::table('optional_fee_receipts as r')->join('optional_fee_charges as c', 'c.id', '=', 'r.charge_id'), 'r.created_at')
            ->groupBy('c.group')->select('c.group', DB::raw('sum(r.amt_paid) as t'))->pluck('t', 'group');
        foreach (OptionalFeeCharge::GROUPS as $g => $label) $line('Income', $label, (float) ($groups[$g] ?? 0), 'credit');
        $other = $sel->apply(DB::table('finance_transactions')->where('type', 'income'), 'date', true)->groupBy('category')
            ->select('category', DB::raw('sum(amount) as t'))->orderBy('category')->pluck('t', 'category');
        foreach ($other as $c => $t) $line('Other income', $c, (float) $t, 'credit');

        return [
            'rows' => $rows,
            'debit' => Money::r(array_sum(array_column($rows, 'debit'))),
            'credit' => Money::r(array_sum(array_column($rows, 'credit'))),
            'from' => $start->toDateString(),
            'to' => end($ranges)[1]->toDateString(),
            'receivable' => Money::r(self::invoiced($sel)['outstanding'] ?? 0),
        ];
    }

    /** Cash now: all money received minus all expenses, ever recorded. */
    public static function cashBalance(): float
    {
        return self::balanceAt(now()->endOfDay());
    }

    /**
     * Every movement of money in the selection, oldest first, with the balance after each one counted
     * from zero at the start of the selection: school-fee receipts, optional receipts, other income, expenses.
     */
    public static function ledger(TermSelection $sel): array
    {
        $school = $sel->apply(DB::table('receipts as r')
            ->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')
            ->join('payments as p', 'p.id', '=', 'pr.payment_id')
            ->join('users as u', 'u.id', '=', 'pr.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'pr.student_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id'), 'r.created_at')
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.balance', 'r.method', 'p.title', 'p.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return [
                    'key' => 's'.$r->id, 'at' => (string) $r->created_at, 'dir' => 'in', 'source' => 'School fees', 'category' => $r->title.' · '.$r->year,
                    'party' => $r->name, 'detail' => trim(implode(' · ', array_filter([$r->class_name, $r->method, 'balance left '.Money::fmt($r->balance)]))),
                    'ref' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => \App\Support\Money::r($r->amt_paid),
                    'url' => route('receipts.show', ['school', \App\Helpers\Qs::hash($r->id)]),
                    'party_url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id)),
                ];
            });

        $optional = $sel->apply(DB::table('optional_fee_receipts as r')
            ->join('optional_fee_charges as ch', 'ch.id', '=', 'r.charge_id')
            ->join('users as u', 'u.id', '=', 'ch.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'ch.student_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id'), 'r.created_at')
            ->select('r.id', 'r.created_at', 'r.amt_paid', 'r.balance', 'r.method', 'ch.group', 'ch.label', 'ch.year', 'u.id as student_id', 'u.name', 'c.name as class_name')->get()
            ->map(function ($r) {
                return [
                    'key' => 'o'.$r->id, 'at' => (string) $r->created_at, 'dir' => 'in',
                    'source' => OptionalFeeCharge::GROUPS[$r->group] ?? ucfirst($r->group), 'category' => $r->label.' · '.$r->year,
                    'party' => $r->name, 'detail' => trim(implode(' · ', array_filter([$r->class_name, $r->method, 'balance left '.Money::fmt($r->balance)]))),
                    'ref' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => \App\Support\Money::r($r->amt_paid),
                    'url' => route('receipts.show', ['optional', \App\Helpers\Qs::hash($r->id)]),
                    'party_url' => route('payments.invoice', \App\Helpers\Qs::hash($r->student_id)),
                ];
            });

        $tx = $sel->apply(DB::table('finance_transactions as t')->leftJoin('users as u', 'u.id', '=', 't.recorded_by'), 't.date', true)
            ->select('t.*', 'u.name as recorder')->get()
            ->map(function ($t) {
                return [
                    'key' => 't'.$t->id, 'at' => $t->date.' 12:00:00', 'dir' => $t->type === 'income' ? 'in' : 'out',
                    'source' => $t->type === 'income' ? 'Other income' : 'Expense', 'category' => $t->category,
                    'party' => $t->description ?: '—', 'detail' => trim(implode(' · ', array_filter([$t->method, $t->recorder ? 'recorded by '.$t->recorder : null]))),
                    'ref' => $t->reference ?: 'TX-'.str_pad($t->id, 5, '0', STR_PAD_LEFT), 'amount' => \App\Support\Money::r($t->amount),
                    'url' => route('finance.transactions.edit', \App\Helpers\Qs::hash($t->id)), 'party_url' => null,
                ];
            });

        $balance = 0;
        $rows = $school->concat($optional)->concat($tx)->sortBy(function ($r) { return $r['at'].'|'.$r['key']; })->values()
            ->map(function ($r) use (&$balance) {
                $balance += $r['dir'] === 'in' ? $r['amount'] : -$r['amount'];
                return $r + ['balance' => $balance];
            });

        return ['balance' => $balance, 'rows' => $rows->reverse()->values()];
    }

    /** Fees, other income and expenses per month across the selection (up to this month). */
    public static function monthly(TermSelection $sel): array
    {
        $ranges = $sel->ranges();
        if (!$ranges) return [];
        $from = $ranges[0][0]->copy()->startOfMonth();
        $to = end($ranges)[1]->copy()->min(now()->endOfMonth());
        $fmt = "DATE_FORMAT(%s, '%%Y-%%m')";
        $school = $sel->apply(DB::table('receipts'), 'created_at')->groupBy('ym')
            ->select(DB::raw(sprintf($fmt, 'created_at').' as ym'), DB::raw('sum(amt_paid) as t'))->pluck('t', 'ym');
        $optional = $sel->apply(DB::table('optional_fee_receipts'), 'created_at')->groupBy('ym')
            ->select(DB::raw(sprintf($fmt, 'created_at').' as ym'), DB::raw('sum(amt_paid) as t'))->pluck('t', 'ym');
        $tx = $sel->apply(DB::table('finance_transactions'), 'date', true)->groupBy('ym', 'type')
            ->select(DB::raw(sprintf($fmt, 'date').' as ym'), 'type', DB::raw('sum(amount) as t'))->get();

        $out = [];
        $multiYear = $from->year !== $to->year;
        for ($m = $from->copy(); $m->lte($to); $m->addMonth()) {
            $k = $m->format('Y-m');
            $inSel = false;
            foreach ($ranges as [$rf, $rt]) $inSel = $inSel || ($m->copy()->endOfMonth()->gte($rf) && $m->lte($rt));
            if (!$inSel) continue; // a month between two chosen terms that are not next to each other
            $fees = Money::r(($school[$k] ?? 0)) + Money::r(($optional[$k] ?? 0));
            $other = Money::r($tx->where('ym', $k)->where('type', 'income')->sum('t'));
            $exp = Money::r($tx->where('ym', $k)->where('type', 'expense')->sum('t'));
            $out[] = ['month' => $m->format($multiYear ? 'M y' : 'M'), 'label' => $m->format('M Y'), 'fees' => $fees, 'other' => $other, 'income' => $fees + $other, 'expenses' => $exp];
        }

        return $out;
    }

    public static function triple(float $expected, float $paid): array
    {
        return ['expected' => Money::r($expected), 'paid' => Money::r($paid), 'due' => Money::r(max($expected - $paid, 0))];
    }

    protected static function sumTriples($list): array
    {
        $list = collect($list);
        return ['expected' => \App\Support\Money::r($list->sum('expected')), 'paid' => \App\Support\Money::r($list->sum('paid')), 'due' => \App\Support\Money::r($list->sum('due'))];
    }
}
