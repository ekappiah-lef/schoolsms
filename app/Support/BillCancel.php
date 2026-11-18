<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\OptionalFeeCharge;
use App\Models\PaymentRecord;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a bill raised for a student (a school fee, or a service / direct bill). Only a bill with nothing
 * paid on it can be cancelled; payments are reversed first by the super admin. Every cancellation is kept in
 * the finance audit trail (what, how much, who, why) and can be restored from there.
 */
class BillCancel
{
    private static $cancelled;

    public static function allowed(): bool
    {
        return Auth::check() && in_array(Auth::user()->user_type, ['super_admin', 'accountant'], true);
    }

    /** Whether this school fee was cancelled for the student (billing then skips it). */
    public static function isCancelled(int $studentId, int $paymentId, string $year): bool
    {
        if (self::$cancelled === null) {
            self::$cancelled = DB::table('cancelled_bills')->get(['student_id', 'payment_id', 'year'])
                ->mapWithKeys(function ($r) { return [$r->student_id.':'.$r->payment_id.':'.$r->year => true]; })->all();
        }

        return isset(self::$cancelled[$studentId.':'.$paymentId.':'.$year]);
    }

    public static function forget(): void
    {
        self::$cancelled = null;
    }

    const PAID = 'This bill has payments on it. The super admin must reverse the payments before it can be cancelled.';

    /** Returns [ok, message, what was cancelled]. */
    public static function school(PaymentRecord $pr, string $reason): array
    {
        $pr->loadMissing(['payment', 'student']);
        if (Money::r($pr->amt_paid) > 0 || DB::table('receipts')->where('pr_id', $pr->id)->exists()) return [false, self::PAID, null];

        $bill = optional($pr->payment)->title.(optional($pr->payment)->term ? ' · Term '.$pr->payment->term : '').' · '.$pr->year;
        $amount = max($pr->billAmount() - Money::r($pr->discount), 0);
        DB::transaction(function () use ($pr, $reason, $bill, $amount) {
            FinanceLog::add('cancel_bill', 'school_bill', $pr->id, [
                'student_id' => $pr->student_id, 'amount' => $amount, 'reason' => $reason,
                'before' => ['bill' => $bill, 'kind' => 'school', 'amount' => $amount, 'gross' => $pr->billAmount(), 'discount' => Money::r($pr->discount),
                    'payment_id' => $pr->payment_id, 'year' => $pr->year, 'ref_no' => $pr->ref_no],
            ]);
            DB::table('cancelled_bills')->updateOrInsert(
                ['student_id' => $pr->student_id, 'payment_id' => $pr->payment_id, 'year' => $pr->year],
                ['finance_log_id' => DB::table('finance_logs')->max('id'), 'created_at' => now(), 'updated_at' => now()]
            );
            $pr->delete();
        });
        self::forget();
        ClearEnroll::studentChanged((int) $pr->student_id);

        return [true, 'Bill cancelled: '.$bill.'.', $bill.' for '.optional($pr->student)->name];
    }

    public static function charge(OptionalFeeCharge $c, string $reason): array
    {
        if (Money::r($c->amt_paid) > 0 || DB::table('optional_fee_receipts')->where('charge_id', $c->id)->exists()) return [false, self::PAID, null];

        $bill = $c->label.($c->group !== 'sales' && $c->term ? ' · Term '.$c->term : '').' · '.$c->year;
        $student = DB::table('users')->where('id', $c->student_id)->value('name');
        DB::transaction(function () use ($c, $reason, $bill) {
            FinanceLog::add('cancel_bill', 'service_bill', $c->id, [
                'student_id' => $c->student_id, 'amount' => Money::r($c->amount), 'reason' => $reason,
                'before' => ['bill' => $bill, 'kind' => 'optional', 'group' => OptionalFeeCharge::GROUPS[$c->group] ?? $c->group, 'amount' => Money::r($c->amount),
                    'row' => collect($c->getAttributes())->only($c->getFillable())->all()],
            ]);
            $c->delete();
        });
        ClearEnroll::studentChanged((int) $c->student_id);

        return [true, 'Bill cancelled: '.$bill.'.', $bill.' for '.$student];
    }

    /** Put a cancelled bill back on the student's account. Returns [ok, message]. */
    public static function restore(int $logId): array
    {
        $log = DB::table('finance_logs')->where(['id' => $logId, 'action' => 'cancel_bill'])->first();
        if (!$log) return [false, 'Not found.'];
        if ($log->after) return [false, 'This bill was already restored.'];
        $b = (array) json_decode((string) $log->before, true);

        DB::transaction(function () use ($log, $b) {
            if (($b['kind'] ?? '') === 'school') {
                DB::table('cancelled_bills')->where(['student_id' => $log->student_id, 'payment_id' => $b['payment_id'], 'year' => $b['year']])->delete();
                self::forget();
                $rec = PaymentRecord::firstOrCreate(['student_id' => $log->student_id, 'payment_id' => $b['payment_id'], 'year' => $b['year']]);
                $rec->update(['ref_no' => $rec->ref_no ?: ($b['ref_no'] ?? mt_rand(100000, 99999999))]);
                $owed = max($rec->billAmount() - Money::r($rec->discount), 0);
                $rec->update(['amt_paid' => 0, 'paid' => $owed <= 0 ? 1 : 0, 'balance' => $owed]);
                if ($sr = StudentRecord::where('user_id', $log->student_id)->first()) Fees::applyDiscount($sr, $b['year']);
            } else {
                OptionalFeeCharge::create(array_merge((array) ($b['row'] ?? []), ['amt_paid' => 0]));
            }
            DB::table('finance_logs')->where('id', $log->id)->update(['after' => json_encode(['restored_by' => optional(Auth::user())->name, 'restored_at' => (string) now()]), 'updated_at' => now()]);
            FinanceLog::add('restore_bill', $log->subject, (int) $log->subject_id, ['student_id' => $log->student_id, 'amount' => $log->amount, 'before' => ['bill' => $b['bill'] ?? '']]);
        });
        ClearEnroll::studentChanged((int) $log->student_id);

        return [true, 'Bill restored: '.($b['bill'] ?? '').'.'];
    }

    public static function urlFor(string $kind, int $id): ?string
    {
        return self::allowed() ? route('payments.cancel_bill', [$kind, Qs::hash($id)]) : null;
    }
}
