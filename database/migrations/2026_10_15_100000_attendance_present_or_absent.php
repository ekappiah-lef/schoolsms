<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The register is present or absent only: a child marked late was in school, so counts as present. */
class AttendancePresentOrAbsent extends Migration
{
    public function up()
    {
        DB::table('attendances')->where('status', 'late')->update(['status' => 'present']);
    }

    public function down()
    {
        // Late marks are not restored.
    }
}
