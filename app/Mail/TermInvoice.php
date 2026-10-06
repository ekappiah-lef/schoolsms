<?php

namespace App\Mail;

use App\Helpers\Qs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** The current term's invoice, with any balance brought forward from earlier terms. */
class TermInvoice extends Mailable
{
    use Queueable;

    public $school, $parentName, $student, $url, $invoice;

    public function __construct($school, $parentName, $student, $url, array $invoice)
    {
        $this->school = $school;
        $this->parentName = $parentName;
        $this->student = $student;
        $this->url = $url;
        $this->invoice = $invoice;
    }

    public function build()
    {
        return $this->subject("Invoice {$this->invoice['label']}: {$this->student} — {$this->school}")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.term_invoice', [
                'instructions' => Qs::getSetting('payment_instructions'),
                'phone' => Qs::getSetting('phone'),
            ]);
    }
}
