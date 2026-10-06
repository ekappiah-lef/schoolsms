<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The terms the finance pages report on, from ?periods=2025-2026:3,2026-2027:1 (the Academic Period
 * picker: school years that open into 1st / 2nd / 3rd term). Opens on every term of the current year.
 *
 * Terms cover the whole calendar with no gaps (Aug–Dec, Jan–Apr, May–Jul), so every bill, payment,
 * income and expense belongs to exactly one term. Each term starts from zero: its balance is the money
 * received during the term (including old fees paid then) less what was spent during it.
 */
class TermSelection
{
    /** @var string[] chosen term keys "YYYY-YYYY:N", oldest first */
    public $keys = [];

    /** Every term from the first money recorded to the end of the current school year, newest first. */
    public static function allKeys(): array
    {
        $first = FinancePeriod::firstActivity();
        [$startY] = FinancePeriod::termOf($first);
        [$cy] = FinancePeriod::termOf(now());
        $keys = [];
        for ($y = $cy; $y >= $startY; $y--) {
            for ($t = 3; $t >= 1; $t--) {
                if (FinancePeriod::termRange($y, $t)[1]->lt($first)) continue; // before anything was recorded
                $keys[] = $y.'-'.($y + 1).':'.$t;
            }
        }

        return $keys;
    }

    public static function fromRequest(Request $req): self
    {
        $all = self::allKeys();
        $s = new self();
        if ($req->has('periods')) {
            $picked = array_values(array_intersect($all, explode(',', (string) $req->query('periods'))));
        } else {
            $current = \App\Helpers\Qs::getCurrentSession();
            $picked = array_values(array_filter($all, function ($k) use ($current) { return strpos($k, $current.':') === 0; }));
        }
        sort($picked);
        $s->keys = $picked;

        return $s;
    }

    public static function of(array $keys): self
    {
        $s = new self();
        sort($keys);
        $s->keys = array_values($keys);

        return $s;
    }

    /** All three terms of one school year. */
    public static function year(string $session): self
    {
        return self::of([$session.':1', $session.':2', $session.':3']);
    }

    /** {session: [terms]} */
    public function periods(): array
    {
        $out = [];
        foreach ($this->keys as $k) {
            [$s, $t] = explode(':', $k);
            $out[$s][] = (int) $t;
        }

        return $out;
    }

    /** Date ranges of the chosen terms, terms that follow one another merged: [[from, to], …]. */
    public function ranges(): array
    {
        $out = [];
        foreach ($this->keys as $k) {
            [$s, $t] = explode(':', $k);
            [$from, $to] = FinancePeriod::termRange((int) substr($s, 0, 4), (int) $t);
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last][1]->copy()->addSecond()->gte($from)) {
                $out[$last][1] = $to;
            } else {
                $out[] = [$from, $to];
            }
        }

        return $out;
    }

    /** Limit a query to the chosen terms. $date: the column holds a date (not a timestamp). */
    public function apply($query, string $column, bool $date = false)
    {
        $ranges = $this->ranges();

        return $query->where(function ($q) use ($ranges, $column, $date) {
            if (!$ranges) $q->whereRaw('1 = 0');
            foreach ($ranges as [$from, $to]) {
                $q->orWhereBetween($column, $date ? [$from->toDateString(), $to->toDateString()] : [$from, $to]);
            }
        });
    }

    /** Whether a moment ("Y-m-d H:i:s") falls in the chosen terms. */
    public function contains(string $at): bool
    {
        foreach ($this->ranges() as [$from, $to]) {
            if ($at >= $from->format('Y-m-d H:i:s') && $at <= $to->format('Y-m-d H:i:s')) return true;
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return !$this->keys;
    }

    /** "1st Term 2025 – 2026", "2026 – 2027 Academic Year", "4 terms in 2 years". */
    public function label(): string
    {
        $p = $this->periods();
        if (!$p) return 'No period chosen';
        $name = function ($t) { return ['1st', '2nd', '3rd'][$t - 1].' Term'; };
        $year = function ($s) { return str_replace('-', ' – ', $s); };
        $full = function ($s, $terms) { return count($terms) === count(array_filter(self::allKeys(), function ($k) use ($s) { return strpos($k, $s.':') === 0; })); };
        if (count($p) === 1) {
            $s = array_key_first($p);
            if ($full($s, $p[$s])) return $year($s).' Academic Year';
            if (count($p[$s]) === 1) return $name($p[$s][0]).' '.$year($s);
            return count($p[$s]).' terms in '.$year($s);
        }
        $allFull = true;
        foreach ($p as $s => $terms) $allFull = $allFull && $full($s, $terms);

        return $allFull ? count($p).' academic years' : count($this->keys).' terms in '.count($p).' years';
    }

    /** What the balance of the selection is called: term balance, year balance, or balance. */
    public function balanceName(): string
    {
        $p = $this->periods();
        if (count($this->keys) === 1) return 'Term balance';
        if (count($p) === 1 && strpos($this->label(), 'Academic Year') !== false) return 'Year balance';

        return 'Balance';
    }

    /** The school year the selection belongs to when it is within one year, else the newest one. */
    public function session(): ?string
    {
        $p = $this->periods();

        return $p ? array_key_last($p) : null;
    }

    /** For the page: the chosen keys, label and the picker tree (years newest first with their terms). */
    public function toArray(): array
    {
        $tree = [];
        foreach (self::allKeys() as $k) {
            [$s, $t] = explode(':', $k);
            $tree[$s][] = (int) $t;
        }
        $ranges = $this->ranges();

        return [
            'keys' => $this->keys,
            'label' => $this->label(),
            'balanceName' => $this->balanceName(),
            'single' => count($this->keys) === 1,
            'from' => $ranges ? $ranges[0][0]->toDateString() : null,
            'to' => $ranges ? end($ranges)[1]->toDateString() : null,
            'tree' => collect($tree)->map(function ($terms, $s) { sort($terms); return ['session' => $s, 'terms' => $terms]; })->values(),
        ];
    }
}
