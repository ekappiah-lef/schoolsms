<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Students the school never bills (e.g. the owner's or director's children): no school fees; services cost 0. */
class StudentNotBilled extends Migration
{
    public function up()
    {
        Schema::table('student_records', function (Blueprint $t) {
            $t->boolean('not_billed')->default(false)->after('next_discount_id');
        });
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $t) {
            $t->dropColumn('not_billed');
        });
    }
}
