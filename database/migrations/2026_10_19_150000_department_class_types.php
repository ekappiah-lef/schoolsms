<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A department can cover class types (e.g. Lower Grade, Upper Grade) instead of subjects (switch departments_by_class_type). */
class DepartmentClassTypes extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('departments', 'class_type_ids')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->text('class_type_ids')->nullable()->after('hod_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('departments', 'class_type_ids')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('class_type_ids');
            });
        }
    }
}
