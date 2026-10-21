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
use Illuminate\Support\Facades\DB;

/**
 * School fees (itemised, billed by class and new/continuing category) and
 * additional fees (feeding, bus, extra-curricular, books) for one student.
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

    /**
     * A graduate's discount moves to their youngest brother or sister still in school who has none
     * (schools with the discount_to_youngest_sibling switch). Returns the sibling who got it, if any.
     */
    public static function passDiscountToSibling(StudentRecord $graduate): ?StudentRecord
    {
        if (!$graduate->fee_discount_id || !$graduate->my_parent_id) return null;
        $sibling = StudentRecord::where('my_parent_id', $graduate->my_parent_id)->where('id', '!=', $graduate->id)
            ->where('grad', 0)->whereNull('fee_discount_id')->with('user')->get()
            ->sortByDesc(function ($s) { return [optional($s->user)->dob ?: '0000-00-00', $s->id]; })->first();
        if (!$sibling) return null;

        $sibling->update(['fee_discount_id' => $graduate->fee_discount_id]);
        DB::table('student_records')->where('id', $graduate->id)->update(['fee_discount_id' => null]);
        self::applyDiscount($sibling->fresh(), Qs::getCurrentSession());

        return $sibling;
    }

    /** Whether a fee should be billed to the student (used by the class billing action). */
    public static function appliesTo(Payment $p, StudentRecord $sr, string $year): bool
    {
        $category = $p->student_category ?: 'all';

        return $category === 'all' || $category === self::categoryFor($sr, $year);
    }

    /**
     * Whether a fee is due yet: a term's fees are billed only once that term has started (a later term's
     * amount may still change). Fees without a term are due for the whole year; past years are all due.
     */
    public static function isDue(Payment $p, string $year): bool
    {
        [$ty, $tt] = FinancePeriod::termOf(now());
        $now = $ty.'-'.($ty + 1);

        return $year < $now || ($year === $now && (!$p->term || (int) $p->term <= $tt));
    }

    /** The term running now in the given session (1 before it starts, 3 after it ends). */
    public static function termNow(?string $session = null): int
    {
        $session = $session ?: Qs::getCurrentSession();
        [$ty, $tt] = FinancePeriod::termOf(now());
        $now = $ty.'-'.($ty + 1);

        return $session === $now ? $tt : ($session > $now ? 1 : 3);
    }

    /** Term a service charge or shop sale belongs to. */
    public static function chargeTerm(OptionalFeeCharge $c): int
    {
        return $c->group === 'sales' ? FinanceSummary::saleTerm($c->created_at ?: now(), $c->year) : ((int) $c->term ?: 1);
    }

    /** Create the student's school-fee records for the session, for the terms that have started (idempotent). */
    public static function billSchoolFees(StudentRecord $sr, string $year): void
    {
        foreach (self::paymentsFor($sr, $year) as $p) {
            if (!self::isDue($p, $year)) continue;
            $rec = PaymentRecord::firstOrCreate(['student_id' => $sr->user_id, 'payment_id' => $p->id, 'year' => $year]);
            $rec->ref_no ?: $rec->update(['ref_no' => mt_rand(100000, 99999999)]);
        }
        self::applyDiscount($sr, $year);
    }

    /**
     * Bill every current student for the terms that have started (run nightly, so a new term is billed on
     * its first day): school fees, and once per new term the services each student had last term, at the
     * prices in Fee setup now (prices can change before a term reopens). Returns the number of new bills.
     */
    public static function billDueTerms(): int
    {
        $year = Qs::getCurrentSession();
        $term = self::termNow($year);
        $before = PaymentRecord::where('year', $year)->count() + OptionalFeeCharge::where('year', $year)->count();
        $students = StudentRecord::where('grad', 0)->get();
        $students->each(function (StudentRecord $sr) use ($year) {
            self::billSchoolFees($sr, $year);
        });

        // Services carry into a new term once (a student taken off a service this term stays off).
        $key = $year.':'.$term;
        $done = DB::table('settings')->where('type', 'services_billed_term')->value('description');
        if ($done !== $key) {
            if ($done) {
                [$py, $pt] = $term > 1 ? [$year, $term - 1] : [((int) substr($year, 0, 4) - 1).'-'.substr($year, 0, 4), 3];
                foreach ($students as $sr) {
                    $has = OptionalFeeCharge::where(['student_id' => $sr->user_id, 'year' => $year, 'term' => $term])->where('group', '!=', 'sales')->exists();
                    $last = OptionalFeeCharge::where(['student_id' => $sr->user_id, 'year' => $py, 'term' => $pt])->where('group', '!=', 'sales')->get();
                    if ($has || $last->isEmpty()) continue;
                    self::syncOptional($sr->user_id, $year, self::selectionOf($last));
                }
            }
            DB::table('settings')->updateOrInsert(['type' => 'services_billed_term'], ['description' => $key]);
        }

        return PaymentRecord::where('year', $year)->count() + OptionalFeeCharge::where('year', $year)->count() - $before;
    }

    /* ------------------------------------------------------------------
     | Tuition discounts
     * -----------------------------------------------------------------*/

    /** The part of a fee a tuition discount applies to: its "Tuition" items, or the whole fee if it is not itemised. */
    public static function tuitionBase(Payment $p): float
    {
        $p->loadMissing('items');
        if ($p->items->isEmpty()) {
            return Money::r($p->amount);
        }

        return Money::r($p->items->filter(function ($i) { return stripos($i->name, 'tuition') !== false; })->sum('amount'));
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
        $discount = Money::r(self::tuitionBase($pr->payment) * $percent / 100);
        $owed = max($pr->billAmount() - $discount, 0);
        $paid = Money::r($pr->amt_paid);
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
            $payment->items()->create(['name' => trim($i['name']), 'amount' => Money::r($i['amount']), 'sort' => $n]);
        }

        if ($clean->count()) {
            $payment->update(['amount' => Money::r($clean->sum('amount'))]);
        }
        self::refreshPaymentDiscounts($payment);
    }

    /* ------------------------------------------------------------------
     | Additional services
     * -----------------------------------------------------------------*/

    /**
     * Normalise the services picked on the admission/edit form.
     * Input: feeding[] / extracurricular[] / books[] (fee option ids), bus_route_id, bus_direction.
     * Returns the charge rows (group, label, amount, keys) they produce.
     */
    public static function chargesFromSelection(array $sel, ?int $studentId = null): array
    {
        $classType = $studentId ? (int) DB::table('student_records as sr')->join('my_classes as c', 'c.id', '=', 'sr.my_class_id')->where('sr.user_id', $studentId)->value('c.class_type_id') : null;
        $ids = collect(['feeding', 'extracurricular'])
            ->flatMap(function ($g) use ($sel) { return (array) ($sel[$g] ?? []); })
            ->filter()->map(function ($v) { return (int) $v; })->unique();

        $rows = FeeOption::whereIn('id', $ids)->where('active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(function (FeeOption $o) use ($classType) {
                return ['group' => $o->group, 'label' => $o->name, 'amount' => $o->priceFor($classType), 'fee_option_id' => $o->id, 'bus_route_id' => null, 'bus_direction' => null];
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
     * Share of the term's service price still to pay when a service starts today (schools with
     * service_prices_by_level and the school days of the term set): school days left ÷ school days in
     * the term, counting Monday–Friday up to Term ends. 1 when not set up or before the term starts.
     */
    public static function termShareLeft(): float
    {
        $days = (int) Qs::getSetting('term_days');
        $ends = Qs::getSetting('term_ends');
        $begins = Qs::getSetting('term_begins');
        if (!School::has('service_prices_by_level') || $days < 1 || !$ends) return 1.0;
        try { $end = \Carbon\Carbon::parse($ends)->startOfDay(); $start = $begins ? \Carbon\Carbon::parse($begins)->startOfDay() : null; } catch (\Throwable $e) { return 1.0; }
        $from = today()->max($start ?: today());
        if ($from->gt($end)) return 0.0;
        $left = 0;
        for ($d = $from->copy(); $d->lte($end); $d->addDay()) if ($d->isWeekday()) $left++;

        return min(1.0, $left / $days);
    }

    /**
     * Make the student's feeding, bus and extra-curricular charges for this term
     * match the selection (services are billed term by term at that term's price).
     * Unpaid charges that were deselected are removed; charges with payments are
     * kept (they must be reset first) and reported. Earlier terms are not touched.
     * Shop sales are never touched here: they are sold and returned under Sales.
     */
    public static function syncOptional(int $studentId, string $year, array $sel, bool $byDays = false): array
    {
        $term = self::termNow($year);
        $wanted = collect(self::chargesFromSelection($sel, $studentId));
        // Started mid-term (admission or a new service): only the school days left are charged.
        $share = $byDays ? self::termShareLeft() : 1.0;
        $existing = OptionalFeeCharge::where(['student_id' => $studentId, 'year' => $year, 'term' => $term])->where('group', '!=', 'sales')->get();
        $key = function ($r) { return ($r['fee_option_id'] ?? '').'|'.($r['bus_route_id'] ?? '').'|'.($r['bus_direction'] ?? ''); };

        $kept = [];
        foreach ($existing as $c) {
            $k = $key($c->toArray());
            if ($wanted->contains(function ($w) use ($key, $k) { return $key($w) === $k; })) {
                continue;
            }
            if (Money::r($c->amt_paid) > 0) {
                $kept[] = $c->label;
                continue;
            }
            $c->delete();
        }

        $have = $existing->map(function ($c) use ($key) { return $key($c->toArray()); });
        foreach ($wanted as $w) {
            if (!$have->contains($key($w))) {
                if ($share < 1) $w['amount'] = Money::r($w['amount'] * $share);
                OptionalFeeCharge::create($w + ['student_id' => $studentId, 'year' => $year, 'term' => $term, 'amt_paid' => 0]);
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
     * Sell an item to a student: adds it to their additional fees and takes it out of stock.
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
            'amount' => $handDown ? 0 : Money::r($item->price * $qty), 'amt_paid' => 0,
        ]);
        if (!$handDown) {
            $item->decrement('stock', $qty);
            StockMovement::create(['item_id' => $item->id, 'qty' => -$qty, 'type' => 'sale', 'charge_id' => $charge->id,
                'note' => 'Sold to '.optional(\App\User::find($studentId))->name, 'user_id' => auth()->id(), 'date' => $date ?: now()->toDateString()]);
        }

        return $charge;
    }

    /** The current selection (this term's services, or the latest term's before it is billed), in the shape the form uses. */
    public static function selectionFor(int $studentId, string $year): array
    {
        $all = OptionalFeeCharge::where('student_id', $studentId)->where('group', '!=', 'sales')
            ->where(function ($q) use ($year) {
                $q->where('year', '<', $year)->orWhere(function ($q) use ($year) { $q->where('year', $year)->where('term', '<=', self::termNow($year)); });
            })->get();
        $latest = $all->map(function ($c) { return $c->year.':'.(int) $c->term; })->max();

        return self::selectionOf($all->filter(function ($c) use ($latest) { return $c->year.':'.(int) $c->term === $latest; }));
    }

    /** A set of service charges as a form selection. */
    public static function selectionOf(Collection $charges): array
    {
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
                    return ['id' => $o->id, 'name' => $o->name, 'amount' => Money::r($o->amount)];
                })->values(),
            ];
        })->values();
        $groups->push([
            'key' => 'sales',
            'label' => 'Shop items',
            'options' => InventoryItem::where('active', true)->where('stock', '>', 0)->orderBy('category')->orderBy('name')->get()->map(function ($i) {
                return ['id' => $i->id, 'name' => $i->name, 'amount' => Money::r($i->price), 'stock' => (int) $i->stock];
            })->values(),
        ]);

        return [
            'groups' => $groups->values(),
            'routes' => BusRoute::where('active', true)->orderBy('name')->get()->map(function ($r) {
                return ['id' => $r->id, 'name' => $r->name, 'both' => Money::r($r->amount_both), 'one_way' => Money::r($r->amount_one_way)];
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
            $gross = $pr->billAmount();
            $discount = Money::r($pr->discount);
            $amount = max($gross - $discount, 0);
            $paid = Money::r($pr->amt_paid);

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
                    return ['name' => $i->name, 'amount' => Money::r($i->amount)];
                })->values(),
                'receipts' => $pr->receipt->sortByDesc('id')->map(function ($r) use ($withUrls) {
                    return ['id' => 's'.$r->id, 'number' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => Money::r($r->amt_paid), 'balance' => Money::r($r->balance), 'date' => optional($r->created_at)->toIso8601String(),
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
                'label' => $c->label.($c->group !== 'sales' && $c->term ? ' · Term '.$c->term : ''),
                'term' => self::chargeTerm($c),
                'year' => $c->year,
                'amount' => Money::r($c->amount),
                'paid' => Money::r($c->amt_paid),
                'balance' => $c->balance,
                'cleared' => $c->balance <= 0,
                'receipts' => $c->receipts->sortByDesc('id')->map(function ($r) use ($withUrls) {
                    return ['id' => 'o'.$r->id, 'number' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'amount' => Money::r($r->amt_paid), 'balance' => Money::r($r->balance), 'date' => optional($r->created_at)->toIso8601String(),
                        'urls' => $withUrls ? \App\Http\Controllers\SupportTeam\ReceiptController::urls('optional', $r->id) : null];
                })->values(),
                'urls' => $withUrls ? [
                    'pay' => route('optional_fees.pay', Qs::hash($c->id)),
                    'reset' => Qs::userIsTeamAdmin() ? route('optional_fees.reset', Qs::hash($c->id)) : null,
                ] : null,
            ];
        })->values();

        $sum = function (Collection $rows) {
            return ['amount' => Money::r($rows->sum('amount')), 'paid' => Money::r($rows->sum('paid')), 'balance' => Money::r($rows->sum('balance'))];
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
     * Account statement for one or more students over a period, like a bank statement:
     * opening balance, each bill (debit) and payment (credit) in date order with the running balance, closing balance.
     * Bills are school fees (less any discount) and additional fees / shop sales; payments are the receipts.
     */
    public static function accountStatement(array $studentIds, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $names = DB::table('users')->whereIn('id', $studentIds)->pluck('name', 'id');
        $lines = collect();
        foreach (PaymentRecord::whereIn('student_id', $studentIds)->with('payment')->get() as $pr) {
            if (!$pr->payment) continue;
            $lines->push(['at' => $pr->created_at, 'student' => $names[$pr->student_id] ?? '', 'ref' => $pr->ref_no,
                'text' => $pr->payment->title.' · '.str_replace('-', '–', $pr->year).($pr->payment->term ? ' · Term '.$pr->payment->term : ''),
                'debit' => max($pr->billAmount(), 0.0), 'credit' => max(-$pr->billAmount(), 0.0)]);
            if ($pr->discount > 0) {
                $lines->push(['at' => $pr->created_at, 'student' => $names[$pr->student_id] ?? '', 'ref' => $pr->ref_no,
                    'text' => 'Discount · '.$pr->payment->title, 'debit' => 0.0, 'credit' => Money::r($pr->discount)]);
            }
        }
        foreach (OptionalFeeCharge::whereIn('student_id', $studentIds)->where('amount', '>', 0)->get() as $c) {
            $lines->push(['at' => $c->created_at, 'student' => $names[$c->student_id] ?? '', 'ref' => null,
                'text' => $c->label.($c->group !== 'sales' && $c->term ? ' · Term '.$c->term : ''), 'debit' => Money::r($c->amount), 'credit' => 0.0]);
        }
        $receipts = DB::table('receipts as r')->join('payment_records as pr', 'pr.id', '=', 'r.pr_id')->whereIn('pr.student_id', $studentIds)
            ->get(['r.id', 'r.created_at', 'r.amt_paid', 'r.method', 'pr.student_id']);
        foreach ($receipts as $r) {
            $lines->push(['at' => \Carbon\Carbon::parse($r->created_at), 'student' => $names[$r->student_id] ?? '', 'ref' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT),
                'text' => 'Payment'.($r->method ? ' · '.$r->method : ''), 'debit' => 0.0, 'credit' => Money::r($r->amt_paid)]);
        }
        $receipts = DB::table('optional_fee_receipts as r')->join('optional_fee_charges as c', 'c.id', '=', 'r.charge_id')->whereIn('c.student_id', $studentIds)
            ->get(['r.id', 'r.created_at', 'r.amt_paid', 'r.method', 'c.student_id']);
        foreach ($receipts as $r) {
            $lines->push(['at' => \Carbon\Carbon::parse($r->created_at), 'student' => $names[$r->student_id] ?? '', 'ref' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT),
                'text' => 'Payment'.($r->method ? ' · '.$r->method : ''), 'debit' => 0.0, 'credit' => Money::r($r->amt_paid)]);
        }

        // Bills before payments on the same moment, so the balance never dips below what was owed.
        $lines = $lines->filter(function ($l) { return $l['at']; })
            ->sortBy(function ($l) { return $l['at']->format('Y-m-d H:i:s').($l['debit'] > 0 ? '0' : '1'); })->values();
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $opening = Money::r($lines->filter(function ($l) use ($start) { return $l['at']->lt($start); })->sum(function ($l) { return $l['debit'] - $l['credit']; }));
        $balance = $opening;
        $rows = $lines->filter(function ($l) use ($start, $end) { return $l['at']->between($start, $end); })->map(function ($l) use (&$balance) {
            $balance = Money::r($balance + $l['debit'] - $l['credit']);
            return ['date' => $l['at']->toDateString(), 'student' => $l['student'], 'ref' => $l['ref'], 'text' => $l['text'],
                'debit' => $l['debit'], 'credit' => $l['credit'], 'balance' => $balance];
        })->values();

        return ['opening' => $opening, 'rows' => $rows, 'billed' => Money::r($rows->sum('debit')), 'paid' => Money::r($rows->sum('credit')), 'closing' => $balance];
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
        // The current term is today's term when the current year is running (Aug–Dec, Jan–Apr, May–Jul);
        // otherwise the latest term billed this year. Fees without a term count as current.
        [$ty, $tt] = FinancePeriod::termOf(now());
        $term = $session === $ty.'-'.($ty + 1) ? $tt : $thisYear->map(function ($pr) { return (int) $pr->payment->term; })->filter()->max();
        $isCurrent = function ($pr) use ($session, $term) {
            return $pr->year === $session && (!$term || !$pr->payment->term || (int) $pr->payment->term === $term);
        };
        // Bills for later terms (already set up, not yet due) are left off this invoice.
        $isLater = function ($pr) use ($session, $term) {
            return $pr->year > $session || ($pr->year === $session && $term && (int) $pr->payment->term > $term);
        };
        $records = $records->reject($isLater);
        $line = function ($label, $amount, $paid) {
            // A negative amount is a credit (e.g. brought forward): it lowers what is due.
            if (Money::r($amount) < 0) return ['label' => 'Credit', 'amount' => 0.0, 'paid' => 0.0, 'balance' => Money::r($amount), 'credit' => Money::r(-$amount)];
            $amount = max(Money::r($amount), 0);
            return ['label' => $label, 'amount' => $amount, 'paid' => Money::r($paid), 'balance' => max($amount - Money::r($paid), 0)];
        };
        $termName = function ($pr) { return ($pr->payment->term ? 'Term '.$pr->payment->term.' · ' : '').str_replace('-', ' – ', $pr->year); };

        $current = $records->filter($isCurrent)->map(function ($pr) use ($line) {
            return $line($pr->payment->title, $pr->billAmount() - Money::r($pr->discount), $pr->amt_paid);
        })->values();
        // Services of this term and shop sales made this term are current; earlier ones unpaid are brought forward.
        $charges = OptionalFeeCharge::where('student_id', $studentId)->get();
        $chargeNow = function ($c) use ($session, $term) {
            return $c->year === $session && (!$term || self::chargeTerm($c) === $term);
        };
        $chargeLater = function ($c) use ($session, $term) {
            return $c->year > $session || ($c->year === $session && $term && self::chargeTerm($c) > $term);
        };
        $charges = $charges->reject($chargeLater);
        foreach ($charges->filter($chargeNow) as $c) {
            $current->push($line($c->label, $c->amount, $c->amt_paid));
        }

        $forward = $records->reject($isCurrent)
            ->sortBy(function ($pr) { return $pr->year.'-'.(int) $pr->payment->term; })
            ->map(function ($pr) use ($line, $termName) { return $line($pr->payment->title.' · '.$termName($pr), $pr->billAmount() - Money::r($pr->discount), $pr->amt_paid); })
            ->filter(function ($l) { return $l['balance'] != 0; })->values();
        foreach ($charges->reject($chargeNow)->sortBy(function ($c) { return $c->year.'-'.self::chargeTerm($c); }) as $c) {
            $group = OptionalFeeCharge::GROUPS[$c->group] ?? ucfirst($c->group);
            $name = $c->group === 'sales' ? $group.': '.$c->label : (strpos($c->label, $group) === 0 ? $c->label : $group.' ('.$c->label.')');
            $l = $line($name.' · Term '.self::chargeTerm($c).' · '.str_replace('-', ' – ', $c->year), $c->amount, $c->amt_paid);
            if ($l['balance'] > 0) $forward->push($l);
        }

        $sum = function ($rows, $k) { return Money::r(collect($rows)->sum($k)); };
        $currentDue = $sum($current, 'balance');
        $forwardDue = $sum($forward, 'balance');

        // The short table parents see: school fees for the term, one line per service, one line for
        // everything brought forward, and the total.
        $schoolNow = $records->filter($isCurrent)->filter(function ($pr) { return $pr->amount === null; });
        $table = [];
        if ($schoolNow->isNotEmpty()) {
            $amt = Money::r($schoolNow->sum(function ($pr) { return max($pr->billAmount() - Money::r($pr->discount), 0); }));
            $paid = Money::r($schoolNow->sum('amt_paid'));
            $table[] = ['label' => 'School fees'.($term ? ' · Term '.$term : ''), 'amount' => $amt, 'paid' => $paid, 'balance' => max($amt - $paid, 0)];
        }
        foreach ($records->filter($isCurrent)->filter(function ($pr) { return $pr->amount !== null; }) as $pr) {
            $l = $line($pr->payment->title, $pr->billAmount() - Money::r($pr->discount), $pr->amt_paid);
            if ($l['balance'] != 0) $table[] = $l;
        }
        foreach (OptionalFeeCharge::GROUPS as $g => $label) {
            $cs = $charges->filter($chargeNow)->where('group', $g);
            if ($cs->isEmpty()) continue;
            $amt = Money::r($cs->sum('amount'));
            $paid = Money::r($cs->sum('amt_paid'));
            $table[] = ['label' => $label, 'amount' => $amt, 'paid' => $paid, 'balance' => max($amt - $paid, 0)];
        }
        // Unpaid bills from earlier terms and years, one line each: the item, its term and year.
        foreach ($forward as $l) {
            $table[] = $l + ['forward' => true];
        }

        return [
            'table' => $table,
            'label' => ($term ? 'Term '.$term.' · ' : '').$session,
            'session' => $session,
            'term' => $term,
            'current' => ['lines' => $current, 'amount' => $sum($current, 'amount'), 'paid' => $sum($current, 'paid'), 'balance' => $currentDue],
            'forward' => ['lines' => $forward, 'total' => $forwardDue],
            // What is due never goes below 0; a credit left over is shown on its own.
            'total' => max(Money::r($currentDue + $forwardDue), 0.0),
            'credit' => max(Money::r(-($currentDue + $forwardDue)), 0.0),
        ];
    }

    /**
     * What a student will owe for the term after ($year, $term), for the report card, from Fee setup
     * and the student's own bills (not typed in by hand):
     *   school fees for the next term (their class and category, less their discount; what is left to
     *   pay if already billed), plus their additional services' share for that term (feeding, bus, clubs),
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
            $school = Money::r($billedNext->sum(function ($pr) { return max($pr->billAmount() - Money::r($pr->discount) - Money::r($pr->amt_paid), 0); }));
            $set = true;
        } else {
            $percent = $sr->fee_discount_id ? (int) optional(FeeDiscount::find($sr->fee_discount_id))->percent : 0;
            $fees = self::paymentsFor($sr, $nYear)->where('term', $nTerm);
            $school = Money::r($fees->sum(function ($p) use ($percent) { return Money::r($p->amount) - Money::r(self::tuitionBase($p) * $percent / 100); }));
            $set = $fees->isNotEmpty();
        }

        // Unpaid school fees up to this term.
        $owed = Money::r($records->filter(function ($pr) use ($upTo) { return $pr->year.':'.$pr->payment->term <= $upTo; })
            ->sum(function ($pr) { return max($pr->billAmount() - Money::r($pr->discount) - Money::r($pr->amt_paid), 0); }));

        // Services and sales: unpaid up to this term are owed; next term's services are billed when it
        // starts, so they are what the student takes now at the prices in Fee setup (or already billed).
        $services = 0;
        $billedNextServices = false;
        foreach (OptionalFeeCharge::where('student_id', $sr->user_id)->get() as $c) {
            $k = $c->year.':'.self::chargeTerm($c);
            $due = max(Money::r($c->amount) - Money::r($c->amt_paid), 0);
            if ($k <= $upTo) $owed += $due;
            elseif ($k === $nKey && $c->group !== 'sales') {
                $services += $due;
                $billedNextServices = true;
            }
        }
        if (!$billedNextServices) {
            $services = Money::r(collect(self::chargesFromSelection(self::selectionFor($sr->user_id, $year)))->sum('amount'));
        }

        return [
            'label' => ['1st', '2nd', '3rd'][$nTerm - 1].' Term '.str_replace('-', ' – ', $nYear),
            'set' => $set, 'school' => $school, 'services' => $services, 'owed' => $owed,
            'total' => $school + $services + $owed,
        ];
    }

    /**
     * Apply one payment to everything the student owes, oldest first: earlier years and terms,
     * then this term's school fees, then additional services. Creates a receipt for each bill paid
     * and returns [['kind' => 'school'|'optional', 'id' => receipt id, 'label', 'amount']].
     */
    public static function applyPayment(int $studentId, float $amount, string $method, ?string $reference = null): array
    {
        $left = Money::r($amount);
        $applied = [];

        $records = PaymentRecord::where('student_id', $studentId)->with('payment')->get()
            ->filter(function ($pr) { return $pr->payment; })
            ->sortBy(function ($pr) { return $pr->year.'-'.(int) $pr->payment->term.'-'.str_pad($pr->id, 8, '0', STR_PAD_LEFT); });
        $charges = OptionalFeeCharge::where('student_id', $studentId)->orderBy('year')->orderBy('id')->get();

        // Oldest first by year and term: school fees, then services of the same term.
        [$ty, $tt] = FinancePeriod::termOf(now());
        $nowYear = $ty.'-'.($ty + 1);
        $queue = [];
        foreach ($records as $pr) {
            $t = (int) $pr->payment->term;
            $queue[] = ['school', $pr->year, $pr->year === $nowYear && $t > $tt ? 10 + $t : $t, $pr];
        }
        foreach ($charges as $c) $queue[] = ['optional', $c->year, self::chargeTerm($c), $c];
        usort($queue, function ($a, $b) { return [$a[1], $a[2]] <=> [$b[1], $b[2]]; });

        foreach ($queue as [$kind, , , $bill]) {
            if ($left <= 0) break;
            if ($kind === 'school') {
                $owed = Money::r(max($bill->billAmount() - $bill->discount - $bill->amt_paid, 0));
                if ($owed <= 0) continue;
                $pay = min($owed, $left);
                $paid = Money::r($bill->amt_paid + $pay);
                $balance = Money::r($bill->billAmount() - $bill->discount - $paid);
                $bill->update(['amt_paid' => $paid, 'balance' => max($balance, 0), 'paid' => $balance <= 0 ? 1 : 0]);
                $r = \App\Models\Receipt::create(['pr_id' => $bill->id, 'amt_paid' => $pay, 'balance' => max($balance, 0), 'year' => $bill->year, 'method' => $method, 'reference' => $reference]);
                $applied[] = ['kind' => 'school', 'id' => $r->id, 'label' => $bill->payment->title.' ('.$bill->year.')', 'amount' => $pay];
            } else {
                $owed = Money::r(max($bill->amount - $bill->amt_paid, 0));
                if ($owed <= 0) continue;
                $pay = min($owed, $left);
                $bill->update(['amt_paid' => Money::r($bill->amt_paid + $pay)]);
                $r = \App\Models\OptionalFeeReceipt::create(['charge_id' => $bill->id, 'amt_paid' => $pay, 'balance' => $bill->fresh()->balance, 'year' => $bill->year, 'method' => $method, 'reference' => $reference]);
                $applied[] = ['kind' => 'optional', 'id' => $r->id, 'label' => $bill->label.' ('.$bill->year.')', 'amount' => $pay];
            }
            $left = Money::r($left - $pay);
        }

        if ($applied) {
            ClearEnroll::studentChanged($studentId, Money::r($amount - $left), $reference ?: $method);
        }

        return $applied;
    }
}
