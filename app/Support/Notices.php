<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Mail\AdmissionLetter;
use App\Mail\FeesStatementLink;
use App\Models\NotificationLog;
use App\Models\ParentDetail;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Admission notices sent to the parent/guardian after a student is admitted:
 *  1. admission letter (email, with the school policy attached) + SMS
 *  2. link to the fees statement (school fees + optional fees) by email + SMS
 * Every attempt is written to notification_logs so failures are visible.
 */
class Notices
{
    public static function mailConfigured(): bool
    {
        $driver = config('mail.driver') ?? config('mail.default');
        if ($driver === 'smtp') {
            return (bool) (config('mail.host') ?? config('mail.mailers.smtp.host')) && (bool) (config('mail.username') ?? config('mail.mailers.smtp.username'));
        }

        return !in_array($driver, ['log', 'array', null], true);
    }

    public static function smsConfigured(): bool
    {
        switch (config('sms.driver')) {
            case 'arkesel':
                return (bool) config('sms.arkesel.api_key') && (bool) config('sms.sender');
            case 'smsonlinegh':
                return (bool) config('sms.smsonlinegh.api_key') && (bool) config('sms.sender');
            case 'hubtel':
                return config('sms.hubtel.client_id') && config('sms.hubtel.client_secret') && config('sms.sender');
            default:
                return false;
        }
    }

    /** Signed link parents can open without logging in (valid for 90 days). */
    public static function statementUrl(int $studentUserId): string
    {
        return URL::temporarySignedRoute('fees.statement', now()->addDays(90), ['student' => $studentUserId]);
    }

