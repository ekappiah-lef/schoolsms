<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\StudentRecord;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Next term's bills go out by themselves on the term's last day (School calendar), once the director has
 * checked the fees and approved them. The director is alerted a week before, and again on the day if not approved.
 */
class NextBills
{
    const ALERT_DAYS = 7;

    protected static function get(string $type)
    {
        return DB::table('settings')->where('type', $type)->value('description');
    }

    protected static function put(string $type, ?string $value): void
    {
        DB::table('settings')->updateOrInsert(['type' => $type], ['description' => $value]);
    }

    /** This term's send date and state, or null when the term's end date is not entered. */
    public static function status(): ?array
    {
        $term = SchoolCalendar::current();
        if (!$term) return null;
        $year = Qs::getCurrentSession();
        $now = Fees::termNow($year);
        $key = $year.':'.$now;
        $start = (int) substr($year, 0, 4);
        [$nextYear, $nextTerm] = $now < 3 ? [$year, $now + 1] : [($start + 1).'-'.($start + 2), 1];

        return ['key' => $key, 'year' => $nextYear, 'term' => $nextTerm, 'date' => $term->ends_on->toDateString(),
            'approved' => self::get('next_bills_approved') === $key, 'sent' => self::get('next_bills_sent') === $key];
    }

    public static function approve(bool $yes): void
    {
        $s = self::status();
        if ($s && !$s['sent']) self::put('next_bills_approved', $yes ? $s['key'] : null);
    }

    /** Dashboard notice for the director, while approval is waiting. */
    public static function waiting(): ?array
    {
        $s = self::status();
        if (!$s || $s['approved'] || $s['sent'] || today()->lt(Carbon::parse($s['date'])->subDays(self::ALERT_DAYS))) return null;

        $url = Payment::where('year', $s['year'])->exists() ? route('payments.show', $s['year']) : route('payments.index');

        return ['date' => $s['date'], 'url' => $url.'?term='.$s['term']];
    }

    /** The daily check: send when approved and due, otherwise alert the director when it is time. */
    public static function run(): string
    {
        $s = self::status();
        if (!$s) return 'This term has no end date.';
        if ($s['sent']) return 'Already sent this term.';
        $date = Carbon::parse($s['date']);
        $due = today()->gte($date);

        if ($s['approved']) {
            if (!$due) return 'Approved, goes out on '.$s['date'].'.';
            self::put('next_bills_sent', $s['key']);

            return self::send();
        }

        $stage = $due ? 'due' : (today()->gte($date->copy()->subDays(self::ALERT_DAYS)) ? 'soon' : null);
        if (!$stage || self::get('next_bills_alerted') === $s['key'].':'.$stage) return 'Waiting for approval.';
        self::put('next_bills_alerted', $s['key'].':'.$stage);

        return self::alert($s['date']);
    }

    protected static function send(): string
    {
        $sent = $held = 0;
        foreach (StudentRecord::where('grad', 0)->orderBy('id')->get() as $sr) {
            $bill = Fees::nextTermBill($sr);
            if (!$bill || $bill['total'] <= 0) continue;
            collect(Notices::sendInvoice($sr, $bill))->where('status', 'sent')->count() ? $sent++ : $held++;
        }

        return "Next term bills sent for {$sent} students ({$held} not sent).";
    }

    protected static function alert(string $date): string
    {
        $school = Qs::getSystemName();
        $when = Carbon::parse($date)->format('j M Y');
        $body = "{$school}: next term's bills go out on {$when} once approved. Please check the fees and approve them in Fee setup.";
        $n = 0;
        foreach (User::where('user_type', 'super_admin')->get() as $u) {
            foreach (array_filter(['sms' => $u->phone, 'email' => $u->email]) as $channel => $to) {
                $error = $channel === 'sms' ? Notices::sms($to, $body) : Notices::email($to, "Next term's bills: {$when}", $body);
                NotificationLog::create(['student_id' => null, 'channel' => $channel, 'kind' => 'next_bills', 'recipient' => $to,
                    'status' => $error ? 'failed' : 'sent', 'error' => $error ? mb_substr($error, 0, 1000) : null]);
                $n += $error ? 0 : 1;
            }
        }

        return "Director alerted ({$n} messages).";
    }
}
