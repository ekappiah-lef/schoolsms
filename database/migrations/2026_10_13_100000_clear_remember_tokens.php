<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Keep me signed in" was removed: clear the saved tokens so nobody is signed in automatically
 * from an old browser cookie.
 */
class ClearRememberTokens extends Migration
{
    public function up()
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    public function down()
    {
        //
    }
}
