<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LEF Control Centre: the schools it manages and the server jobs it has asked for. Only in the Control Centre's database. */
class ControlCentre extends Migration
{
    public function up()
    {
        if (config('app.role') !== 'control') return;

        if (!Schema::hasTable('control_schools')) {
            Schema::create('control_schools', function (Blueprint $table) {
                $table->increments('id');
                $table->string('slug', 30)->unique();
                $table->string('name', 120);
                $table->string('domain', 150)->nullable();
                $table->unsignedInteger('port')->nullable();
                $table->text('token')->nullable();           // encrypted Control Centre key for this school
                $table->string('status', 20)->default('creating');   // creating | setting_up | active | error
                $table->text('setup')->nullable();            // JSON: what was chosen when the school was created
                $table->text('logins')->nullable();           // encrypted JSON: first logins, shown once
                $table->text('last_status')->nullable();      // JSON: last status read from the school / server
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('control_jobs')) {
            Schema::create('control_jobs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('uuid', 64)->unique();
                $table->string('school_slug', 30)->nullable()->index();
                $table->string('type', 30);
                $table->text('payload')->nullable();
                $table->string('status', 12)->default('pending');   // pending | done | failed
                $table->text('result')->nullable();
                $table->text('error')->nullable();
                $table->unsignedInteger('requested_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('control_jobs');
        Schema::dropIfExists('control_schools');
    }
}
