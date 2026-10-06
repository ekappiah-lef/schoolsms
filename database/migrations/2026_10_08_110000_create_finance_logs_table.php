<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for money: reversed (voided) receipts and changes to income / expense entries.
 * Rows are only ever added, never edited or deleted by the app.
 */
class CreateFinanceLogsTable extends Migration
{
    public function up()
    {
        Schema::create('finance_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->nullable();   // who did it
            $t->string('action', 30);                     // void_receipt | update_entry | delete_entry
            $t->string('subject', 30);                    // school_receipt | optional_receipt | finance_transaction
            $t->unsignedInteger('subject_id');
            $t->unsignedInteger('student_id')->nullable();
            $t->integer('amount')->nullable();
            $t->string('reason', 255)->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamps();
            $t->index(['action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('finance_logs');
    }
}
