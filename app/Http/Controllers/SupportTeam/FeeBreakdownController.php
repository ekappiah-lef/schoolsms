<?php

namespace App\Http\Controllers\SupportTeam;

use App\Http\Controllers\Controller;
use App\Support\FinanceSummary;
use App\Support\TermSelection;
use Illuminate\Http\Request;
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
        // One filter for the whole page: the terms ticked in the Academic Period (opens on the current year).
        $sel = TermSelection::fromRequest($req);
        $data = FinanceSummary::itemBreakdown($sel);
        $item = $req->query('item');
        if (!$data['items']->contains('name', $item)) {
            $item = optional($data['items']->first())['name'];
        }

        return Inertia::render('Finance/FeeBreakdown', [
            'selection' => $sel->toArray(),
            'item' => $item,
            'items' => $data['items'],
            'termly' => FinanceSummary::termly(),
            'rows' => collect($data['rows'])->where('item', $item)->sortBy('student')->values(),
            'urls' => ['self' => route('finance.fee_breakdown'), 'dashboard' => route('finance.dashboard'), 'ledger' => route('finance.ledger')],
        ]);
    }
}
