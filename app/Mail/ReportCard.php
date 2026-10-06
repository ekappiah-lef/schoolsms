<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** A student's report sheet for an exam, as a PDF attachment. */
class ReportCard extends Mailable
{
    use Queueable;

    public $school, $parentName, $student, $examLabel;
    protected $pdf, $file;

    public function __construct($school, $parentName, $student, $examLabel, string $pdf, string $file)
    {
        $this->school = $school;
        $this->parentName = $parentName;
        $this->student = $student;
        $this->examLabel = $examLabel;
        $this->pdf = $pdf;
        $this->file = $file;
    }

    public function build()
    {
        return $this->subject("{$this->student}: {$this->examLabel} report sheet")
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.announcement', [
                'title' => "{$this->student}'s report sheet",
                'body' => 'Dear '.($this->parentName ?: 'Parent/Guardian').",\n\nPlease find attached {$this->student}'s report sheet for the {$this->examLabel}.\n\nThank you.",
            ])
            ->attachData($this->pdf, $this->file, ['mime' => 'application/pdf']);
    }
}
