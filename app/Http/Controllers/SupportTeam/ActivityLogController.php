<?php

namespace App\Http\Controllers\SupportTeam;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Who did what and when (super admin only). */
class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('super_admin');
    }

    public function index(Request $req)
    {
        $from = $req->query('from') ?: now()->subDays(6)->toDateString();
        $to = $req->query('to') ?: now()->toDateString();
        $q = DB::table('activity_logs')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($req->query('user'), function ($q, $u) { $q->where('user_id', (int) $u); })
            ->when($req->query('search'), function ($q, $s) { $q->where(function ($w) use ($s) { $w->where('action', 'like', "%$s%")->orWhere('details', 'like', "%$s%")->orWhere('user_name', 'like', "%$s%"); }); })
            ->orderByDesc('id');
        $page = $q->paginate(50)->withQueryString();

        return Inertia::render('ActivityLog/Index', [
            'filters' => ['from' => $from, 'to' => $to, 'user' => $req->query('user'), 'search' => $req->query('search')],
            'users' => DB::table('activity_logs')->whereNotNull('user_id')->groupBy('user_id', 'user_name')->orderBy('user_name')->get(['user_id', 'user_name'])
                ->map(function ($u) { return ['value' => (string) $u->user_id, 'label' => $u->user_name]; })->values(),
            'rows' => collect($page->items())->map(function ($r) {
                return ['id' => $r->id, 'at' => $r->created_at, 'user' => $r->user_name, 'role' => $r->role ? ucwords(str_replace('_', ' ', $r->role)) : null,
                    'action' => $r->action, 'details' => $r->details, 'status' => $r->status, 'ip' => $r->ip];
            }),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem(), 'per_page' => 50],
            'urls' => ['self' => route('activity_log')],
        ]);
    }
}
