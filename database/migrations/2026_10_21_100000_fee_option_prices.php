<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A service's price for one class type (e.g. Lunch: Preschool 1,760, Lower Grade 2,000); the service's own price otherwise. */
class FeeOptionPrices extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('fee_option_prices')) {
            Schema::create('fee_option_prices', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('fee_option_id')->index();
                $table->unsignedInteger('class_type_id');
                $table->decimal('amount', 12, 2);
                $table->timestamps();
                $table->unique(['fee_option_id', 'class_type_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('fee_option_prices');
    }
}
