<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DEMO DATA ONLY: gives the demo receipts a realistic mix of payment methods (cash, mobile payment,
 * bank transfer…) so "Payments by method" has something to show. Never run on real data —
 * real payments record the method chosen when they are taken.
 */
class DemoPaymentMethods extends Command
{
    protected $signature = 'demo:payment-methods {--force : Run without asking}';

    protected $description = 'Demo data only: spread demo receipts across payment methods';

    const MIX = ['Cash' => 40, 'Mobile payment' => 47, 'Bank transfer' => 11, 'Cheque' => 2];

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('This overwrites the payment method of every receipt. Only run it on DEMO data. Continue?')) {
            return 0;
        }

        $pick = function (int $id) {
            $n = crc32('pm'.$id) % 100;
            foreach (self::MIX as $method => $share) {
                if ($n < $share) return $method;
                $n -= $share;
            }
            return 'Cash';
        };

        foreach (['receipts', 'optional_fee_receipts'] as $table) {
            DB::table($table)->orderBy('id')->select('id')->chunk(500, function ($rows) use ($table, $pick) {
                $by = [];
                foreach ($rows as $r) $by[$pick($r->id)][] = $r->id;
                foreach ($by as $method => $ids) {
                    DB::table($table)->whereIn('id', $ids)->update(['method' => $method]);
                }
            });
        }
        $this->info('Demo receipts now have a mix of payment methods.');

        return 0;
    }
}
