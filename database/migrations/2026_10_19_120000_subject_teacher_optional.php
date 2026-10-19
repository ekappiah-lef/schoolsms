<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A subject can be added before its teacher is chosen (teacher_id may be empty). */
class SubjectTeacherOptional extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE subjects MODIFY teacher_id INT UNSIGNED NULL');
    }

    public function down()
    {
        // Subjects without a teacher would block this; they are left as they are.
    }
}
