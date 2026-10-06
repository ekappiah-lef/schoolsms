<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    const AUDIENCES = [
        'parents' => 'All parents',
        'class' => 'Parents of a class',
        'staff' => 'All staff',
        'teachers' => 'Teaching staff',
        'non_teaching' => 'Non-teaching staff',
    ];

    protected $fillable = ['audience', 'audience_label', 'status', 'class_id', 'section_id', 'subject', 'body', 'sms', 'email', 'whatsapp', 'recipients', 'sent', 'failed', 'sent_by', 'approved_by', 'approved_at', 'review_note'];

    protected $casts = ['sms' => 'boolean', 'email' => 'boolean', 'whatsapp' => 'boolean', 'approved_at' => 'datetime'];

    public function sender()
    {
        return $this->belongsTo(\App\User::class, 'sent_by');
    }
}
