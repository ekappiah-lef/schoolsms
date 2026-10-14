<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Delivery report: every SMS, email and WhatsApp sent to a person, whether it went, was held (demo mode)
 * or failed, and why. Admins and the academic admin see everything; a class teacher sees what they sent.
 */
class DeliveryReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSAT');
        $this->middleware(function ($request, $next) {
            if (Qs::userIsTeacher() && !\App\Support\TeacherScope::ownSectionIds()) {
                return redirect()->route('dashboard')->with('pop_error', 'Only class teachers can see the delivery report.');
            }
            return $next($request);
        });
    }

    public function index(Request $req)
    {
        $parse = function ($v, $default) {
            try { return $v ? Carbon::parse($v)->startOfDay() : $default; } catch (\Throwable $e) { return $default; }
        };
        $to = $parse($req->query('to'), today())->min(today());
        $from = $parse($req->query('from'), $to->copy()->subDays(6))->min($to);
        $channel = in_array($req->query('channel'), ['sms', 'email', 'whatsapp'], true) ? $req->query('channel') : null;
        $status = in_array($req->query('status'), ['sent', 'held', 'failed'], true) ? $req->query('status') : null;

        $q = NotificationLog::query()->from('notification_logs as l')
            ->leftJoin('messages as m', 'm.id', '=', 'l.message_id')
            ->leftJoin('users as s', 's.id', '=', 'l.student_id')
            ->leftJoin('users as by', 'by.id', '=', DB::raw('coalesce(m.sent_by, l.sent_by)'))
            ->whereBetween('l.created_at', [$from, $to->copy()->endOfDay()])
            ->when(Qs::userIsTeacher(), function ($q) {
                $me = Auth::id();
                $q->where(function ($w) use ($me) { $w->where('m.sent_by', $me)->orWhere(function ($x) use ($me) { $x->whereNull('l.message_id')->where('l.sent_by', $me); }); });
            })
            ->when($channel, function ($q) use ($channel) { $q->where('l.channel', $channel); });

        $rows = (clone $q)->orderByDesc('l.id')->limit(1000)
            ->get(['l.id', 'l.created_at', 'l.channel', 'l.kind', 'l.recipient', 'l.status', 'l.error', 's.name as student', 'by.name as sender', 'm.subject', 'm.body', 'm.audience_label'])
            ->map(function ($r) {
                $held = $r->error && strpos($r->error, 'Demo mode') === 0;
                $what = $r->kind === 'message'
                    ? ($r->subject ?: \Illuminate\Support\Str::limit((string) $r->body, 60)).($r->audience_label ? ' · '.$r->audience_label : '')
                    : (NotificationLog::KINDS[$r->kind] ?? ucfirst(str_replace('_', ' ', $r->kind))).($r->student ? ' · '.$r->student : '');
                return [
                    'id' => $r->id, 'at' => Carbon::parse($r->created_at)->toIso8601String(), 'channel' => $r->channel, 'to' => $r->recipient,
                    'what' => $what, 'status' => $r->status === 'sent' ? 'sent' : ($held ? 'held' : 'failed'),
                    'reason' => $r->status === 'sent' || $held ? null : $r->error, 'by' => $r->sender,
                ];
            });
        $counts = ['sent' => $rows->where('status', 'sent')->count(), 'held' => $rows->where('status', 'held')->count(), 'failed' => $rows->where('status', 'failed')->count()];
        if ($status) {
            $rows = $rows->where('status', $status)->values();
        }

        return Inertia::render('Messages/Delivery', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'channel' => $channel, 'status' => $status],
            'counts' => $counts,
            'rows' => $rows->values(),
            'urls' => ['self' => route('messages.delivery')],
        ]);
    }
}
