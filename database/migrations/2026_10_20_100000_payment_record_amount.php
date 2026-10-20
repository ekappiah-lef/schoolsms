<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bill's own amount for one student (e.g. a balance brought forward from the school's previous system);
 * empty means the fee's amount. Negative = the student is in credit.
 */
class PaymentRecordAmount extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('payment_records', 'amount')) {
            Schema::table('payment_records', function (Blueprint $table) {
                $table->decimal('amount', 12, 2)->nullable()->after('payment_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('payment_records', 'amount')) {
            Schema::table('payment_records', function (Blueprint $table) {
                $table->dropColumn('amount');
            });
        }
    }
}
