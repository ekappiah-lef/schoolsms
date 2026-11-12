<?php

namespace App\Console\Commands;

use App\Helpers\Qs;
use App\Models\NotificationLog;
use App\Models\StudentRecord;
use App\Support\Notices;
use App\User;
use Illuminate\Console\Command;

/**
 * Birthday wishes, sent each morning (Settings → Birthday messages):
 *   staff on their birthday;
 *   parents on their child's birthday.
 * One message a day per person being wished and recipient (twins: one each) by SMS, and email when there is an address. 29 February birthdays are
 * wished on 28 February in other years.
 */
class SendBirthdays extends Command
{
    protected $signature = 'notices:birthdays {--date= : Pretend today is this date (Y-m-d), for testing}';

    protected $description = 'Send birthday wishes to staff, and to parents on their child\'s birthday';

    const STAFF = ['teacher', 'non_teaching', 'accountant', 'academic_admin', 'academic_head', 'admin', 'librarian'];

    public function handle()
    {
        if (!Qs::getSetting('birthday_messages')) {
            $this->line('Birthday messages are off (Settings).');
            return 0;
        }
        $day = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : today();
        $md = [$day->format('m-d')];
        if ($day->format('m-d') === '02-28' && !$day->isLeapYear()) $md[] = '02-29';
        $school = Qs::getSystemName();
        $born = function ($q) use ($md) { $q->whereIn(\DB::raw("substr(dob, 6, 5)"), $md); };
        $wish = function ($name) use ($school) {
            return "Happy Birthday, {$name}! Wishing you a wonderful day filled with joy, happiness, and God's blessings. May all your dreams come true.\n\nBest wishes from {$school}\nManagement.";
        };
        $n = 0;

        foreach (User::whereIn('user_type', self::STAFF)->whereNotNull('dob')->where($born)->get() as $u) {
            $n += $this->send($u->phone, $u->email, "Happy Birthday, {$u->name}!", $wish($u->name), null);
        }

        $students = StudentRecord::where('grad', 0)->whereHas('user', $born)->with(['user', 'my_parent'])->get();
        foreach ($students as $sr) {
            $to = Notices::recipients($sr);
            $child = $sr->user->name;
            $body = $wish($child);
            foreach (array_unique($to['phones']) as $p) $n += $this->send($p, null, null, $body, $sr->user_id);
            foreach (array_unique($to['emails']) as $e) $n += $this->send(null, $e, "Happy Birthday, {$child}!", $body, $sr->user_id);
        }

        $this->info("Birthday messages sent: {$n}");

        return 0;
    }

    /** One SMS and/or email, once a day per recipient; logged for the delivery report. */
    protected function send(?string $phone, ?string $email, ?string $subject, string $body, ?int $studentId): int
    {
        $sent = 0;
        foreach (array_filter(['sms' => $phone, 'email' => $email]) as $channel => $to) {
            if (NotificationLog::where(['kind' => 'birthday', 'recipient' => $to, 'student_id' => $studentId])->whereDate('created_at', today())->exists()) continue;
            $error = $channel === 'sms' ? Notices::sms($to, $body) : Notices::email($to, $subject, $body);
            NotificationLog::create(['student_id' => $studentId, 'channel' => $channel, 'kind' => 'birthday', 'recipient' => $to,
                'status' => $error ? 'failed' : 'sent', 'error' => $error ? mb_substr($error, 0, 1000) : null]);
            $sent += $error ? 0 : 1;
        }

        return $sent;
    }
}
