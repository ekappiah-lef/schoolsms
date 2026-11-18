<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Benchmarks report (creche, nursery): the class teacher's remark for each statement, by statement. */
class ReportBenchmarks extends Migration
{
    public function up()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->text('benchmarks')->nullable();
        });
    }

    public function down()
    {
        Schema::table('exam_records', function (Blueprint $t) {
            $t->dropColumn('benchmarks');
        });
    }
}
