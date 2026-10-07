<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Empties the school so it can be set up from zero with real (or test) data.
 *
 * Keeps: super admin accounts, the role logins named with --keep (default admin, academic, accountant),
 * school settings and logo, reference lists (nationalities, regions, LGAs, blood groups, class types,
 * grading scales, skills, user types).
 * Removes: every other user (teachers, parents, students, other staff), classes, sections, subjects,
 * dormitories, timetables, exams, marks, attendance, messages, fee setup, discounts, services and
 * bus routes, shop items, all bills, payments, receipts, sales, income, expenses and the audit trail.
 *
 * Take a database backup first. Run: php artisan school:clear-data
 */
class ClearSchoolData extends Command
{
    protected $signature = 'school:clear-data {--keep=admin,academic,accountant : Usernames to keep besides super admins} {--force : Do not ask}';

    protected $description = 'Remove all school data (students, parents, classes, fees, payments…) and keep logins + reference lists';

    const CLEAR = [
        // academics
        'my_classes', 'sections', 'subjects', 'dorms', 'time_tables', 'time_table_records', 'time_slots',
        'exams', 'exam_records', 'marks', 'pins', 'promotions', 'attendances', 'books', 'book_requests',
        // people
        'student_records', 'student_details', 'parent_details',
        // finance
        'payments', 'payment_items', 'payment_records', 'receipts', 'fee_discounts', 'fee_options', 'bus_routes',
        'optional_fee_charges', 'optional_fee_receipts', 'inventory_items', 'stock_movements',
        'finance_transactions', 'finance_logs', 'momo_payments',
        // communication
        'messages', 'notification_logs',
    ];

    public function handle()
    {
        $keepNames = array_filter(array_map('trim', explode(',', (string) $this->option('keep'))));
        $keep = DB::table('users')->where('user_type', 'super_admin')->orWhereIn('username', $keepNames)->get(['id', 'name', 'username', 'user_type']);

        $this->info('These logins are kept:');
        $this->table(['Name', 'Username', 'Role'], $keep->map(function ($u) { return [$u->name, $u->username, $u->user_type]; })->all());
        $this->warn('Everything else listed in this command is deleted: '.(DB::table('users')->count() - $keep->count()).' users, all classes, fees, payments, exams and records.');

        if (!$this->option('force') && !$this->confirm('Have you made a database backup, and do you want to clear the school data?')) {
            $this->line('Nothing changed.');
            return 0;
        }

        $ids = $keep->pluck('id')->all();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::CLEAR as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
            DB::table('staff_records')->whereNotIn('user_id', $ids)->delete();
            DB::table('users')->whereNotIn('id', $ids)->delete();
            DB::table('password_resets')->truncate();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Done. The school is empty and ready to be set up: classes, sections, subjects, fee setup, then staff, parents and students.');

        return 0;
    }
}
