<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This school's configuration set by the LEF Control Centre: switches (features) and the assessment scheme.
 * When a key is missing the app falls back to config/school.php (the school's .env).
 */
class SchoolConfig extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('school_config')) {
            Schema::create('school_config', function (Blueprint $table) {
                $table->string('key', 60)->primary();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('school_config');
    }
}
