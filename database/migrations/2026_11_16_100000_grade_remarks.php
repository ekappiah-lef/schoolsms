<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The remarks a grade can carry (Classes & subjects → Grade remarks). Starts with what the school already has:
 * the list typed in Settings, else the remarks its grades use, else the standard list.
 */
class GradeRemarks extends Migration
{
    public function up()
    {
        Schema::create('grade_remarks', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60)->unique();
            $t->timestamps();
        });

        $split = function ($text) { return array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text))); };
        $names = $split(DB::table('settings')->where('type', 'grade_remarks')->value('description'));
        if (!$names) {
            $names = DB::table('grades')->whereNotNull('remark')->where('remark', '!=', '')->orderBy('class_type_id')->orderByDesc('mark_to')->orderBy('id')->pluck('remark')->map('trim')->all();
        }
        if (!$names) {
            $names = ['Excellent', 'Very Good', 'Good', 'Credit', 'Pass', 'Fail'];
        }
        $now = now();
        foreach (array_values(array_unique($names)) as $name) {
            DB::table('grade_remarks')->insert(['name' => mb_substr($name, 0, 60), 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('settings')->where('type', 'grade_remarks')->delete();
    }

    public function down()
    {
        Schema::dropIfExists('grade_remarks');
    }
}
