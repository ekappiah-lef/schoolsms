<?php

namespace App\Mail;

use App\Support\Notices;

/** School emails come from the school's sender address and replies go to the school's own email. */
trait FromSchool
{
    protected function fromSchool(string $name)
    {
        $this->from(Notices::senderAddress(), $name);
        if ($reply = Notices::replyTo()) {
            $this->replyTo($reply, $name);
        }

        return $this;
    }
}
