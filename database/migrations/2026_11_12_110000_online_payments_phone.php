<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A prompt sent to a parent's phone from Student payments: the number and its network. */
class OnlinePaymentsPhone extends Migration
{
    public function up()
    {
        Schema::table('online_payments', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->after('email');
            $t->string('network', 10)->nullable()->after('phone');
        });
    }

    public function down()
    {
        Schema::table('online_payments', function (Blueprint $t) {
            $t->dropColumn(['phone', 'network']);
        });
    }
}
