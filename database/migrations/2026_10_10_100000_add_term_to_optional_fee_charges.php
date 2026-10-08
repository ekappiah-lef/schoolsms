<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services (feeding, bus, clubs) are billed term by term at that term's price, like school fees.
 * Existing service charges are put in the term they were billed in. Shop sales keep no term
 * (they belong to the term of the sale date).
 */
class AddTermToOptionalFeeCharges extends Migration
{
    public function up()
    {
        Schema::table('optional_fee_charges', function (Blueprint $t) {
            $t->unsignedTinyInteger('term')->nullable()->after('year');
            $t->index(['student_id', 'year', 'term']);
        });

        foreach (DB::table('optional_fee_charges')->where('group', '!=', 'sales')->get(['id', 'year', 'created_at']) as $c) {
            $start = (int) substr($c->year, 0, 4);
            $at = $c->created_at ? \Carbon\Carbon::parse($c->created_at) : now();
            [$y, $term] = \App\Support\FinancePeriod::termOf($at);
            DB::table('optional_fee_charges')->where('id', $c->id)->update(['term' => $y < $start ? 1 : ($y > $start ? 3 : $term)]);
        }
    }

    public function down()
    {
        Schema::table('optional_fee_charges', function (Blueprint $t) {
            $t->dropIndex(['student_id', 'year', 'term']);
            $t->dropColumn('term');
        });
    }
}
