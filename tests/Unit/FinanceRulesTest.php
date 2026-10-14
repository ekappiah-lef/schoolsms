<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Support\Fees;
use App\Support\FinancePeriod;
use App\Support\FinanceSummary;
use Carbon\Carbon;
use Tests\TestCase;

/** The money rules the school relies on. No database needed. */
class FinanceRulesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testTermsFollowTheSchoolCalendar()
    {
        $this->assertSame([2026, 1], FinancePeriod::termOf(Carbon::parse('2026-10-08')));
        $this->assertSame([2026, 2], FinancePeriod::termOf(Carbon::parse('2027-01-11')));
        $this->assertSame([2026, 3], FinancePeriod::termOf(Carbon::parse('2027-05-03')));
    }

    public function testATermIsBilledOnlyOnceItHasStarted()
    {
        Carbon::setTestNow('2026-10-08 09:00:00');
        $this->assertTrue(Fees::isDue(new Payment(['term' => 1]), '2026-2027'));
        $this->assertFalse(Fees::isDue(new Payment(['term' => 2]), '2026-2027'), 'Term 2 must not be billed during Term 1');
        $this->assertTrue(Fees::isDue(new Payment(['term' => 3]), '2025-2026'), 'A past year is always due');
        $this->assertFalse(Fees::isDue(new Payment(['term' => 1]), '2027-2028'), 'A future year is never due');

        Carbon::setTestNow('2027-01-11 09:00:00');
        $this->assertTrue(Fees::isDue(new Payment(['term' => 2]), '2026-2027'));
        $this->assertFalse(Fees::isDue(new Payment(['term' => 3]), '2026-2027'));
    }

    public function testOwedIsBilledLessPaidAndNeverNegative()
    {
        $this->assertSame(['expected' => 24515.0, 'paid' => 13915.0, 'due' => 10600.0], FinanceSummary::triple(24515, 13915));
        $this->assertSame(0.0, FinanceSummary::triple(500, 700)['due']);
        // Cedis and pesewas: no float noise in the balance.
        $this->assertSame(['expected' => 1000.1, 'paid' => 600.33, 'due' => 399.77], FinanceSummary::triple(1000.10, 600.33));
    }

    public function testPaymentModesAreTheFourAgreed()
    {
        $this->assertSame(['Cash', 'Mobile payment', 'Bank transfer', 'Cheque'], Fees::METHODS);
    }
}
