<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** More than one class teacher per section (schools with the multiple_class_teachers switch). */
class SectionTeachers extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('section_teachers')) {
            Schema::create('section_teachers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('section_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->timestamps();
                $table->unique(['section_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('section_teachers');
    }
}
