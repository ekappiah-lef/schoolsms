<?php

namespace App\Console\Commands;

use App\Support\ClearEnroll;
use Illuminate\Console\Command;

/** Send every student who owes fees (with the amount) to ClearEnroll. */
class ClearEnrollSync extends Command
{
    protected $signature = 'clearenroll:sync {--if-new-term : Only if this term has not been sent yet}';

    protected $description = 'Send students who owe fees to ClearEnroll (school-to-school fee clearance)';

    public function handle()
    {
        if (!ClearEnroll::enabled()) {
            $this->warn('ClearEnroll is not connected (CLEARENROLL_API_URL / CLEARENROLL_API_KEY).');
            return 0;
        }
        $r = $this->option('if-new-term') ? ClearEnroll::syncIfNewTerm() : ClearEnroll::syncAll();
        if ($r === null) {
            $this->line('This term was already sent to ClearEnroll.');
            return 0;
        }
        if (!empty($r['error'])) {
            $this->error('ClearEnroll sync failed: '.$r['error']);
            return 1;
        }
        $this->info("Sent {$r['sent']}: {$r['flagged']} flagged, {$r['updated']} updated, {$r['cleared']} cleared.");

        return 0;
    }
}
