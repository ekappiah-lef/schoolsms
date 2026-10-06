<?php

namespace App\Models;

use Eloquent;

class State extends Eloquent
{
    protected $fillable = ['name'];

    public function ministry()
    {
       // return $this->hasMany(Ministry::class);
    }
}
