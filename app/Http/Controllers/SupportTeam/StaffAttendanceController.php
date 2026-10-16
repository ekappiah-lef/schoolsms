<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Staff clock in / clock out for a day: teaching and non-teaching staff, from the clock-in device,
 * with corrections by hand (admins and the academic admin).
 */
class StaffAttendanceController extends Controller
{
    const STAFF = ['teacher', 'non_teaching', 'accountant', 'academic_admin', 'admin', 'librarian'];

    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function index(Request $req)
    {
        try { $date = $req->query('date') ? Carbon::parse($req->query('date'))->startOfDay() : today(); } catch (\Throwable $e) { $date = today(); }
        $date = $date->min(today());
        $types = DB::table('user_types')->pluck('name', 'title');
        $staff = DB::table('users as u')->leftJoin('staff_records as s', 's.user_id', '=', 'u.id')->whereIn('u.user_type', self::STAFF)
            ->orderBy('u.name')->get(['u.id', 'u.name', 'u.photo', 'u.user_type', 's.job_title', 's.clock_id']);
        $marks = StaffAttendance::whereDate('date', $date)->get()->keyBy('user_id');

        $rows = $staff->map(function ($u) use ($marks, $types) {
            $m = $marks[$u->id] ?? null;
            $hours = $m && $m->clock_in && $m->clock_out ? round((strtotime($m->clock_out) - strtotime($m->clock_in)) / 3600, 1) : null;
            return [
                'id' => Qs::hash($u->id), 'name' => $u->name, 'photo' => $u->photo,
                'category' => $u->user_type === 'teacher' ? 'teaching' : 'non_teaching',
                'role' => $u->job_title ?: ($types[$u->user_type] ?? $u->user_type), 'clock_id' => $u->clock_id,
                'in' => $m && $m->clock_in ? substr($m->clock_in, 0, 5) : null, 'out' => $m && $m->clock_out ? substr($m->clock_out, 0, 5) : null,
                'hours' => $hours, 'manual' => $m && $m->source === 'manual', 'note' => optional($m)->note,
            ];
        })->values();

        return Inertia::render('Staff/Attendance', [
            'date' => $date->toDateString(),
            'rows' => $rows,
            'summary' => ['staff' => $rows->count(), 'in' => $rows->whereNotNull('in')->count()],
            'urls' => ['self' => route('staff.attendance'), 'save' => route('staff.attendance.save')],
        ]);
    }

    /** Set or correct a staff member's times for a day by hand (e.g. a missed punch). */
    public function save(Request $req)
    {
        $d = $req->validate([
            'user_id' => 'required|string', 'date' => 'required|date|before_or_equal:today',
            'in' => 'nullable|date_format:H:i', 'out' => 'nullable|date_format:H:i|after:in', 'note' => 'nullable|string|max:191',
        ], [], ['in' => 'Clock in', 'out' => 'Clock out']);
        $userId = Qs::decodeHash($d['user_id']);
        abort_unless(DB::table('users')->where('id', $userId)->whereIn('user_type', self::STAFF)->exists(), 404);

        if (!$d['in'] && !$d['out']) {
            StaffAttendance::where('user_id', $userId)->whereDate('date', $d['date'])->delete();
            return Qs::json('Times cleared.');
        }
        StaffAttendance::updateOrCreate(['user_id' => $userId, 'date' => Carbon::parse($d['date'])->toDateString()], [
            'clock_in' => $d['in'] ? $d['in'].':00' : null, 'clock_out' => $d['out'] ? $d['out'].':00' : null,
            'source' => 'manual', 'note' => $d['note'] ?? null, 'recorded_by' => Auth::id(),
        ]);

        return Qs::json('Times saved.');
    }
}
