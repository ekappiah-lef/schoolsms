<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Settings → Birthday messages (on) and Fee reminders (off; or every 7, 14 or 30 days). */
class BirthdayAndReminderSettings extends Migration
{
    public function up()
    {
        foreach (['birthday_messages' => '1', 'fee_reminders' => '0'] as $type => $value) {
            if (!DB::table('settings')->where('type', $type)->exists()) {
                DB::table('settings')->insert(['type' => $type, 'description' => $value]);
            }
        }
    }

    public function down()
    {
        DB::table('settings')->whereIn('type', ['birthday_messages', 'fee_reminders', 'fee_reminders_last'])->delete();
    }
}
