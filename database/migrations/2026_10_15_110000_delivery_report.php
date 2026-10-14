<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery report: who sent each SMS / email, and which broadcast message it belongs to. */
class DeliveryReport extends Migration
{
    public function up()
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_logs', 'sent_by')) $table->unsignedInteger('sent_by')->nullable()->after('error');
            if (!Schema::hasColumn('notification_logs', 'message_id')) $table->unsignedInteger('message_id')->nullable()->after('sent_by');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['sent_by', 'message_id']);
        });
    }
}
