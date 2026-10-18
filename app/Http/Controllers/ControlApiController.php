<?php

namespace App\Http\Controllers;

use App\Helpers\Qs;
use App\Support\Assessment;
use App\Support\School;
use App\Support\SchoolConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The LEF Control Centre talks to each school here (no login: a secret token per school, CONTROL_API_TOKEN).
 *   GET  /control-api/status          name, version, counts, switches, scheme, settings
 *   PUT  /control-api/config          switches, assessment scheme, settings
 *   POST /control-api/init            once, on a new school: empty it, Ghana lists, school details, LEF + school admin logins
 *   POST /control-api/password        new password for the LEF login or the school admin
 */
class ControlApiController extends Controller
{
    const SETTINGS = ['has_boarding', 'birthday_messages', 'fee_reminders', 'lock_exam'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $token = (string) config('services.control.token');
            if ($token === '') abort(404);
            if (!hash_equals($token, (string) $request->bearerToken())) {
                return response()->json(['ok' => false, 'message' => 'Not allowed.'], 401);
            }
            return $next($request);
        });
    }

    public function status()
    {
        $count = function ($types) { return DB::table('users')->whereIn('user_type', (array) $types)->count(); };

        return response()->json([
            'ok' => true,
            'name' => Qs::getSystemName(),
            'url' => config('app.url'),
            'version' => config('app.version'),
            'initialized' => (bool) SchoolConfig::get('initialized'),
            'session' => Qs::getCurrentSession(),
            'counts' => [
                'students' => DB::table('student_records')->where('grad', 0)->count(),
                'teachers' => $count('teacher'),
                'staff' => $count(['teacher', 'non_teaching', 'accountant', 'academic_admin', 'admin', 'librarian']),
                'parents' => $count('parent'),
                'classes' => DB::table('my_classes')->count(),
            ],
            'features' => School::features(),
            'scheme' => Assessment::key(),
            'scheme_detail' => Assessment::scheme(),
            'settings' => collect(self::SETTINGS)->mapWithKeys(function ($k) { return [$k => (string) Qs::getSetting($k)]; }),
            'demo_mode' => (bool) array_filter(explode(',', (string) config('sms.allowlist'))),
            'schemes' => collect(config('school.schemes'))->map(function ($s, $k) { return $s['title'] ?? ucfirst(str_replace('_', ' ', $k)); }),
            'available_features' => config('school.FEATURES'),
        ]);
    }

    public function config(Request $req)
    {
        $known = array_keys((array) config('school.FEATURES'));
        $d = $req->validate([
            'features' => 'sometimes|array',
            'features.*' => 'in:'.implode(',', $known),
            'scheme' => 'sometimes|nullable',
            'settings' => 'sometimes|array',
        ]);
        if (array_key_exists('features', $d)) {
            SchoolConfig::set('features', array_values(array_unique($d['features'] ?? [])));
        }
        if (array_key_exists('scheme', $d)) {
            $scheme = $d['scheme'];
            if ($scheme === null || $scheme === '') {
                SchoolConfig::forget('scheme');
            } elseif (is_string($scheme)) {
                abort_unless(isset(config('school.schemes')[$scheme]), 422, 'Unknown scheme.');
                SchoolConfig::set('scheme', $scheme);
            } else {
                $error = self::schemeError((array) $scheme);
                if ($error) return response()->json(['ok' => false, 'message' => $error], 422);
                SchoolConfig::set('scheme', self::cleanScheme((array) $scheme));
            }
        }
        foreach ((array) ($d['settings'] ?? []) as $k => $v) {
            if (!in_array($k, self::SETTINGS, true)) continue;
            $v = $k === 'fee_reminders' ? (in_array((int) $v, [0, 7, 14, 30], true) ? (int) $v : 0) : ((int) (bool) $v);
            DB::table('settings')->updateOrInsert(['type' => $k], ['description' => (string) $v]);
        }

        return $this->status();
    }

    /** First setup of a new school: zero data, Ghana reference lists, school details, the two logins. */
    public function init(Request $req)
    {
        if (SchoolConfig::get('initialized')) {
            return response()->json(['ok' => false, 'message' => 'This school is already set up.'], 409);
        }
        $d = $req->validate([
            'name' => 'required|string|min:3|max:120', 'short' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email', 'address' => 'nullable|string|max:191', 'session' => 'nullable|string|max:9',
            'admin_name' => 'required|string|min:3|max:120', 'admin_email' => 'nullable|email', 'admin_phone' => 'nullable|string|max:30',
            'admin_username' => 'required|alpha_dash|min:4|max:40', 'lef_username' => 'nullable|alpha_dash|min:4|max:40',
        ]);

        Artisan::call('school:clear-data', ['--force' => true, '--keep' => 'cj']);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('users')->where('id', '!=', 1)->delete();
            DB::table('staff_records')->where('user_id', '!=', 1)->delete();
            foreach (['dorms', 'notification_logs', 'password_resets'] as $t) DB::table($t)->delete();
            $ref = json_decode(file_get_contents(database_path('seeders/data/ghana_reference.json')), true);
            foreach ($ref as $table => $rows) {
                DB::table($table)->delete();
                foreach (array_chunk($rows, 200) as $chunk) DB::table($table)->insert($chunk);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $pass = function () { return Str::random(4).'-'.random_int(1000, 9999).'-'.Str::random(4); };
        $lefPass = $pass(); $adminPass = $pass();
        DB::table('users')->updateOrInsert(['id' => 1], ['name' => 'LEF Signature', 'username' => $d['lef_username'] ?? 'lefadmin', 'email' => null, 'phone' => null,
            'password' => Hash::make($lefPass), 'remember_token' => null, 'user_type' => 'super_admin', 'code' => strtoupper(Str::random(10)), 'updated_at' => now()]);
        $id = DB::table('users')->insertGetId(['name' => $d['admin_name'], 'username' => $d['admin_username'], 'email' => $d['admin_email'] ?? null, 'phone' => $d['admin_phone'] ?? null,
            'user_type' => 'admin', 'code' => strtoupper(Str::random(10)), 'photo' => Qs::getDefaultUserImage(), 'gender' => 'Male',
            'password' => Hash::make($adminPass), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('staff_records')->insert(['user_id' => $id, 'code' => strtoupper($d['short'] ?: 'SCH').'/STAFF/'.date('Y/m').'/'.random_int(1000, 9999), 'emp_date' => today(), 'created_at' => now(), 'updated_at' => now()]);

        $start = (int) date('n') >= 8 ? (int) date('Y') : (int) date('Y') - 1;
        foreach (['system_name' => $d['name'], 'system_title' => $d['short'] ?? '', 'phone' => $d['phone'] ?? '', 'system_email' => $d['email'] ?? '',
                  'address' => $d['address'] ?? '', 'current_session' => $d['session'] ?? ($start.'-'.($start + 1)), 'term_begins' => '', 'term_ends' => '',
                  'lock_exam' => '0', 'has_boarding' => '0', 'birthday_messages' => '1', 'fee_reminders' => '0'] as $k => $v) {
            DB::table('settings')->updateOrInsert(['type' => $k], ['description' => $v]);
        }
        DB::table('settings')->whereIn('type', ['services_billed_term', 'fee_reminders_last', 'demo_seed'])->delete();
        SchoolConfig::set('initialized', now()->toDateTimeString());

        return response()->json(['ok' => true, 'logins' => [
            ['username' => $d['lef_username'] ?? 'lefadmin', 'password' => $lefPass, 'who' => 'LEF super admin'],
            ['username' => $d['admin_username'], 'password' => $adminPass, 'who' => 'School admin'],
        ]]);
    }

    /** A new password for the LEF login (user 1) or the school admin (the first admin). */
    public function password(Request $req)
    {
        $d = $req->validate(['who' => 'required|in:lef,admin']);
        $user = $d['who'] === 'lef' ? DB::table('users')->where('id', 1)->first() : DB::table('users')->where('user_type', 'admin')->orderBy('id')->first();
        if (!$user) return response()->json(['ok' => false, 'message' => 'No such login.'], 404);
        $pass = Str::random(4).'-'.random_int(1000, 9999).'-'.Str::random(4);
        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($pass), 'remember_token' => null]);

        return response()->json(['ok' => true, 'username' => $user->username, 'password' => $pass]);
    }

    /** A scheme built in the Control Centre must be complete and add up. */
    public static function schemeError(array $s): ?string
    {
        $parts = $s['parts'] ?? null;
        if (!is_array($parts) || count($parts) < 2) return 'A scheme needs at least two score boxes.';
        $keys = [];
        foreach ($parts as $p) {
            if (empty($p['key']) || !preg_match('/^[a-z][a-z0-9_]{0,9}$/', $p['key'])) return 'Every box needs a short key (letters and numbers).';
            if (in_array($p['key'], $keys, true)) return 'Box keys must be different.';
            if (!isset($p['max']) || !is_numeric($p['max']) || $p['max'] <= 0 || $p['max'] > 100) return 'Every box needs a maximum between 1 and 100.';
            $keys[] = $p['key'];
        }
        if (empty($s['exam']) || !in_array($s['exam'], $keys, true)) return 'Choose which box is the exam.';
        $class = collect($parts)->where('key', '!=', $s['exam'])->sum('max');
        $w = $s['weights'] ?? null;
        if ($w) {
            if (!isset($w['class'], $w['exam']) || abs($w['class'] + $w['exam'] - 1) > 0.001) return 'Class and exam percentages must add up to 100%.';
            if (abs($class - 100) > 0.001) return 'The class boxes must add up to 100 (they are '.$class.').';
        } elseif (abs($class + collect($parts)->firstWhere('key', $s['exam'])['max'] - 100) > 0.001) {
            return 'All boxes together must add up to 100.';
        }

        return null;
    }

    protected static function cleanScheme(array $s): array
    {
        return [
            'title' => isset($s['title']) ? mb_substr((string) $s['title'], 0, 120) : null,
            'groups' => !empty($s['groups']) ? collect($s['groups'])->mapWithKeys(function ($v, $k) { return [preg_replace('/[^a-z0-9_]/', '', strtolower($k)) => mb_substr((string) $v, 0, 40)]; })->all() : null,
            'parts' => collect($s['parts'])->map(function ($p) {
                return array_filter(['key' => $p['key'], 'label' => mb_substr((string) ($p['label'] ?? $p['key']), 0, 30), 'max' => $p['max'] + 0,
                    'group' => isset($p['group']) ? preg_replace('/[^a-z0-9_]/', '', strtolower($p['group'])) : null], function ($v) { return $v !== null; });
            })->values()->all(),
            'exam' => $s['exam'],
            'weights' => !empty($s['weights']) ? ['class' => $s['weights']['class'] + 0, 'exam' => $s['weights']['exam'] + 0] : null,
            'report' => !empty($s['weights'])
                ? ['ca1' => false, 'ca2' => false, 'ca_total' => 'CLASS SCORE ('.round($s['weights']['class'] * 100).'%)', 'exam' => 'EXAM SCORE ('.round($s['weights']['exam'] * 100).'%)', 'total' => 'TOTAL (100%)']
                : ['ca1' => false, 'ca2' => false, 'ca_total' => 'CLASS SCORE ('.collect($s['parts'])->where('key', '!=', $s['exam'])->sum('max').')',
                   'exam' => 'EXAM ('.collect($s['parts'])->firstWhere('key', $s['exam'])['max'].')', 'total' => 'TOTAL (100%)'],
        ];
    }
}
