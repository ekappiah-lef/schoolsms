<?php

namespace App\Console\Commands;

use App\Helpers\Qs;
use App\Models\NotificationLog;
use App\Models\StudentRecord;
use App\Support\Fees;
use App\Support\Money;
use App\Support\Notices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reminds the parents of every student who owes fees (this term plus anything brought forward), with the amount
 * and a link to the statement. Runs every morning and sends as often as Settings → Fee reminders says
 * (every 7, 14 or 30 days; off by default). --now sends straight away.
 */
class SendFeeReminders extends Command
{
    protected $signature = 'fees:remind {--now : Send now, whatever the schedule}';

    protected $description = 'Remind parents of students who owe fees (as often as Settings → Fee reminders)';

    public function handle()
    {
        $every = (int) Qs::getSetting('fee_reminders');
        $last = Qs::getSetting('fee_reminders_last');
        if (!$this->option('now')) {
            if (!$every) {
                $this->line('Fee reminders are off (Settings).');
                return 0;
            }
            if ($last && \Carbon\Carbon::parse($last)->addDays($every)->gt(today())) {
                $this->line("Next reminders on ".\Carbon\Carbon::parse($last)->addDays($every)->toDateString().'.');
                return 0;
            }
        }

        $school = Qs::getSystemName();
        $students = StudentRecord::where('grad', 0)->with(['user', 'my_parent'])->get()->filter(function ($sr) { return $sr->user; });
        $parents = $messages = 0;
        foreach ($students as $sr) {
            $owed = Money::r(Fees::termInvoice($sr->user_id)['total']);
            if ($owed <= 0) continue;
            $to = Notices::recipients($sr);
            if (!$to['phones'] && !$to['emails']) continue;
            $parent = optional($sr->my_parent)->name ? strtok($sr->my_parent->name, ' ') : 'Parent';
            $link = Notices::statementUrl($sr->user_id);
            $body = "Dear {$parent}, this is a reminder that GHS ".Money::fmt($owed)." is outstanding on {$sr->user->name}'s school fees. "
                ."See the statement and pay here: {$link} If you have already paid, please ignore this message. — {$school}";
            foreach (array_unique($to['phones']) as $p) $messages += $this->log($sr->user_id, 'sms', $p, Notices::sms($p, $body));
            foreach (array_unique($to['emails']) as $e) $messages += $this->log($sr->user_id, 'email', $e, Notices::email($e, "Fees reminder: {$sr->user->name}", $body));
            $parents++;
        }
        DB::table('settings')->updateOrInsert(['type' => 'fee_reminders_last'], ['description' => today()->toDateString()]);
        $this->info("Fee reminders: {$parents} students owing, {$messages} messages sent.");

        return 0;
    }

    protected function log(int $studentId, string $channel, string $to, ?string $error): int
    {
        NotificationLog::create(['student_id' => $studentId, 'channel' => $channel, 'kind' => 'reminder', 'recipient' => $to,
            'status' => $error ? 'failed' : 'sent', 'error' => $error ? mb_substr($error, 0, 1000) : null]);

        return $error ? 0 : 1;
    }
}
