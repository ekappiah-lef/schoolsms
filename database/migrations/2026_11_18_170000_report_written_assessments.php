<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Written assessments on the report card (e.g. Well-being, Sustainability): the class teacher's paragraph for each, by name. */
class ReportWrittenAssessments extends Migration
{
    public function up()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->text('assessments')->nullable();
        });
    }

    public function down()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->dropColumn('assessments');
        });
    }
}
