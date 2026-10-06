<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feeding is offered as two options: "Breakfast" and "Lunch & Fruits".
 * The separate Lunch and Fruits options are merged; each student's Lunch and Fruits
 * charges for a year become one "Lunch & Fruits" charge (amounts and payments added
 * together, receipts moved across), so every finance total is unchanged.
 */
class MergeLunchAndFruits extends Migration
{
    public function up()
    {
        $lunch = DB::table('fee_options')->where(['group' => 'feeding', 'name' => 'Lunch'])->first();
        $fruits = DB::table('fee_options')->where(['group' => 'feeding', 'name' => 'Fruits'])->first();
        if (!$lunch && !$fruits) {
            return; // already merged
        }

        DB::transaction(function () use ($lunch, $fruits) {
            $price = (int) optional($lunch)->amount + (int) optional($fruits)->amount;
            if ($lunch) {
                DB::table('fee_options')->where('id', $lunch->id)->update(['name' => 'Lunch & Fruits', 'amount' => $price ?: 4000, 'updated_at' => now()]);
                $keepId = $lunch->id;
            } else {
                DB::table('fee_options')->where('id', $fruits->id)->update(['name' => 'Lunch & Fruits', 'amount' => $price ?: 4000, 'updated_at' => now()]);
                $keepId = $fruits->id;
            }
            $ids = array_filter([optional($lunch)->id, optional($fruits)->id]);

            $groups = DB::table('optional_fee_charges')->where('group', 'feeding')->whereIn('fee_option_id', $ids)
                ->orderBy('id')->get()->groupBy(function ($c) { return $c->student_id.'|'.$c->year; });

            foreach ($groups as $charges) {
                $keep = $charges->first();
                $amount = (int) $charges->sum('amount');
                $paid = (int) $charges->sum('amt_paid');
                $others = $charges->slice(1)->pluck('id');
                if ($others->count()) {
                    DB::table('optional_fee_receipts')->whereIn('charge_id', $others)->update(['charge_id' => $keep->id]);
                    DB::table('optional_fee_charges')->whereIn('id', $others)->delete();
                }
                DB::table('optional_fee_charges')->where('id', $keep->id)->update([
                    'label' => 'Lunch & Fruits', 'fee_option_id' => $keepId, 'amount' => $amount, 'amt_paid' => $paid,
                ]);
                // Receipt balances: what was still owed on the merged charge after each payment.
                $left = $amount;
                foreach (DB::table('optional_fee_receipts')->where('charge_id', $keep->id)->orderBy('created_at')->orderBy('id')->get() as $r) {
                    $left -= (int) $r->amt_paid;
                    DB::table('optional_fee_receipts')->where('id', $r->id)->update(['balance' => max($left, 0)]);
                }
            }

            if ($lunch && $fruits) {
                DB::table('fee_options')->where('id', $fruits->id)->delete();
            }
        });
    }

    public function down()
    {
        // Merging cannot be undone automatically.
    }
}
