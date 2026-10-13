<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff are full time or part time (shown on the Teachers page). */
class AddEmploymentTypeToStaffRecords extends Migration
{
    public function up()
    {
        Schema::table('staff_records', function (Blueprint $t) {
            $t->string('employment_type', 20)->nullable()->after('emp_date');
        });
    }

    public function down()
    {
        Schema::table('staff_records', function (Blueprint $t) {
            $t->dropColumn('employment_type');
        });
    }
}
