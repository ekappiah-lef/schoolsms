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

    protected $fillable = ['audience', 'audience_label', 'subject', 'body', 'sms', 'email', 'recipients', 'sent', 'failed', 'sent_by'];

    protected $casts = ['sms' => 'boolean', 'email' => 'boolean'];

    public function sender()
    {
        return $this->belongsTo(\App\User::class, 'sent_by');
    }
}
