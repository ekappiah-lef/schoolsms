<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A subject can be kept off the report card (e.g. Well Being): not printed, and not counted in totals, averages or positions. */
class SubjectsOnReport extends Migration
{
    public function up()
    {
        Schema::table('subjects', function (Blueprint $t) {
            $t->boolean('on_report')->default(true);
        });
    }

    public function down()
    {
        Schema::table('subjects', function (Blueprint $t) {
            $t->dropColumn('on_report');
        });
    }
}
