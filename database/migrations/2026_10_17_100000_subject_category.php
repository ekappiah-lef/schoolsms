<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Core or elective subject (report cards and mark entry group by it where the school's switch is on). */
class SubjectCategory extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('subjects', 'category')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->string('category', 10)->default('core')->after('slug');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('subjects', 'category')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
}
