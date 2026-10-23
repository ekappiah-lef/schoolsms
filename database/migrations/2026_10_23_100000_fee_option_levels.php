<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services offered only to some levels (class types), e.g. "Lunch and Fruits (Preschool)".
 * Empty = every level. Existing services named "… (Level)" or "… (Level & Level)" are linked
 * to the levels in brackets (a level also matches its initials, e.g. JHS = Junior High School).
 */
class FeeOptionLevels extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('fee_options', 'class_type_ids')) {
            Schema::table('fee_options', function (Blueprint $t) {
                $t->text('class_type_ids')->nullable()->after('amount');
            });
        }

        $types = DB::table('class_types')->get(['id', 'name']);
        $norm = function ($s) { return strtolower(trim(preg_replace('/\s+/', ' ', (string) $s))); };
        $initials = function ($s) { return strtolower(implode('', array_map(function ($w) { return $w[0]; }, preg_split('/\s+/', trim($s))))); };

        foreach (DB::table('fee_options')->whereNull('class_type_ids')->get(['id', 'name']) as $o) {
            if (!preg_match('/\(([^()]+)\)\s*$/', $o->name, $m)) continue;
            $ids = [];
            foreach (preg_split('/\s*(?:&|,|\band\b|\/)\s*/i', $m[1]) as $part) {
                $part = $norm($part);
                if ($part === '') continue;
                $t = $types->first(function ($t) use ($part, $norm, $initials) { return $norm($t->name) === $part || $initials($t->name) === str_replace(' ', '', $part); });
                if (!$t) { $ids = []; break; }   // not all levels: leave it for everyone
                $ids[] = (int) $t->id;
            }
            if ($ids) DB::table('fee_options')->where('id', $o->id)->update(['class_type_ids' => json_encode(array_values(array_unique($ids)))]);
        }
    }

    public function down()
    {
        Schema::table('fee_options', function (Blueprint $t) {
            $t->dropColumn('class_type_ids');
        });
    }
}
