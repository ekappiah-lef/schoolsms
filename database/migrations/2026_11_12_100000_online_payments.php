<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fees paid online through a payment company (Paystack): one row per attempt, applied to the fees once. */
class OnlinePayments extends Migration
{
    public function up()
    {
        Schema::create('online_payments', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20)->default('paystack');
            $t->string('reference', 64)->unique();
            $t->unsignedInteger('student_id');
            $t->decimal('amount', 12, 2);
            $t->string('currency', 5)->default('GHS');
            $t->string('email', 150)->nullable();
            $t->string('status', 15)->default('pending');   // pending | successful | failed
            $t->string('channel', 30)->nullable();           // mobile_money | card | …
            $t->string('provider_id', 60)->nullable();       // the payment company's transaction id
            $t->string('reason')->nullable();
            $t->unsignedInteger('initiated_by')->nullable();
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('online_payments');
    }
}
