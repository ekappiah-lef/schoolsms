<?php

namespace App\Http\Controllers;

use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Clock-in devices send staff punches here (no login):
 *
 *  1. Fingerprint / face devices with "push" (ZKTeco ADMS / iclock): set the device's server address to this site.
 *     The device's serial number must be listed in STAFF_CLOCK_DEVICES (comma separated).
 *       GET  /iclock/cdata?SN=…            → the device asks for its settings
 *       POST /iclock/cdata?SN=…&table=ATTLOG → lines "ClockID<TAB>2026-10-16 07:42:10<TAB>…"
 *       GET  /iclock/getrequest?SN=…       → "no commands"
 *
 *  2. Any other device or app: POST /api/staff-clock with header "Authorization: Bearer <STAFF_CLOCK_TOKEN>"
 *     and JSON {"clock_id": "105", "time": "2026-10-16T07:42:10"} (time optional: now).
 */
class StaffClockController extends Controller
{
    public function admsHandshake(Request $req)
    {
        $sn = $this->device($req);
        if (!$sn) return response('Unknown device', 403);

        return response(implode("\n", [
            "GET OPTION FROM: {$sn}", 'ATTLOGStamp=None', 'OPERLOGStamp=9999', 'ATTPHOTOStamp=None', 'ErrorDelay=30', 'Delay=10',
            'TransTimes=00:00;14:05', 'TransInterval=1', 'TransFlag=TransData AttLog', 'Realtime=1', 'Encrypt=None',
        ]), 200)->header('Content-Type', 'text/plain');
    }

    public function admsData(Request $req)
    {
        $sn = $this->device($req);
        if (!$sn) return response('Unknown device', 403);
        if (strtoupper((string) $req->query('table')) !== 'ATTLOG') {
            return response('OK', 200)->header('Content-Type', 'text/plain');   // user lists, photos, logs: not needed
        }
        $n = 0;
        foreach (preg_split('/\r\n|\n|\r/', (string) $req->getContent()) as $line) {
            $f = explode("\t", trim($line));
            if (count($f) < 2 || $f[0] === '') continue;
            try { $at = Carbon::parse($f[1]); } catch (\Throwable $e) { continue; }
            if (StaffAttendance::punch($f[0], $at, $sn)) $n++;
        }

        return response("OK: {$n}", 200)->header('Content-Type', 'text/plain');
    }

    public function admsRequest(Request $req)
    {
        return response($this->device($req) ? 'OK' : 'Unknown device', $this->device($req) ? 200 : 403)->header('Content-Type', 'text/plain');
    }

    public function punch(Request $req)
    {
        $token = (string) config('services.staff_clock.token');
        if ($token === '' || !hash_equals($token, (string) $req->bearerToken())) {
            return response()->json(['ok' => false, 'message' => 'Not allowed.'], 401);
        }
        $d = $req->validate(['clock_id' => 'required|string|max:30', 'time' => 'nullable|date', 'device' => 'nullable|string|max:60']);
        $at = isset($d['time']) ? Carbon::parse($d['time']) : now();
        if ($at->isFuture()) $at = now();
        $row = StaffAttendance::punch($d['clock_id'], $at, $d['device'] ?? 'api');
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'No staff member has this clock-in ID.'], 404);
        }

        return response()->json(['ok' => true, 'date' => $row->date->toDateString(), 'clock_in' => $row->clock_in, 'clock_out' => $row->clock_out]);
    }

    /** The device's serial number when it is one of the school's registered devices. */
    protected function device(Request $req): ?string
    {
        $sn = trim((string) $req->query('SN'));
        $allowed = array_filter(array_map('trim', explode(',', (string) config('services.staff_clock.devices'))));

        return $sn !== '' && in_array($sn, $allowed, true) ? $sn : null;
    }
}
