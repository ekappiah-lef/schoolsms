<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** A child's attendance for a period, as a short PDF attachment. */
class AttendanceReport extends Mailable
{
    use Queueable;

    public $school, $parentName, $student, $period;
    protected $pdf, $file;

    public function __construct($school, $parentName, $student, $period, string $pdf, string $file)
    {
        $this->school = $school;
        $this->parentName = $parentName;
        $this->student = $student;
        $this->period = $period;
        $this->pdf = $pdf;
        $this->file = $file;
    }

    public function build()
    {
        return $this->subject("{$this->student}: attendance, {$this->period}")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.announcement', [
                'title' => "{$this->student}'s attendance",
                'body' => 'Dear '.($this->parentName ?: 'Parent/Guardian').",\n\nPlease find attached {$this->student}'s attendance for {$this->period}.\n\nThank you.",
            ])
            ->attachData($this->pdf, $this->file, ['mime' => 'application/pdf']);
    }
}
