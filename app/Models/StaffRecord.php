<?php

namespace App\Models;

use App\User;
use Eloquent;

class StaffRecord extends Eloquent
{
    protected $fillable = ['code', 'emp_date', 'employment_type', 'job_title', 'clock_id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
