<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dated items on the school calendar besides the terms: holidays, exams, mid-term exams, revision weeks, events. */
class SchoolEvents extends Migration
{
    public function up()
    {
        Schema::create('school_events', function (Blueprint $t) {
            $t->id();
            $t->string('year', 9);          // school year it belongs to, 2026-2027
            $t->string('type', 20);         // holiday | exam | midterm_exam | revision | event
            $t->string('title', 120);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestamps();
            $t->index(['year', 'starts_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('school_events');
    }
}
