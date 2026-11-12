<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Academic head: runs the academics (timetables, exams, grades, approving results) without admissions or student records. */
class AcademicHead extends Migration
{
    public function up()
    {
        if (!DB::table('user_types')->where('title', 'academic_head')->exists()) {
            DB::table('user_types')->insert(['title' => 'academic_head', 'name' => 'Academic Head', 'level' => 3]);
        }
    }

    public function down()
    {
        DB::table('user_types')->where('title', 'academic_head')->delete();
    }
}
