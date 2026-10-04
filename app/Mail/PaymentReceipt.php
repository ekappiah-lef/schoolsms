<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** Payment receipt for a parent, with the PDF attached. */
class PaymentReceipt extends Mailable
{
    use Queueable;

    public $r, $pdf;

    public function __construct(array $r, ?string $pdf)
    {
        $this->r = $r;
        $this->pdf = $pdf;
    }

    public function build()
    {
        $mail = $this->subject("Payment receipt {$this->r['number']} — {$this->r['student']['name']}")
            ->from(config('mail.from.address'), $this->r['school']['name'])
            ->view('emails.receipt');

        if ($this->pdf) {
            $mail->attachData($this->pdf, $this->r['number'].'.pdf', ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
