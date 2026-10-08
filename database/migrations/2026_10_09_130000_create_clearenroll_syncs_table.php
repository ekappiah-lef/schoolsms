<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every exchange with ClearEnroll (full sync of debtors, or one student's new balance) and its result. */
class CreateClearenrollSyncsTable extends Migration
{
    public function up()
    {
        Schema::create('clearenroll_syncs', function (Blueprint $t) {
            $t->increments('id');
            $t->string('kind', 10);                 // full | student
            $t->string('term', 20)->nullable();     // YYYY-YYYY:N at the time
            $t->unsignedInteger('student_id')->nullable();
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('flagged')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('cleared')->default(0);
            $t->text('rejected')->nullable();       // JSON: students ClearEnroll could not take, and why
            $t->string('error', 255)->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->timestamps();
            $t->index(['kind', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('clearenroll_syncs');
    }
}
