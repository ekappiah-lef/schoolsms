<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;

/**
 * Checks run before every deployment: the app boots against a migrated and seeded database,
 * the public pages load, private pages are locked, and each main area opens for the right role.
 * Read-only: nothing is written to the database.
 */
class SmokeTest extends TestCase
{
    protected function user(string $type): User
    {
        $user = User::where('user_type', $type)->first();
        $this->assertNotNull($user, "The seeded database has no $type user.");

        return $user;
    }

    public function testHealthCheckAnswers()
    {
        $this->get('/healthz')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function testLoginPageLoads()
    {
        $this->get('/login')->assertOk()->assertSee('Auth\/Login', false);
    }

    public function testGuestsAreSentToLogin()
    {
        foreach (['/', '/finance', '/timetables', '/exams', '/finance/audit-trail'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function testAdminPagesOpen()
    {
        $admin = $this->user('admin');
        foreach (['/dashboard', '/finance', '/finance/ledger', '/finance/audit-trail', '/exams', '/timetables', '/clearenroll/verify-student', '/staff', '/users', '/reports/design'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function testReportCardPreviewRendersForEveryClassType()
    {
        $admin = $this->user('admin');
        foreach (\Illuminate\Support\Facades\DB::table('class_types')->pluck('id') as $type) {
            $this->actingAs($admin)->get(route('reports.design.preview', $type))->assertOk()->assertSee('Sample Student');
        }
    }

    public function testReportCardCanShowTheKeyToTheGrading()
    {
        $admin = $this->user('admin');
        $type = \Illuminate\Support\Facades\DB::table('class_types')->value('id');
        $tpl = \App\Support\ReportTemplate::for((int) $type);
        $url = route('reports.design.preview', $type);
        $this->actingAs($admin)->post($url, ['template' => json_encode(array_merge($tpl, ['grading' => false]))])->assertOk()->assertDontSee('KEY TO THE GRADING');
        $tpl['grading'] = true;
        $tpl['columns'] = collect($tpl['columns'])->map(function ($c) { return $c['key'] === 'remark' ? array_merge($c, ['show' => false]) : $c; })->all();
        $tpl['groups'] = collect($tpl['groups'])->map(function ($g) { return array_merge($g, ['show' => false]); })->all();
        $tpl['key']['show'] = false;
        $html = $this->actingAs($admin)->post($url, ['template' => json_encode($tpl)])->assertOk()->getContent();
        $this->assertStringContainsString('KEY TO THE GRADING', $html);
        $this->assertStringNotContainsString('RATING</strong>', $html);
    }

    public function testAnalyticsAndDashboardOpenForAdmins()
    {
        foreach (['/analytics', '/dashboard', '/students/create', '/finance/config'] as $url) {
            $this->actingAs($this->user('admin'))->get($url)->assertOk();
        }
    }

    public function testAccountantAssignsServicesAndPrintsVouchers()
    {
        $acc = $this->user('accountant');
        $this->actingAs($acc)->get('/finance/assign')->assertOk()->assertSee('Finance\/AssignServices', false);
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if ($sr) {
            $this->actingAs($acc)->get('/finance/assign?student='.\App\Helpers\Qs::hash($sr->user_id))->assertOk()->assertSee('classLevels', false);
        }
        $t = \App\Models\FinanceTransaction::first();
        if ($t) {
            $this->actingAs($acc)->get(route('finance.transactions.voucher', \App\Helpers\Qs::hash($t->id)))->assertOk()->assertSee($t->category);
            $this->actingAs($acc)->get(route('finance.transactions.voucher_pdf', \App\Helpers\Qs::hash($t->id)))->assertOk();
        }
    }

    public function testSchoolCalendarAndClassTypesOpen()
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/school-calendar')->assertOk()->assertSee('Calendar\/Index', false);
        $this->actingAs($admin)->get('/class-types')->assertOk()->assertSee('Classes\/Types', false);
    }

    public function testTermsFollowTheMonthsWithoutACalendar()
    {
        \App\Support\SchoolCalendar::forget();
        $this->assertSame([2026, 1], \App\Support\SchoolCalendar::termOf(\Carbon\Carbon::create(2026, 10, 1)));
        $this->assertSame([2026, 2], \App\Support\SchoolCalendar::termOf(\Carbon\Carbon::create(2027, 2, 1)));
        $this->assertSame([2026, 3], \App\Support\SchoolCalendar::termOf(\Carbon\Carbon::create(2027, 6, 1)));
    }

    public function testParentSeesChildrenWithAttendance()
    {
        $this->actingAs($this->user('parent'))->get(route('my_children'))->assertOk()->assertSee('Parent\/Children', false);
    }

    public function testOnlyTheSuperAdminOpensTheActivityLog()
    {
        $this->actingAs($this->user('super_admin'))->get('/activity-log')->assertOk();
        $this->assertNotEquals(200, $this->actingAs($this->user('admin'))->get('/activity-log')->getStatusCode());
    }

    public function testAccountantCanOpenFinanceButNotExamSetup()
    {
        $accountant = $this->user('accountant');
        $this->actingAs($accountant)->get('/finance')->assertOk();
        $this->assertNotEquals(200, $this->actingAs($accountant)->get('/exams')->getStatusCode());
    }

    public function testParentsAndTeachersCannotOpenFinance()
    {
        foreach (['parent', 'teacher'] as $type) {
            $this->assertNotEquals(200, $this->actingAs($this->user($type))->get('/finance')->getStatusCode(), "$type opened the finance dashboard");
        }
    }

    public function testAccountantGoesThroughStudents()
    {
        $acc = $this->user('accountant');
        $this->assertNotEquals(200, $this->actingAs($acc)->get(route('students.index'))->getStatusCode());
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if ($sr) {
            $this->actingAs($acc)->get(route('students.edit', \App\Helpers\Qs::hash($sr->id)))->assertStatus(302);
            $this->actingAs($acc)->get(route('students.show', \App\Helpers\Qs::hash($sr->id)))->assertStatus(302);
            $this->actingAs($acc)->get('/finance/assign?student='.\App\Helpers\Qs::hash($sr->user_id))->assertOk();
        }
    }

    public function testStaffUsernameIsShort()
    {
        $this->assertMatchesRegularExpression('/^ama\.mensah\d*$/', \App\Helpers\Qs::nameUsername('Ama Serwaa Mensah'));
    }

    public function testPromotedStudentStillOwesTheOldClass()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        $to = \App\Models\MyClass::where('id', '!=', optional($sr)->my_class_id)->first();
        if (!$sr || !$to) return $this->assertTrue(true);
        $year = \App\Helpers\Qs::getCurrentSession();
        $start = (int) substr($year, 0, 4);
        $next = ($start + 1).'-'.($start + 2);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $old = \App\Models\Payment::create(['title' => 'Old class fee', 'amount' => 500, 'my_class_id' => $sr->my_class_id, 'student_category' => 'all', 'year' => $year, 'term' => 1, 'ref_no' => uniqid()]);
            \App\Models\PaymentRecord::create(['student_id' => $sr->user_id, 'payment_id' => $old->id, 'year' => $year, 'amt_paid' => 100]);
            $owedBefore = \App\Support\Fees::termInvoice($sr->user_id)['total'];
            $sr->update(['my_class_id' => $to->id, 'session' => $next]);
            $this->assertEquals($owedBefore, \App\Support\Fees::termInvoice($sr->user_id)['total']);
            $this->assertGreaterThanOrEqual(400, \App\Support\Fees::nextTermFees($sr->fresh(), $year, 3)['owed']);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testEditPageServicesStartNextTermAndImportedBusKeepsItsBill()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        $year = \App\Helpers\Qs::getCurrentSession();
        $term = \App\Support\Fees::termNow($year);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $route = \App\Models\BusRoute::where('active', true)->first();
            $before = \App\Models\OptionalFeeCharge::where('student_id', $sr->user_id)->count();
            $sel = ['feeding' => [], 'extracurricular' => [], 'bus_route_id' => $route ? $route->id : '', 'bus_direction' => $route ? array_keys(\App\Models\BusRoute::DIRECTIONS)[0] : ''];
            \App\Support\Fees::planServices($sr, $year, $sel);
            $this->assertSame($before, \App\Models\OptionalFeeCharge::where('student_id', $sr->user_id)->count());
            $this->assertEquals($sel['bus_route_id'], \App\Support\Fees::nextServices($sr->fresh(), $year)['bus_route_id']);

            if ($route) {
                \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'year' => $year, 'term' => $term])->delete();
                $old = \App\Models\OptionalFeeCharge::create(['student_id' => $sr->user_id, 'year' => $year, 'term' => $term, 'group' => 'bus', 'label' => 'Imported', 'amount' => 1234, 'amt_paid' => 100]);
                \App\Support\Fees::syncOptional($sr->user_id, $year, $sel);
                $bus = \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'year' => $year, 'term' => $term, 'group' => 'bus'])->get();
                $this->assertCount(1, $bus);
                $this->assertEquals($route->id, $bus[0]->bus_route_id);
                $this->assertEquals(1234, $bus[0]->amount);
                $this->assertEquals($old->id, $bus[0]->id);
            }
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testPromotedStudentIsNotBilledTheNewClassForTheOldYear()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        $to = \App\Models\MyClass::where('id', '!=', optional($sr)->my_class_id)->first();
        if (!$sr || !$to) return $this->assertTrue(true);
        $year = \App\Helpers\Qs::getCurrentSession();
        $start = (int) substr($year, 0, 4);
        $next = ($start + 1).'-'.($start + 2);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $make = function ($y, $amount) use ($to) {
                return \App\Models\Payment::create(['title' => 'Promotion test', 'amount' => $amount, 'my_class_id' => $to->id, 'student_category' => 'all', 'year' => $y, 'term' => 1, 'ref_no' => uniqid()]);
            };
            $old = $make($year, 200);
            $new = $make($next, 220);
            $sr->update(['my_class_id' => $to->id, 'session' => $next]);
            \App\Support\Fees::billSchoolFees($sr->fresh(), $year);
            $this->assertSame(0, \App\Models\PaymentRecord::where(['student_id' => $sr->user_id, 'payment_id' => $old->id])->count());
            $this->assertTrue(\App\Support\Fees::paymentsFor($sr->fresh(), $next)->contains('id', $new->id));
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testDirectBillForAnEarlierTerm()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        $year = \App\Helpers\Qs::getCurrentSession();
        $start = (int) substr($year, 0, 4);
        $last = ($start - 1).'-'.$start;
        $acc = $this->user('accountant');
        $super = $this->user('super_admin');
        $hash = \App\Helpers\Qs::hash($sr->user_id);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $before = \App\Support\Fees::termInvoice($sr->user_id)['total'];
            $services = \App\Support\Fees::selectionFor($sr->user_id, $year);
            $thisYear = \App\Support\FinanceSummary::fees(\App\Support\TermSelection::year($year))['total']['expected'];
            $this->actingAs($acc)->post(route('finance.assign.direct'), ['student' => $hash, 'label' => 'Lunch arrears', 'amount' => 150, 'year' => $last, 'term' => 3])->assertOk();
            $invoice = \App\Support\Fees::termInvoice($sr->user_id);
            $this->assertEquals($before + 150, $invoice['total']);
            $this->assertEquals(150, collect($invoice['forward']['lines'])->firstWhere('label', 'Lunch arrears · Term 3 · '.str_replace('-', ' – ', $last))['balance']);
            $this->assertEquals($services, \App\Support\Fees::selectionFor($sr->user_id, $year));
            $this->assertEquals($thisYear, \App\Support\FinanceSummary::fees(\App\Support\TermSelection::year($year))['total']['expected']);
            $this->actingAs($acc)->get(route('finance.direct_bills'))->assertOk()->assertSee('Lunch arrears');
            $this->actingAs($acc)->get('/finance/assign?student='.$hash)->assertOk()->assertDontSee('Lunch arrears');

            $c = \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'group' => 'direct'])->latest('id')->first();
            // The accountant can cancel it (kept in the audit trail) and put it back from there.
            $this->actingAs($acc)->delete(route('finance.assign.direct.remove', $c->id), ['reason' => 'Wrong child'])->assertOk();
            $this->assertNull($c->fresh());
            $log = \Illuminate\Support\Facades\DB::table('finance_logs')->where(['action' => 'cancel_bill', 'subject_id' => $c->id])->first();
            $this->assertSame('Wrong child', $log->reason);
            $this->actingAs($acc)->postJson(route('finance.audit.restore', $log->id))->assertJson(['ok' => true]);
            $c = \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'group' => 'direct'])->latest('id')->first();
            $this->assertEquals(150, $c->amount);

            // It is paid like any other bill, oldest first; once paid only part way it cannot be removed.
            \App\Support\Fees::applyPayment($sr->user_id, 50, 'Cash');
            $this->assertEquals(50, $c->fresh()->amt_paid);
            $this->assertEquals($before + 100, \App\Support\Fees::termInvoice($sr->user_id)['total']);
            $this->actingAs($super)->delete(route('finance.assign.direct.remove', $c->id))->assertOk();
            $this->assertNotNull($c->fresh());

            \App\Models\OptionalFeeReceipt::where('charge_id', $c->id)->delete();
            $c->refresh()->update(['amt_paid' => 0]);
            $this->actingAs($super)->delete(route('finance.assign.direct.remove', $c->id))->assertOk();
            $this->assertNull($c->fresh());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testOneDiscountBoxKeepsNextTermOnlyApart()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $sr->update(['fee_discount_id' => null, 'next_discount_id' => null]);
            $mk = function ($once) { return \App\Models\FeeDiscount::create(['name' => 'Test '.(int) $once, 'kind' => 'percent', 'percent' => 5, 'next_term_only' => $once, 'active' => true]); };
            $standing = $mk(false);
            $once = $mk(true);
            $post = function ($discount) use ($sr) {
                $this->actingAs($this->user('accountant'))->post(route('finance.assign.save'), ['student' => \App\Helpers\Qs::hash($sr->user_id), 'discount' => $discount, 'services' => '{}'])->assertOk();
                return $sr->fresh();
            };
            $this->assertEquals($once->id, $post($once->id)->next_discount_id);
            $this->assertEquals([$standing->id, $once->id], [$post($standing->id)->fee_discount_id, $sr->fresh()->next_discount_id]);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testOneStaffMemberCanBeGivenClassesAndSubjects()
    {
        $hod = \App\User::whereIn('user_type', ['academic_head', 'teacher'])->first();
        if (!$hod) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $hod->forceFill(['manages_classes' => false])->save();
            $this->actingAs($hod)->get('/classes')->assertRedirect();
            $hod->forceFill(['manages_classes' => true])->save();
            foreach (['/classes', '/subjects', '/sections'] as $url) $this->actingAs($hod)->get($url)->assertOk();
            $this->actingAs($hod)->get('/users')->assertRedirect();
            $this->actingAs($hod)->get('/class-types')->assertRedirect();
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAccountantCancelsABillAndItCanBeRestored()
    {
        $acc = $this->user('accountant');
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        $DB = \Illuminate\Support\Facades\DB::class;
        $DB::beginTransaction();
        try {
            $year = \App\Helpers\Qs::getCurrentSession();
            $fee = \App\Models\Payment::create(['title' => 'Cancel test', 'amount' => 500, 'student_category' => 'all', 'my_class_id' => $sr->my_class_id, 'year' => $year, 'ref_no' => uniqid()]);
            $applied = \App\Support\Fees::appliesTo($fee, $sr, $year);
            $pr = \App\Models\PaymentRecord::create(['student_id' => $sr->user_id, 'payment_id' => $fee->id, 'year' => $year, 'balance' => 500]);
            $url = route('payments.cancel_bill', ['school', \App\Helpers\Qs::hash($pr->id)]);

            $this->actingAs($this->user('teacher'))->delete($url, ['reason' => 'Billed by mistake']);
            $this->assertNotNull(\App\Models\PaymentRecord::find($pr->id));
            $this->actingAs($acc)->deleteJson($url, ['reason' => 'no'])->assertJson(['ok' => false]);

            // With a payment on it, it cannot be cancelled.
            $pr->update(['amt_paid' => 100]);
            $this->actingAs($acc)->deleteJson($url, ['reason' => 'Billed by mistake'])->assertJson(['ok' => false]);
            $pr->update(['amt_paid' => 0]);

            $this->actingAs($acc)->deleteJson($url, ['reason' => 'Billed by mistake'])->assertJson(['ok' => true]);
            $this->assertNull(\App\Models\PaymentRecord::find($pr->id));
            $log = $DB::table('finance_logs')->where(['action' => 'cancel_bill', 'subject_id' => $pr->id])->first();
            $this->assertSame('Billed by mistake', $log->reason);
            $this->assertEquals(500, $log->amount);
            $act = $DB::table('activity_logs')->where('action', 'Cancelled a bill')->latest('id')->first();
            $this->assertStringContainsString('Cancel test', \App\Support\ActivityLog::readable($act->route, $act->details));

            // Billing does not bring it back.
            \App\Support\Fees::billSchoolFees($sr, $year);
            $this->assertSame(0, \App\Models\PaymentRecord::where(['student_id' => $sr->user_id, 'payment_id' => $fee->id])->count());

            $audit = $this->actingAs($this->user('super_admin'))->get(route('finance.audit'))->assertOk();
            $this->actingAs($this->user('super_admin'))->postJson(route('finance.audit.restore', $log->id))->assertJson(['ok' => true]);
            $back = \App\Models\PaymentRecord::where(['student_id' => $sr->user_id, 'payment_id' => $fee->id])->first();
            $this->assertNotNull($back);
            $this->assertEquals(500, $back->balance);
            $this->actingAs($acc)->postJson(route('finance.audit.restore', $log->id))->assertJson(['ok' => false]);   // only once
            if ($applied) {
                $back->delete();
                \App\Support\Fees::billSchoolFees($sr, $year);
                $this->assertSame(1, \App\Models\PaymentRecord::where(['student_id' => $sr->user_id, 'payment_id' => $fee->id])->count());
            }

            // A service / direct bill.
            $c = \App\Models\OptionalFeeCharge::create(['student_id' => $sr->user_id, 'year' => $year, 'term' => 1, 'group' => 'direct', 'label' => 'Broken chair', 'amount' => 80, 'amt_paid' => 0]);
            $this->actingAs($acc)->deleteJson(route('payments.cancel_bill', ['optional', \App\Helpers\Qs::hash($c->id)]), ['reason' => 'Not this child'])->assertJson(['ok' => true]);
            $this->assertNull(\App\Models\OptionalFeeCharge::find($c->id));
            $log2 = $DB::table('finance_logs')->where(['action' => 'cancel_bill', 'subject_id' => $c->id, 'subject' => 'service_bill'])->first();
            $this->actingAs($acc)->postJson(route('finance.audit.restore', $log2->id))->assertJson(['ok' => true]);
            $this->assertSame(1, \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'label' => 'Broken chair', 'amount' => 80])->count());
        } finally {
            $DB::rollBack();
            \App\Support\BillCancel::forget();
        }
    }

    public function testPaymentKeepsDatePaidAndDateEntered()
    {
        $acc = $this->user('accountant');
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $fee = \App\Models\Payment::create(['title' => 'Date test', 'amount' => 500, 'student_category' => 'opening', 'year' => \App\Helpers\Qs::getCurrentSession(), 'term' => 1, 'ref_no' => uniqid()]);
            $pr = \App\Models\PaymentRecord::create(['student_id' => $sr->user_id, 'payment_id' => $fee->id, 'year' => $fee->year, 'amount' => 500]);
            $url = route('payments.pay_now', \App\Helpers\Qs::hash($pr->id));

            $this->actingAs($acc)->postJson($url, ['amt_paid' => 100, 'paid_on' => today()->addDay()->toDateString()])->assertStatus(422);

            $paid = today()->subDays(10);
            $balanceBefore = \App\Support\FinanceSummary::balanceAt($paid->copy()->endOfDay());
            $this->actingAs($acc)->postJson($url, ['amt_paid' => 100, 'paid_on' => $paid->toDateString()])->assertOk();
            $r = \App\Models\Receipt::where('pr_id', $pr->id)->first();
            $this->assertSame($paid->toDateString(), $r->paid_on->toDateString());
            $this->assertSame(today()->toDateString(), $r->created_at->toDateString());
            $this->assertEquals($balanceBefore + 100, \App\Support\FinanceSummary::balanceAt($paid->copy()->endOfDay()));

            // Without a date (e.g. mobile money coming in): today.
            $this->actingAs($acc)->postJson($url, ['amt_paid' => 50])->assertOk();
            $this->assertSame(today()->toDateString(), \App\Models\Receipt::where('pr_id', $pr->id)->latest('id')->first()->paid_on->toDateString());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAccountantLooksUpStudentsWhereTheSchoolAllowsIt()
    {
        $acc = $this->user('accountant');
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        $before = \App\Support\SchoolConfig::get('features');
        try {
            \App\Support\SchoolConfig::set('features', ['accountant_views_students']);
            $this->actingAs($acc)->get(route('students.index'))->assertOk();
            $this->actingAs($acc);
            $labels = collect(\App\Support\Navigation::build())->flatMap(function ($section) { return collect($section['items'] ?? [])->pluck('label'); });
            $this->assertTrue($labels->contains('Students'));
            $this->assertFalse($labels->contains('Examinations'));
            $this->assertFalse($labels->contains('Attendance'));
            $this->actingAs($acc)->get(route('students.show', \App\Helpers\Qs::hash($sr->id)))->assertOk()->assertDontSee(route('students.edit', \App\Helpers\Qs::hash($sr->id)), false);
            $this->actingAs($acc)->get(route('students.edit', \App\Helpers\Qs::hash($sr->id)))->assertStatus(302);
            \App\Support\SchoolConfig::set('features', []);
            $this->actingAs($acc)->get(route('students.index'))->assertStatus(403);
        } finally {
            \App\Support\SchoolConfig::set('features', $before);
        }
    }

    public function testReceiptGoesOutByItselfWhereTheSchoolAllowsIt()
    {
        $acc = $this->user('accountant');
        $sr = \App\Models\StudentRecord::where('grad', 0)->whereNotNull('my_parent_id')->first();
        if (!$sr) return $this->assertTrue(true);
        config(['sms.driver' => 'log', 'sms.allowlist' => '0000000000', 'mail.default' => 'array', 'mail.driver' => 'array']);
        $before = \App\Support\SchoolConfig::get('features');
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $fee = \App\Models\Payment::create(['title' => 'Receipt test', 'amount' => 500, 'student_category' => 'opening', 'year' => \App\Helpers\Qs::getCurrentSession(), 'term' => 1, 'ref_no' => uniqid()]);
            $pr = \App\Models\PaymentRecord::create(['student_id' => $sr->user_id, 'payment_id' => $fee->id, 'year' => $fee->year, 'amount' => 500]);
            $url = route('payments.pay_now', \App\Helpers\Qs::hash($pr->id));
            $logs = function () use ($sr) { return \App\Models\NotificationLog::where(['student_id' => $sr->user_id, 'kind' => 'receipt'])->count(); };

            \App\Support\SchoolConfig::set('features', []);
            $n = $logs();
            $this->actingAs($acc)->postJson($url, ['amt_paid' => 10])->assertOk()->assertJson(['sent' => false]);
            $this->assertSame($n, $logs());

            \App\Support\SchoolConfig::set('features', ['auto_send_receipts']);
            $this->actingAs($acc)->postJson($url, ['amt_paid' => 10])->assertOk()->assertJson(['sent' => true]);
            $this->assertGreaterThan($n, $logs());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
            \App\Support\SchoolConfig::set('features', $before);
        }
    }

    public function testPaystackPaymentIsAppliedOnceAfterPaystackConfirms()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->get()->first(function ($s) { return \App\Support\Fees::termInvoice($s->user_id)['total'] >= 50; });
        if (!$sr) return $this->assertTrue(true);
        config(['paystack.secret_key' => 'sk_test_unit', 'paystack.base_url' => 'https://paystack.test', 'sms.driver' => 'log', 'sms.allowlist' => '0000000000', 'mail.default' => 'array']);
        $paystack = ['status' => 'success', 'channel' => 'mobile_money', 'amount' => 5000, 'currency' => 'GHS', 'id' => 123];
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$paystack) {
            if (strpos($request->url(), '/transaction/initialize') !== false) {
                return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc']]);
            }
            return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => $paystack]);
        });
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $before = \App\Support\Fees::termInvoice($sr->user_id)['total'];
            $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('paystack.pay', now()->addDay(), ['student' => $sr->user_id]);
            $this->postJson($url, ['amount' => 50])->assertOk()->assertJson(['url' => 'https://checkout.paystack.test/abc']);
            $this->postJson(route('paystack.pay', ['student' => $sr->user_id]), ['amount' => 50])->assertStatus(403);
            $p = \App\Models\OnlinePayment::where('student_id', $sr->user_id)->latest('id')->first();
            $this->assertSame('pending', $p->status);

            // A webhook without Paystack's signature does nothing.
            $this->postJson(route('paystack.webhook'), ['event' => 'charge.success', 'data' => ['reference' => $p->reference]])->assertStatus(401);
            $this->assertSame('pending', $p->fresh()->status);

            $this->get(route('paystack.callback', ['reference' => $p->reference]))->assertRedirect();
            $this->assertSame('successful', $p->fresh()->status);
            // The parent gets the receipt by itself, whatever the school's receipt switch.
            $to = \App\Support\Notices::recipients($sr);
            if ($to['phones'] || $to['emails']) $this->assertGreaterThan(0, \App\Models\NotificationLog::where(['student_id' => $sr->user_id, 'kind' => 'receipt'])->where('created_at', '>=', now()->subMinute())->count());
            $this->assertEquals($before - 50, \App\Support\Fees::termInvoice($sr->user_id)['total']);

            // Coming back again, or Paystack's signed webhook, does not apply it twice.
            $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $p->reference]]);
            $this->call('POST', route('paystack.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_unit')], $body)->assertOk();
            $this->get(route('paystack.callback', ['reference' => $p->reference]))->assertRedirect();
            $this->assertEquals($before - 50, \App\Support\Fees::termInvoice($sr->user_id)['total']);

            // A failed payment changes nothing; a wrong amount is refused.
            $this->postJson($url, ['amount' => 20])->assertOk();
            $q = \App\Models\OnlinePayment::where('student_id', $sr->user_id)->latest('id')->first();
            $paystack = ['status' => 'failed', 'gateway_response' => 'Declined'];
            $this->get(route('paystack.callback', ['reference' => $q->reference]));
            $this->assertSame('failed', $q->fresh()->status);
            $this->postJson($url, ['amount' => 20])->assertOk();
            $w = \App\Models\OnlinePayment::where('student_id', $sr->user_id)->latest('id')->first();
            $paystack = ['status' => 'success', 'channel' => 'card', 'amount' => 100, 'currency' => 'GHS'];
            $this->get(route('paystack.callback', ['reference' => $w->reference]));
            $this->assertSame('failed', $w->fresh()->status);
            $this->assertEquals($before - 50, \App\Support\Fees::termInvoice($sr->user_id)['total']);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testStaffSendsAPaystackPromptToTheParentsPhone()
    {
        $acc = $this->user('accountant');
        $sr = \App\Models\StudentRecord::where('grad', 0)->get()->first(function ($s) { return \App\Support\Fees::termInvoice($s->user_id)['total'] >= 50; });
        if (!$sr) return $this->assertTrue(true);
        config(['paystack.secret_key' => 'sk_test_unit', 'paystack.subaccount' => 'ACCT_school', 'paystack.base_url' => 'https://paystack.test', 'sms.driver' => 'log', 'sms.allowlist' => '0000000000', 'mail.default' => 'array']);
        $sent = [];
        $charge = ['status' => 'send_otp', 'display_text' => 'Enter the code sent to the phone'];
        $verify = ['status' => 'ongoing'];
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$sent, &$charge, &$verify) {
            $sent[] = ['url' => $request->url(), 'body' => $request->data()];
            if (strpos($request->url(), '/transaction/initialize') !== false) return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/x']]);
            if (strpos($request->url(), '/charge/submit_otp') !== false) return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => ['status' => 'pay_offline', 'display_text' => 'Approve on your phone']]);
            if (strpos($request->url(), '/charge') !== false) return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => $charge]);
            return \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => $verify]);
        });
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $before = \App\Support\Fees::termInvoice($sr->user_id)['total'];
            $this->assertSame('vod', \App\Support\Paystack::network('050 123 4567'));
            $this->assertSame('mtn', \App\Support\Paystack::network('+233 24 123 4567'));
            $this->assertSame('atl', \App\Support\Paystack::network('0271234567'));

            $r = $this->actingAs($acc)->postJson(route('payments.paystack', \App\Helpers\Qs::hash($sr->user_id)), ['phone' => '0501234567', 'network' => 'vod', 'amount' => 50])
                ->assertOk()->assertJsonPath('next.step', 'otp');
            $charged = collect($sent)->first(function ($x) { return strpos($x['url'], '/charge') !== false; });
            $this->assertSame(['phone' => '0501234567', 'provider' => 'vod'], $charged['body']['mobile_money']);
            $this->assertSame(5000, $charged['body']['amount']);
            $this->assertSame('ACCT_school', $charged['body']['subaccount']);
            $this->assertSame('subaccount', $charged['body']['bearer']);

            $this->actingAs($acc)->postJson($r->json('otp_url'), ['otp' => '123456'])->assertOk()->assertJsonPath('next.step', 'prompt');
            $this->getJson($r->json('status_url'))->assertJson(['status' => 'pending']);
            $this->assertEquals($before, \App\Support\Fees::termInvoice($sr->user_id)['total']);

            $verify = ['status' => 'success', 'channel' => 'mobile_money', 'amount' => 5000, 'currency' => 'GHS', 'id' => 9];
            $this->getJson($r->json('status_url'))->assertJson(['status' => 'successful']);
            $this->getJson($r->json('status_url'))->assertJson(['status' => 'successful']);
            $this->assertEquals($before - 50, \App\Support\Fees::termInvoice($sr->user_id)['total']);

            // Parents' checkout carries the school's subaccount too; teachers cannot send prompts.
            $sent = [];
            \App\Support\Paystack::start($sr, 5, null);
            $init = collect($sent)->first(function ($x) { return strpos($x['url'], '/transaction/initialize') !== false; });
            $this->assertSame(['ACCT_school', 'subaccount'], [$init['body']['subaccount'], $init['body']['bearer']]);
            $this->actingAs($this->user('teacher'))->postJson(route('payments.paystack', \App\Helpers\Qs::hash($sr->user_id)), ['phone' => '0241234567', 'network' => 'mtn', 'amount' => 5])
                ->assertJson(['ok' => false]);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAcademicHeadRunsAcademicsButNotAdmissions()
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $head = $this->user('teacher')->replicate();
            $head->forceFill(['user_type' => 'academic_head', 'username' => 'head.test'.uniqid(), 'email' => 'head'.uniqid().'@example.test', 'code' => strtoupper(uniqid())])->save();
            $sr = \App\Models\StudentRecord::where('grad', 0)->first();
            $this->actingAs($head);

            foreach (['tt.index', 'exams.index', 'grades.index', 'marks.index', 'students.index'] as $r) {
                $this->get(route($r))->assertOk();
            }
            $this->assertTrue(\App\Helpers\Qs::userIsAcademicLead());
            $this->assertFalse(\App\Helpers\Qs::userIsTeamSA());
            foreach (['students.create', 'students.promotion', 'payments.index', 'settings'] as $r) {
                $this->assertNotEquals(200, $this->get(route($r))->getStatusCode(), $r);
            }
            if ($sr) $this->assertNotEquals(200, $this->get(route('students.edit', \App\Helpers\Qs::hash($sr->id)))->getStatusCode());

            $labels = collect(\App\Support\Navigation::build())->flatMap(function ($section) {
                return collect($section['items'] ?? [])->flatMap(function ($i) { return array_merge([$i['label']], collect($i['children'] ?? [])->pluck('label')->all()); });
            });
            foreach (['Exam list', 'Grades', 'Tabulation sheet', 'Report cards'] as $l) $this->assertTrue($labels->contains($l), $l);
            foreach (['Admit student', 'Promote students', 'Student payments'] as $l) $this->assertFalse($labels->contains($l), $l);
            $this->assertTrue(\Illuminate\Support\Facades\DB::table('user_types')->where('title', 'academic_head')->exists());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAdminChangesAStaffMembersRole()
    {
        $admin = $this->user('super_admin');
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $t = $this->user('teacher');
            $head = \Illuminate\Support\Facades\DB::table('user_types')->where('title', 'academic_head')->first();
            $parentType = \Illuminate\Support\Facades\DB::table('user_types')->where('title', 'parent')->first();
            $base = ['name' => $t->name.' X', 'gender' => $t->gender ?: 'Male', 'address' => $t->address ?: 'Some address here', 'state_id' => $t->state_id ?: 1, 'nal_id' => $t->nal_id ?: 1];
            $url = route('users.update', \App\Helpers\Qs::hash($t->id));
            $this->actingAs($admin)->put($url, $base + ['user_type' => \App\Helpers\Qs::hash($head->id)])->assertOk()->assertJson(['ok' => true]);
            $this->assertSame('academic_head', $t->fresh()->user_type);
            $this->actingAs($admin)->put($url, $base + ['user_type' => \App\Helpers\Qs::hash($parentType->id)])->assertOk();
            $this->assertSame('academic_head', $t->fresh()->user_type);
            $this->actingAs($admin)->put(route('users.update', \App\Helpers\Qs::hash($admin->id)), $base + ['user_type' => \App\Helpers\Qs::hash($head->id)]);
            $this->assertSame('super_admin', $admin->fresh()->user_type);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testGradeRemarksArePickedFromTheirOwnList()
    {
        $admin = $this->user('super_admin');
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->actingAs($admin)->get(route('grade_remarks.index'))->assertOk();
            \Illuminate\Support\Facades\DB::table('grade_remarks')->delete();
            $this->assertContains('Very Good', \App\Helpers\Mk::getRemarks());
            $this->actingAs($admin)->post(route('grade_remarks.store'), ['name' => ' Highest '])->assertOk()->assertJson(['ok' => true]);
            $this->actingAs($admin)->post(route('grade_remarks.store'), ['name' => 'Highest'])->assertStatus(302);
            $this->assertSame(['Highest'], \App\Helpers\Mk::getRemarks());
            $this->actingAs($admin)->get(route('grades.index'))->assertOk()->assertSee('Highest');

            // Renaming renames it on the grades; a remark in use cannot be deleted.
            $id = \Illuminate\Support\Facades\DB::table('grade_remarks')->where('name', 'Highest')->value('id');
            $g = \App\Models\Grade::first();
            if ($g) {
                $g->update(['remark' => 'Highest']);
                $this->actingAs($admin)->put(route('grade_remarks.update', $id), ['name' => 'Top'])->assertOk();
                $this->assertSame('Top', $g->fresh()->remark);
                $this->actingAs($admin)->delete(route('grade_remarks.destroy', $id))->assertJson(['ok' => false]);
            }
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAcademicAdminDeletesAGrade()
    {
        $aa = \App\User::where('user_type', 'academic_admin')->first();
        $g = \App\Models\Grade::first();
        if (!$aa || !$g) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->actingAs($aa)->get(route('grades.index'))->assertOk();
            $this->actingAs($aa)->delete(route('grades.destroy', $g->id))->assertRedirect();
            $this->assertNull(\App\Models\Grade::find($g->id));
            $this->assertNotEquals(200, $this->actingAs($this->user('teacher'))->get(route('grades.index'))->getStatusCode());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testTimetableSlotOnSomeDaysOnly()
    {
        $admin = $this->user('super_admin');
        $class = \App\Models\MyClass::first();
        $subject = \App\Models\Subject::where('my_class_id', optional($class)->id)->first();
        if (!$class || !$subject) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $ttr = \App\Models\TimeTableRecord::create(['name' => 'Days test', 'my_class_id' => $class->id, 'year' => \App\Helpers\Qs::getCurrentSession()]);
            $slot = ['ttr_id' => $ttr->id, 'hour_from' => 7, 'min_from' => '30', 'meridian_from' => 'AM', 'hour_to' => 8, 'min_to' => '00', 'meridian_to' => 'AM', 'label' => 'Assembly', 'days' => 'Monday,Wednesday'];
            $this->actingAs($admin)->post(route('ts.store'), $slot)->assertOk();
            $ts = \App\Models\TimeSlot::where('ttr_id', $ttr->id)->first();
            $this->assertSame(['Monday', 'Wednesday'], $ts->days);

            // Monday is Assembly; Tuesday takes a subject.
            $this->actingAs($admin)->post(route('tt.store'), ['ttr_id' => $ttr->id, 'ts_id' => $ts->id, 'day' => 'Monday', 'subject_id' => $subject->id])->assertJson(['ok' => false]);
            $this->actingAs($admin)->post(route('tt.store'), ['ttr_id' => $ttr->id, 'ts_id' => $ts->id, 'day' => 'Tuesday', 'subject_id' => $subject->id])->assertOk();
            $this->assertSame(1, \App\Models\TimeTable::where('ttr_id', $ttr->id)->count());

            $show = $this->actingAs($admin)->get(route('ttr.show', $ttr->id))->assertOk();
            $grid = $show->viewData('page')['props'];
            $this->assertArrayHasKey('rows', $grid);
            if ($grid) {
                $byDay = collect($grid['rows'])->keyBy('day');
                $this->assertSame('activity', $byDay['Monday']['cells'][0]['kind']);
                $this->assertSame('subject', $byDay['Tuesday']['cells'][0]['kind']);
                $this->assertSame('empty', $byDay['Thursday']['cells'][0]['kind']);
            }

            // A slot added later still sorts by its time of day.
            \App\Models\TimeSlot::where('id', $ts->id)->update(['timestamp_from' => strtotime('2000-01-01 7:30 AM') + 0]);
            $this->actingAs($admin)->post(route('ts.store'), array_merge($slot, ['hour_from' => 6, 'min_from' => '45', 'hour_to' => 7, 'min_to' => '15', 'label' => '', 'days' => '']))->assertOk();
            $order = \App\Models\TimeSlot::where('ttr_id', $ttr->id)->orderBy('timestamp_from')->pluck('time_from')->all();
            $this->assertSame('6:45 AM', $order[0]);
            // An ordinary break still runs across every day.
            $this->actingAs($admin)->post(route('ts.store'), ['days' => ''] + array_merge($slot, ['hour_from' => 10, 'min_from' => '00', 'hour_to' => 10, 'min_to' => '30', 'label' => 'Break', 'days' => '']))->assertOk();
            $this->assertNull(\App\Models\TimeSlot::where(['ttr_id' => $ttr->id, 'label' => 'Break'])->value('days'));
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testWeightedTotalIsTheSumOfTheRoundedHalves()
    {
        $w = ['class' => 0.5, 'exam' => 0.5];
        $this->assertSame(80, \App\Support\Assessment::weightedTotal(73, 85, $w));
        $this->assertSame(70, \App\Support\Assessment::weightedTotal(61, 77, $w));
        $this->assertSame(79, \App\Support\Assessment::weightedTotal(73, 84, $w));
        $this->assertSame(100, \App\Support\Assessment::weightedTotal(100, 100, $w));
        $this->assertSame(37, \App\Support\Assessment::weightedTotal(73, null, $w));
    }

    public function testClassTeacherIsSetFromTheTeachersPage()
    {
        $admin = $this->user('super_admin');
        $sections = \App\Models\Section::orderBy('id')->take(2)->get();
        if ($sections->count() < 2) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $t = $this->user('teacher');
            $base = ['name' => $t->name.' X', 'gender' => $t->gender ?: 'Male', 'address' => $t->address ?: 'Some address here', 'state_id' => $t->state_id ?: 1, 'nal_id' => $t->nal_id ?: 1];
            $url = route('users.update', \App\Helpers\Qs::hash($t->id));
            [$a, $b] = [$sections[0]->id, $sections[1]->id];

            $this->actingAs($admin)->put($url, $base + ['class_sections' => (string) $a])->assertOk();
            $this->assertContains($a, \App\Support\ClassTeachers::sectionIdsOf($t->id));

            // Moving to another class.
            $this->actingAs($admin)->put($url, $base + ['class_sections' => (string) $b])->assertOk();
            $now = \App\Support\ClassTeachers::sectionIdsOf($t->id);
            $this->assertContains($b, $now);
            $this->assertNotContains($a, $now);

            // An academic head can be one too, and shows in the Sections page's list.
            $head = \Illuminate\Support\Facades\DB::table('user_types')->where('title', 'academic_head')->first();
            $this->actingAs($admin)->put($url, $base + ['user_type' => \App\Helpers\Qs::hash($head->id), 'class_sections' => (string) $b])->assertOk();
            $this->assertContains($b, \App\Support\ClassTeachers::sectionIdsOf($t->id));
            $this->actingAs($admin)->get(route('sections.index'))->assertOk()->assertSee($t->fresh()->name);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testAcademicAdminMakesATeacherAnHod()
    {
        $aa = \App\User::where('user_type', 'academic_admin')->first();
        if (!$aa) return $this->assertTrue(true);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->assertSame('HOD', \Illuminate\Support\Facades\DB::table('user_types')->where('title', 'academic_head')->value('name'));
            $t = $this->user('teacher');
            $hod = \Illuminate\Support\Facades\DB::table('user_types')->where('title', 'academic_head')->first();
            $base = ['name' => $t->name.' X', 'gender' => $t->gender ?: 'Male', 'address' => $t->address ?: 'Some address here', 'state_id' => $t->state_id ?: 1, 'nal_id' => $t->nal_id ?: 1];
            $this->actingAs($aa)->put(route('users.update', \App\Helpers\Qs::hash($t->id)), $base + ['user_type' => \App\Helpers\Qs::hash($hod->id)])->assertOk()->assertJson(['ok' => true]);
            $this->assertSame('academic_head', $t->fresh()->user_type);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testClassAndExamOutOf50AddUp()
    {
        $before = \App\Support\SchoolConfig::get('scheme');
        try {
            \App\Support\SchoolConfig::set('scheme', 'class_exam_50');
            $this->assertSame('class_exam_50', \App\Support\Assessment::key());
            $c = \App\Support\Assessment::columns(['t1' => 38, 'exm' => 45], 1);
            $this->assertEquals(38, $c['tca']);
            $this->assertEquals(45, $c['exm']);
            $this->assertEquals(83, $c['tex1']);
            $this->assertSame(\App\Support\Assessment::columns(['t1' => 51, 'exm' => 45], 1)['tex1'], null);
        } finally {
            $before === null ? \App\Support\SchoolConfig::forget('scheme') : \App\Support\SchoolConfig::set('scheme', $before);
        }
    }

    public function testClassAndExamOutOf100WithDecimals()
    {
        $before = \App\Support\SchoolConfig::get('scheme');
        try {
            \App\Support\SchoolConfig::set('scheme', 'class_exam_100');
            $c = \App\Support\Assessment::columns(['t1' => 50.7, 'exm' => 61.3], 1);
            $this->assertEquals(50.7, $c['tca']);
            $this->assertEquals(61.3, $c['exm']);
            $this->assertSame(25 + 31, $c['tex1']);   // 25.35 → 25, 30.65 → 31
            $this->assertSame(null, \App\Support\Assessment::columns(['t1' => 100.5, 'exm' => 10], 1)['tex1']);
        } finally {
            $before === null ? \App\Support\SchoolConfig::forget('scheme') : \App\Support\SchoolConfig::set('scheme', $before);
        }
    }

    public function testMarkChangesAreKeptAndTeachersHaveNoAttendanceReport()
    {
        $teacher = $this->user('teacher');
        $this->assertNotEquals(200, $this->actingAs($teacher)->get(route('attendance.report'))->getStatusCode());
        $this->actingAs($teacher);
        $labels = collect(\App\Support\Navigation::build())->flatMap(function ($s) { return collect($s['items'] ?? [])->flatMap(function ($i) { return array_merge([$i['label']], collect($i['children'] ?? [])->pluck('label')->all()); }); });
        $this->assertFalse($labels->contains('Attendance report'));
        $this->actingAs($this->user('super_admin'))->get(route('attendance.report'))->assertOk();

        $mk = \App\Models\Mark::first();
        if (!$mk) return;
        $admin = $this->user('super_admin');
        $part = collect(\App\Support\Assessment::scheme()['parts'])->first();
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $url = route('marks.update', [$mk->exam_id, $mk->my_class_id, $mk->section_id, $mk->subject_id]);
            $scores = \App\Support\Assessment::scores($mk);
            $old = $scores[$part['key']];
            $new = $old === null || $old >= $part['max'] ? 1 : $old + 1;
            // The whole sheet is sent, as the page does.
            $send = [];
            foreach (\App\Models\Mark::where(['exam_id' => $mk->exam_id, 'my_class_id' => $mk->my_class_id, 'section_id' => $mk->section_id, 'subject_id' => $mk->subject_id])->get() as $m) {
                foreach (\App\Support\Assessment::scores($m) as $k => $v) $send[$k.'_'.$m->id] = $v;
            }
            $send[$part['key'].'_'.$mk->id] = $new;
            $this->actingAs($admin)->put($url, $send);
            $h = $this->actingAs($admin)->getJson(route('marks.history', [$mk->exam_id, $mk->my_class_id, $mk->section_id, $mk->subject_id]))->assertOk()->json('rows');
            $this->assertCount(1, $h);   // only the one score that changed
            $this->assertEquals($old, $h[0]['changes'][0]['from']);
            $this->assertEquals($new, $h[0]['changes'][0]['to']);
            $this->assertSame($admin->name, $h[0]['by']);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testLoginForgivesHowPhonesTypeUsernames()
    {
        $c = \App\Http\Controllers\Auth\LoginController::class;
        $this->assertSame('doris.sackitey', $c::cleanIdentity('doris. sackitey'));
        $this->assertSame('abraham.gati', $c::cleanIdentity('abraham .gati '));
        $this->assertSame('John.Kpemeora', $c::cleanIdentity(' John Kpemeora'));
        $this->assertSame('florenceansong2019@gmail.com', $c::cleanIdentity('florenceansong2019 @gmail.com'));

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $t = $this->user('teacher');
            $t->forceFill(['username' => 'ama.test'.substr(uniqid(), -4), 'password' => \Illuminate\Support\Facades\Hash::make('abc1234')])->save();
            [$first, $last] = explode('.', $t->username);
            $this->post(route('login'), ['identity' => ucfirst($first).' '.$last, 'password' => ' abc1234 '])->assertRedirect();
            $this->assertAuthenticatedAs($t);
            $this->assertSame('Signed in', \Illuminate\Support\Facades\DB::table('activity_logs')->where('user_id', $t->id)->latest('id')->value('action'));
            auth()->logout();
            $this->post(route('login'), ['identity' => $t->username, 'password' => 'wrong']);
            $row = \Illuminate\Support\Facades\DB::table('activity_logs')->where('action', 'Failed sign-in')->latest('id')->first();
            $this->assertStringContainsString('Tried: '.$t->username, \App\Support\ActivityLog::readable($row->route, $row->details));
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function testParentHasTimetablesInTheirMenu()
    {
        $this->actingAs($this->user('parent'));
        $labels = collect(\App\Support\Navigation::build())->flatMap(function ($section) {
            return collect($section['items'] ?? [])->pluck('label');
        });
        $this->assertTrue($labels->contains('Timetables'));
    }
}
