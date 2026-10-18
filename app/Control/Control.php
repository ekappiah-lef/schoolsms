<?php

namespace App\Control;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LEF Control Centre plumbing:
 *  - server jobs: a JSON file dropped into the server helper's folder (deploy/agent/agent.py); results are collected later;
 *  - schools: each school's own control connection (ControlApiController), with the school's key.
 */
class Control
{
    public static function jobsDir(): string
    {
        return rtrim((string) env('CONTROL_JOBS_DIR', '/control-jobs'), '/');
    }

    /** Ask the server helper to do something; returns the job's uuid. */
    public static function dispatch(string $type, array $payload = [], ?string $slug = null): string
    {
        $uuid = (string) Str::uuid();
        $file = self::jobsDir().'/pending/'.$uuid.'.json';
        if (@file_put_contents($file, json_encode(['type' => $type, 'payload' => $payload])) === false) {
            throw new \RuntimeException('Could not reach the server helper (jobs folder not writable).');
        }
        DB::table('control_jobs')->insert(['uuid' => $uuid, 'school_slug' => $slug, 'type' => $type, 'payload' => json_encode(self::safePayload($payload)),
            'status' => 'pending', 'requested_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);

        return $uuid;
    }

    /** Pick up finished jobs and act on them (store a new school's key, set the school up, …). */
    public static function collect(): void
    {
        foreach (DB::table('control_jobs')->where('status', 'pending')->get() as $job) {
            $file = self::jobsDir().'/done/'.$job->uuid.'.json';
            if (!is_readable($file)) {
                if ($job->created_at < now()->subMinutes(30)) {
                    DB::table('control_jobs')->where('id', $job->id)->update(['status' => 'failed', 'error' => 'No answer from the server helper.', 'updated_at' => now()]);
                }
                continue;
            }
            $done = json_decode((string) file_get_contents($file), true) ?: [];
            $result = $done['result'] ?? [];
            $ok = !empty($done['ok']);
            $error = $ok ? null : ($done['error'] ?? 'Failed.');
            if ($ok) {
                try {
                    self::after($job, $result);
                } catch (\Throwable $e) {
                    $ok = false;
                    $error = $e->getMessage();
                }
            } elseif ($job->type === 'create_school') {
                DB::table('control_schools')->where('slug', $job->school_slug)->update(['status' => 'error', 'updated_at' => now()]);
            }
            unset($result['token']);
            DB::table('control_jobs')->where('id', $job->id)->update(['status' => $ok ? 'done' : 'failed', 'result' => json_encode($result), 'error' => $error, 'updated_at' => now()]);
        }
    }

    protected static function after($job, array $result): void
    {
        $school = DB::table('control_schools')->where('slug', $job->school_slug)->first();
        switch ($job->type) {
            case 'create_school':
            case 'issue_token':
                DB::table('control_schools')->where('id', $school->id)->update([
                    'token' => Crypt::encryptString($result['token']), 'port' => $result['port'] ?? null, 'domain' => $result['domain'] ?? $school->domain,
                    'status' => $job->type === 'create_school' ? 'setting_up' : 'active', 'updated_at' => now(),
                ]);
                if ($job->type === 'create_school') {
                    self::setUp(DB::table('control_schools')->find($school->id));
                }
                break;
            case 'inventory':
                foreach ($result['schools'] ?? [] as $s) {
                    $row = DB::table('control_schools')->where('slug', $s['slug'])->first();
                    if (!$row) continue;
                    $last = json_decode((string) $row->last_status, true) ?: [];
                    DB::table('control_schools')->where('id', $row->id)->update(['last_status' => json_encode(array_replace($last, ['server' => $s])), 'checked_at' => now()]);
                }
                break;
        }
    }

    /** A new school is running: empty it, give it its details and logins, then apply the switches chosen on the form. */
    public static function setUp($school): void
    {
        $setup = json_decode((string) $school->setup, true) ?: [];
        $r = self::call($school, 'post', 'init', $setup['details'] ?? []);
        if (empty($r['ok'])) throw new \RuntimeException('Setting up the school failed: '.($r['message'] ?? 'no answer'));
        DB::table('control_schools')->where('id', $school->id)->update(['logins' => Crypt::encryptString(json_encode($r['logins'])), 'updated_at' => now()]);
        $c = self::call($school, 'put', 'config', array_filter(['features' => $setup['features'] ?? [], 'scheme' => $setup['scheme'] ?? null, 'settings' => $setup['settings'] ?? []], function ($v) { return $v !== null; }));
        if (empty($c['ok'])) throw new \RuntimeException('Applying the switches failed: '.($c['message'] ?? 'no answer'));
        DB::table('control_schools')->where('id', $school->id)->update(['status' => 'active', 'updated_at' => now()]);
    }

    /** Call a school's control connection. */
    public static function call($school, string $method, string $path, array $data = []): array
    {
        if (!$school->token) return ['ok' => false, 'message' => 'This school has no Control Centre key yet.'];
        // Through the server's own nginx (HTTPS): inside the Control Centre's container, host.docker.internal is the server.
        $host = gethostbyname('host.docker.internal');
        $opts = $host !== 'host.docker.internal' ? ['curl' => [CURLOPT_RESOLVE => [$school->domain.':443:'.$host]]] : [];
        try {
            $res = Http::withToken(Crypt::decryptString($school->token))->acceptJson()->timeout(60)->withOptions($opts)
                ->{$method}('https://'.$school->domain.'/control-api/'.$path, $data);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the school: '.$e->getMessage()];
        }
        $json = $res->json() ?: [];

        return $res->successful() ? array_replace(['ok' => true], $json) : ['ok' => false, 'message' => $json['message'] ?? ('HTTP '.$res->status())];
    }

    /** Never keep secrets (keys, passwords) in the job log. */
    protected static function safePayload(array $p): array
    {
        if (isset($p['values']) && is_array($p['values'])) {
            $p['values'] = array_map(function ($v) { return $v === '' ? '' : '••••'; }, $p['values']);
        }

        return $p;
    }
}
