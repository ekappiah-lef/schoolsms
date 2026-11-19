<?php

namespace App\Console\Commands;

use App\Helpers\Qs;
use App\Http\Controllers\SupportTeam\MarkController;
use App\Support\Fees;
use App\Support\School;
use App\Support\SchoolCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report cards go to parents on vacation day (the term's last day in the school calendar), once, at 3 pm
 * (schools with the reports_on_vacation switch). A report is held back while the pupil owes fees
 * (report_after_fees_cleared) or while a subject's marks are not approved.
 */
class SendVacationReports extends Command
{
    protected $signature = 'reports:vacation';

    protected $description = "Send this term's report cards to parents on vacation day";

    public function handle()
    {
        if (!School::has('reports_on_vacation')) return 0;
        $term = SchoolCalendar::current();
        if (!$term || !today()->isSameDay($term->ends_on)) return 0;

        $year = Qs::getCurrentSession();
        $exam = DB::table('exams')->where(['year' => $year, 'term' => Fees::termNow($year)])->where('name', '!=', 'TEST EXAM')->first();
        if (!$exam) {
            $this->line('No exam for this term.');
            return 0;
        }
        $key = $year.':'.$exam->id;
        if (DB::table('settings')->where('type', 'vacation_reports_sent')->value('description') === $key) return 0;
        DB::table('settings')->updateOrInsert(['type' => 'vacation_reports_sent'], ['description' => $key]);

        $r = app(MarkController::class)->sendTermReports((int) $exam->id, $year);
        $this->line("Reports sent {$r['sent']}, held for fees {$r['owing']}, held for unapproved marks {$r['unapproved']}, no parent contact {$r['failed']}.");

        return 0;
    }
}
