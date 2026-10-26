<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's standing: active, away (deactivated for a while: travel, illness; still enrolled) or left
 * (withdrawn; archived with the graduated students, records kept). Graduated students stay "active" + grad.
 */
class StudentStatus extends Migration
{
    public function up()
    {
        Schema::table('student_records', function (Blueprint $t) {
            $t->string('status', 10)->default('active')->after('grad_date');
            $t->date('away_from')->nullable()->after('status');
            $t->date('away_until')->nullable()->after('away_from');
            $t->date('left_on')->nullable()->after('away_until');
            $t->string('status_reason', 190)->nullable()->after('left_on');
        });
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $t) {
            $t->dropColumn(['status', 'away_from', 'away_until', 'left_on', 'status_reason']);
        });
    }
}
