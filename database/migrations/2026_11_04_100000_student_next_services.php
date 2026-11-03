<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff children, and the services set on a student's edit page for the coming term ({"for": "year:term", "services": …}). */
class StudentNextServices extends Migration
{
    public function up()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->boolean('staff_child')->default(false)->after('not_billed');
            $table->text('next_services')->nullable()->after('staff_child');
        });
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn(['staff_child', 'next_services']);
        });
    }
}
