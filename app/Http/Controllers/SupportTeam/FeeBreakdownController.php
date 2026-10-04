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
        $years = DB::table('payment_records')->distinct()->orderByDesc('year')->pluck('year');
        $year = $years->contains($req->query('year')) ? $req->query('year') : Qs::getCurrentSession();
        $term = in_array((int) $req->query('term'), [1, 2, 3], true) ? (int) $req->query('term') : null;

        $data = FinanceSummary::itemBreakdown($year, $term);
        $item = $req->query('item');
        if (!$data['items']->contains('name', $item)) {
            $item = optional($data['items']->first())['name'];
        }

        return Inertia::render('Finance/FeeBreakdown', [
            'session' => $year,
            'years' => $years->values(),
            'term' => $term,
            'item' => $item,
            'items' => $data['items'],
            'termly' => FinanceSummary::termly(),
            'rows' => collect($data['rows'])->where('item', $item)->sortBy('student')->values(),
            'urls' => ['self' => route('finance.fee_breakdown'), 'dashboard' => route('finance.dashboard'), 'ledger' => route('finance.ledger')],
        ]);
    }
}