    /** Parent/guardian emails and phone numbers, de-duplicated. */
    public static function recipients(StudentRecord $sr): array
    {
        $emails = [];
        $phones = [];
        $parent = $sr->my_parent;
        if ($parent) {
            $emails[] = $parent->email;
            $phones[] = $parent->phone;
            $d = ParentDetail::where('user_id', $parent->id)->first();
            if ($d) {
                array_push($emails, $d->father_email, $d->mother_email);
                array_push($phones, $d->father_phone, $d->mother_phone, $d->guardian_phone);
            }
        }

        $emails = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $emails)), function ($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        })));
        $phones = collect($phones)->map(function ($p) { return self::normalisePhone((string) $p); })->filter()->unique()->values()->all();

        return ['emails' => $emails, 'phones' => $phones];
    }

    /** Send both notices; returns what happened per recipient. */
    public static function sendAdmission(StudentRecord $sr): array
    {
        $sr->loadMissing(['user', 'my_class', 'section', 'my_parent']);
        $to = self::recipients($sr);
        $url = self::statementUrl($sr->user_id);
        $school = Qs::getSystemName();
        $student = $sr->user->name;
        $class = trim(optional($sr->my_class)->name.' '.optional($sr->section)->name);
        $parentName = optional($sr->my_parent)->name;
        $statement = Fees::statement($sr->user_id, Qs::getCurrentSession());
        $policy = Qs::getSetting('admission_policy');
        $policyPath = $policy ? storage_path('app/public/'.$policy) : null;

        $results = [];
        $log = function ($channel, $kind, $recipient, $error = null) use (&$results, $sr) {
            $status = $error ? 'failed' : 'sent';
            NotificationLog::create([
                'student_id' => $sr->user_id, 'channel' => $channel, 'kind' => $kind,
                'recipient' => $recipient, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 1000) : null,
            ]);
            $results[] = compact('channel', 'kind', 'recipient', 'status', 'error');
        };

        $mailReady = self::mailConfigured();
        foreach ($to['emails'] as $email) {
            if ($why = self::blocked($email)) {
                $log('email', 'admission', $email, $why);
                $log('email', 'fees_link', $email, $why);
                continue;
            }
            if (!$mailReady) {
                $msg = 'Email is not configured (set MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD in .env).';
                $log('email', 'admission', $email, $msg);
                $log('email', 'fees_link', $email, $msg);
                continue;
            }
            try {
                Mail::to($email)->send(new AdmissionLetter($school, $parentName, $student, $class, $policyPath && is_file($policyPath) ? $policyPath : null));
                $log('email', 'admission', $email);
            } catch (Throwable $e) {
                $log('email', 'admission', $email, $e->getMessage());
            }
            try {
                Mail::to($email)->send(new FeesStatementLink($school, $parentName, $student, $url, $statement['totals'], $statement['school']['totals'], $statement['optional']['totals']));
                $log('email', 'fees_link', $email);
            } catch (Throwable $e) {
                $log('email', 'fees_link', $email, $e->getMessage());
            }
        }

        $first = $parentName ? strtok($parentName, ' ') : 'Parent';
        $sms1 = "Dear {$first}, congratulations! {$student} has been admitted to {$school}".($class ? " ({$class})" : '').'. Details have been sent to your email.';
        $sms2 = "{$school}: view {$student}'s fees (school fees and optional services) here: {$url}";
        foreach ($to['phones'] as $phone) {
            $log('sms', 'admission', $phone, self::sms($phone, $sms1));
            $log('sms', 'fees_link', $phone, self::sms($phone, $sms2));
        }

        if (!$to['emails'] && !$to['phones']) {
            $log('email', 'admission', '(none)', 'No parent email or phone number on record.');
        }

        return $results;
    }

    /** Email (PDF attached) and SMS a payment receipt to the parent. $r comes from ReceiptController::data(). */
    public static function sendReceipt(array $r): array
    {
        $sr = $r['record'];
        $to = self::recipients($sr);
        $results = [];
        $log = function ($channel, $recipient, $error = null) use (&$results, $sr) {
            $status = $error ? 'failed' : 'sent';
            NotificationLog::create([
                'student_id' => $sr->user_id, 'channel' => $channel, 'kind' => 'receipt',
                'recipient' => $recipient, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 1000) : null,
            ]);
            $results[] = ['channel' => $channel, 'kind' => 'receipt', 'recipient' => $recipient, 'status' => $status, 'error' => $error];
        };

        if ($to['emails']) {
            $pdf = null;
            try {
                $pdf = \App\Http\Controllers\SupportTeam\ReceiptController::renderPdf($r)->output();
            } catch (Throwable $e) {
                Log::warning('Receipt PDF failed: '.$e->getMessage());
            }
            foreach ($to['emails'] as $email) {
                if ($why = self::blocked($email)) {
                    $log('email', $email, $why);
                    continue;
                }
                if (!self::mailConfigured()) {
                    $log('email', $email, 'Email is not configured (set MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD in .env).');
                    continue;
                }
                try {
                    Mail::to($email)->send(new \App\Mail\PaymentReceipt($r, $pdf));
                    $log('email', $email);
                } catch (Throwable $e) {
                    $log('email', $email, $e->getMessage());
                }
            }
        }

        $money = function ($n) { return 'GHS '.number_format((int) $n); };
        $sms = "{$r['school']['name']}: payment of {$money($r['amount'])} received for {$r['student']['name']} ({$r['item']}). "
            .($r['balance'] > 0 ? "Balance: {$money($r['balance'])}." : 'Fully paid.')." Receipt {$r['number']}. Thank you.";
        foreach ($to['phones'] as $phone) {
            $log('sms', $phone, self::sms($phone, $sms));
        }

        if (!$to['emails'] && !$to['phones']) {
            $log('email', '(none)', 'No parent email or phone number on record.');
        }

        return $results;
    }

    /**
     * While NOTICE_ALLOWLIST is set (demo data), only those emails/phones are
     * ever contacted; returns the reason when a recipient is held back.
     */
    public static function blocked(string $recipient): ?string
    {
        $list = array_filter(array_map('trim', explode(',', (string) config('sms.allowlist'))));
        if (!$list) {
            return null;
        }
        $norm = function ($v) { return filter_var($v, FILTER_VALIDATE_EMAIL) ? strtolower($v) : (self::normalisePhone($v) ?? $v); };
        $allowed = array_map($norm, $list);

        return in_array($norm($recipient), $allowed, true) ? null : 'Demo mode: not sent. Only contacts in NOTICE_ALLOWLIST receive messages.';
    }

    /** Returns null when sent, otherwise the error message. */
    public static function sms(string $phone, string $message): ?string
    {
        if ($why = self::blocked($phone)) {
            return $why;
        }
        $driver = config('sms.driver');
        try {
            if ($driver === 'arkesel') {
                if (!self::smsConfigured()) return 'Arkesel is not configured (ARKESEL_API_KEY, SMS_SENDER_ID).';
                $r = Http::withHeaders(['api-key' => config('sms.arkesel.api_key')])->timeout(15)
                    ->post('https://sms.arkesel.com/api/v2/sms/send', [
                        'sender' => config('sms.sender'), 'message' => $message, 'recipients' => [$phone],
                    ]);
                return $r->successful() && ($r->json('status') === 'success') ? null : 'Arkesel: '.($r->json('message') ?: $r->status());
            }
            if ($driver === 'smsonlinegh') {
                if (!self::smsConfigured()) return 'SMS Online GH is not configured (SMSONLINEGH_API_KEY, SMS_SENDER_ID).';
                $r = Http::withHeaders(['Authorization' => 'key '.config('sms.smsonlinegh.api_key'), 'Accept' => 'application/json'])
                    ->timeout(15)
                    ->post('https://api.smsonlinegh.com/v5/message/sms/send', [
                        'text' => $message, 'type' => 0, 'sender' => config('sms.sender'), 'destinations' => [$phone],
                    ]);
                if (!$r->successful() || (int) $r->json('handshake.id') !== 0) {
                    return 'SMS Online GH: '.($r->json('handshake.label') ?: $r->status());
                }
                $status = (string) $r->json('data.destinations.0.status.label');
                return preg_match('/REJECT|INVALID|FAIL|ERROR/i', $status) ? "SMS Online GH: {$status}" : null;
            }
            if ($driver === 'hubtel') {
                if (!self::smsConfigured()) return 'Hubtel is not configured (HUBTEL_CLIENT_ID, HUBTEL_CLIENT_SECRET, SMS_SENDER_ID).';
                $r = Http::timeout(15)->get('https://smsc.hubtel.com/v1/messages/send', [
                    'clientid' => config('sms.hubtel.client_id'), 'clientsecret' => config('sms.hubtel.client_secret'),
                    'from' => config('sms.sender'), 'to' => $phone, 'content' => $message,
                ]);
                return $r->successful() ? null : 'Hubtel: '.($r->json('message') ?: $r->status());
            }
            // "log": nothing leaves the server; recorded as failed so it is not mistaken for delivery.
            Log::info("SMS (not sent, SMS_DRIVER=log) to {$phone}: {$message}");
            return 'SMS provider not configured (SMS_DRIVER=log). Message written to the log only.';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /** 0241234567 / +233 24 123 4567 → 233241234567 */
    public static function normalisePhone(string $p): ?string
    {
        $d = preg_replace('/\D+/', '', $p);
        if (strlen($d) === 10 && $d[0] === '0') {
            $d = '233'.substr($d, 1);
        }

        return strlen($d) >= 9 ? $d : null;
    }
}
