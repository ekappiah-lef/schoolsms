<?php

namespace App\Mail;

use App\Helpers\Qs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** "Congratulations, your ward has been admitted", with the school policy attached. */
class AdmissionLetter extends Mailable
{
    use Queueable;

    public $school, $parentName, $student, $class, $policyPath;

    public function __construct($school, $parentName, $student, $class, $policyPath = null)
    {
        $this->school = $school;
        $this->parentName = $parentName;
        $this->student = $student;
        $this->class = $class;
        $this->policyPath = $policyPath;
    }

    public function build()
    {
        $mail = $this->subject("Admission: {$this->student} — {$this->school}")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.admission', [
                'address' => Qs::getSetting('address'),
                'phone' => Qs::getSetting('phone'),
                'email' => Qs::getSetting('system_email'),
            ]);

        if ($this->policyPath) {
            $mail->attach($this->policyPath, ['as' => 'School policy - '.basename($this->policyPath)]);
        }

        return $mail;
    }
}
