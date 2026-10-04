<?php

namespace App\Http\Controllers;

use App\Helpers\Qs;
use App\Models\StudentRecord;
use App\Support\Fees;
use Inertia\Inertia;

/**
 * Fees statement opened from the link in the admission email/SMS.
 * No login: the URL is signed and expires (route middleware "signed").
 */
class FeeStatementController extends Controller
{
    public function show($student)
    {
        $sr = StudentRecord::where('user_id', (int) $student)->with(['user', 'my_class', 'section'])->first();
        abort_unless($sr, 404);

        $year = Qs::getCurrentSession();
        $logo = Qs::localAsset(Qs::getSetting('logo'));

        return Inertia::render('Public/FeeStatement', [
            'school' => [
                'name' => Qs::getSystemName(),
                'code' => Qs::getAppCode(),
                'address' => Qs::getSetting('address'),
                'phone' => Qs::getSetting('phone'),
                'email' => Qs::getSetting('system_email'),
                'logo' => $logo,
            ],
            'student' => [
                'name' => $sr->user->name,
                'adm_no' => $sr->adm_no,
                'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
                'category' => Fees::categoryFor($sr, $year),
            ],
            'session' => $year,
            'current' => Fees::statement($sr->user_id, $year),
            'overall' => Fees::statement($sr->user_id)['totals'],
            'instructions' => Qs::getSetting('payment_instructions'),
            'generated' => now()->toIso8601String(),
        ]);
    }
}
