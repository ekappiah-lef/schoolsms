<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\StudentRecord;
use Illuminate\Http\Request;

/**
 * Away (deactivated for a while; still enrolled, school fees continue, services not carried into a new term,
 * off the attendance register) and Left (withdrawn: archived with the graduated students, everything kept).
 */
class StudentStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSA');
    }

    protected function record($student): StudentRecord
    {
        return StudentRecord::with('user')->findOrFail((int) Qs::decodeHash((string) $student));
    }

    public function deactivate(Request $req, $student)
    {
        $d = $req->validate([
            'away_from' => 'required|date',
            'away_until' => 'nullable|date',
            'reason' => 'nullable|string|max:190',
        ], [], ['away_from' => 'from', 'away_until' => 'expected return']);
        if (!empty($d['away_until']) && strtotime($d['away_until']) < strtotime($d['away_from'])) {
            return Qs::json('The expected return is before the first day away.', false);
        }
        $sr = $this->record($student);
        if ($sr->grad) return Qs::json('Only students in school can be deactivated.', false);
        $sr->update(['status' => 'away', 'away_from' => $d['away_from'], 'away_until' => $d['away_until'] ?? null, 'status_reason' => $d['reason'] ?? null]);

        return Qs::json($sr->user->name.' is marked away.', true);
    }

    public function reactivate($student)
    {
        $sr = $this->record($student);
        $sr->update(['status' => 'active', 'away_from' => null, 'away_until' => null, 'status_reason' => null]);

        return Qs::json($sr->user->name.' is back in school.', true);
    }

    public function leave(Request $req, $student)
    {
        $d = $req->validate(['left_on' => 'required|date', 'reason' => 'nullable|string|max:190'], [], ['left_on' => 'date left']);
        $sr = $this->record($student);
        if ($sr->grad) return Qs::json('This student is not in school.', false);
        $sr->update(['status' => 'left', 'grad' => 1, 'grad_date' => substr($d['left_on'], 0, 4), 'left_on' => $d['left_on'],
            'status_reason' => $d['reason'] ?? null, 'away_from' => null, 'away_until' => null]);

        return Qs::json($sr->user->name.' is marked as left. Their records and any balance are kept.', true);
    }

    public function readmit($student)
    {
        $sr = $this->record($student);
        if ($sr->status !== 'left') return Qs::json('Only students who left can be re-admitted here.', false);
        $sr->update(['status' => 'active', 'grad' => 0, 'grad_date' => null, 'left_on' => null, 'status_reason' => null, 'session' => Qs::getCurrentSession()]);

        return Qs::json($sr->user->name.' is re-admitted.', true);
    }
}
