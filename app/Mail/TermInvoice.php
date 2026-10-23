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
        $word = empty($this->invoice['next']) ? 'Invoice' : 'Bill';
        $data = ['instructions' => Qs::getSetting('payment_instructions'), 'phone' => Qs::getSetting('phone')];
        $pdf = \App\Support\Pdf::loadView('pdf.bill', $data + ['school' => $this->school, 'student' => $this->student, 'parentName' => $this->parentName,
            'invoice' => $this->invoice, 'word' => $word])->setPaper('a4')->output();

        return $this->subject("{$word} {$this->invoice['label']}: {$this->student} — {$this->school}")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.term_invoice', $data)
            ->attachData($pdf, $word.' '.$this->invoice['label'].' - '.$this->student.'.pdf', ['mime' => 'application/pdf']);
    }
}
