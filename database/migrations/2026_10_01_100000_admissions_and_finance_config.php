<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissions + finance configuration.
 *
 * Additive only: new tables and nullable columns, so existing records and
 * the classic (Blade) screens keep working unchanged.
 */
class AdmissionsAndFinanceConfig extends Migration
{
    public function up()
    {
        // Class capacity, e.g. "Class 6 Gold (18/24)".
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->after('name');
        });

        // Full admission date (dd/mm/yyyy) and the session the student joined in,
        // which decides whether "new student" or "old student" fees apply.
        Schema::table('student_records', function (Blueprint $table) {
            $table->date('admission_date')->nullable()->after('year_admitted');
            $table->string('admitted_session', 20)->nullable()->after('admission_date');
        });

        // Extra details from the admission form (Student Information Update).
        Schema::create('student_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('first_name', 60)->nullable();
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60)->nullable();
            $table->string('height', 20)->nullable();
            $table->string('weight', 20)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('religion', 60)->nullable();
            $table->string('past_school', 150)->nullable();
            $table->string('past_school_address', 200)->nullable();
            $table->string('past_qualification', 150)->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->string('insurance_card_no', 60)->nullable();
            $table->string('insurance_provider', 100)->nullable();
            $table->string('insurance_category', 60)->nullable();
            $table->string('insurance_coverage_limit', 60)->nullable();
            $table->date('insurance_valid_until')->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->string('doctor_name', 100)->nullable();
            $table->string('doctor_hospital', 150)->nullable();
            $table->string('doctor_contact', 40)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Father / mother / guardian details, kept on the parent account.
        Schema::create('parent_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            foreach (['father', 'mother'] as $p) {
                $table->string("{$p}_name", 120)->nullable();
                $table->string("{$p}_phone", 30)->nullable();
                $table->string("{$p}_ghana_card", 30)->nullable();
                $table->string("{$p}_occupation", 100)->nullable();
                $table->string("{$p}_workplace", 200)->nullable();
                $table->string("{$p}_email", 100)->nullable();
                $table->string("{$p}_residential_address", 200)->nullable();
                $table->string("{$p}_postal_address", 200)->nullable();
            }
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_ghana_card', 30)->nullable();
            $table->string('guardian_relationship', 60)->nullable();
            $table->string('guardian_address', 200)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // School fees: who a fee applies to, and its itemised breakdown.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('student_category', 10)->default('all')->after('my_class_id'); // all | new | old
        });

        Schema::create('payment_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payment_id');
            $table->string('name', 100);
            $table->integer('amount');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });

        // Optional services priced in Finance configuration.
        Schema::create('fee_options', function (Blueprint $table) {
            $table->increments('id');
            $table->string('group', 20); // feeding | extracurricular | books
            $table->string('name', 100);
            $table->integer('amount');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bus_routes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->integer('amount_both');
            $table->integer('amount_one_way');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // A student's chosen optional services for a session (the "Optional fees" invoice).
        Schema::create('optional_fee_charges', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('student_id'); // users.id, like payment_records
            $table->string('year', 20);
            $table->string('group', 20); // feeding | bus | extracurricular | books
            $table->string('label', 150);
            $table->unsignedInteger('fee_option_id')->nullable();
            $table->unsignedInteger('bus_route_id')->nullable();
            $table->string('bus_direction', 10)->nullable(); // in | out | both
            $table->integer('amount');
            $table->integer('amt_paid')->default(0);
            $table->timestamps();
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('optional_fee_receipts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('charge_id');
            $table->integer('amt_paid');
            $table->integer('balance');
            $table->string('year', 20);
            $table->timestamps();
            $table->foreign('charge_id')->references('id')->on('optional_fee_charges')->onDelete('cascade');
        });

        // Admission emails / SMS and whether they were delivered.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('student_id')->nullable();
            $table->string('channel', 10); // email | sms
            $table->string('kind', 40);    // admission | fees_link
            $table->string('recipient', 150);
            $table->string('status', 10);  // sent | failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('optional_fee_receipts');
        Schema::dropIfExists('optional_fee_charges');
        Schema::dropIfExists('bus_routes');
        Schema::dropIfExists('fee_options');
        Schema::dropIfExists('payment_items');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('student_category');
        });
        Schema::dropIfExists('parent_details');
        Schema::dropIfExists('student_details');
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn(['admission_date', 'admitted_session']);
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
}
