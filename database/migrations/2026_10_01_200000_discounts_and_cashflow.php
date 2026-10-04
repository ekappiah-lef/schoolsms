<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tuition discounts and the school's other income / expenses (cash flow).
 * Additive only.
 */
class DiscountsAndCashflow extends Migration
{
    public function up()
    {
        // Discount categories, e.g. "Director's special package" 100% of tuition.
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->unsignedTinyInteger('percent'); // of tuition
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('student_records', function (Blueprint $table) {
            $table->unsignedInteger('fee_discount_id')->nullable()->after('admitted_session');
        });

        // Amount taken off this student's fee (amount owed = payment amount - discount).
        Schema::table('payment_records', function (Blueprint $table) {
            $table->integer('discount')->default(0)->after('amt_paid');
        });

        // Money in (capital injections, donations, other income) and out (expenses).
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 10); // income | expense
            $table->string('category', 60);
            $table->integer('amount');
            $table->date('date');
            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->index(['type', 'date']);
        });

        // The three packages the school uses today; editable in Finance configuration.
        $now = now();
        DB::table('fee_discounts')->insert([
            ['name' => "Director's special package", 'percent' => 100, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '50% of tuition', 'percent' => 50, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '20% of tuition', 'percent' => 20, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('finance_transactions');
        Schema::table('payment_records', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn('fee_discount_id');
        });
        Schema::dropIfExists('fee_discounts');
    }
}
