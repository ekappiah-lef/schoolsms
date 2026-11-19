<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A days-only slot can hold a different activity on each day (Worship on Wednesday, Sports on Thursday). */
class TimeSlotDayNames extends Migration
{
    public function up()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->text('day_names')->nullable()->after('days');
        });
    }

    public function down()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->dropColumn('day_names');
        });
    }
}
