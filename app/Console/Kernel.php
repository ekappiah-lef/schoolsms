<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // School fees: a term is billed on its first day (later terms are not billed ahead).
        $schedule->command('fees:bill-terms')->dailyAt('00:10');
        // ClearEnroll: send every student who owes fees, with the amount, at the end of each day (Sync now does it at once).
        $schedule->command('clearenroll:sync')->dailyAt('23:00');
        // Birthday wishes to staff, and to parents on their child's birthday (Settings → Birthday messages).
        $schedule->command('notices:birthdays')->dailyAt('07:30');
        // Fees reminders to parents who owe, as often as Settings → Fee reminders says.
        $schedule->command('fees:remind')->dailyAt('09:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
