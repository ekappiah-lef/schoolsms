<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The term a student was admitted in: they pay the new-student fees that term, the regular fees after it. */
class StudentAdmittedTerm extends Migration
{
    public function up()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->unsignedTinyInteger('admitted_term')->nullable()->after('admitted_session');
        });
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn('admitted_term');
        });
    }
}
