<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments with a head (HOD), and the approval of each subject's marks by the HOD
 * (schools with the hod_marks_approval switch).
 */
class DepartmentsAndMarkApprovals extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->unsignedInteger('hod_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('subjects', 'department_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->unsignedInteger('department_id')->nullable()->after('category')->index();
            });
        }

        // One row per subject sheet (exam, class, section, subject, year): submitted, approved or returned.
        if (!Schema::hasTable('mark_approvals')) {
            Schema::create('mark_approvals', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('exam_id');
                $table->unsignedInteger('my_class_id');
                $table->unsignedInteger('section_id');
                $table->unsignedInteger('subject_id');
                $table->string('year', 20);
                $table->string('status', 12);
                $table->unsignedInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('note')->nullable();
                $table->text('history')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'my_class_id', 'section_id', 'subject_id', 'year'], 'mark_approvals_sheet');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mark_approvals');
        if (Schema::hasColumn('subjects', 'department_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });
        }
        Schema::dropIfExists('departments');
    }
}
