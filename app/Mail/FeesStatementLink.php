<?php

namespace App\Mail;

use App\Helpers\Qs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** Link to the student's fees statement (school fees + optional fees). */
class FeesStatementLink extends Mailable
{
    use Queueable;

    public $school, $parentName, $student, $url, $totals, $schoolTotals, $optionalTotals;

    public function __construct($school, $parentName, $student, $url, $totals, $schoolTotals, $optionalTotals)
    {
        $this->school = $school;
        $this->parentName = $parentName;
        $this->student = $student;
        $this->url = $url;
        $this->totals = $totals;
        $this->schoolTotals = $schoolTotals;
        $this->optionalTotals = $optionalTotals;
    }

    public function build()
    {
        return $this->subject("Fees for {$this->student} — {$this->school}")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.fees_link', [
                'instructions' => Qs::getSetting('payment_instructions'),
                'phone' => Qs::getSetting('phone'),
            ]);
    }
}
