<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discount types: a percentage of tuition (decimals allowed) or a fixed amount off tuition, and
 * "next term only" (e.g. 5% early payment: taken off the student's next term's tuition once).
 */
class DiscountKinds extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE fee_discounts MODIFY percent DECIMAL(5,2) NOT NULL DEFAULT 0');
        Schema::table('fee_discounts', function (Blueprint $t) {
            $t->string('kind', 10)->default('percent')->after('name');   // percent | fixed
            $t->decimal('amount', 12, 2)->nullable()->after('percent');   // fixed amount off tuition
            $t->boolean('next_term_only')->default(false)->after('amount');
        });
        Schema::table('student_records', function (Blueprint $t) {
            $t->unsignedInteger('next_discount_id')->nullable()->after('fee_discount_id');
        });
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $t) {
            $t->dropColumn('next_discount_id');
        });
        Schema::table('fee_discounts', function (Blueprint $t) {
            $t->dropColumn(['kind', 'amount', 'next_term_only']);
        });
        DB::statement('ALTER TABLE fee_discounts MODIFY percent TINYINT UNSIGNED NOT NULL');
    }
}
