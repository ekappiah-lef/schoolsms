<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Settings → Boarding facility: when on, dormitories show in the menu and on the admission form. */
class BoardingSetting extends Migration
{
    public function up()
    {
        if (!DB::table('settings')->where('type', 'has_boarding')->exists()) {
            DB::table('settings')->insert(['type' => 'has_boarding', 'description' => '0']);
        }
    }

    public function down()
    {
        DB::table('settings')->where('type', 'has_boarding')->delete();
    }
}
