<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - How each payment was made (Cash, MTN MoMo, bank transfer…) and its reference
 * - MTN MoMo "request to pay" transactions
 * - Messages from the academic admin wait for an admin's approval; WhatsApp as a channel
 */
class PaymentMethodsMomoApprovals extends Migration
{
    public function up()
    {
        foreach (['receipts', 'optional_fee_receipts'] as $t) {
            Schema::table($t, function (Blueprint $b) use ($t) {
                $b->string('method', 30)->default('Cash')->after('amt_paid');
                $b->string('reference', 100)->nullable()->after('method');
            });
        }

        Schema::create('momo_payments', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();          // X-Reference-Id sent to MTN
            $t->unsignedInteger('student_id');
            $t->string('phone', 20);
            $t->unsignedInteger('amount');
            $t->string('currency', 5);
            $t->string('status', 15)->default('pending'); // pending | successful | failed
            $t->string('financial_transaction_id', 60)->nullable();
            $t->string('reason')->nullable();
            $t->unsignedInteger('initiated_by')->nullable(); // staff user, or null when the parent paid from the statement
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'status']);
        });

        Schema::table('messages', function (Blueprint $t) {
            $t->string('status', 15)->default('sent')->after('audience_label'); // pending | sent | rejected
            $t->unsignedInteger('class_id')->nullable()->after('status');
            $t->unsignedInteger('section_id')->nullable()->after('class_id');
            $t->boolean('whatsapp')->default(false)->after('email');
            $t->unsignedInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('review_note')->nullable();
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn(['status', 'class_id', 'section_id', 'whatsapp', 'approved_by', 'approved_at', 'review_note']);
        });
        Schema::dropIfExists('momo_payments');
        foreach (['receipts', 'optional_fee_receipts'] as $t) {
            Schema::table($t, function (Blueprint $b) {
                $b->dropColumn(['method', 'reference']);
            });
        }
    }
}
