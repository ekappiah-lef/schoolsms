<?php

namespace App\Support;

use App\Models\Lga;
use App\Models\Nationality;
use App\Models\State;
use Illuminate\Http\Request;

/**
 * Nationality / state / LGA pickers accept a name that is not in the list yet: the form sends
 * "new:Name" and it is added to the list (or matched, if it already exists) before saving.
 */
class Locations
{
    public static function resolve(Request $req): void
    {
        $merge = [];
        $name = function ($v) { return is_string($v) && strpos($v, 'new:') === 0 ? trim(mb_substr($v, 4)) : null; };

        if ($n = $name($req->input('nal_id'))) {
            $merge['nal_id'] = Nationality::firstOrCreate(['name' => mb_substr($n, 0, 100)])->id;
        }
        $stateId = $req->input('state_id');
        if ($n = $name($stateId)) {
            $stateId = $merge['state_id'] = State::firstOrCreate(['name' => mb_substr($n, 0, 100)])->id;
        }
        if (($n = $name($req->input('lga_id'))) && $stateId && is_numeric($stateId)) {
            $merge['lga_id'] = Lga::firstOrCreate(['state_id' => (int) $stateId, 'name' => mb_substr($n, 0, 100)])->id;
        }

        if ($merge) {
            $req->merge($merge);
        }
    }
}
