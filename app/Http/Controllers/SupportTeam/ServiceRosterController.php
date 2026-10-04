<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\BusRoute;
use App\Models\OptionalFeeCharge;
use App\Support\ClassOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Feeding / Bus / Extra-curricular / Books: every student on the service this
 * session, what they take, and what they have paid. Filtering happens in the page.
 */
class ServiceRosterController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function show(Request $req, $group)
    {
        $years = OptionalFeeCharge::where('group', $group)->distinct()->orderByDesc('year')->pluck('year');
        $current = Qs::getCurrentSession();
        $year = $years->contains($req->query('year')) ? $req->query('year') : $current;

        $charges = DB::table('optional_fee_charges as c')
            ->join('users as u', 'u.id', '=', 'c.student_id')
            ->leftJoin('student_records as sr', 'sr.user_id', '=', 'c.student_id')
            ->leftJoin('my_classes as mc', 'mc.id', '=', 'sr.my_class_id')
            ->leftJoin('sections as s', 's.id', '=', 'sr.section_id')
            ->leftJoin('bus_routes as br', 'br.id', '=', 'c.bus_route_id')
            ->where('c.group', $group)->where('c.year', $year)
            ->select('c.*', 'u.name', 'u.photo', 'sr.adm_no', 'sr.my_class_id', 'mc.name as class_name', 's.name as section_name', 'br.name as route_name')
            ->orderBy('u.name')->get();

        // Class during the chosen year (from that year's fee records), so past years read correctly.
        $classOf = \App\Support\FinanceSummary::classesInYear($year);
        $classNames = DB::table('my_classes')->pluck('name', 'id');
        $isCurrent = $year === $current;

        $rows = $charges->groupBy('student_id')->map(function ($g) use ($classOf, $classNames, $isCurrent) {
            $f = $g->first();
            $classId = $classOf[$f->student_id] ?? $f->my_class_id;
            $amount = (int) $g->sum('amount');
            $paid = (int) $g->sum('amt_paid');
            $bus = $g->firstWhere('bus_route_id', '!=', null);

            return [
                'id' => Qs::hash($f->student_id),
                'name' => $f->name,
                'photo' => $f->photo,
                'adm_no' => $f->adm_no,
                'class_id' => $classId,
                'class' => $isCurrent ? trim($f->class_name.' '.$f->section_name) : ($classNames[$classId] ?? ''),
                // What the student takes: option ids (feeding, activities, books) or route + direction (bus).
                'options' => $g->pluck($g->first()->group === 'sales' ? 'inventory_item_id' : 'fee_option_id')->filter()->map(function ($v) { return (int) $v; })->unique()->values(),
                'route_id' => $bus ? (int) $bus->bus_route_id : null,
                'route' => $bus ? $bus->route_name : null,
                'direction' => $bus ? $bus->bus_direction : null,
                'lines' => $g->map(function ($c) {
                    return ['label' => $c->label, 'amount' => (int) $c->amount, 'paid' => (int) $c->amt_paid, 'balance' => max((int) $c->amount - (int) $c->amt_paid, 0)];
                })->values(),
                'amount' => $amount,
                'paid' => $paid,
                'balance' => max($amount - $paid, 0),
                'invoice_url' => route('payments.invoice', Qs::hash($f->student_id)),
            ];
        })->values();

        // Filter choices: every option/route seen on charges plus the active catalogue.
        if ($group === 'sales') {
            $optionNames = collect();
            $catalogue = DB::table('inventory_items')->orderBy('category')->orderBy('name')->pluck('name', 'id');
        } else {
            $optionNames = $charges->whereNotNull('fee_option_id')->pluck('label', 'fee_option_id');
            $catalogue = DB::table('fee_options')->where('group', $group)->orderBy('sort')->pluck('name', 'id');
        }
        $options = $catalogue->union($optionNames)->map(function ($name, $id) { return ['value' => (int) $id, 'label' => $name]; })->values();

        $routes = BusRoute::orderBy('name')->get()->map(function ($r) { return ['value' => $r->id, 'label' => $r->name, 'both' => (int) $r->amount_both, 'one_way' => (int) $r->amount_one_way]; })->values();

        return Inertia::render('Finance/Services', [
            'group' => $group,
            'label' => OptionalFeeCharge::GROUPS[$group],
            'year' => $year,
            'years' => $years->push($current)->unique()->sort()->reverse()->values(),
            'rows' => $rows,
            'options' => $options,
            'routes' => $group === 'bus' ? $routes : [],
            'directions' => collect(BusRoute::DIRECTIONS)->map(function ($label, $value) { return compact('value', 'label'); })->values(),
            'classes' => ClassOrder::sort(DB::table('my_classes')->select('id', 'name', 'class_type_id')->get())->map(function ($c) { return ['value' => $c->id, 'label' => $c->name]; })->values(),
            'urls' => ['self' => route('finance.services', $group), 'config' => route('finance.config')],
        ]);
    }
}
