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

    /** Field names shown to people (anything not listed and not an id is shown with its own name tidied). */
    const LABELS = [
        'amt_paid' => 'Amount', 'amount' => 'Amount', 'method' => 'Paid by', 'reference' => 'Reference', 'reason' => 'Reason', 'note' => 'Note',
        'name' => 'Name', 'title' => 'Title', 'term' => 'Term', 'year' => 'Year', 'type' => 'Type', 'category' => 'Category', 'description' => 'Description',
        'date' => 'Date', 'gender' => 'Gender', 'dob' => 'Date of birth', 'phone' => 'Phone', 'email' => 'Email', 'username' => 'Username',
        'job_title' => 'Job title', 'address' => 'Address', 'capacity' => 'Capacity', 'slug' => 'Short name', 'direction' => 'Direction',
        'kind' => 'Bill', 'student_category' => 'Students', 'action' => 'Step', 'term_days' => 'School days', 'admission_date' => 'Admission date',
    ];
    /** Fields that are too long or too technical to show; summarised instead. */
    const SUMMARY = ['template' => 'Report card design', 'items' => 'Fee items', 'services' => 'Services', 'prices' => 'Prices', 'section_teachers' => 'Teachers by section'];

    /** The details of a log entry in plain words: "Amount 2,350.00 · Paid by Cash · Bill School Fees (Term 1) for NANA KOFI OKYERE". */
    public static function readable(?string $route, ?string $json): string
    {
        $d = json_decode((string) $json, true);
        if (!is_array($d)) return '';
        $parts = [];
        $record = (array) ($d['record'] ?? []);
        $in = (array) ($d['input'] ?? []);
        $id = function ($v) { return is_numeric($v) ? (int) $v : (int) \App\Helpers\Qs::decodeHash((string) $v); };
        $name = function ($table, $v, $col = 'name') use ($id) { $n = DB::table($table)->where('id', $id($v))->value($col); return $n ?: null; };
        $student = function ($userId) { return DB::table('users')->where('id', (int) $userId)->value('name'); };

        // What the entry was about (from the address of the action).
        foreach ($record as $k => $v) {
            if ($v === '' || $v === null) continue;
            if (preg_match('/^payments\.(pay_now|reset_record)$/', (string) $route)) {
                $pr = DB::table('payment_records as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')->where('pr.id', $id($v))->first(['pr.student_id', 'p.title', 'p.term']);
                if ($pr) $parts[] = 'Bill '.$pr->title.($pr->term ? ' (Term '.$pr->term.')' : '').' for '.$student($pr->student_id);
            } elseif (preg_match('/^optional_fees\./', (string) $route)) {
                $c = DB::table('optional_fee_charges')->where('id', $id($v))->first(['student_id', 'label']);
                if ($c) $parts[] = $c->label.' for '.$student($c->student_id);
            } elseif (preg_match('/^(students|st)\./', (string) $route)) {
                $sr = DB::table('student_records')->where('id', $id($v))->value('user_id');
                $parts[] = 'Student '.($sr ? $student($sr) : $student($id($v)));
            } elseif (preg_match('/^users\./', (string) $route)) {
                $parts[] = 'User '.($student($id($v)) ?: $v);
            } elseif ($k === 'exam') { $parts[] = 'Exam '.$name('exams', $v);
            } elseif ($k === 'class') { $parts[] = 'Class '.$name('my_classes', $v);
            } elseif ($k === 'section') { $parts[] = 'Section '.$name('sections', $v);
            } elseif ($k === 'subject') { $parts[] = 'Subject '.$name('subjects', $v);
            } elseif ($k === 'group') { $parts[] = ucfirst(str_replace('extracurricular', 'Extra-curricular', $v));
            } elseif ($k === 'type' && strpos((string) $route, 'reports.') === 0) { $parts[] = 'Class type '.$name('class_types', $v);
            }
        }

        // What was entered.
        foreach ($in as $k => $v) {
            if ($v === '' || $v === null || in_array($k, ['identity', 'form_version', 'terms_accepted', 'send_notices', 'offset'], true)) continue;
            if (isset(self::SUMMARY[$k])) { $parts[] = self::SUMMARY[$k].' changed'; continue; }
            $label = self::LABELS[$k] ?? null;
            if ($k === 'my_class_id') { $parts[] = 'Class '.$name('my_classes', $v); continue; }
            if ($k === 'section_id') { $parts[] = 'Section '.$name('sections', $v); continue; }
            if ($k === 'subject_id') { $parts[] = 'Subject '.$name('subjects', $v); continue; }
            if ($k === 'exam_id') { $parts[] = 'Exam '.$name('exams', $v); continue; }
            if (in_array($k, ['teacher_id', 'hod_id', 'my_parent_id'], true)) { $parts[] = ucfirst(str_replace(['_id', 'my_', 'hod'], ['', '', 'Head of department'], $k)).' '.$student($id($v)); continue; }
            if ($k === 'option') { $parts[] = 'Service '.$name('fee_options', $v); continue; }
            if ($k === 'route') { $parts[] = 'Bus route '.$name('bus_routes', $v); continue; }
            if ($k === 'students') { $n = count(array_filter(explode(',', (string) $v))); $parts[] = $n.($n === 1 ? ' student' : ' students'); continue; }
            if ($k === 'class_type_id') { $parts[] = 'Class type '.$name('class_types', $v); continue; }
            if ($k === 'user_type') { $parts[] = 'Role '.ucwords(str_replace('_', ' ', (string) (DB::table('user_types')->where('id', $id($v))->value('title') ?: $v))); continue; }
            if (preg_match('/^(t1|t2|t3|t4|exm|a\d|e\d|ct\d|pw)_\d+$/', $k)) continue;   // individual mark boxes: summarised below
            if (preg_match('/_id$/', $k)) continue;
            if (in_array($k, ['amt_paid', 'amount'], true) && is_numeric($v)) $v = \App\Support\Money::fmt($v);
            if (is_string($v) && strlen($v) > 120) $v = mb_substr($v, 0, 117).'…';
            $parts[] = ($label ?: ucfirst(str_replace('_', ' ', $k))).' '.$v;
        }
        $marks = count(array_filter(array_keys($in), function ($k) { return preg_match('/^(t1|t2|t3|t4|exm|a\d|e\d|ct\d|pw)_\d+$/', $k); }));
        if ($marks) $parts[] = $marks.' scores';
        if (!empty($d['result'])) $parts[] = 'Not done: '.$d['result'];

        return implode(' · ', array_filter($parts));
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
            'payments.send_all_bills' => 'Sent bills to parents', 'finance.term_days' => 'Changed school days this term', 'finance.options.store' => 'Added a service',
            'finance.options.update' => 'Edited a service', 'finance.routes.store' => 'Added a bus route', 'finance.routes.update' => 'Edited a bus route', 'marks.comment_update' => 'Wrote a report comment',
            'class_types.store' => 'Added a class type', 'class_types.update' => 'Renamed a class type', 'departments.store' => 'Added a department', 'departments.update' => 'Edited a department', 'payments.send_invoice' => 'Sent an invoice', 'finance.services.add' => 'Added students to a service',
        ];
        if ($name && isset($labels[$name])) return $labels[$name];
        if (!$name) return $req->method().' '.$req->path();
        $parts = explode('.', $name);
        $verb = ['store' => 'Created', 'update' => 'Edited', 'destroy' => 'Deleted', 'save' => 'Saved'][end($parts)] ?? str_replace('_', ' ', end($parts));

        return ucfirst($verb).' · '.str_replace('_', ' ', implode(' ', array_slice($parts, 0, -1)) ?: $name);
    }
}
