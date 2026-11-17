<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The academic head role is the school's HOD (head of department): timetables, exams, grades, approving results. */
class AcademicHeadNamedHod extends Migration
{
    public function up()
    {
        DB::table('user_types')->where('title', 'academic_head')->update(['name' => 'HOD']);
    }

    public function down()
    {
        DB::table('user_types')->where('title', 'academic_head')->update(['name' => 'Academic Head']);
    }
}
