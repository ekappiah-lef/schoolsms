<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money is kept in cedis and pesewas: every amount column becomes DECIMAL(12,2).
 * Existing whole amounts are unchanged (1500 becomes 1500.00). Nullability and defaults are kept.
 */
class MoneyColumnsWithPesewas extends Migration
{
    const COLUMNS = [
        'bus_routes' => ['amount_both', 'amount_one_way'],
        'fee_options' => ['amount'],
        'finance_logs' => ['amount'],
        'finance_transactions' => ['amount'],
        'inventory_items' => ['price'],
        'momo_payments' => ['amount'],
        'optional_fee_charges' => ['amount', 'amt_paid'],
        'optional_fee_receipts' => ['amt_paid', 'balance'],
        'payments' => ['amount'],
        'payment_items' => ['amount'],
        'payment_records' => ['amt_paid', 'discount', 'balance'],
        'receipts' => ['amt_paid', 'balance'],
        'stock_movements' => ['unit_cost'],
    ];

    public function up()
    {
        $this->convert('DECIMAL(12,2)');
    }

    public function down()
    {
        $this->convert('INT');
    }

    protected function convert(string $type): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                $c = DB::selectOne('select is_nullable, column_default from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?', [$table, $column]);
                if (!$c) continue;
                $null = $c->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
                $default = $c->column_default !== null && strtoupper($c->column_default) !== 'NULL' ? ' DEFAULT '.(float) trim($c->column_default, "'") : '';
                DB::statement("ALTER TABLE `$table` MODIFY `$column` $type $null$default");
            }
        }
    }
}
