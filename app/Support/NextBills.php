<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\MyClass;
use App\Models\NotificationLog;
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
        $key = $year.':'.Fees::termNow($year);

        return ['key' => $key, 'date' => $term->ends_on->toDateString(),
            'approved' => self::get('next_bills_approved') === $key, 'sent' => self::get('next_bills_sent') === $key];
    }

    public static function approve(bool $yes): void
    {
        $s = self::status();
        if ($s && !$s['sent']) self::put('next_bills_approved', $yes ? $s['key'] : null);
    }

    /** For the director's check: what each class will be billed. */
    public static function summary(): array
    {
        $rows = [];
        $notSet = $clear = 0;
        foreach (StudentRecord::where('grad', 0)->where('not_billed', false)->get() as $sr) {
            $bill = Fees::nextTermBill($sr);
            if (!$bill) { $notSet++; continue; }
            if ($bill['total'] <= 0) { $clear++; continue; }
            $r = $rows[$sr->my_class_id] ?? ['students' => 0, 'next' => 0.0, 'owed' => 0.0, 'total' => 0.0];
            $r['students']++;
            $r['next'] += $bill['current']['balance'];
            $r['owed'] += $bill['forward']['total'];
            $r['total'] += $bill['total'];
            $rows[$sr->my_class_id] = $r;
        }
        $classes = [];
        foreach (ClassOrder::sort(MyClass::all()) as $c) {
            if (isset($rows[$c->id])) $classes[] = ['class' => $c->name] + array_map(function ($v) { return Money::r($v); }, $rows[$c->id]);
        }

        return ['status' => self::status(), 'classes' => $classes, 'not_set' => $notSet, 'clear' => $clear];
    }

    /** Dashboard notice for the director, while approval is waiting. */
    public static function waiting(): ?array
    {
        $s = self::status();
        if (!$s || $s['approved'] || $s['sent'] || today()->lt(Carbon::parse($s['date'])->subDays(self::ALERT_DAYS))) return null;

        return ['date' => $s['date'], 'url' => route('payments.manage')];
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
        $body = "{$school}: next term's bills go out on {$when} once approved. Please check the fees and approve them under Payments.";
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
