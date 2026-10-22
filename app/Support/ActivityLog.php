<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Who did what and when: every change made in the app, and sign-ins (Administration → Activity log, super admin). */
class ActivityLog
{
    /** Never stored: passwords, tokens, file contents. */
    const HIDDEN = ['password', 'password_confirmation', 'current_password', '_token', '_method', 'photo', 'file', 'logo', 'pin', 'secret', 'token', 'api_key'];

    public static function add(string $action, array $details = [], ?Request $req = null, ?int $status = null, $user = null): void
    {
        try {
            // false = nobody (e.g. a failed sign-in); null = whoever is signed in.
            $user = $user === false ? null : ($user ?: Auth::user());
            $req = $req ?: request();
            DB::table('activity_logs')->insert([
                'user_id' => optional($user)->id, 'user_name' => optional($user)->name, 'role' => optional($user)->user_type,
                'action' => mb_substr($action, 0, 190), 'method' => $req ? $req->method() : null,
                'route' => $req && $req->route() ? mb_substr((string) $req->route()->getName(), 0, 120) : null,
                'url' => $req ? mb_substr($req->path(), 0, 300) : null,
                'details' => $details ? mb_substr(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000) : null,
                'status' => $status, 'ip' => $req ? $req->ip() : null, 'agent' => $req ? mb_substr((string) $req->userAgent(), 0, 200) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never stop the action itself.
        }
    }

    /** What the request changed, without secrets or files. */
    public static function inputOf(Request $req): array
    {
        $in = collect($req->except(self::HIDDEN))->map(function ($v) {
            if (is_object($v)) return '(file)';
            if (is_array($v)) return mb_substr(json_encode($v), 0, 300);
            return mb_substr((string) $v, 0, 300);
        })->filter(function ($v) { return $v !== ''; });
        $params = collect(optional($req->route())->parameters() ?? [])->map(function ($v) { return is_object($v) ? (method_exists($v, 'getKey') ? $v->getKey() : '') : $v; });

        return array_filter(['record' => $params->all(), 'input' => $in->all()]);
    }

    /** A readable name for a route: "payments.pay_now" → "Payments · pay now". */
    public static function describe(Request $req): string
    {
        $name = optional($req->route())->getName();
        $labels = [
            'payments.pay_now' => 'Recorded a payment', 'payments.reset_record' => 'Reversed payments on a bill', 'optional_fees.pay' => 'Recorded a service payment',
            'optional_fees.reset' => 'Reversed payments on a service', 'payments.store' => 'Created a fee', 'payments.update' => 'Edited a fee', 'payments.destroy' => 'Deleted a fee',
            'students.store' => 'Admitted a student', 'students.update' => 'Edited a student', 'students.destroy' => 'Deleted a student',
            'users.store' => 'Created a user', 'users.update' => 'Edited a user', 'users.destroy' => 'Deleted a user', 'users.reset_pass' => 'Reset a password',
            'marks.update' => 'Saved marks', 'marks.approval' => 'Marks approval step', 'students.promote' => 'Promoted students',
            'finance.transactions.store' => 'Recorded income or expense', 'finance.transactions.update' => 'Edited income or expense', 'finance.transactions.destroy' => 'Deleted income or expense',
            'finance.sales.return' => 'Returned a sale', 'settings.update' => 'Changed school settings', 'messages.send' => 'Sent a message',
            'payments.send_all_bills' => 'Sent bills to parents', 'payments.send_invoice' => 'Sent an invoice', 'finance.services.add' => 'Added students to a service',
        ];
        if ($name && isset($labels[$name])) return $labels[$name];
        if (!$name) return $req->method().' '.$req->path();
        $parts = explode('.', $name);
        $verb = ['store' => 'Created', 'update' => 'Edited', 'destroy' => 'Deleted', 'save' => 'Saved'][end($parts)] ?? str_replace('_', ' ', end($parts));

        return ucfirst($verb).' · '.str_replace('_', ' ', implode(' ', array_slice($parts, 0, -1)) ?: $name);
    }
}
