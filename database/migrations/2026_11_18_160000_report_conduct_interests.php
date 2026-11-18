<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The class teacher's Conduct and Interest(s) lines on the detailed report card. */
class ReportConductInterests extends Migration
{
    public function up()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->string('conduct', 120)->nullable();
            $t->string('interests', 120)->nullable();
        });
    }

    public function down()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->dropColumn(['conduct', 'interests']);
        });
    }
}
