<?php

namespace App\Models;

use Eloquent;

/** One SMS / email / WhatsApp to one person (the delivery report). */
class NotificationLog extends Eloquent
{
    const KINDS = [
        'message' => 'Message', 'absence' => 'Absence alert', 'attendance' => 'Attendance report', 'report' => 'Report sheet',
        'receipt' => 'Receipt', 'invoice' => 'Invoice', 'admission' => 'Admission', 'fees_link' => 'Fees statement',
    ];

    protected $fillable = ['student_id', 'channel', 'kind', 'recipient', 'status', 'error', 'sent_by', 'message_id'];

    protected static function booted()
    {
        // Whoever is signed in sent it (nightly jobs have no sender).
        static::creating(function (self $log) {
            if (!$log->sent_by && auth()->check()) {
                $log->sent_by = auth()->id();
            }
        });
    }
}
