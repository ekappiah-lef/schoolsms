<?php

namespace App\Http\Controllers\Control;

use App\Control\Control;
use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** LEF Control Centre: create schools and manage their switches, scheme, settings and keys (LEF super admins only). */
class ControlController extends Controller
{
    const RESERVED = ['control', 'www', 'api', 'mail', 'admin', 'edusuite', 'test', 'demo'];
    const KEYS = ['MAIL_DRIVER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        'SMS_DRIVER', 'SMS_SENDER_ID', 'SMSONLINEGH_API_KEY', 'NOTICE_ALLOWLIST', 'MOMO_DRIVER', 'MOMO_COLLECTION_SUBSCRIPTION_KEY', 'MOMO_API_USER',
        'MOMO_API_KEY', 'CLEARENROLL_API_URL', 'CLEARENROLL_API_KEY', 'WHATSAPP_ENABLED', 'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID',
        'WHATSAPP_TEMPLATE', 'WHATSAPP_TEMPLATE_LANG', 'STAFF_CLOCK_DEVICES', 'STAFF_CLOCK_TOKEN'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(config('app.role') === 'control' && Qs::userIsSuperAdmin(), 403);
            return $next($request);
        });
    }

    public function index()
    {
        Control::collect();
        // The server's view of every school (version, health, backups), refreshed at most every two minutes.
        $fresh = DB::table('control_jobs')->where('type', 'inventory')->where('created_at', '>', now()->subMinutes(2))->exists();
        if (!$fresh) {
            try { Control::dispatch('inventory'); } catch (\Throwable $e) {}
        }

        return Inertia::render('Control/Index', [
            'schools' => DB::table('control_schools')->orderBy('name')->get()->map(function ($s) {
                $last = json_decode((string) $s->last_status, true) ?: [];
                return ['slug' => $s->slug, 'name' => $s->name, 'url' => $s->domain ? 'https://'.$s->domain : null, 'status' => $s->status,
                    'version' => $last['server']['version'] ?? null, 'healthy' => $last['server']['healthy'] ?? null, 'last_backup' => $last['server']['last_backup'] ?? null,
                    'students' => $last['school']['counts']['students'] ?? null, 'demo' => $last['school']['demo_mode'] ?? null,
                    'checked' => $s->checked_at, 'urls' => ['show' => route('control.show', $s->slug)]];
            })->values(),
            'unregistered' => collect(['edusuite' => 'Test school (edusuite.lefsignature.com)', 'desvy' => 'Desvy International School'])
                ->reject(function ($n, $slug) { return DB::table('control_schools')->where('slug', $slug)->exists(); })
                ->map(function ($n, $slug) { return ['slug' => $slug, 'name' => $n]; })->values(),
            'jobs' => $this->jobs(null),
            'urls' => ['create' => route('control.create'), 'register' => route('control.register'), 'self' => route('control.index')],
        ]);
    }

    public function create()
    {
        return Inertia::render('Control/Create', [
            'features' => config('school.FEATURES'),
            'schemes' => collect(config('school.schemes'))->map(function ($s, $k) { return $s['title'] ?? ucfirst(str_replace('_', ' ', $k)); }),
            'baseDomain' => 'edunetwork.lefsignature.com',
            'urls' => ['store' => route('control.store'), 'index' => route('control.index')],
        ]);
    }

    public function store(Request $req)
    {
        self::decode($req, ['features']);
        $d = $req->validate([
            'name' => 'required|string|min:3|max:120',
            'slug' => ['required', 'regex:/^[a-z][a-z0-9-]{1,29}$/', 'not_in:'.implode(',', self::RESERVED), 'unique:control_schools,slug'],
            'short' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'address' => 'nullable|string|max:191',
            'admin_name' => 'required|string|min:3|max:120', 'admin_email' => 'nullable|email', 'admin_phone' => 'nullable|string|max:30',
            'admin_username' => 'required|alpha_dash|min:4|max:40',
            'features' => 'nullable|array', 'features.*' => 'in:'.implode(',', array_keys(config('school.FEATURES'))),
            'scheme' => 'nullable|in:'.implode(',', array_keys(config('school.schemes'))),
            'has_boarding' => 'nullable|boolean', 'fee_reminders' => 'nullable|in:0,7,14,30',
        ], ['slug.regex' => 'Use small letters, numbers and dashes (e.g. desvy).', 'slug.not_in' => 'This web name is reserved.', 'slug.unique' => 'A school already uses this web name.'],
           ['slug' => 'Web name', 'admin_username' => 'Admin username']);

        $domain = $d['slug'].'.edunetwork.lefsignature.com';
        DB::table('control_schools')->insert(['slug' => $d['slug'], 'name' => $d['name'], 'domain' => $domain, 'status' => 'creating',
            'setup' => json_encode([
                'details' => array_intersect_key($d, array_flip(['name', 'short', 'phone', 'email', 'address', 'admin_name', 'admin_email', 'admin_phone', 'admin_username'])),
                'features' => array_values($d['features'] ?? []), 'scheme' => $d['scheme'] ?? 'standard',
                'settings' => ['has_boarding' => (int) ($d['has_boarding'] ?? 0), 'fee_reminders' => (int) ($d['fee_reminders'] ?? 0)],
            ]), 'created_at' => now(), 'updated_at' => now()]);
        Control::dispatch('create_school', ['slug' => $d['slug'], 'domain' => $domain, 'name' => $d['name']], $d['slug']);

        return Qs::json('Creating '.$d['name'].'. This takes a few minutes.', true);
    }

    public function show($slug)
    {
        Control::collect();
        $s = self::school($slug);
        $status = $s->token ? Control::call($s, 'get', 'status') : ['ok' => false, 'message' => $s->status === 'creating' ? 'Being created…' : 'No key yet.'];
        if (!empty($status['ok'])) {
            $last = json_decode((string) $s->last_status, true) ?: [];
            DB::table('control_schools')->where('id', $s->id)->update(['last_status' => json_encode(array_replace($last, ['school' => $status])), 'checked_at' => now()]);
            if ($s->name !== $status['name']) DB::table('control_schools')->where('id', $s->id)->update(['name' => $status['name']]);
        }
        $last = json_decode((string) $s->last_status, true) ?: [];

        return Inertia::render('Control/Show', [
            'school' => ['slug' => $s->slug, 'name' => $s->name, 'url' => $s->domain ? 'https://'.$s->domain : null, 'status' => $s->status, 'server' => $last['server'] ?? null],
            'live' => !empty($status['ok']) ? $status : null,
            'liveError' => empty($status['ok']) ? ($status['message'] ?? null) : null,
            'logins' => $s->logins ? json_decode(Crypt::decryptString($s->logins), true) : null,
            'keys' => self::KEYS,
            'features' => config('school.FEATURES'),
            'schemes' => collect(config('school.schemes'))->map(function ($x, $k) { return $x['title'] ?? ucfirst(str_replace('_', ' ', $k)); }),
            'jobs' => $this->jobs($slug),
            'urls' => [
                'self' => route('control.show', $slug), 'index' => route('control.index'),
                'config' => route('control.config', $slug), 'keys' => route('control.keys', $slug), 'action' => route('control.action', $slug),
                'password' => route('control.password', $slug), 'logins_seen' => route('control.logins_seen', $slug),
            ],
        ]);
    }

    /** Switches, assessment scheme and settings: applied at once in the school. */
    public function config(Request $req, $slug)
    {
        $s = self::school($slug);
        self::decode($req, ['features', 'settings']);
        $d = $req->validate(['features' => 'nullable|array', 'scheme' => 'nullable', 'settings' => 'nullable|array']);
        $scheme = $d['scheme'] ?? null;
        if (is_string($scheme) && strlen($scheme) && $scheme[0] === '{') $scheme = json_decode($scheme, true);
        $r = Control::call($s, 'put', 'config', ['features' => $d['features'] ?? [], 'scheme' => $scheme, 'settings' => $d['settings'] ?? []]);

        return Qs::json(!empty($r['ok']) ? 'Saved. The school uses it now.' : ($r['message'] ?? 'Not saved.'), !empty($r['ok']));
    }

    /** Email, SMS, MoMo, ClearEnroll, WhatsApp and clock-in keys: the server helper writes them and restarts the school. */
    public function keys(Request $req, $slug)
    {
        self::school($slug);
        self::decode($req, ['values']);
        $values = collect($req->input('values', []))->only(self::KEYS)->map(function ($v) { return trim((string) $v); })->filter(function ($v, $k) use ($req) {
            return $v !== '' || in_array($k, (array) $req->input('clear', []), true);
        })->all();
        if (!$values) return Qs::json('Nothing to change.', false);
        Control::dispatch('set_env', ['slug' => $slug, 'values' => $values], $slug);

        return Qs::json('Saving '.count($values).' setting(s); the school restarts in about a minute.', true);
    }

    public function action(Request $req, $slug)
    {
        self::school($slug);
        $type = $req->validate(['type' => 'required|in:restart,backup'])['type'];
        Control::dispatch($type, ['slug' => $slug], $slug);

        return Qs::json($type === 'restart' ? 'Restarting the school.' : 'Backing up the school.', true);
    }

    /** New password for the LEF login or the school admin, shown once. */
    public function password(Request $req, $slug)
    {
        $s = self::school($slug);
        $r = Control::call($s, 'post', 'password', ['who' => $req->input('who') === 'lef' ? 'lef' : 'admin']);
        if (empty($r['ok'])) return Qs::json($r['message'] ?? 'Not changed.', false);

        return response()->json(['ok' => true, 'msg' => 'New password set.', 'username' => $r['username'], 'password' => $r['password']]);
    }

    /** First logins of a new school were saved by you: remove them from the Control Centre. */
    public function loginsSeen($slug)
    {
        DB::table('control_schools')->where('slug', $slug)->update(['logins' => null]);

        return Qs::json('Removed from the Control Centre.', true);
    }

    /** Bring an existing school (created before the Control Centre) under it. */
    public function register(Request $req)
    {
        $d = $req->validate(['slug' => 'required|in:edusuite,desvy']);
        $names = ['edusuite' => ['Test school', 'edusuite.lefsignature.com'], 'desvy' => ['Desvy International School', 'desvy.edunetwork.lefsignature.com']];
        if (!DB::table('control_schools')->where('slug', $d['slug'])->exists()) {
            DB::table('control_schools')->insert(['slug' => $d['slug'], 'name' => $names[$d['slug']][0], 'domain' => $names[$d['slug']][1], 'status' => 'creating',
                'created_at' => now(), 'updated_at' => now()]);
        }
        Control::dispatch('issue_token', ['slug' => $d['slug']], $d['slug']);

        return Qs::json('Connecting '.$names[$d['slug']][0].' (it restarts once).', true);
    }

    /** Job log for the page (and its auto-refresh). */
    public function jobsFeed(Request $req)
    {
        Control::collect();

        return response()->json(['jobs' => $this->jobs($req->query('school'))]);
    }

    protected static function school($slug)
    {
        $s = DB::table('control_schools')->where('slug', $slug)->first();
        abort_unless($s, 404);

        return $s;
    }

    /** The pages send lists as JSON text (form posts); turn them back into arrays. */
    protected static function decode(Request $req, array $keys): void
    {
        foreach ($keys as $k) {
            if (is_string($req->input($k))) $req->merge([$k => json_decode($req->input($k), true) ?: []]);
        }
    }

    protected function jobs(?string $slug)
    {
        $labels = ['create_school' => 'Create school', 'issue_token' => 'Connect school', 'set_env' => 'Change keys', 'restart' => 'Restart', 'backup' => 'Backup', 'inventory' => 'Check schools'];

        return DB::table('control_jobs')->when($slug, function ($q) use ($slug) { $q->where('school_slug', $slug); }, function ($q) { $q->where('type', '!=', 'inventory'); })
            ->orderByDesc('id')->limit(20)->get()->map(function ($j) use ($labels) {
                return ['id' => $j->id, 'type' => $labels[$j->type] ?? $j->type, 'school' => $j->school_slug, 'status' => $j->status, 'error' => $j->error,
                    'at' => \Carbon\Carbon::parse($j->created_at)->toIso8601String(), 'result' => json_decode((string) $j->result, true)];
            })->values();
    }
}
