<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Support\ClearEnroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * ClearEnroll inside the school system (through ClearEnroll's API, no embedded website):
 * verify a student or teacher, and keep ClearEnroll's fee flags in step with what parents owe here.
 */
class ClearEnrollController extends Controller
{
    const STATUSES = ['cleared' => 'Cleared', 'not_found' => 'Not found', 'flagged' => 'Flagged', 'not_checked' => 'Not checked'];

    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function student()
    {
        return Inertia::render('ClearEnroll/Verify', ['kind' => 'student', 'connected' => ClearEnroll::enabled(), 'urls' => ['search' => route('clearenroll.search')]]);
    }

    public function teacher()
    {
        return Inertia::render('ClearEnroll/Verify', ['kind' => 'teacher', 'connected' => ClearEnroll::enabled(), 'urls' => ['search' => route('clearenroll.search')]]);
    }

    /** Search ClearEnroll (used by the verify pages and the admission form). */
    public function search(Request $req)
    {
        $d = $req->validate(['kind' => 'required|in:student,teacher', 'query' => 'required|string|min:2|max:100']);
        $r = ClearEnroll::verify($d['kind'], trim($d['query']));
        if (!empty($r['error'])) {
            return response()->json(['message' => $r['error']], 422);
        }

        // Only what the school needs to see (ClearEnroll masks other schools' contact details), with photos.
        if ($d['kind'] === 'teacher') {
            $r['teachers'] = collect($r['teachers'] ?? [])->map(function ($t) {
                $t = (array) $t;
                return array_intersect_key($t, array_flip(['id', 'first_name', 'last_name', 'other_names', 'date_of_birth', 'gender', 'qualification', 'status', 'reason', 'school']))
                    + ['photo' => ClearEnroll::photoUrl($t['teacher_photo'] ?? null, 'teachers')];
            })->values();
        } else {
            foreach (['students', 'flags'] as $k) {
                $r[$k] = collect($r[$k] ?? [])->unique('id')->map(function ($s) {
                    $s = (array) $s;
                    return $s + ['photo' => ClearEnroll::photoUrl($s['student_photo'] ?? null, 'students')];
                })->values();
            }
        }

        return response()->json($r);
    }

    public function sync()
    {
        $debtors = collect(ClearEnroll::debtors());

        return Inertia::render('ClearEnroll/Sync', [
            'connection' => ClearEnroll::ping(),
            'debtors' => $debtors->map(function ($p) {
                return ['id' => $p['external_id'], 'name' => $p['_name'], 'class' => $p['class_name'], 'balance' => $p['balance'],
                    'consented' => $p['_consented'], 'problem' => ClearEnroll::problem($p),
                    'url' => route('payments.invoice', Qs::hash((int) $p['external_id']))];
            })->values(),
            'history' => DB::table('clearenroll_syncs as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->leftJoin('users as st', 'st.id', '=', 's.student_id')
                ->orderByDesc('s.id')->limit(50)->get(['s.*', 'u.name as by_name', 'st.name as student_name'])
                ->map(function ($r) {
                    return ['id' => $r->id, 'kind' => $r->kind, 'term' => $r->term, 'student' => $r->student_name, 'sent' => $r->sent, 'flagged' => $r->flagged,
                        'updated' => $r->updated, 'cleared' => $r->cleared, 'rejected' => $r->rejected ? count(json_decode($r->rejected, true)) : 0,
                        'error' => $r->error, 'by' => $r->by_name ?: 'Automatic', 'at' => $r->created_at];
                })->values(),
            'canSync' => ClearEnroll::enabled(),
            'urls' => ['run' => route('clearenroll.sync.run')],
        ]);
    }

    public function runSync()
    {
        $r = ClearEnroll::syncAll();
        if (!empty($r['error'])) {
            return Qs::json('ClearEnroll sync failed: '.$r['error'], false);
        }

        return Qs::json("Sent {$r['sent']} students to ClearEnroll: {$r['flagged']} flagged, {$r['updated']} updated, {$r['cleared']} cleared".($r['rejected'] ? ', some could not be taken (see below).' : '.'));
    }
}
