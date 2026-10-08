<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\BusRoute;
use App\Models\FeeDiscount;
use App\Models\FeeOption;
use App\Models\StudentRecord;
use App\Support\Fees;
use App\Models\OptionalFeeCharge;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Notices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Finance configuration: prices for optional services (feeding,
 * extra-curricular, books), bus routes, and what admission notices contain.
 * School-fee breakdowns live on each fee in Fee setup.
 */
class FinanceConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    public function index()
    {
        $year = Qs::getCurrentSession();

        // How many students currently use each option/route this session.
        $term = \App\Support\Fees::termNow($year);
        $optionUse = OptionalFeeCharge::where(['year' => $year, 'term' => $term])->whereNotNull('fee_option_id')
            ->groupBy('fee_option_id')->select('fee_option_id', DB::raw('count(*) as n'))->pluck('n', 'fee_option_id');
        $routeUse = OptionalFeeCharge::where(['year' => $year, 'term' => $term])->whereNotNull('bus_route_id')
            ->groupBy('bus_route_id')->select('bus_route_id', DB::raw('count(*) as n'))->pluck('n', 'bus_route_id');

        $policy = Qs::getSetting('admission_policy');

        return Inertia::render('Finance/Config', [
            'session' => $year,
            'groups' => collect(FeeOption::GROUPS)->map(function ($label, $key) { return ['key' => $key, 'label' => $label]; })->values(),
            'options' => FeeOption::orderBy('group')->orderBy('sort')->orderBy('id')->get()->map(function ($o) use ($optionUse) {
                return [
                    'id' => $o->id,
                    'group' => $o->group,
                    'name' => $o->name,
                    'amount' => (int) $o->amount,
                    'active' => $o->active,
                    'students' => (int) ($optionUse[$o->id] ?? 0),
                ];
            })->values(),
            'routes' => BusRoute::orderBy('name')->get()->map(function ($r) use ($routeUse) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'amount_both' => (int) $r->amount_both,
                    'amount_one_way' => (int) $r->amount_one_way,
                    'active' => $r->active,
                    'students' => (int) ($routeUse[$r->id] ?? 0),
                ];
            })->values(),
            'discounts' => FeeDiscount::orderByDesc('percent')->get()->map(function ($d) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'percent' => (int) $d->percent,
                    'active' => $d->active,
                    'students' => StudentRecord::where('grad', 0)->where('fee_discount_id', $d->id)->count(),
                ];
            })->values(),
            'schoolFees' => Payment::where('year', $year)->withCount('items')->with('my_class')->orderBy('my_class_id')->get()->map(function ($p) {
                return [
                    'title' => $p->title,
                    'class' => optional($p->my_class)->name ?: 'All classes',
                    'category' => Payment::CATEGORIES[$p->student_category ?: 'all'],
                    'amount' => (int) $p->amount,
                    'items' => (int) $p->items_count,
                    'edit_url' => route('payments.edit', $p->id),
                ];
            })->values(),
            'notices' => [
                'payment_instructions' => (string) Qs::getSetting('payment_instructions'),
                'policy_name' => $policy ? basename($policy) : null,
                'policy_url' => $policy ? asset('storage/'.$policy) : null,
                'mail_ready' => Notices::mailConfigured(),
                'sms_driver' => config('sms.driver'),
                'sms_ready' => Notices::smsConfigured(),
            ],
            'urls' => [
                'options' => route('finance.options.store'),
                'option' => route('finance.options.update', ':id'),
                'routes' => route('finance.routes.store'),
                'route' => route('finance.routes.update', ':id'),
                'notices' => route('finance.notices'),
                'discounts' => route('finance.discounts.store'),
                'discount' => route('finance.discounts.update', ':id'),
                'fee_setup' => route('payments.index'),
                'fee_create' => route('payments.create'),
            ],
        ]);
    }

    public function storeOption(Request $req)
    {
        $d = $this->validateOption($req);
        $d['sort'] = (int) FeeOption::where('group', $d['group'])->max('sort') + 1;
        FeeOption::create($d);

        return Qs::jsonStoreOk();
    }

    public function updateOption(Request $req, $fee_option)
    {
        FeeOption::findOrFail($fee_option)->update($this->validateOption($req));

        return Qs::jsonUpdateOk();
    }

    public function destroyOption($fee_option)
    {
        $o = FeeOption::findOrFail($fee_option);
        if (OptionalFeeCharge::where('fee_option_id', $o->id)->exists()) {
            // Keep history intact: an option students were billed for is switched off instead.
            $o->update(['active' => false]);
            return back()->with('flash_success', $o->name.' is used on student invoices, so it was deactivated instead of deleted.');
        }
        $o->delete();

        return back()->with('flash_success', __('msg.del_ok'));
    }

    public function storeRoute(Request $req)
    {
        BusRoute::create($this->validateRoute($req));

        return Qs::jsonStoreOk();
    }

    public function updateRoute(Request $req, $bus_route)
    {
        BusRoute::findOrFail($bus_route)->update($this->validateRoute($req, $bus_route));

        return Qs::jsonUpdateOk();
    }

    public function destroyRoute($bus_route)
    {
        $r = BusRoute::findOrFail($bus_route);
        if (OptionalFeeCharge::where('bus_route_id', $r->id)->exists()) {
            $r->update(['active' => false]);
            return back()->with('flash_success', $r->name.' is used on student invoices, so it was deactivated instead of deleted.');
        }
        $r->delete();

        return back()->with('flash_success', __('msg.del_ok'));
    }

    public function storeDiscount(Request $req)
    {
        FeeDiscount::create($this->validateDiscount($req));

        return Qs::jsonStoreOk();
    }

    public function updateDiscount(Request $req, $fee_discount)
    {
        $d = FeeDiscount::findOrFail($fee_discount);
        $old = (int) $d->percent;
        $d->update($this->validateDiscount($req, $d->id));

        // A new percentage changes what students on this discount owe this session.
        if ($old !== (int) $d->percent) {
            StudentRecord::where('grad', 0)->where('fee_discount_id', $d->id)->get()->each(function ($sr) {
                Fees::applyDiscount($sr);
            });
        }

        return Qs::jsonUpdateOk();
    }

    public function destroyDiscount($fee_discount)
    {
        $d = FeeDiscount::findOrFail($fee_discount);
        if (StudentRecord::where('fee_discount_id', $d->id)->exists()) {
            $d->update(['active' => false]);
            return back()->with('flash_success', $d->name.' is assigned to students, so it was deactivated instead of deleted. Their discount stays until you change it on their record.');
        }
        $d->delete();

        return back()->with('flash_success', __('msg.del_ok'));
    }

    protected function validateDiscount(Request $req, $id = null): array
    {
        $d = $req->validate([
            'name' => 'required|string|max:100|unique:fee_discounts,name'.($id ? ','.$id : ''),
            'percent' => 'required|integer|min:1|max:100',
        ], [], ['percent' => 'Percentage']);
        $d['active'] = $req->boolean('active', true);

        return $d;
    }

    public function saveNotices(Request $req)
    {
        $req->validate([
            'payment_instructions' => 'nullable|string|max:2000',
            'policy' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'remove_policy' => 'nullable|boolean',
        ], [], ['policy' => 'School policy document']);

        Setting::updateOrCreate(['type' => 'payment_instructions'], ['description' => (string) $req->payment_instructions]);

        $current = Qs::getSetting('admission_policy');
        if ($req->hasFile('policy') || $req->boolean('remove_policy')) {
            if ($current) {
                Storage::disk('public')->delete($current);
            }
            $path = null;
            if ($req->hasFile('policy')) {
                $file = $req->file('policy');
                $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName());
                $path = $file->storeAs('uploads/policies', $name, 'public');
            }
            Setting::updateOrCreate(['type' => 'admission_policy'], ['description' => (string) $path]);
        }

        return Qs::jsonUpdateOk();
    }

    protected function validateOption(Request $req): array
    {
        $d = $req->validate([
            'group' => 'required|in:'.implode(',', array_keys(FeeOption::GROUPS)),
            'name' => 'required|string|max:100',
            'amount' => 'required|integer|min:0',
            'active' => 'nullable|boolean',
        ], [], ['group' => 'Service type', 'amount' => 'Price']);
        $d['active'] = $req->boolean('active', true);

        return $d;
    }

    protected function validateRoute(Request $req, $id = null): array
    {
        $d = $req->validate([
            'name' => 'required|string|max:100|unique:bus_routes,name'.($id ? ','.$id : ''),
            'amount_both' => 'required|integer|min:0',
            'amount_one_way' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ], [], ['name' => 'Location', 'amount_both' => 'Both-ways price', 'amount_one_way' => 'One-way price']);

        // One way (in or out) defaults to half the both-ways price.
        $d['amount_one_way'] = $req->filled('amount_one_way') ? (int) $req->amount_one_way : (int) round($d['amount_both'] / 2);
        $d['active'] = $req->boolean('active', true);

        return $d;
    }
}
