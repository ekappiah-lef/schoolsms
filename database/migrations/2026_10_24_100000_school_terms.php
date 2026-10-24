<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school calendar: for each school year, when each term starts and ends and its mid-term break.
 * Any date can be changed; the app works out the current term and school days from it.
 */
class SchoolTerms extends Migration
{
    public function up()
    {
        Schema::create('school_terms', function (Blueprint $t) {
            $t->id();
            $t->string('year', 9);                 // 2026-2027
            $t->unsignedTinyInteger('term');       // 1–3
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->date('midterm_starts_on')->nullable();
            $t->date('midterm_ends_on')->nullable();
            $t->timestamps();
            $t->unique(['year', 'term']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('school_terms');
    }
}
