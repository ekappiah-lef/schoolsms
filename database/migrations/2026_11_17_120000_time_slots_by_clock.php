<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Time slots were ordered by the moment they were typed in; order them by their time of day. */
class TimeSlotsByClock extends Migration
{
    public function up()
    {
        foreach (DB::table('time_slots')->get(['id', 'time_from', 'time_to']) as $t) {
            $from = strtotime('2000-01-01 '.$t->time_from);
            $to = strtotime('2000-01-01 '.$t->time_to);
            if ($from && $to) DB::table('time_slots')->where('id', $t->id)->update(['timestamp_from' => $from, 'timestamp_to' => $to]);
        }
    }

    public function down()
    {
    }
}
