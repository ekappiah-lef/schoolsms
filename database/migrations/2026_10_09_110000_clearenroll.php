<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ClearEnroll (school-to-school fee clearance): the portal address in Settings, and the result of the
 * check made before a student is admitted (cleared / not found / flagged / not checked).
 */
class Clearenroll extends Migration
{
    public function up()
    {
        if (!DB::table('settings')->where('type', 'clearenroll_url')->exists()) {
            DB::table('settings')->insert(['type' => 'clearenroll_url', 'description' => '']);
        }
        Schema::table('student_records', function (Blueprint $t) {
            $t->string('clearenroll_status', 20)->nullable();
            $t->timestamp('clearenroll_checked_at')->nullable();
        });
    }

    public function down()
    {
        DB::table('settings')->where('type', 'clearenroll_url')->delete();
        Schema::table('student_records', function (Blueprint $t) {
            $t->dropColumn(['clearenroll_status', 'clearenroll_checked_at']);
        });
    }
}
