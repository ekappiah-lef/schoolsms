<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'hod_id'];

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
