<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff clock in / clock out (from a fingerprint / face device or entered by hand). */
class StaffAttendance extends Migration
{
    public function up()
    {
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('source', 10)->default('device'); // device | manual
            $table->string('device', 60)->nullable();
            $table->string('note', 191)->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
        if (!Schema::hasColumn('staff_records', 'clock_id')) {
            Schema::table('staff_records', function (Blueprint $table) {
                // The number the staff member is enrolled under on the clock-in device.
                $table->string('clock_id', 30)->nullable()->after('job_title');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('staff_attendances');
        if (Schema::hasColumn('staff_records', 'clock_id')) {
            Schema::table('staff_records', function (Blueprint $table) {
                $table->dropColumn('clock_id');
            });
        }
    }
}
