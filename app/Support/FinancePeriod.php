<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The period the finance pages report on, from ?period=… or ?from=&to=.
 *   month | quarter | year (this calendar year) | all
 *   school:2025-2026  (1 August 2025 – 31 July 2026; fees for a new year are paid from August)
 *   cal:2025          (calendar year)
 */
class FinancePeriod
{
    public $key;
    public $from;
    public $to;
    public $label;
    public $session; // school year, for term and school-year periods
    public $term;    // 1–3 for term periods

    /** Term dates within a school year starting in $y: Aug–Dec, Jan–Apr, May–Jul. */
    public static function termRange(int $y, int $term): array
    {
        if ($term === 1) return [Carbon::create($y, 8, 1)->startOfDay(), Carbon::create($y, 12, 31)->endOfDay()];
        if ($term === 2) return [Carbon::create($y + 1, 1, 1)->startOfDay(), Carbon::create($y + 1, 4, 30)->endOfDay()];
        return [Carbon::create($y + 1, 5, 1)->startOfDay(), Carbon::create($y + 1, 7, 31)->endOfDay()];
    }

    /** [school-year start, term] for a date. */
    public static function termOf(Carbon $d): array
    {
        if ($d->month >= 8) return [$d->year, 1];
        return $d->month <= 4 ? [$d->year - 1, 2] : [$d->year - 1, 3];
    }

    public static function fromRequest(Request $req, string $default = 'year'): self
    {
        $p = new self();
        $today = now()->endOfDay();

        if ($req->filled('from') || $req->filled('to')) {
            try {
                $from = Carbon::parse($req->query('from', '2000-01-01'))->startOfDay();
                $to = Carbon::parse($req->query('to', $today->toDateString()))->endOfDay();
                if ($from->lte($to)) {
                    return $p->set('custom', $from, $to, $from->format('d/m/Y').' – '.$to->format('d/m/Y'));
                }
            } catch (\Throwable $e) {
                // fall through to the named period
            }
        }

        $key = (string) $req->query('period', $default);
        if ($key === 'term') {
            [$y, $t] = self::termOf(now());
            $key = 'term:'.$y.'-'.($y + 1).':'.$t;
        }
        if (preg_match('/^term:(\d{4})-(\d{4}):([123])$/', $key, $m) && (int) $m[2] === (int) $m[1] + 1) {
            [$from, $to] = self::termRange((int) $m[1], (int) $m[3]);
            $p->session = $m[1].'-'.$m[2];
            $p->term = (int) $m[3];
            return $p->set($key, $from, $to->min($today), 'Term '.$m[3].' · '.$m[1].' – '.$m[2]);
        }
        if (preg_match('/^school:(\d{4})-(\d{4})$/', $key, $m) && (int) $m[2] === (int) $m[1] + 1) {
            $from = Carbon::create((int) $m[1], 8, 1)->startOfDay();
            $to = Carbon::create((int) $m[2], 7, 31)->endOfDay();
            $p->session = $m[1].'-'.$m[2];
            return $p->set($key, $from, $to->min($today), $m[1].' – '.$m[2]);
        }
        if (preg_match('/^cal:(\d{4})$/', $key, $m)) {
            $from = Carbon::create((int) $m[1], 1, 1)->startOfDay();
            return $p->set($key, $from, $from->copy()->endOfYear()->min($today), $m[1]);
        }

        switch ($key) {
            case 'month':
                return $p->set('month', now()->startOfMonth(), $today, now()->format('F Y'));
            case 'quarter':
                return $p->set('quarter', now()->firstOfQuarter(), $today, 'Q'.now()->quarter.' '.now()->year);
            case 'all':
                return $p->set('all', self::firstActivity()->startOfDay(), $today, 'All time');
            default:
                return $p->set('year', now()->startOfYear(), $today, (string) now()->year);
        }
    }

    protected function set(string $key, Carbon $from, Carbon $to, string $label): self
    {
        $this->key = $key;
        $this->from = $from;
        $this->to = $to;
        $this->label = $label;

        return $this;
    }

    /** Whether the period runs up to today (so its closing balance is the current balance). */
    public function isCurrent(): bool
    {
        return $this->to->isSameDay(now());
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'current' => $this->isCurrent(),
            'session' => $this->session,
            'term' => $this->term,
        ];
    }

    /** Date of the first money recorded (receipt, income or expense). */
    public static function firstActivity(): Carbon
    {
        return collect([
            DB::table('receipts')->min('created_at'),
            DB::table('optional_fee_receipts')->min('created_at'),
            DB::table('finance_transactions')->min('date'),
        ])->filter()->map(function ($d) { return Carbon::parse($d); })->min() ?? now();
    }

    /** Choices for the period picker: quick periods, school years and calendar years that have data. */
    /** @param bool $withTerms  false for pages that only filter by years (the finance dashboard) */
    public static function options(bool $withTerms = true): array
    {
        $first = self::firstActivity();

        $schoolYears = [];
        $startYear = $first->month >= 8 ? $first->year : $first->year - 1;
        $currentStart = now()->month >= 8 ? now()->year : now()->year - 1;
        for ($y = $currentStart; $y >= $startYear; $y--) {
            $schoolYears[] = ['value' => 'school:'.$y.'-'.($y + 1), 'label' => $y.' – '.($y + 1)];
        }
        $calendar = [];
        for ($y = now()->year; $y >= $first->year; $y--) {
            $calendar[] = ['value' => 'cal:'.$y, 'label' => (string) $y];
        }

        // Terms, newest first, up to the current term.
        [$cy, $ct] = self::termOf(now());
        $terms = [];
        for ($y = $currentStart; $y >= $startYear; $y--) {
            for ($t = 3; $t >= 1; $t--) {
                if ($y === $cy && $t > $ct) continue;
                if (self::termRange($y, $t)[1]->lt($first)) continue; // before anything was recorded
                $terms[] = ['value' => 'term:'.$y.'-'.($y + 1).':'.$t, 'label' => 'Term '.$t.' · '.$y.' – '.($y + 1)];
            }
        }

        return array_values(array_filter([
            ['label' => 'Quick', 'options' => array_values(array_filter([
                $withTerms ? ['value' => 'term', 'label' => 'This term'] : null,
                ['value' => 'month', 'label' => 'This month'],
                ['value' => 'quarter', 'label' => 'This quarter'],
                ['value' => 'year', 'label' => 'This year ('.now()->year.')'],
                ['value' => 'all', 'label' => 'All time'],
            ]))],
            $withTerms ? ['label' => 'Terms', 'options' => $terms] : null,
            ['label' => 'School years', 'options' => $schoolYears],
            ['label' => 'Calendar years', 'options' => $calendar],
        ]));
    }
}
