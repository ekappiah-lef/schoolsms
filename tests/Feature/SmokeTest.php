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
            $this->actingAs($acc)->delete(route('finance.assign.direct.remove', $c->id))->assertOk();
            $this->assertNotNull($c->fresh());

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

    public function testParentHasTimetablesInTheirMenu()
    {
        $this->actingAs($this->user('parent'));
        $labels = collect(\App\Support\Navigation::build())->flatMap(function ($section) {
            return collect($section['items'] ?? [])->pluck('label');
        });
        $this->assertTrue($labels->contains('Timetables'));
    }
}
