<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Support\FinanceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Fee breakdown: every fee item (Tuition, P.T.A, Medicals, Uniform …) for the
 * whole school, and which parent / child has paid each one.
 */
class FeeBreakdownController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index(Request $req)
    {
        $termly = FinanceSummary::termly();
        $keys = collect($termly)->pluck('key');

        // One filter for the whole page: the terms ticked in the Academic Period ("YYYY-YYYY:N,…").
        // Opens on every term of the current school year.
        if ($req->has('periods')) {
            $picked = $keys->intersect(array_filter(explode(',', (string) $req->query('periods'))))->values();
        } else {
            $current = Qs::getCurrentSession();
            $picked = $keys->filter(function ($k) use ($current) { return strpos($k, $current.':') === 0; })->values();
        }
        $periods = $picked->groupBy(function ($k) { return explode(':', $k)[0]; })
            ->map(function ($g) { return $g->map(function ($k) { return (int) explode(':', $k)[1]; })->sort()->values()->all(); })->all();

        $data = FinanceSummary::itemBreakdown($periods);
        $item = $req->query('item');
        if (!$data['items']->contains('name', $item)) {
            $item = optional($data['items']->first())['name'];
        }

        return Inertia::render('Finance/FeeBreakdown', [
            'picked' => $picked,
            'item' => $item,
            'items' => $data['items'],
            'termly' => $termly,
            'rows' => collect($data['rows'])->where('item', $item)->sortBy('student')->values(),
            'urls' => ['self' => route('finance.fee_breakdown'), 'dashboard' => route('finance.dashboard'), 'ledger' => route('finance.ledger')],
        ]);
    }
}
