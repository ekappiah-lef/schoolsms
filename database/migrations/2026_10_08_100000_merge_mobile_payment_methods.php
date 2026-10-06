<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modes of payment are now Cash, Mobile payment, Bank transfer and Cheque.
 * MTN MoMo, Telecel Cash and AirtelTigo Money receipts become "Mobile payment";
 * the network is kept at the start of the reference so nothing is lost.
 */
class MergeMobilePaymentMethods extends Migration
{
    const NETWORKS = ['MTN MoMo', 'Telecel Cash', 'AirtelTigo Money'];

    public function up()
    {
        foreach (['receipts', 'optional_fee_receipts'] as $table) {
            foreach (self::NETWORKS as $network) {
                DB::table($table)->where('method', $network)->update([
                    'method' => 'Mobile payment',
                    'reference' => DB::raw("LEFT(TRIM(CONCAT('".$network." ', COALESCE(reference, ''))), 100)"),
                ]);
            }
        }
        // Income & expenses use the same words.
        DB::table('finance_transactions')->whereIn('method', array_merge(self::NETWORKS, ['Mobile money']))->update(['method' => 'Mobile payment']);
    }

    public function down()
    {
        foreach (['receipts', 'optional_fee_receipts'] as $table) {
            foreach (self::NETWORKS as $network) {
                DB::table($table)->where('method', 'Mobile payment')->where('reference', 'like', $network.'%')->update(['method' => $network]);
            }
        }
    }
}
