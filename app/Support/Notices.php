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

    /** Email and SMS the current term's invoice (with any balance brought forward) to the parent. */
    public static function sendInvoice(StudentRecord $sr): array
    {
        $sr->loadMissing(['user', 'my_parent']);
        $to = self::recipients($sr);
        $invoice = Fees::termInvoice($sr->user_id);
        $url = self::statementUrl($sr->user_id);
        $school = Qs::getSystemName();
        $student = $sr->user->name;
        $parentName = optional($sr->my_parent)->name;

        $results = [];
        $log = function ($channel, $recipient, $error = null) use (&$results, $sr) {
            $status = $error ? 'failed' : 'sent';
            NotificationLog::create([
                'student_id' => $sr->user_id, 'channel' => $channel, 'kind' => 'invoice',
                'recipient' => $recipient, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 1000) : null,
            ]);
            $results[] = ['channel' => $channel, 'kind' => 'invoice', 'recipient' => $recipient, 'status' => $status, 'error' => $error];
        };

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
                Mail::to($email)->send(new \App\Mail\TermInvoice($school, $parentName, $student, $url, $invoice));
                $log('email', $email);
            } catch (Throwable $e) {
                $log('email', $email, $e->getMessage());
            }
        }

        $money = function ($n) { return 'GHS '.number_format((int) $n); };
        $sms = "{$school}: {$student}, {$invoice['label']} fees {$money($invoice['current']['balance'])}"
            .($invoice['forward']['total'] > 0 ? " + balance brought forward {$money($invoice['forward']['total'])}" : '')
            .". Total due: {$money($invoice['total'])}. Details: {$url}";
        foreach ($to['phones'] as $phone) {
            $log('sms', $phone, self::sms($phone, $sms));
            if (self::whatsappEnabled()) $log('whatsapp', $phone, self::whatsapp($phone, $sms));
        }

        if (!$to['emails'] && !$to['phones']) {
            $log('email', '(none)', 'No parent email or phone number on record.');
        }

        return $results;
    }

    /** Email a report sheet (PDF attached) to the parents, with an SMS saying it has been sent. */
    public static function sendReport(StudentRecord $sr, string $examLabel, string $pdf): array
    {
        $sr->loadMissing(['user', 'my_parent']);
        $to = self::recipients($sr);
        $school = Qs::getSystemName();
        $child = $sr->user->name;
        $parent = optional($sr->my_parent)->name;
        $file = 'Report-'.preg_replace('/[^A-Za-z0-9]+/', '-', $child.' '.$examLabel).'.pdf';

        $results = [];
        $log = function ($channel, $recipient, $error = null) use (&$results, $sr) {
            $status = $error ? 'failed' : 'sent';
            NotificationLog::create(['student_id' => $sr->user_id, 'channel' => $channel, 'kind' => 'report', 'recipient' => $recipient, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 1000) : null]);
            $results[] = compact('channel', 'recipient', 'status', 'error');
        };

        $emailed = 0;
        foreach ($to['emails'] as $email) {
            if ($why = self::blocked($email)) { $log('email', $email, $why); continue; }
            if (!self::mailConfigured()) { $log('email', $email, 'Email is not configured (set MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD in .env).'); continue; }
            try {
                Mail::to($email)->send(new \App\Mail\ReportCard($school, $parent, $child, $examLabel, $pdf, $file));
                $log('email', $email);
                $emailed++;
            } catch (Throwable $e) {
                $log('email', $email, $e->getMessage());
            }
        }
        if ($emailed) {
            foreach ($to['phones'] as $phone) {
                $log('sms', $phone, self::sms($phone, "{$school}: {$child}'s {$examLabel} report sheet has been sent to your email."));
            }
        }
        if (!$to['emails']) {
            $log('email', '(none)', 'No parent email address on record.');
        }

        return $results;
    }

    /** Kind words to the parents of a child marked absent: asks after them and hopes to see them soon. */
    public static function sendAbsence(StudentRecord $sr, string $date): array
    {
        $sr->loadMissing(['user', 'my_parent']);
        $to = self::recipients($sr);
        $school = Qs::getSystemName();
        $child = $sr->user->name;
        $first = strtok($child, ' ');
        $parent = optional($sr->my_parent)->name ? strtok($sr->my_parent->name, ' ') : 'Parent';
        $they = ['Male' => 'him', 'Female' => 'her'][$sr->user->gender] ?? 'them';
        $day = \Carbon\Carbon::parse($date)->isToday() ? 'today' : 'on '.\Carbon\Carbon::parse($date)->format('l j F');
        $body = "Dear {$parent}, we noticed {$first} was not in school {$day}. We hope you and {$first} are doing well, and we look forward to seeing {$they} soon. — {$school}";

        $results = [];
        $log = function ($channel, $recipient, $error = null) use (&$results, $sr) {
            $status = $error ? 'failed' : 'sent';
            NotificationLog::create(['student_id' => $sr->user_id, 'channel' => $channel, 'kind' => 'absence', 'recipient' => $recipient, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 1000) : null]);
            $results[] = compact('channel', 'recipient', 'status', 'error');
        };
        foreach ($to['phones'] as $phone) {
            $log('sms', $phone, self::sms($phone, $body));
            if (self::whatsappEnabled()) $log('whatsapp', $phone, self::whatsapp($phone, $body));
        }
        foreach ($to['emails'] as $email) {
            $log('email', $email, self::email($email, "{$first} was not in school {$day}", $body));
        }
        if (!$to['emails'] && !$to['phones']) {
            $log('sms', '(none)', 'No parent email or phone number on record.');
        }

        return $results;
    }

    /**
     * Send one message to many people (Messages page). Returns counts of sent, held back
     * (demo allowlist) and failed messages.
     */
    public static function broadcast(array $emails, array $phones, ?string $subject, string $body, bool $sms, bool $email, bool $whatsapp = false): array
    {
        $count = ['sent' => 0, 'held' => 0, 'failed' => 0];
        $tally = function (?string $error) use (&$count) {
            if ($error === null) $count['sent']++;
            elseif (strpos($error, 'Demo mode') === 0) $count['held']++;
            else $count['failed']++;
        };
        if ($sms) {
            foreach (array_unique($phones) as $phone) {
                $tally(self::sms($phone, $body));
            }
        }
        if ($whatsapp) {
            foreach (array_unique($phones) as $phone) {
                $tally(self::whatsapp($phone, $body));
            }
        }
        if ($email) {
            foreach (array_unique($emails) as $address) {
                $tally(self::email($address, $subject ?: 'Message from '.Qs::getSystemName(), $body));
            }
        }

        return $count;
    }

    /** Send a plain message by email; returns null when sent, otherwise the reason. */
    public static function email(string $address, string $subject, string $body): ?string
    {
        if ($why = self::blocked($address)) {
            return $why;
        }
        if (!self::mailConfigured()) {
            return 'Email is not configured (set MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD in .env).';
        }
        try {
            Mail::to($address)->send(new \App\Mail\Announcement(Qs::getSystemName(), $subject, $body));
            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
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

    /** True when the school's WhatsApp Business number is connected (config/whatsapp.php). */
    public static function whatsappEnabled(): bool
    {
        return config('whatsapp.enabled') && config('whatsapp.token') && config('whatsapp.phone_number_id');
    }

    /**
     * Send a WhatsApp message with the approved template (its {{1}} is filled with $text).
     * Returns null when sent, otherwise the reason.
     */
    public static function whatsapp(string $phone, string $text): ?string
    {
        if ($why = self::blocked($phone)) {
            return $why;
        }
        if (!self::whatsappEnabled()) {
            return 'WhatsApp is not connected (WHATSAPP_* settings).';
        }
        $to = self::normalisePhone($phone);
        if (!$to) {
            return 'Invalid phone number.';
        }
        // Template parameters cannot contain new lines, tabs or more than 4 spaces in a row.
        $param = trim(preg_replace('/\s+/', ' ', $text));
        try {
            $res = Http::withToken(config('whatsapp.token'))->timeout(20)
                ->post('https://graph.facebook.com/'.config('whatsapp.version').'/'.config('whatsapp.phone_number_id').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => config('whatsapp.template'),
                        'language' => ['code' => config('whatsapp.template_lang')],
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($param, 0, 1000)]]]],
                    ],
                ]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return $res->successful() ? null : 'WhatsApp error '.$res->status().': '.mb_substr((string) $res->json('error.message', $res->body()), 0, 300);
    }
}
