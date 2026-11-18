<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** School-fee bills that were cancelled for a student, so billing does not create them again. */
class CancelledBills extends Migration
{
    public function up()
    {
        Schema::create('cancelled_bills', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('student_id');
            $t->unsignedInteger('payment_id');
            $t->string('year', 20);
            $t->unsignedBigInteger('finance_log_id')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'payment_id', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cancelled_bills');
    }
}
