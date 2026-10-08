<?php

namespace App\Console\Commands;

use App\Support\Fees;
use Illuminate\Console\Command;

/** Bill current students for the terms that have started (a new term is billed on its first day). */
class BillDueTerms extends Command
{
    protected $signature = 'fees:bill-terms';

    protected $description = 'Bill school fees for the terms that have started';

    public function handle()
    {
        $n = Fees::billDueTerms();
        $this->info($n ? "Billed {$n} new school-fee records." : 'Nothing new to bill.');

        return 0;
    }
}
