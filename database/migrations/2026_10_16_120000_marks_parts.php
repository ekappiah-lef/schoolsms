<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Score boxes of schemes other than the standard one (e.g. assignments, class exercises, CATs), as JSON. */
class MarksParts extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('marks', 'parts')) {
            Schema::table('marks', function (Blueprint $table) {
                $table->text('parts')->nullable()->after('exm');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('marks', 'parts')) {
            Schema::table('marks', function (Blueprint $table) {
                $table->dropColumn('parts');
            });
        }
    }
}
