<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores can have decimals (e.g. 50.7); totals stay whole numbers (rounded when worked out).
 * Every change to a mark is kept: who changed it, when, and from what to what.
 */
class DecimalMarksAndMarkChanges extends Migration
{
    public function up()
    {
        foreach (['t1', 't2', 't3', 't4', 'tca', 'exm'] as $c) {
            DB::statement("ALTER TABLE marks MODIFY `$c` DECIMAL(6,2) NULL");
        }

        Schema::create('mark_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('mark_id')->index();
            $t->unsignedInteger('student_id');
            $t->unsignedInteger('exam_id');
            $t->unsignedInteger('my_class_id');
            $t->unsignedInteger('section_id');
            $t->unsignedInteger('subject_id');
            $t->unsignedInteger('user_id')->nullable();
            $t->text('changes');   // JSON: [{label, from, to}]
            $t->timestamps();
            $t->index(['exam_id', 'my_class_id', 'section_id', 'subject_id'], 'mark_changes_sheet');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mark_changes');
        foreach (['t1', 't2', 't3', 't4', 'tca', 'exm'] as $c) {
            DB::statement("ALTER TABLE marks MODIFY `$c` INT NULL");
        }
    }
}
