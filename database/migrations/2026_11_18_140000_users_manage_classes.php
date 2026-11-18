<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A staff member (e.g. an HOD) given the Classes, Sections and Subjects pages without the rest of administration. */
class UsersManageClasses extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('manages_classes')->default(false);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('manages_classes');
        });
    }
}
