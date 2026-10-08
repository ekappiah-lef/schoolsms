<?php

namespace App\Support;

use App\Models\OptionalFeeCharge;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The finance audit trail. Reversing payments never loses a receipt: each one is written here
 * (number, amount, mode, reference, date, bill, student) with who reversed it and why, before it
 * is taken out of the totals. Changes to income / expense entries are written here too.
 */
class FinanceLog
{
    public static function add(string $action, string $subject, int $subjectId, array $data = []): void
    {
        DB::table('finance_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject' => $subject,
            'subject_id' => $subjectId,
            'student_id' => $data['student_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'reason' => $data['reason'] ?? null,
            'before' => isset($data['before']) ? json_encode($data['before']) : null,
            'after' => isset($data['after']) ? json_encode($data['after']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Reverse every payment on a school-fee bill: receipts go to the trail, the bill returns to unpaid. Returns the amount reversed. */
    public static function voidSchoolBill(PaymentRecord $pr, string $reason): int
    {
        return DB::transaction(function () use ($pr, $reason) {
            $pr->loadMissing('payment');
            $total = 0;
            foreach (DB::table('receipts')->where('pr_id', $pr->id)->lockForUpdate()->get() as $r) {
                self::add('void_receipt', 'school_receipt', $r->id, [
                    'student_id' => $pr->student_id, 'amount' => (int) $r->amt_paid, 'reason' => $reason,
                    'before' => ['number' => 'SF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'bill' => optional($pr->payment)->title.' · '.$pr->year.(optional($pr->payment)->term ? ' · Term '.$pr->payment->term : ''),
                        'amount' => (int) $r->amt_paid, 'method' => $r->method, 'reference' => $r->reference, 'paid_on' => (string) $r->created_at],
                ]);
                $total += (int) $r->amt_paid;
            }
            DB::table('receipts')->where('pr_id', $pr->id)->delete();
            $owed = max((int) optional($pr->payment)->amount - (int) $pr->discount, 0);
            $pr->update(['amt_paid' => 0, 'paid' => 0, 'balance' => $owed]);
            ClearEnroll::studentChanged((int) $pr->student_id);

            return $total;
        });
    }

    /** Same for an optional-service or shop charge. */
    public static function voidCharge(OptionalFeeCharge $c, string $reason): int
    {
        return DB::transaction(function () use ($c, $reason) {
            $total = 0;
            foreach (DB::table('optional_fee_receipts')->where('charge_id', $c->id)->lockForUpdate()->get() as $r) {
                self::add('void_receipt', 'optional_receipt', $r->id, [
                    'student_id' => $c->student_id, 'amount' => (int) $r->amt_paid, 'reason' => $reason,
                    'before' => ['number' => 'OF-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'bill' => $c->label.' · '.$c->year,
                        'amount' => (int) $r->amt_paid, 'method' => $r->method, 'reference' => $r->reference, 'paid_on' => (string) $r->created_at],
                ]);
                $total += (int) $r->amt_paid;
            }
            DB::table('optional_fee_receipts')->where('charge_id', $c->id)->delete();
            $c->update(['amt_paid' => 0]);
            ClearEnroll::studentChanged((int) $c->student_id);

            return $total;
        });
    }
}
