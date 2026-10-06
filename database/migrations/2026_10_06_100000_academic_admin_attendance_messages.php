<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Academic Admin role (academics and students, no finance or user management)
 * - Daily class attendance register
 * - Messages sent to parents / staff (absentee alerts and announcements)
 * - Who recorded each income / expense entry is kept; only admins may delete
 */
class AcademicAdminAttendanceMessages extends Migration
{
    public function up()
    {
        if (!DB::table('user_types')->where('title', 'academic_admin')->exists()) {
            DB::table('user_types')->insert(['title' => 'academic_admin', 'name' => 'Academic Admin', 'level' => 3]);
        }

        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('student_id');
            $t->unsignedInteger('my_class_id');
            $t->unsignedInteger('section_id');
            $t->date('date');
            $t->string('status', 10); // present | absent | late
            $t->string('note')->nullable();
            $t->unsignedInteger('recorded_by')->nullable();
            $t->timestamp('alerted_at')->nullable(); // absentee SMS sent to the parent
            $t->string('year', 20);
            $t->timestamps();
            $t->unique(['student_id', 'date']);
            $t->index(['section_id', 'date']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->string('audience', 30); // parents | staff | teachers | non_teaching | class | absentees
            $t->string('audience_label');
            $t->string('subject')->nullable();
            $t->text('body');
            $t->boolean('sms')->default(true);
            $t->boolean('email')->default(true);
            $t->unsignedInteger('recipients')->default(0);
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unsignedInteger('sent_by')->nullable();
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('attendances');
        DB::table('user_types')->where('title', 'academic_admin')->delete();
    }
}
