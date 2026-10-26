<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A discount set on one student's bill (e.g. carried over from SmartSapp): kept when fees or discount types change. */
class FixedDiscounts extends Migration
{
    public function up()
    {
        Schema::table('payment_records', function (Blueprint $t) {
            $t->boolean('discount_fixed')->default(false)->after('discount');
        });
    }

    public function down()
    {
        Schema::table('payment_records', function (Blueprint $t) {
            $t->dropColumn('discount_fixed');
        });
    }
}
