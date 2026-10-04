<?php

namespace App\Models;

use Eloquent;

class NotificationLog extends Eloquent
{
    protected $fillable = ['student_id', 'channel', 'kind', 'recipient', 'status', 'error'];
}
