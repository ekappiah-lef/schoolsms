<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A subject's teachers per section, several allowed (schools with the subject_teachers_per_section switch). */
class SubjectTeachers extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('subject_teachers')) {
            Schema::create('subject_teachers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('subject_id')->index();
                $table->unsignedInteger('section_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->timestamps();
                $table->unique(['subject_id', 'section_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('subject_teachers');
    }
}
