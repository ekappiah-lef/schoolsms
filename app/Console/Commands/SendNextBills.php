<?php

namespace App\Console\Commands;

use App\Support\NextBills;
use Illuminate\Console\Command;

class SendNextBills extends Command
{
    protected $signature = 'fees:next-bills';

    protected $description = "Send next term's bills on the term's last day once the director has approved them";

    public function handle()
    {
        $this->line(NextBills::run());

        return 0;
    }
}
