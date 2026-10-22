<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity log: every change anyone makes (and sign-ins), who, when, from where (super admin only).
 * Stopped services: a service taken off a student stays billed for the term but is not carried forward.
 */
class ActivityLogAndStoppedServices extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('user_name', 150)->nullable();
                $table->string('role', 40)->nullable();
                $table->string('action', 190);
                $table->string('method', 10)->nullable();
                $table->string('route', 120)->nullable()->index();
                $table->string('url', 300)->nullable();
                $table->text('details')->nullable();
                $table->unsignedSmallInteger('status')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('agent', 200)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('optional_fee_charges', 'stopped')) {
            Schema::table('optional_fee_charges', function (Blueprint $table) {
                $table->boolean('stopped')->default(false);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
        if (Schema::hasColumn('optional_fee_charges', 'stopped')) {
            Schema::table('optional_fee_charges', function (Blueprint $table) {
                $table->dropColumn('stopped');
            });
        }
    }
}
