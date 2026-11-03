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
        $this->actingAs($acc)->get(route('students.index'))->assertStatus(302);
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

    public function testDirectBillIsOnTheInvoiceAndLeavesServicesAlone()
    {
        $sr = \App\Models\StudentRecord::where('grad', 0)->first();
        if (!$sr) return $this->assertTrue(true);
        $year = \App\Helpers\Qs::getCurrentSession();
        $acc = $this->user('accountant');
        $hash = \App\Helpers\Qs::hash($sr->user_id);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $before = \App\Support\Fees::termInvoice($sr->user_id)['total'];
            $services = \App\Support\Fees::selectionFor($sr->user_id, $year);
            $this->actingAs($acc)->post(route('finance.assign.direct'), ['student' => $hash, 'label' => 'Lunch arrears', 'amount' => 150])->assertOk();
            $invoice = \App\Support\Fees::termInvoice($sr->user_id);
            $this->assertEquals($before + 150, $invoice['total']);
            $this->assertTrue(collect($invoice['table'])->contains('label', 'Lunch arrears'));
            $this->assertEquals($services, \App\Support\Fees::selectionFor($sr->user_id, $year));
            $this->actingAs($acc)->get('/finance/assign?student='.$hash)->assertOk()->assertSee('Lunch arrears');
            $this->actingAs($this->user('super_admin'))->get(route('finance.dashboard'))->assertOk();

            $c = \App\Models\OptionalFeeCharge::where(['student_id' => $sr->user_id, 'group' => 'direct'])->first();
            $c->update(['amt_paid' => 10]);
            $this->actingAs($acc)->delete(route('finance.assign.direct.remove', $c->id))->assertOk();
            $this->assertNotNull($c->fresh());
            $c->update(['amt_paid' => 0]);
            $this->actingAs($acc)->delete(route('finance.assign.direct.remove', $c->id))->assertOk();
            $this->assertNull($c->fresh());
            $this->assertEquals($before, \App\Support\Fees::termInvoice($sr->user_id)['total']);
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
