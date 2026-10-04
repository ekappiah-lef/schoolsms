<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Terms on school fees, and the school shop (sales & inventory).
 * "Books & stationery" optional services become shop sales.
 */
class TermsInventory extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('term')->nullable()->after('year');
        });
        DB::table('payments')->where('title', 'like', 'First Term%')->update(['term' => 1]);
        DB::table('payments')->where('title', 'like', 'Second Term%')->update(['term' => 2]);
        DB::table('payments')->where('title', 'like', 'Third Term%')->update(['term' => 3]);

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('category', 40); // Uniform | Books & stationery | Accessories | Other
            $table->integer('price');
            $table->integer('stock')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id');
            $table->integer('qty');                // + stock in, − stock out
            $table->string('type', 20);            // restock | sale | return | adjust
            $table->integer('unit_cost')->nullable();
            $table->unsignedInteger('charge_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->date('date');
            $table->timestamps();
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('cascade');
        });

        Schema::table('optional_fee_charges', function (Blueprint $table) {
            $table->unsignedInteger('inventory_item_id')->nullable()->after('bus_direction');
            $table->unsignedSmallInteger('qty')->default(1)->after('inventory_item_id');
            $table->unsignedInteger('handed_down_from')->nullable()->after('qty'); // sibling's users.id
        });

        // Move "Books & stationery" priced options into the shop.
        foreach (DB::table('fee_options')->where('group', 'books')->get() as $o) {
            $itemId = DB::table('inventory_items')->insertGetId([
                'name' => $o->name, 'category' => 'Books & stationery', 'price' => $o->amount, 'stock' => 0,
                'reorder_level' => 0, 'active' => $o->active, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('optional_fee_charges')->where('fee_option_id', $o->id)->update(['inventory_item_id' => $itemId, 'fee_option_id' => null]);
        }
        DB::table('fee_options')->where('group', 'books')->delete();
        DB::table('optional_fee_charges')->where('group', 'books')->update(['group' => 'sales']);
    }

    public function down()
    {
        DB::table('optional_fee_charges')->where('group', 'sales')->update(['group' => 'books']);
        Schema::table('optional_fee_charges', function (Blueprint $table) {
            $table->dropColumn(['inventory_item_id', 'qty', 'handed_down_from']);
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('term');
        });
    }
}
