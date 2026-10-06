<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** A message from the school (announcements, absence alerts). */
class Announcement extends Mailable
{
    use Queueable;

    public $school, $title, $body;

    public function __construct($school, $title, $body)
    {
        $this->school = $school;
        $this->title = $title;
        $this->body = $body;
    }

    public function build()
    {
        return $this->subject($this->title)
            ->from(config('mail.from.address'), $this->school)
            ->view('emails.announcement');
    }
}
