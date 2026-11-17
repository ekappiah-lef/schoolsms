<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A labelled slot held only on some days (e.g. Assembly on Monday, Worship on Wednesday); a lesson period on the others. */
class TimeSlotDays extends Migration
{
    public function up()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->text('days')->nullable()->after('label');
        });
    }

    public function down()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->dropColumn('days');
        });
    }
}
