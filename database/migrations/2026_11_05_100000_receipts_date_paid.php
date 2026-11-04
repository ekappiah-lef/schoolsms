<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The date the money was actually paid; created_at stays as the date it was entered. Earlier receipts: the date entered. */
class ReceiptsDatePaid extends Migration
{
    public function up()
    {
        foreach (['receipts', 'optional_fee_receipts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->date('paid_on')->nullable()->after('amt_paid')->index();
            });
            DB::table($table)->update(['paid_on' => DB::raw('DATE(created_at)')]);
        }
    }

    public function down()
    {
        foreach (['receipts', 'optional_fee_receipts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('paid_on');
            });
        }
    }
}
