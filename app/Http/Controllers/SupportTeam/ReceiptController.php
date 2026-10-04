<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\OptionalFeeReceipt;
use App\Models\Receipt;
use App\Models\StudentRecord;
use App\Support\Notices;
use PDF;

/**
 * A single payment receipt (one part or full payment), for school fees or an
 * optional service: printable page, PDF download, and send to the parent.
 */
class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamAccount');
    }

    /** URLs for a receipt, used by the fee screens. */
    public static function urls(string $kind, int $id): array
    {
        $hash = Qs::hash($id);

        return [
            'view' => route('receipts.show', [$kind, $hash]),
            'pdf' => route('receipts.pdf', [$kind, $hash]),
            'send' => route('receipts.send', [$kind, $hash]),
        ];
    }

    public function show($kind, $id)
    {
        $r = self::data($kind, (int) $id);
        abort_unless($r, 404);

        return view('receipts.single', ['r' => $r, 'pdf' => false]);
    }

    public function pdf($kind, $id)
    {
        $r = self::data($kind, (int) $id);
        abort_unless($r, 404);

        return self::renderPdf($r)->download($r['number'].'.pdf');
    }

    public function send($kind, $id)
    {
        $r = self::data($kind, (int) $id);
        abort_unless($r, 404);

        $results = Notices::sendReceipt($r);
        $sent = collect($results)->where('status', 'sent')->count();

        return response()->json([
            'ok' => $sent > 0,
            'msg' => $sent ? "Receipt sent ({$sent} of ".count($results).').' : 'The receipt could not be sent. '.(collect($results)->pluck('error')->filter()->first() ?? ''),
            'notices' => $results,
        ]);
    }

    public static function renderPdf(array $r)
    {
        return PDF::loadView('receipts.single', ['r' => $r, 'pdf' => true])->setPaper('a5', 'portrait');
    }

    /** Everything a receipt shows, for school-fee ("school") or optional-service ("optional") payments. */
    public static function data(string $kind, int $id): ?array
    {
        if ($kind === 'school') {
            $rc = Receipt::with(['pr.payment', 'pr.student'])->find($id);
            if (!$rc || !$rc->pr || !$rc->pr->payment || !$rc->pr->student) return null;
            $pr = $rc->pr;
            $item = $pr->payment->title;
            $owed = max((int) $pr->payment->amount - (int) $pr->discount, 0);
            $studentId = $pr->student_id;
            $ref = $pr->ref_no;
            $number = 'SF-'.str_pad($rc->id, 6, '0', STR_PAD_LEFT);
            $category = 'School fees';
        } elseif ($kind === 'optional') {
            $rc = OptionalFeeReceipt::with('charge')->find($id);
            if (!$rc || !$rc->charge) return null;
            $item = $rc->charge->label;
            $owed = (int) $rc->charge->amount;
            $studentId = $rc->charge->student_id;
            $ref = null;
            $number = 'OF-'.str_pad($rc->id, 6, '0', STR_PAD_LEFT);
            $category = 'Optional fees · '.(\App\Models\OptionalFeeCharge::GROUPS[$rc->charge->group] ?? ucfirst($rc->charge->group));
        } else {
            return null;
        }

        $sr = StudentRecord::where('user_id', $studentId)->with(['user', 'my_class', 'section', 'my_parent'])->first();
        if (!$sr) return null;

        $logo = Qs::getSetting('logo');
        $logoPath = $logo && preg_match('#/storage/(.+)$#', $logo, $m) ? public_path('storage/'.ltrim(preg_replace('#/{2,}#', '/', $m[1]), '/')) : null;

        return [
            'kind' => $kind,
            'id' => $rc->id,
            'number' => $number,
            'date' => $rc->created_at,
            'category' => $category,
            'item' => $item,
            'year' => $rc->year,
            'ref' => $ref,
            'owed' => $owed,
            'amount' => (int) $rc->amt_paid,
            'balance' => (int) $rc->balance,
            'paid_to_date' => max($owed - (int) $rc->balance, 0),
            'student' => [
                'id' => $sr->user_id,
                'name' => $sr->user->name,
                'adm_no' => $sr->adm_no,
                'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
            ],
            'record' => $sr,
            'school' => [
                'name' => Qs::getSystemName(),
                'address' => Qs::getSetting('address'),
                'phone' => Qs::getSetting('phone'),
                'email' => Qs::getSetting('system_email'),
                'logo_url' => Qs::localAsset($logo),
                // Images in PDFs need the GD extension; without it the PDF is made without the logo.
                'logo_path' => $logoPath && is_file($logoPath) && extension_loaded('gd') ? $logoPath : null,
            ],
        ];
    }
}
