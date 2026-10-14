<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Non-teaching staff (cook, driver, security, cleaner…): a user type of their own, with a job title. */
class NonTeachingStaff extends Migration
{
    public function up()
    {
        if (!DB::table('user_types')->where('title', 'non_teaching')->exists()) {
            DB::table('user_types')->insert(['title' => 'non_teaching', 'name' => 'Non-teaching staff', 'level' => '5']);
        }
        if (!Schema::hasColumn('staff_records', 'job_title')) {
            Schema::table('staff_records', function (Blueprint $table) {
                $table->string('job_title', 60)->nullable()->after('employment_type');
            });
        }
    }

    public function down()
    {
        DB::table('user_types')->where('title', 'non_teaching')->delete();
        if (Schema::hasColumn('staff_records', 'job_title')) {
            Schema::table('staff_records', function (Blueprint $table) {
                $table->dropColumn('job_title');
            });
        }
    }
}
