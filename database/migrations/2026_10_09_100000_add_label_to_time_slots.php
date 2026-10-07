<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A time slot with a label (Break, Registration, Assembly …) is not a teaching period: it spans every day. */
class AddLabelToTimeSlots extends Migration
{
    public function up()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->string('label', 40)->nullable()->after('full');
        });
    }

    public function down()
    {
        Schema::table('time_slots', function (Blueprint $t) {
            $t->dropColumn('label');
        });
    }
}
