<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Key/value configuration of this school (table school_config), written by the LEF Control Centre. JSON values. */
class SchoolConfig
{
    protected static $cache = null;

    public static function get(string $key, $default = null)
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, $value): void
    {
        DB::table('school_config')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        self::$cache = null;
    }

    public static function forget(string $key): void
    {
        DB::table('school_config')->where('key', $key)->delete();
        self::$cache = null;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = Schema::hasTable('school_config')
                    ? DB::table('school_config')->pluck('value', 'key')->map(function ($v) { return json_decode($v, true); })->all()
                    : [];
            } catch (\Throwable $e) {
                self::$cache = [];   // no database yet (e.g. while installing)
            }
        }

        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
